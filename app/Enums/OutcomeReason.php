<?php

namespace App\Enums;

/** Reason list shown on Outcome Confirmation "No" (PRD §7.6). */
enum OutcomeReason: string
{
    case InvalidToken = 'invalid_token';
    case MeterProblem = 'meter_problem';
    case AreaOutage = 'area_outage';
    case NotSure = 'not_sure';
}
