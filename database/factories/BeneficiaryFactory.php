<?php

namespace Database\Factories;

use App\Models\Beneficiary;
use App\Models\Biller;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Beneficiary>
 */
class BeneficiaryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'biller_id' => Biller::factory(),
            'label' => 'My phone',
            'identifier' => fake()->unique()->numerify('080########'),
            'is_favorite' => false,
        ];
    }
}
