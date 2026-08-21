<?php

namespace App\Domain\Referrals;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * One-level, immutable Agent → Personal user referral attribution.
 */
class ReferralService
{
    public function ensureAgentCode(User $agent): string
    {
        if (! $agent->isAgentAccount()) {
            throw new \RuntimeException('Only Agent accounts receive Agent referral codes.');
        }

        if ($agent->referral_code) {
            return $agent->referral_code;
        }

        do {
            $code = 'JLX-'.Str::upper(Str::substr(Str::slug($agent->full_name), 0, 4)).'-'.Str::upper(Str::random(6));
        } while (User::where('referral_code', $code)->exists());

        $agent->forceFill(['referral_code' => $code, 'agent_approved_at' => $agent->agent_approved_at ?? now()])->save();

        return $code;
    }

    /**
     * Called from AuthController::register(). Silently ignores an
     * unknown/already-claimed/self-referral code rather than failing
     * registration over it — a typo'd referral code should never block
     * someone from creating an account.
     */
    public function redeem(User $newUser, ?string $code): void
    {
        if (! $code) {
            return;
        }

        if ($newUser->isAgentAccount() || Referral::where('referred_user_id', $newUser->id)->exists()) {
            return;
        }

        $agent = User::where('referral_code', trim($code))->first();

        if (! $agent || ! $agent->isAgentAccount() || ! $agent->agent_approved_at || $agent->id === $newUser->id) {
            Log::info('Agent referral code not redeemed.', [
                'code' => $code,
                'new_user_id' => $newUser->id,
            ]);

            return;
        }

        Referral::create([
            'referrer_id' => $agent->id,
            'referred_user_id' => $newUser->id,
            'code' => $agent->referral_code,
            'status' => 'qualified',
            'attributed_at' => now(),
        ]);
    }
}
