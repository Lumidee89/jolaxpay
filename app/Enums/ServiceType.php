<?php

namespace App\Enums;

/** PRD §7.1 — electricity is Phase 1; the rest are Phase 2. */
enum ServiceType: string
{
    case Electricity = 'electricity';
    case Airtime = 'airtime';
    case Data = 'data';
    case CableTv = 'cable_tv';
}
