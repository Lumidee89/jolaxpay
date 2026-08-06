<?php

namespace App\Enums;

/**
 * PRD §7.1 — electricity is Phase 1; the rest are Phase 2. Electricity
 * vends against a saved Meter/Disco; every other case vends against a
 * generic Biller (+ optional saved Beneficiary) — see
 * App\Domain\Vending\VendingManager and App\Models\Biller.
 */
enum ServiceType: string
{
    case Electricity = 'electricity';
    case Airtime = 'airtime';
    case Data = 'data';
    case CableTv = 'cable_tv';
    case Education = 'education';

    /** Electricity is the only meter-anchored service — everything else is biller-anchored. */
    public function isBillerBased(): bool
    {
        return $this !== self::Electricity;
    }
}
