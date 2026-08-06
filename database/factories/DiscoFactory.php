<?php

namespace Database\Factories;

use App\Models\Disco;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Disco>
 */
class DiscoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Electric',
            'code' => fake()->unique()->lexify('????').fake()->numberBetween(1, 99),
            'region' => fake()->state(),
            'service_type' => 'electricity',
            'api_provider_code' => 'mock',
            'health_status' => 'healthy',
            'health_checked_at' => now(),
            'is_active' => true,
        ];
    }
}
