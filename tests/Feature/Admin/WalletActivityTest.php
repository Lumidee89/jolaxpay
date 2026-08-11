<?php

use App\Domain\Wallet\LedgerService;
use App\Enums\LedgerReason;
use App\Models\User;
use App\Models\WalletFundingIntent;
use App\Models\Withdrawal;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');
});

it('blocks a guest and a permission-less staff member', function () {
    $this->get('/admin/wallet-activity')->assertRedirect('/admin/login');

    $support = User::factory()->create();
    $support->assignRole('support');
    $this->actingAs($support)->get('/admin/wallet-activity')->assertForbidden();
});

it('lets ops (who hold view-reconciliation) see wallet activity', function () {
    $ops = User::factory()->create();
    $ops->assignRole('ops');

    $this->actingAs($ops)->get('/admin/wallet-activity')->assertOk();
});

it('lists fundings, withdrawals, and transfers with the right people attached', function () {
    $sender = User::factory()->create(['full_name' => 'Sender Sam']);
    $recipient = User::factory()->create(['full_name' => 'Recipient Rita']);

    $ledger = app(LedgerService::class);
    $senderWallet = $ledger->walletFor($sender);
    $ledger->credit($senderWallet, '5000.00', LedgerReason::WalletFunding);
    $ledger->transfer($senderWallet, $ledger->walletFor($recipient)->wallet_address, '1500.00', 'lunch money');

    WalletFundingIntent::factory()->for($sender)->create(['status' => 'success', 'amount' => '2000.00']);
    Withdrawal::factory()->for($sender)->create(['status' => 'failed', 'failure_reason' => 'Could not resolve account name']);

    $response = $this->actingAs($this->admin)->get('/admin/wallet-activity');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Admin/WalletActivity/Index')
        ->where('fundings.total', 1)
        ->where('fundings.data.0.user.full_name', 'Sender Sam')
        ->where('withdrawals.total', 1)
        ->where('withdrawals.data.0.status', 'failed')
        ->where('transfers.total', 1) // only the transfer_out side, not both legs
        ->where('transfers.data.0.wallet.user.full_name', 'Sender Sam')
        ->where('transfers.data.0.recipient_name', 'Recipient Rita')
    );
});

it('filters fundings and withdrawals by status independently', function () {
    $user = User::factory()->create();
    WalletFundingIntent::factory()->for($user)->create(['status' => 'success']);
    WalletFundingIntent::factory()->for($user)->create(['status' => 'failed']);
    Withdrawal::factory()->for($user)->create(['status' => 'pending']);
    Withdrawal::factory()->for($user)->create(['status' => 'success']);

    $response = $this->actingAs($this->admin)->get('/admin/wallet-activity?funding_status=failed&withdrawal_status=pending');

    $response->assertInertia(fn ($page) => $page
        ->where('fundings.total', 1)
        ->where('fundings.data.0.status', 'failed')
        ->where('withdrawals.total', 1)
        ->where('withdrawals.data.0.status', 'pending')
    );
});
