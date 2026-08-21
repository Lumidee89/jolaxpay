<?php

namespace App\Domain\Referrals;

use App\Domain\Notifications\NotificationDispatcher;
use App\Enums\DeliveryChannel;
use App\Enums\TransactionStatus;
use App\Models\AgentAchievement;
use App\Models\AgentCommission;
use App\Models\CommissionRule;
use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommissionService
{
    public function __construct(private readonly NotificationDispatcher $notifier) {}

    public function accrue(Transaction $transaction): void
    {
        if ($transaction->status !== TransactionStatus::Delivered) {
            return;
        }

        DB::transaction(function () use ($transaction) {
            $transaction->loadMissing(['user', 'meter.disco', 'biller']);

            if ($transaction->user->isAgentAccount() && $transaction->user->agent_approved_at) {
                $this->createCommission($transaction->user, $transaction, 'direct');
            }

            $referral = Referral::with('referrer')
                ->where('referred_user_id', $transaction->user_id)
                ->whereNotIn('status', ['flagged', 'cancelled'])
                ->first();

            if ($referral?->referrer?->isAgentAccount() && $referral->referrer->agent_approved_at) {
                $commission = $this->createCommission($referral->referrer, $transaction, 'referral', $transaction->user_id);
                $this->activateReferralIfQualified($referral);
                if ($commission) {
                    $this->notifier->send($referral->referrer, 'agent_referral_commission', DeliveryChannel::InApp, [
                        'amount' => (string) $commission->amount,
                        'currency' => $transaction->currency,
                        'service_type' => $transaction->service_type->value,
                    ]);
                }
            }
        });
    }

    public function reverseFor(Transaction $transaction, string $reason): void
    {
        DB::transaction(function () use ($transaction, $reason) {
            AgentCommission::where('transaction_id', $transaction->id)
                ->whereIn('earning_type', ['direct', 'referral'])
                ->whereIn('status', ['pending', 'available', 'paid'])
                ->lockForUpdate()
                ->get()
                ->each(function (AgentCommission $commission) use ($reason) {
                    AgentCommission::firstOrCreate(
                        [
                            'transaction_id' => $commission->transaction_id,
                            'agent_id' => $commission->agent_id,
                            'earning_type' => $commission->earning_type.'_reversal',
                        ],
                        [
                            'referred_user_id' => $commission->referred_user_id,
                            'commission_rule_id' => $commission->commission_rule_id,
                            'amount' => bcmul((string) $commission->amount, '-1', 2),
                            'status' => 'reversed',
                            'reversal_of_id' => $commission->id,
                            'reversed_at' => now(),
                            'meta' => ['reason' => $reason],
                        ],
                    );
                    $commission->update(['status' => 'reversed', 'reversed_at' => now()]);
                });
        });
    }

    private function createCommission(User $agent, Transaction $transaction, string $earningType, ?int $referredUserId = null): ?AgentCommission
    {
        $rule = $this->matchingRule($transaction, $earningType);
        if (! $rule) {
            return null;
        }

        $amount = $rule->calculation_type === 'percentage'
            ? bcmul((string) $transaction->amount, bcdiv((string) $rule->value, '100', 6), 2)
            : number_format((float) $rule->value, 2, '.', '');

        if ($rule->minimum_commission !== null && bccomp($amount, (string) $rule->minimum_commission, 2) < 0) {
            $amount = (string) $rule->minimum_commission;
        }
        if ($rule->maximum_commission !== null && bccomp($amount, (string) $rule->maximum_commission, 2) > 0) {
            $amount = (string) $rule->maximum_commission;
        }
        if (bccomp($amount, '0', 2) <= 0) {
            return null;
        }

        return AgentCommission::firstOrCreate(
            ['transaction_id' => $transaction->id, 'agent_id' => $agent->id, 'earning_type' => $earningType],
            [
                'referred_user_id' => $referredUserId,
                'commission_rule_id' => $rule->id,
                'amount' => $amount,
                'status' => 'available',
                'available_at' => now(),
                'meta' => [
                    'service_type' => $transaction->service_type->value,
                    'jolaxpay_margin' => $rule->jolaxpay_margin,
                    'retained_margin' => $rule->jolaxpay_margin !== null ? bcsub((string) $rule->jolaxpay_margin, $amount, 2) : null,
                ],
            ],
        );
    }

    private function matchingRule(Transaction $transaction, string $earningType): ?CommissionRule
    {
        $now = now();

        return CommissionRule::query()
            ->where('earning_type', $earningType)
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('service_type')->orWhere('service_type', $transaction->service_type->value))
            ->where(fn ($q) => $q->whereNull('biller_id')->orWhere('biller_id', $transaction->biller_id))
            ->where(fn ($q) => $q->whereNull('disco_id')->orWhere('disco_id', $transaction->meter?->disco_id))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now))
            ->orderByRaw('(biller_id IS NOT NULL OR disco_id IS NOT NULL) DESC')
            ->orderByRaw('(service_type IS NOT NULL) DESC')
            ->orderByDesc('starts_at')
            ->first();
    }

    private function activateReferralIfQualified(Referral $referral): void
    {
        $required = ReferralSetting::current()->active_min_transactions;
        $successful = Transaction::where('user_id', $referral->referred_user_id)
            ->whereIn('status', [TransactionStatus::Delivered, TransactionStatus::OutcomeConfirmed])
            ->count();

        if ($successful >= $required && ! $referral->activated_at) {
            $referral->update(['activated_at' => now(), 'status' => 'active']);
            $this->unlockAchievements($referral->referrer);
        }
    }

    private function unlockAchievements(User $agent): void
    {
        $active = Referral::where('referrer_id', $agent->id)->whereNotNull('activated_at')->count();
        foreach (ReferralSetting::current()->milestones ?? [] as $milestone) {
            $threshold = (int) ($milestone['threshold'] ?? 0);
            if ($threshold > 0 && $active >= $threshold) {
                AgentAchievement::firstOrCreate(
                    ['agent_id' => $agent->id, 'key' => 'active-referrals-'.$threshold],
                    ['name' => $milestone['name'] ?? "{$threshold} Referrals", 'threshold' => $threshold, 'unlocked_at' => now()],
                );
            }
        }
    }
}
