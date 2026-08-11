<?php

// Power Circle Rewards (PRD §16). A referred user's code is redeemed at
// registration (AuthController::register -> ReferralService::redeem());
// the reward itself only fires once that referred user completes their
// first delivered transaction (RewardReferralOnFirstTransaction listener),
// not at signup — so a code can't be farmed without a real purchase behind it.
return [
    'reward_amount' => env('REFERRAL_REWARD_AMOUNT', 500),
    'reward_currency' => env('REFERRAL_REWARD_CURRENCY', 'NGN'),
];
