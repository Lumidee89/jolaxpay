<?php

namespace App\Domain\Payments;

use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Wallet\LedgerService;
use App\Enums\DeliveryChannel;
use App\Enums\LedgerReason;
use App\Models\WalletFundingIntent;
use Illuminate\Support\Facades\DB;

class SafeHavenFundingService
{
    public function __construct(private readonly LedgerService $ledger, private readonly NotificationDispatcher $notifier) {}

    public function confirm(string $reference, array $providerData): bool
    {
        return DB::transaction(function () use ($reference, $providerData) {
            $intent = WalletFundingIntent::where('reference', $reference)->lockForUpdate()->first();
            if (! $intent || $intent->status === 'success') return false;
            $status = strtolower((string) ($providerData['status'] ?? ''));
            $amount = (float) ($providerData['amount'] ?? 0);
            if (! in_array($status, ['completed', 'successful', 'success'], true) || $amount < (float) $intent->amount) return false;
            $this->ledger->credit($intent->wallet, (string) $intent->amount, LedgerReason::WalletFunding, null, [
                'provider' => 'safehaven', 'provider_reference' => $providerData['paymentReference'] ?? $providerData['sessionId'] ?? null,
            ]);
            $intent->update(['status' => 'success', 'meta' => [...($intent->meta ?? []), 'confirmation' => $providerData]]);
            $this->notifier->send($intent->user, 'wallet_funded', DeliveryChannel::InApp, [
                'amount' => (string) $intent->amount, 'currency' => $intent->currency, 'balance' => (string) $intent->wallet->fresh()->balance,
            ]);
            return true;
        });
    }
}
