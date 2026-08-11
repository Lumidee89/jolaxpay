<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletFundingIntent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WalletFundingIntent>
 */
class WalletFundingIntentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wallet_id' => Wallet::factory(),
            'reference' => 'WF-'.Str::upper(Str::random(12)),
            'amount' => '1000.00',
            'currency' => 'NGN',
            'status' => 'pending',
        ];
    }
}
