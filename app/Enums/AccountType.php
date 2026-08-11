<?php

namespace App\Enums;

/** PRD §13 — chosen at registration; gates the Business Dashboard surface. */
enum AccountType: string
{
    case Individual = 'individual';
    case Business = 'business';
}
