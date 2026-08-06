<?php

namespace Database\Seeders;

use App\Models\Disco;
use Illuminate\Database\Seeder;

/**
 * Nigeria's electricity distribution companies (PRD §7.1, TRD §4).
 * `api_provider_code` holds VTpass's `serviceID` for that biller
 * (https://vtpass.com/documentation/) — verified against VTpass's own
 * per-biller docs, not guessed, since VtpassElectricityProvider sends this
 * value verbatim as `serviceID` on every merchant-verify/pay/requery call.
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
            Disco::updateOrCreate(
                ['code' => $disco['code']],
                [
                    'name' => $disco['name'],
                    'region' => $disco['region'],
                    'service_type' => 'electricity',
                    'api_provider_code' => $disco['vtpass'],
                    'health_status' => 'healthy',
                    'health_checked_at' => now(),
                    'is_active' => true,
                ]
            );
        }
    }
}
