<?php

use App\Domain\Support\Events\SupportMessageSent;
use App\Models\SupportTicket;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/** PRD §22 "live chat" — a reply broadcasts on private-support-ticket.{id} (see routes/channels.php). */

it('broadcasts when the ticket owner sends a reply', function () {
    Event::fake([SupportMessageSent::class]);
    $user = User::factory()->create();
    $ticket = SupportTicket::factory()->for($user)->create();
    Sanctum::actingAs($user);

    $this->postJson("/api/v1/support/tickets/{$ticket->id}/messages", ['body' => 'Any update?'])
        ->assertCreated();

    Event::assertDispatched(SupportMessageSent::class, fn ($event) => $event->message->support_ticket_id === $ticket->id);
});

it('broadcasts when staff replies from the admin panel', function () {
    Event::fake([SupportMessageSent::class]);
    $this->seed(RolesAndPermissionsSeeder::class);
    $customer = User::factory()->create();
    $ticket = SupportTicket::factory()->for($customer)->create();
    $staff = User::factory()->create();
    $staff->assignRole('support');

    $this->actingAs($staff)->post(route('admin.support.reply', $ticket->id), ['body' => 'We are looking into it.'])
        ->assertRedirect();

    Event::assertDispatched(SupportMessageSent::class, fn ($event) => $event->message->is_staff_reply === true);
});

it('authorizes only the ticket owner or staff on the private channel', function () {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $ticket = SupportTicket::factory()->for($owner)->create();

    $channelCallback = fn ($user) => $user->id === $ticket->user_id || $user->isStaff();

    expect($channelCallback($owner))->toBeTrue()
        ->and($channelCallback($stranger))->toBeFalse();
});
