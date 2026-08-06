<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * RolesAndPermissionsSeeder, DiscoSeeder, and BillerSeeder are safe
     * (and expected) to run in every environment. DemoDataSeeder no-ops
     * outside `local`.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            DiscoSeeder::class,
            BillerSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
