<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Analytics\ProductAnalyticsService;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/** PRD §23 product analytics — see ProductAnalyticsService's docblock. */
class AnalyticsController extends Controller
{
    public function __construct(private readonly ProductAnalyticsService $analytics) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Analytics/Index', [
            'activation' => $this->analytics->activationRate(),
            'electricityFlow' => $this->analytics->electricityFlow(),
            'aiInsightEngagement' => $this->analytics->aiInsightEngagement(),
            'referralConversion' => $this->analytics->referralConversionRate(),
            'ticketsByCategory' => $this->analytics->supportTicketsByCategory(),
        ]);
    }
}
