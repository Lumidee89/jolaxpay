<?php

use App\Models\BusinessLedgerEntry;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('registers as an individual account by default', function () {
    $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Regular User',
        'phone_number' => '08031114444',
        'email' => 'regular@example.com',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
        'device_name' => 'test-device',
    ])->assertCreated()->assertJsonPath('user.account_type', 'individual');
});

it('registers as a business account when chosen', function () {
    $this->postJson('/api/v1/auth/register', [
        'full_name' => 'Shop Owner',
        'phone_number' => '08031115555',
        'email' => 'shop@example.com',
        'password' => 'Password1',
        'password_confirmation' => 'Password1',
        'account_type' => 'agent',
        'device_name' => 'test-device',
    ])->assertCreated()->assertJsonPath('user.account_type', 'agent');
});

it('blocks an individual account from the business ledger', function () {
    $user = User::factory()->create(['account_type' => 'individual']);
    Sanctum::actingAs($user);

    $this->getJson('/api/v1/agent/entries')->assertForbidden();
    $this->postJson('/api/v1/agent/entries', [
        'type' => 'income', 'category' => 'Sales', 'amount' => '5000', 'entry_date' => now()->toDateString(),
    ])->assertForbidden();
});

it('lets a business account record, list, and delete ledger entries', function () {
    $user = User::factory()->create(['account_type' => 'agent']);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/agent/entries', [
        'type' => 'income',
        'category' => 'Sales',
        'amount' => '15000',
        'note' => 'Weekly sales',
        'entry_date' => now()->toDateString(),
    ]);
    $response->assertCreated();
    $entryId = $response->json('data.id');

    $this->getJson('/api/v1/agent/entries')->assertOk()->assertJsonCount(1, 'data');

    $this->deleteJson("/api/v1/agent/entries/{$entryId}")->assertOk();
    $this->getJson('/api/v1/agent/entries')->assertOk()->assertJsonCount(0, 'data');
});

it('blocks a business account from deleting another user\'s ledger entry', function () {
    $owner = User::factory()->create(['account_type' => 'agent']);
    $entry = BusinessLedgerEntry::create([
        'user_id' => $owner->id, 'type' => 'expense', 'category' => 'Rent', 'amount' => '20000', 'entry_date' => now(),
    ]);

    $other = User::factory()->create(['account_type' => 'agent']);
    Sanctum::actingAs($other);

    $this->deleteJson("/api/v1/agent/entries/{$entry->id}")->assertForbidden();
});

it('summarizes income, expense, and net for the current month', function () {
    $user = User::factory()->create(['account_type' => 'agent']);
    Sanctum::actingAs($user);

    BusinessLedgerEntry::create(['user_id' => $user->id, 'type' => 'income', 'category' => 'Sales', 'amount' => '50000', 'entry_date' => now()]);
    BusinessLedgerEntry::create(['user_id' => $user->id, 'type' => 'expense', 'category' => 'Rent', 'amount' => '20000', 'entry_date' => now()]);
    BusinessLedgerEntry::create(['user_id' => $user->id, 'type' => 'income', 'category' => 'Sales', 'amount' => '99999', 'entry_date' => now()->subMonths(2)]);

    $response = $this->getJson('/api/v1/agent/summary');

    $response->assertOk()
        ->assertJsonPath('data.this_month.income', 50000)
        ->assertJsonPath('data.this_month.expense', 20000)
        ->assertJsonPath('data.this_month.net', 30000);
});
