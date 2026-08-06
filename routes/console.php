<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduling domain (PRD §7.4, TRD §5) — evaluates due scheduled/recurring
// purchases. Requires `php artisan schedule:work` (or a cron entry calling
// `schedule:run` every minute) to actually fire.
Schedule::command('purchases:evaluate-scheduled')->everyFiveMinutes()->withoutOverlapping();

// VTpass's data/cable_tv/education bundle catalog and pricing change from
// time to time — keep the cached `biller_variations` table fresh.
Schedule::command('vtpass:sync-variations')->daily()->withoutOverlapping();
