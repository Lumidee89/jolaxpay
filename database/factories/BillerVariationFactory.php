<?php

namespace Database\Factories;

use App\Models\Biller;
use App\Models\BillerVariation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillerVariation>
 */
class BillerVariationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'biller_id' => Biller::factory(),
            'variation_code' => fake()->unique()->lexify('bundle-????'),
            'name' => fake()->words(3, true),
            'amount' => fake()->randomElement(['100.00', '200.00', '500.00']),
            'fixed_price' => true,
            'is_active' => true,
        ];
    }
}
