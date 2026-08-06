<?php

namespace App\Enums;

/** Direct Token Delivery destination choice at checkout (PRD §7.3). */
enum DeliveryDestination: string
{
    case Me = 'me';
    case MeterOwner = 'meter_owner';
    case SomeoneElse = 'someone_else';
}
