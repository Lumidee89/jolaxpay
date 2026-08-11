<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Payments\PaystackChargeReconciler;
use App\Domain\Payments\PaystackGateway;
use App\Domain\Wallet\LedgerService;
use App\Enums\DeliveryChannel;
use App\Enums\LedgerReason;
use App\Http\Controllers\Controller;
use App\Models\Withdrawal;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Public endpoint (no Sanctum guard — Paystack calls this directly, not
 * the mobile app) confirming everything that goes through
 * PaystackGateway asynchronously: card purchases, wallet top-ups, and bank
 * withdrawals. All three are "initiate now, confirmed later" flows (see
 * PaystackGateway's class docblock) — this is the "later."
 *
 * Always responds 200 once the signature checks out, even for an event
 * type or reference we don't recognise — Paystack retries on anything
 * else, and retry-storming ourselves over an event we intentionally don't
 * act on helps no one.
 */
class PaystackWebhookController extends Controller
{
    public function __construct(
        private readonly PaystackGateway $paystack,
        private readonly PaystackChargeReconciler $reconciler,
        private readonly LedgerService $ledger,
        private readonly NotificationDispatcher $notifier,
    ) {}

    public function handle(Request $request): Response
    {
        if (! $this->paystack->verifyWebhookSignature($request->getContent(), $request->header('x-paystack-signature'))) {
            Log::warning('Paystack webhook signature mismatch — ignored.');

            return response('', 400);
        }

        $event = $request->input('event');
        $data = $request->input('data', []);

        match ($event) {
            'charge.success' => $this->handleChargeSuccess($data),
            'charge.failed' => $this->handleChargeFailed($data),
            'transfer.success' => $this->handleTransferResolved($data, success: true),
            'transfer.failed', 'transfer.reversed' => $this->handleTransferResolved($data, success: false),
            default => Log::info("Paystack webhook: unhandled event [{$event}]"),
        };

        return response('', 200);
    }

    protected function handleChargeSuccess(array $data): void
    {
        if ($reference = $data['reference'] ?? null) {
            $this->reconciler->markChargeSuccessful($reference);
        }
    }

    protected function handleChargeFailed(array $data): void
    {
        if ($reference = $data['reference'] ?? null) {
            $this->reconciler->markChargeFailed($reference, $data['gateway_response'] ?? null);
        }
    }

    /** Covers both transfer.success and transfer.failed/transfer.reversed — see WithdrawalController for why a withdrawal is always "pending" until one of these arrives. */
    protected function handleTransferResolved(array $data, bool $success): void
    {
        $reference = $data['reference'] ?? null;
        if (! $reference) {
            return;
        }

        $withdrawal = Withdrawal::where('reference', $reference)->where('status', 'pending')->first();

        if (! $withdrawal) {
            Log::info('Paystack transfer webhook for an unrecognised or already-resolved reference', ['reference' => $reference]);

            return;
        }

        if ($success) {
            $withdrawal->update(['status' => 'success']);

            $this->notifier->send($withdrawal->user, 'withdrawal_completed', DeliveryChannel::InApp, [
                'amount' => (string) $withdrawal->amount,
                'bank_name' => $withdrawal->bank_name,
                'account_number' => $withdrawal->account_number,
            ]);

            return;
        }

        $withdrawal->update([
            'status' => 'failed',
            'failure_reason' => $data['failures'] ?? ($data['message'] ?? 'Transfer failed or was reversed by Paystack.'),
        ]);

        $this->ledger->credit($withdrawal->wallet, (string) $withdrawal->amount, LedgerReason::WithdrawalReversal, null, [
            'withdrawal_id' => $withdrawal->id,
            'reason' => 'Automatic reversal — Paystack transfer failed.',
        ]);

        $this->notifier->send($withdrawal->user, 'withdrawal_failed', DeliveryChannel::InApp, [
            'amount' => (string) $withdrawal->amount,
        ]);
    }
}
