<?php

use App\Models\SupportTicket;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Sanctum::actingAs(User::factory()->create());
});

it('auto-categorizes a new ticket from its subject and message', function () {
    $this->postJson('/api/v1/support/tickets', [
        'subject' => 'Wallet not credited',
        'message' => 'I funded my wallet by card but the balance did not update.',
    ])->assertCreated();

    expect(SupportTicket::first()->category)->toBe('billing');
});

it('falls back to "other" when nothing matches', function () {
    $this->postJson('/api/v1/support/tickets', [
        'subject' => 'Question',
        'message' => 'Just wanted to say thanks for the great service.',
    ])->assertCreated();

    expect(SupportTicket::first()->category)->toBe('other');
});
