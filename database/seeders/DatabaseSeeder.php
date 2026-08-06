<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * RolesAndPermissionsSeeder and DiscoSeeder are safe (and expected) to
     * run in every environment. DemoDataSeeder no-ops outside `local`.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            DiscoSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
