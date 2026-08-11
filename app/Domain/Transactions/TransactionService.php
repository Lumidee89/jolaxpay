<?php

namespace App\Domain\Transactions;

use App\Domain\Fraud\FraudCheckService;
use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Payments\PaymentManager;
use App\Domain\Payments\PaystackGateway;
use App\Domain\Vending\VendingManager;
use App\Domain\Wallet\Exceptions\InsufficientFundsException;
use App\Domain\Wallet\LedgerService;
use App\Enums\DeliveryChannel;
use App\Enums\DeliveryDestination;
use App\Enums\LedgerReason;
use App\Enums\OutcomeReason;
use App\Enums\ServiceType;
use App\Enums\TransactionStatus;
use App\Jobs\DeliverToken;
use App\Jobs\ProcessTransactionPayment;
use App\Jobs\ProcessVending;
use App\Models\Beneficiary;
use App\Models\Meter;
use App\Models\Transaction;
use App\Models\TransactionStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the full Payment Flow (TRD §6) end to end: fee disclosure
 * through payment capture, vending, delivery, and outcome confirmation.
 * Controllers and jobs call into this rather than talking to
 * TransactionStateMachine, PaymentManager, VendingManager, or
 * LedgerService directly, so the pipeline stays in one place.
 */
class TransactionService
{
    public function __construct(
        private readonly TransactionStateMachine $stateMachine,
        private readonly PaymentManager $paymentManager,
        private readonly VendingManager $vendingManager,
        private readonly LedgerService $ledger,
        private readonly NotificationDispatcher $notifier,
        private readonly PaystackGateway $paystack,
        private readonly FraudCheckService $fraud,
    ) {}

    /**
     * Creates the transaction (Fee Disclosed) and immediately moves it to
     * Payment Initiated, queuing the async payment/vend/deliver pipeline.
     * Called from `POST /v1/transactions` behind the idempotency middleware.
     */
    public function initiate(User $user, array $data): Transaction
    {
        $meter = isset($data['meter_id']) ? Meter::findOrFail($data['meter_id']) : null;
        $beneficiary = isset($data['beneficiary_id']) ? Beneficiary::findOrFail($data['beneficiary_id']) : null;
        // A saved beneficiary supplies its biller/identifier by default —
        // still overridable by an explicit biller_id/biller_identifier in
        // the request (StoreTransactionRequest requires one or the other).
        $billerId = $data['biller_id'] ?? $beneficiary?->biller_id;
        $billerIdentifier = $data['biller_identifier'] ?? $beneficiary?->identifier;
        $currency = $data['currency'] ?? 'NGN';
        $fee = $this->calculateFee($data['amount']);

        [$recipientName, $recipientPhone, $recipientEmail, $recipientUserId] = $this->resolveRecipient(
            $user,
            $meter,
            $beneficiary,
            DeliveryDestination::from($data['delivery_destination'] ?? 'me'),
            $data,
        );

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'meter_id' => $meter?->id,
            'meter_group_id' => $data['meter_group_id'] ?? null,
            'biller_id' => $billerId,
            'beneficiary_id' => $beneficiary?->id,
            'biller_identifier' => $billerIdentifier,
            'variation_code' => $data['variation_code'] ?? null,
            'service_type' => ServiceType::from($data['service_type'] ?? 'electricity'),
            'amount' => $data['amount'],
            'fee' => $fee,
            'currency' => $currency,
            'fx_rate' => $data['fx_rate'] ?? null,
            'amount_ngn' => $currency === 'NGN' ? $data['amount'] : ($data['amount_ngn'] ?? null),
            'payment_method' => $data['payment_method'],
            'delivery_destination' => $data['delivery_destination'] ?? DeliveryDestination::Me->value,
            'recipient_name' => $recipientName,
            'recipient_phone' => $recipientPhone,
            'recipient_email' => $recipientEmail,
            'recipient_user_id' => $recipientUserId,
            'idempotency_key' => $data['idempotency_key'] ?? null,
            'meta' => $data['meta'] ?? [],
        ]);

        // 'status' is deliberately absent from Transaction's #[Fillable] list
        // (TransactionStateMachine owns every transition after this point) —
        // set it explicitly rather than relying on the DB column default,
        // which Eloquent's create() doesn't reflect back onto the in-memory
        // model.
        $transaction->forceFill(['status' => TransactionStatus::FeeDisclosed])->save();

        // Seed the audit trail with the row's initial state (not a transition — there's no "from").
        TransactionStatusHistory::create([
            'transaction_id' => $transaction->id,
            'from_status' => null,
            'to_status' => TransactionStatus::FeeDisclosed,
            'note' => 'Fee and total disclosed to buyer before confirmation.',
        ]);

        // PRD §15: detective, not preventive — never allowed to block a
        // purchase (TRD §8 degrade-gracefully principle, same reasoning as
        // TransactionStateMachine's broadcast-failure try/catch).
        try {
            $this->fraud->evaluate($transaction);
        } catch (\Throwable $e) {
            Log::warning('Fraud check failed to run; purchase proceeds unflagged.', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->stateMachine->transition($transaction, TransactionStatus::PaymentInitiated, 'Payment confirmed by user; processor call queued.', $user);

        // A card payment routed through Paystack is a hosted-checkout
        // redirect, not something to capture inline — the buyer pays on
        // Paystack's page and a webhook (PaystackWebhookController) tells
        // us it succeeded, at which point *that* dispatches
        // ProcessTransactionPayment. Everything else (wallet, or 'card'
        // while PAYMENTS_DOMESTIC_DRIVER is still 'mock') keeps working
        // exactly as before: dispatch immediately.
        if ($this->requiresPaystackRedirect($transaction)) {
            $this->initializePaystackCheckout($transaction, $user);
        } else {
            ProcessTransactionPayment::dispatch($transaction);
        }

        return $transaction->fresh();
    }

    protected function requiresPaystackRedirect(Transaction $transaction): bool
    {
        return $transaction->payment_method !== 'wallet'
            && $transaction->currency === config('payments.domestic_currency', 'NGN')
            && config('payments.domestic.driver') === 'paystack';
    }

    /**
     * Stashes the Paystack authorization_url on `transaction.meta` for the
     * mobile app to open in a checkout WebView, and the reference so the
     * webhook can find its way back to this transaction. If Paystack can't
     * even be reached to start the checkout, fails fast rather than
     * leaving the buyer stuck on a transaction with no way to pay it.
     */
    protected function initializePaystackCheckout(Transaction $transaction, User $user): void
    {
        $reference = 'txn-'.$transaction->reference;
        $amountKobo = (int) round(((float) $transaction->total()) * 100);

        $init = $this->paystack->initializeTransaction($user->email, $amountKobo, $reference, [
            'transaction_id' => $transaction->id,
            'purpose' => 'transaction',
        ]);

        if (! $init) {
            $this->fail($transaction, 'Could not start card payment — please try again.');

            return;
        }

        $transaction->update(['meta' => [
            ...($transaction->meta ?? []),
            'paystack_reference' => $reference,
            'paystack_authorization_url' => $init['authorization_url'],
        ]]);
    }

    public function calculateFee(string $amount): string
    {
        return bcmul($amount, (string) config('payments.convenience_fee_rate', '0.015'), 2);
    }

    /**
     * Called from ProcessTransactionPayment — either immediately after
     * initiate() (wallet, or 'card' while still on the mock driver), or
     * from PaystackWebhookController once a `charge.success` webhook
     * confirms a hosted-checkout payment actually went through.
     */
    public function processPayment(Transaction $transaction): void
    {
        if ($transaction->payment_method === 'wallet') {
            try {
                $wallet = $this->ledger->walletFor($transaction->user, $transaction->currency);
                $this->ledger->debit($wallet, $transaction->total(), LedgerReason::Purchase, $transaction);
            } catch (InsufficientFundsException $e) {
                $this->fail($transaction, 'Insufficient wallet balance: '.$e->getMessage());

                return;
            }
        } elseif ($transaction->meta['paystack_reference'] ?? null) {
            // Paystack already confirmed this via webhook before this job
            // ran — nothing left to charge, just record the reference.
            $transaction->update(['payment_reference' => $transaction->meta['paystack_reference']]);
        } else {
            $result = $this->paymentManager->driverFor($transaction)->charge($transaction);

            if (! $result->successful) {
                $this->fail($transaction, $result->message ?? 'Payment declined by processor.');

                return;
            }

            $transaction->update(['payment_reference' => $result->processorReference]);
        }

        $this->stateMachine->transition($transaction, TransactionStatus::PaymentReceived, 'Processor/wallet confirmed funds captured.');
        $this->stateMachine->transition($transaction, TransactionStatus::PaymentConfirmed, 'Ledger updated; vending unlocked.');

        ProcessVending::dispatch($transaction);
    }

    /** Called from ProcessVending. */
    public function processVending(Transaction $transaction): void
    {
        if ($transaction->status !== TransactionStatus::GeneratingToken) {
            $this->stateMachine->transition($transaction, TransactionStatus::GeneratingToken, 'Calling vending provider.');
        }

        $transaction->increment('vend_attempts');
        $result = $this->vendingManager->driverFor($transaction->service_type)->vend($transaction);

        if (! $result->successful) {
            if ($transaction->vend_attempts < config('vending.max_vend_attempts')) {
                ProcessVending::dispatch($transaction)->delay(now()->addSeconds(10 * $transaction->vend_attempts));

                return;
            }

            $this->fail($transaction, $result->message ?? 'Vending failed after bounded retries.');

            return;
        }

        $transaction->update([
            'token' => $result->token,
            // Merges in whatever the driver returned (VTpass: units, its
            // own request_id for requery, token_amount, ...) without
            // clobbering meta set earlier in the flow (e.g. simulate_failure).
            'meta' => [...($transaction->meta ?? []), ...$result->raw],
        ]);
        $this->stateMachine->transition($transaction, TransactionStatus::TokenGenerated, 'Provider returned a valid token.');

        DeliverToken::dispatch($transaction);
    }

    /** Called from DeliverToken. */
    public function deliverToken(Transaction $transaction): void
    {
        $transaction->increment('delivery_attempts');

        try {
            $this->dispatchDelivery($transaction);
        } catch (\Throwable $e) {
            Log::warning('Token delivery attempt failed', ['transaction_id' => $transaction->id, 'error' => $e->getMessage()]);

            if ($transaction->delivery_attempts < config('vending.max_delivery_attempts')) {
                DeliverToken::dispatch($transaction)->delay(now()->addSeconds(10 * $transaction->delivery_attempts));

                return;
            }

            $this->fail($transaction, 'Delivery failed after bounded retries: '.$e->getMessage());

            return;
        }

        $transaction->update(['delivered_at' => now()]);
        $this->stateMachine->transition($transaction, TransactionStatus::Delivered, 'Token dispatched to recipient.');
    }

    protected function dispatchDelivery(Transaction $transaction): void
    {
        $channel = $transaction->recipient_user_id
            ? DeliveryChannel::InApp
            : ($transaction->recipient_phone ? DeliveryChannel::Sms : DeliveryChannel::Email);

        $transaction->update(['delivery_channel' => $channel]);

        // Buyer always gets payment/delivery confirmation regardless of who received the token (PRD §7.3).
        // `service_type` rides along on both notifications so
        // NotificationLogResource can render service-appropriate wording
        // ("token" only makes sense for electricity/education — airtime/
        // data/cable_tv are a direct recharge, not a token to redeem).
        $this->notifier->send($transaction->user, 'payment_confirmation', DeliveryChannel::InApp, [
            'transaction_id' => $transaction->id,
            'reference' => $transaction->reference,
            'service_type' => $transaction->service_type->value,
        ]);

        $recipientUser = $transaction->recipientUser;

        if ($recipientUser && $recipientUser->isNot($transaction->user)) {
            $this->notifier->send($recipientUser, 'token_delivery', DeliveryChannel::InApp, [
                'transaction_id' => $transaction->id,
                'token' => $transaction->token,
                'service_type' => $transaction->service_type->value,
            ]);

            return;
        }

        $this->notifier->send($transaction->user, 'token_delivery', $channel, [
            'transaction_id' => $transaction->id,
            'recipient_name' => $transaction->recipient_name,
            'recipient_phone' => $transaction->recipient_phone,
            'recipient_email' => $transaction->recipient_email,
            'token' => $transaction->token,
            'service_type' => $transaction->service_type->value,
        ]);
    }

    /**
     * Moves the transaction to Failed and, if funds were already captured
     * (payment_confirmed was reached), automatically refunds to wallet —
     * the retry-then-refund fallback from TRD §6.
     */
    public function fail(Transaction $transaction, string $reason): void
    {
        if ($transaction->status->isTerminal()) {
            return;
        }

        $this->stateMachine->transition($transaction, TransactionStatus::Failed, $reason);

        $paymentWasCaptured = $transaction->statusHistory()
            ->where('to_status', TransactionStatus::PaymentConfirmed->value)
            ->exists();

        if ($paymentWasCaptured) {
            $this->ledger->refundToWallet($transaction);
        }
    }

    /**
     * Outcome Confirmation (PRD §7.6). A "No" does not itself reverse a
     * successfully delivered transaction — it records the reason for
     * support/ops follow-up. Only an actual vend/delivery failure
     * triggers the automatic refund in `fail()`.
     */
    public function confirmOutcome(Transaction $transaction, bool $confirmed, ?OutcomeReason $reason = null): Transaction
    {
        $transaction->update([
            'outcome_confirmed' => $confirmed,
            'outcome_reason' => $reason,
            'outcome_confirmed_at' => now(),
        ]);

        if ($confirmed && $transaction->status === TransactionStatus::Delivered) {
            $this->stateMachine->transition($transaction, TransactionStatus::OutcomeConfirmed, 'Recipient confirmed restoration.');
        }

        return $transaction->fresh();
    }

    /** Admin manual retry (User Journey §7: "triggers manual retry"). */
    public function manualRetry(Transaction $transaction, User $admin): void
    {
        if ($transaction->status->isTerminal()) {
            throw new \RuntimeException('Cannot retry a terminal transaction.');
        }

        match ($transaction->status) {
            TransactionStatus::PaymentInitiated => ProcessTransactionPayment::dispatch($transaction),
            TransactionStatus::PaymentConfirmed, TransactionStatus::GeneratingToken => ProcessVending::dispatch($transaction),
            TransactionStatus::TokenGenerated => DeliverToken::dispatch($transaction),
            default => throw new \RuntimeException("Nothing to retry from status [{$transaction->status->value}]."),
        };

        TransactionStatusHistory::create([
            'transaction_id' => $transaction->id,
            'from_status' => $transaction->status,
            'to_status' => $transaction->status,
            'note' => 'Manual retry triggered by admin.',
            'caused_by_user_id' => $admin->id,
        ]);
    }

    /** Admin manual refund (User Journey §7). */
    public function manualRefund(Transaction $transaction, User $admin): void
    {
        $entry = $this->ledger->refundToWallet($transaction);

        TransactionStatusHistory::create([
            'transaction_id' => $transaction->id,
            'from_status' => $transaction->status,
            'to_status' => $transaction->status,
            'note' => $entry ? 'Manual refund to wallet issued by admin.' : 'Manual refund skipped — already refunded.',
            'caused_by_user_id' => $admin->id,
        ]);
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?int} [name, phone, email, recipient_user_id]
     */
    protected function resolveRecipient(User $buyer, ?Meter $meter, ?Beneficiary $beneficiary, DeliveryDestination $destination, array $data): array
    {
        // DeliveryDestination::MeterOwner means "the person tied to the
        // saved thing being topped up" — a Meter for electricity, a
        // Beneficiary for everything else; whichever one this purchase has.
        $name = match ($destination) {
            DeliveryDestination::Me => $buyer->full_name,
            DeliveryDestination::MeterOwner => $meter?->label ?? $beneficiary?->label ?? $data['recipient_name'] ?? null,
            DeliveryDestination::SomeoneElse => $data['recipient_name'] ?? null,
        };

        $phone = match ($destination) {
            DeliveryDestination::Me => $buyer->phone_number,
            DeliveryDestination::MeterOwner => $meter?->recipient_phone ?? $beneficiary?->recipient_phone,
            DeliveryDestination::SomeoneElse => $data['recipient_phone'] ?? null,
        };

        $email = match ($destination) {
            DeliveryDestination::Me => $buyer->email,
            DeliveryDestination::MeterOwner => $meter?->recipient_email ?? $beneficiary?->recipient_email,
            DeliveryDestination::SomeoneElse => $data['recipient_email'] ?? null,
        };

        // Automatic in-app delivery when the recipient is an existing JolaxPay user (PRD §7.3).
        $recipientUserId = match (true) {
            $destination === DeliveryDestination::Me => $buyer->id,
            (bool) ($phone || $email) => User::query()
                ->where(function ($q) use ($phone, $email) {
                    $phone && $q->orWhere('phone_number', $phone);
                    $email && $q->orWhere('email', $email);
                })
                ->value('id'),
            default => null,
        };

        return [$name, $phone, $email, $recipientUserId];
    }
}
