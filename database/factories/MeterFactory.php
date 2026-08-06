<?php

namespace Database\Factories;

use App\Models\Disco;
use App\Models\Meter;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meter>
 */
class MeterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'disco_id' => Disco::factory(),
            'label' => 'Home',
            'meter_number' => fake()->unique()->numerify('#############'),
            'meter_type' => 'prepaid',
            'is_favorite' => false,
        ];
    }
}
