<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Withdrawal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Withdrawal>
 */
class WithdrawalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wallet_id' => Wallet::factory(),
            'amount' => '1000.00',
            'currency' => 'NGN',
            'bank_code' => '058',
            'bank_name' => 'GTBank',
            'account_number' => '0022728151',
            'account_name' => 'TEST ACCOUNT',
            'reference' => 'WD-'.Str::upper(Str::random(12)),
            'status' => 'pending',
        ];
    }
}
