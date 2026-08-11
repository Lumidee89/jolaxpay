<?php

namespace Database\Seeders;

use App\Models\Biller;
use Illuminate\Database\Seeder;

/**
 * Airtime/data/cable_tv/education billers (PRD §7.1 Phase 2) — the
 * counterpart to DiscoSeeder. `api_provider_code` holds VTpass's
 * `serviceID`, and `requires_billers_code`/`requires_variation`/
 * `supports_verify` drive VtpassBillerProvider's request shape — all
 * verified against VTpass's own per-product docs
 * (https://vtpass.com/documentation/), not guessed.
 */
class BillerSeeder extends Seeder
{
    public function run(): void
    {
        $billers = [
            // Airtime: just a phone number and an amount — no billersCode, no variation.
            ['name' => 'MTN Airtime', 'code' => 'MTN_AIRTIME', 'service_type' => 'airtime', 'vtpass' => 'mtn', 'label' => 'Phone number'],
            ['name' => 'Glo Airtime', 'code' => 'GLO_AIRTIME', 'service_type' => 'airtime', 'vtpass' => 'glo', 'label' => 'Phone number'],
            ['name' => 'Airtel Airtime', 'code' => 'AIRTEL_AIRTIME', 'service_type' => 'airtime', 'vtpass' => 'airtel', 'label' => 'Phone number'],
            ['name' => '9mobile Airtime', 'code' => '9MOBILE_AIRTIME', 'service_type' => 'airtime', 'vtpass' => 'etisalat', 'label' => 'Phone number'],

            // Data: billersCode is the phone number receiving the bundle; variation_code picks the bundle.
            ['name' => 'MTN Data', 'code' => 'MTN_DATA', 'service_type' => 'data', 'vtpass' => 'mtn-data', 'label' => 'Phone number', 'billers_code' => true, 'variation' => true],
            ['name' => 'Glo Data', 'code' => 'GLO_DATA', 'service_type' => 'data', 'vtpass' => 'glo-data', 'label' => 'Phone number', 'billers_code' => true, 'variation' => true],
            ['name' => 'Airtel Data', 'code' => 'AIRTEL_DATA', 'service_type' => 'data', 'vtpass' => 'airtel-data', 'label' => 'Phone number', 'billers_code' => true, 'variation' => true],
            ['name' => '9mobile Data', 'code' => '9MOBILE_DATA', 'service_type' => 'data', 'vtpass' => 'etisalat-data', 'label' => 'Phone number', 'billers_code' => true, 'variation' => true],

            // Cable TV: billersCode is the smartcard/IUC number; DSTV/GOtv/Startimes support merchant-verify, Showmax doesn't.
            ['name' => 'DStv', 'code' => 'DSTV', 'service_type' => 'cable_tv', 'vtpass' => 'dstv', 'label' => 'Smartcard number', 'billers_code' => true, 'variation' => true, 'verify' => true],
            ['name' => 'GOtv', 'code' => 'GOTV', 'service_type' => 'cable_tv', 'vtpass' => 'gotv', 'label' => 'Smartcard number', 'billers_code' => true, 'variation' => true, 'verify' => true],
            ['name' => 'StarTimes', 'code' => 'STARTIMES', 'service_type' => 'cable_tv', 'vtpass' => 'startimes', 'label' => 'Smartcard number', 'billers_code' => true, 'variation' => true, 'verify' => true],
            ['name' => 'Showmax', 'code' => 'SHOWMAX', 'service_type' => 'cable_tv', 'vtpass' => 'showmax', 'label' => 'Phone number', 'billers_code' => true, 'variation' => true],

            // Education: WAEC Registration and WAEC Result Checker both need only a
            // variation_code (registration type / pin type) and a phone number — no
            // billersCode; JAMB needs a Profile ID as billersCode and supports verify.
            ['name' => 'WAEC Registration', 'code' => 'WAEC_REG', 'service_type' => 'education', 'vtpass' => 'waec-registration', 'label' => null, 'variation' => true],
            ['name' => 'WAEC Result Checker', 'code' => 'WAEC', 'service_type' => 'education', 'vtpass' => 'waec', 'label' => null, 'variation' => true],
            ['name' => 'JAMB Pin Vending', 'code' => 'JAMB', 'service_type' => 'education', 'vtpass' => 'jamb', 'label' => 'JAMB Profile ID', 'billers_code' => true, 'variation' => true, 'verify' => true],
        ];

        foreach ($billers as $biller) {
            Biller::updateOrCreate(
                ['code' => $biller['code']],
                [
                    'name' => $biller['name'],
                    'service_type' => $biller['service_type'],
                    'api_provider_code' => $biller['vtpass'],
                    'identifier_label' => $biller['label'],
                    'requires_billers_code' => $biller['billers_code'] ?? false,
                    'requires_variation' => $biller['variation'] ?? false,
                    'supports_verify' => $biller['verify'] ?? false,
                    'health_status' => 'healthy',
                    'health_checked_at' => now(),
                    'is_active' => true,
                ]
            );
        }
    }
}
