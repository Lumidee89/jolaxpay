<?php

use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

/**
 * The admin panel has no self-serve registration and no public surface —
 * every route (besides /admin/login itself) must be blocked for anyone
 * without a staff role (TRD §7).
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('redirects a guest to the login page', function () {
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('blocks a logged-in customer with no staff role from the dashboard', function () {
    $customer = User::factory()->create();

    $this->actingAs($customer)->get('/admin')->assertForbidden();
});

it('lets a super_admin into the dashboard', function () {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');

    $this->actingAs($admin)->get('/admin')->assertOk();
});

it('lets ops manage transactions but keeps them off the users page', function () {
    $ops = User::factory()->create();
    $ops->assignRole('ops');

    $this->actingAs($ops)->get('/admin/transactions')->assertOk();
    $this->actingAs($ops)->get('/admin/users')->assertForbidden();
});

it('keeps support staff off transaction retry/refund actions', function () {
    $support = User::factory()->create();
    $support->assignRole('support');
    $transaction = Transaction::factory()->create();

    $this->actingAs($support)
        ->post("/admin/transactions/{$transaction->id}/retry")
        ->assertForbidden();
});
