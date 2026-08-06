<?php

namespace Database\Factories;

use App\Models\Biller;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Biller>
 */
class BillerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'code' => fake()->unique()->lexify('????'),
            'service_type' => 'airtime',
            'api_provider_code' => 'mock',
            'identifier_label' => 'Phone number',
            'requires_billers_code' => false,
            'requires_variation' => false,
            'supports_verify' => false,
            'health_status' => 'healthy',
            'health_checked_at' => now(),
            'is_active' => true,
        ];
    }

    public function data(): static
    {
        return $this->state(fn () => [
            'service_type' => 'data',
            'identifier_label' => 'Phone number',
            'requires_billers_code' => true,
            'requires_variation' => true,
        ]);
    }

    public function cableTv(): static
    {
        return $this->state(fn () => [
            'service_type' => 'cable_tv',
            'identifier_label' => 'Smartcard number',
            'requires_billers_code' => true,
            'requires_variation' => true,
            'supports_verify' => true,
        ]);
    }

    public function education(): static
    {
        return $this->state(fn () => [
            'service_type' => 'education',
            'identifier_label' => 'Profile ID',
            'requires_billers_code' => false,
            'requires_variation' => true,
        ]);
    }
}
