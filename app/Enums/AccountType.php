<?php

namespace App\Enums;

/** Chosen at registration; gates the Agent tools and referral centre. */
enum AccountType: string
{
    case Individual = 'individual';
    case Agent = 'agent';
}
