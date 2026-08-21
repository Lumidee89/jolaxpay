<?php

namespace Database\Seeders;

use App\Models\Disco;
use Illuminate\Database\Seeder;

/**
 * Nigeria's electricity distribution companies (PRD §7.1, TRD §4).
 * `api_provider_code` holds MoreValue Digital's provider ID. Only IDs
 * published in its documentation have defaults; remaining IDs must be
 * copied from the authenticated vendor dashboard into MOREVALUE_* settings.
 */
class DiscoSeeder extends Seeder
{
    public function run(): void
    {
        $discos = [
            ['name' => 'Ikeja Electric', 'code' => 'IKEDC', 'region' => 'Lagos (Ikeja)', 'vtpass' => 'ikeja-electric'],
            ['name' => 'Eko Electricity Distribution Company', 'code' => 'EKEDC', 'region' => 'Lagos (Eko)', 'vtpass' => 'eko-electric'],
            ['name' => 'Abuja Electricity Distribution Company', 'code' => 'AEDC', 'region' => 'Abuja/FCT', 'vtpass' => 'abuja-electric'],
            ['name' => 'Port Harcourt Electricity Distribution Company', 'code' => 'PHED', 'region' => 'Rivers/South-South', 'vtpass' => 'portharcourt-electric'],
            ['name' => 'Kano Electricity Distribution Company', 'code' => 'KEDCO', 'region' => 'Kano/North-West', 'vtpass' => 'kano-electric'],
            ['name' => 'Enugu Electricity Distribution Company', 'code' => 'EEDC', 'region' => 'Enugu/South-East', 'vtpass' => 'enugu-electric'],
            ['name' => 'Ibadan Electricity Distribution Company', 'code' => 'IBEDC', 'region' => 'Oyo/South-West', 'vtpass' => 'ibadan-electric'],
            ['name' => 'Benin Electricity Distribution Company', 'code' => 'BEDC', 'region' => 'Edo/South-South', 'vtpass' => 'benin-electric'],
            ['name' => 'Jos Electricity Distribution Company', 'code' => 'JED', 'region' => 'Plateau/North-Central', 'vtpass' => 'jos-electric'],
            ['name' => 'Kaduna Electric', 'code' => 'KAEDCO', 'region' => 'Kaduna/North-West', 'vtpass' => 'kaduna-electric'],
        ];

        foreach ($discos as $disco) {
            $providerId = config('vending.morevalue.electricity_providers.'.$disco['code']);
            Disco::updateOrCreate(
                ['code' => $disco['code']],
                [
                    'name' => $disco['name'],
                    'region' => $disco['region'],
                    'service_type' => 'electricity',
                    'api_provider_code' => $providerId ?: null,
                    'health_status' => 'healthy',
                    'health_checked_at' => now(),
                    'is_active' => filled($providerId),
                ]
            );
        }
    }
}
