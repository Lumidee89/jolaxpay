<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\NotificationLog */
class NotificationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->titleForType(),
            'body' => $this->bodyForType(),
            'payload' => $this->payload,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    private function titleForType(): string
    {
        return match ($this->type) {
            'login_success' => $this->payload['title'] ?? 'Welcome back to JolaxPay',
            'wallet_funded' => 'Wallet funded',
            'payment_confirmation' => 'Purchase confirmed',
            'token_delivery' => $this->deliveryWording()['title'],
            'wallet_transfer_sent' => 'Money sent',
            'wallet_transfer_received' => 'Money received',
            'withdrawal_completed' => 'Withdrawal successful',
            'withdrawal_failed' => 'Withdrawal failed',
            'referral_reward' => 'You earned a referral reward! 🎉',
            default => 'JolaxPay update',
        };
    }

    private function bodyForType(): string
    {
        return match ($this->type) {
            'login_success' => $this->payload['body'] ?? 'You have successfully signed in.',
            'wallet_funded' => 'Your wallet has been credited with ₦'.number_format((float) ($this->payload['amount'] ?? 0), 2).'.',
            'payment_confirmation' => 'Your payment has been confirmed and we are processing your '.$this->serviceLabel().'.',
            'token_delivery' => $this->deliveryWording()['body'],
            'wallet_transfer_sent' => '₦'.number_format((float) ($this->payload['amount'] ?? 0), 2).' was sent to '.($this->payload['recipient_wallet_address'] ?? 'another wallet').'.',
            'wallet_transfer_received' => 'You received ₦'.number_format((float) ($this->payload['amount'] ?? 0), 2).' from '.($this->payload['sender_name'] ?? 'another JolaxPay user').'.',
            'withdrawal_completed' => '₦'.number_format((float) ($this->payload['amount'] ?? 0), 2).' has been sent to your '.($this->payload['bank_name'] ?? 'bank').' account ending '.substr((string) ($this->payload['account_number'] ?? ''), -4).'.',
            'withdrawal_failed' => 'Your withdrawal of ₦'.number_format((float) ($this->payload['amount'] ?? 0), 2).' could not be completed and has been returned to your wallet.',
            'referral_reward' => ($this->payload['referred_name'] ?? 'Someone you referred').' made their first purchase — ₦'.number_format((float) ($this->payload['amount'] ?? 0), 2).' has been added to your wallet.',
            default => $this->payload['body'] ?? 'You have a new account update.',
        };
    }

    /**
     * Every non-electricity service type is a direct recharge/subscription,
     * not a token to redeem — calling it "your electricity token" on an
     * airtime/data/cable_tv/education purchase was a leftover from when
     * this app only vended electricity (see TransactionService::
     * dispatchDelivery(), which now rides `service_type` along on both
     * `payment_confirmation` and `token_delivery` for exactly this).
     *
     * @return array{title: string, body: string}
     */
    private function deliveryWording(): array
    {
        return match ($this->payload['service_type'] ?? 'electricity') {
            'airtime' => ['title' => 'Airtime delivered', 'body' => 'Your airtime recharge is complete.'],
            'data' => ['title' => 'Data delivered', 'body' => 'Your data bundle has been activated.'],
            'cable_tv' => ['title' => 'Subscription renewed', 'body' => 'Your TV subscription has been renewed.'],
            'education' => ['title' => 'Pin delivered', 'body' => 'Your exam pin is ready. Open JolaxPay to view it.'],
            default => ['title' => 'Token delivered', 'body' => 'Your electricity token is ready. Open JolaxPay to view it.'],
        };
    }

    private function serviceLabel(): string
    {
        return match ($this->payload['service_type'] ?? 'electricity') {
            'airtime' => 'airtime recharge',
            'data' => 'data bundle',
            'cable_tv' => 'TV subscription',
            'education' => 'exam pin purchase',
            default => 'electricity token',
        };
    }
}
