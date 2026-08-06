<?php

namespace App\Enums;

enum LedgerReason: string
{
    case Purchase = 'purchase';
    case Refund = 'refund';
    case WalletFunding = 'wallet_funding';
    case ReferralReward = 'referral_reward';
    case Adjustment = 'adjustment';
}
