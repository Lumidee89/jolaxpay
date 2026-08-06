<?php

namespace Database\Seeders;

use App\Models\Disco;
use Illuminate\Database\Seeder;

/** Nigeria's electricity distribution companies (PRD §7.1, TRD §4). */
class DiscoSeeder extends Seeder
{
    public function run(): void
    {
        $discos = [
            ['name' => 'Ikeja Electric', 'code' => 'IKEDC', 'region' => 'Lagos (Ikeja)'],
            ['name' => 'Eko Electricity Distribution Company', 'code' => 'EKEDC', 'region' => 'Lagos (Eko)'],
            ['name' => 'Abuja Electricity Distribution Company', 'code' => 'AEDC', 'region' => 'Abuja/FCT'],
            ['name' => 'Port Harcourt Electricity Distribution Company', 'code' => 'PHED', 'region' => 'Rivers/South-South'],
            ['name' => 'Kano Electricity Distribution Company', 'code' => 'KEDCO', 'region' => 'Kano/North-West'],
            ['name' => 'Enugu Electricity Distribution Company', 'code' => 'EEDC', 'region' => 'Enugu/South-East'],
            ['name' => 'Ibadan Electricity Distribution Company', 'code' => 'IBEDC', 'region' => 'Oyo/South-West'],
            ['name' => 'Benin Electricity Distribution Company', 'code' => 'BEDC', 'region' => 'Edo/South-South'],
            ['name' => 'Jos Electricity Distribution Company', 'code' => 'JED', 'region' => 'Plateau/North-Central'],
            ['name' => 'Kaduna Electric', 'code' => 'KAEDCO', 'region' => 'Kaduna/North-West'],
        ];

        foreach ($discos as $disco) {
            Disco::firstOrCreate(
                ['code' => $disco['code']],
                [
                    'name' => $disco['name'],
                    'region' => $disco['region'],
                    'service_type' => 'electricity',
                    'api_provider_code' => 'mock',
                    'health_status' => 'healthy',
                    'health_checked_at' => now(),
                    'is_active' => true,
                ]
            );
        }
    }
}
