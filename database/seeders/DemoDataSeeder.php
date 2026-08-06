<?php

namespace Database\Seeders;

use App\Domain\Wallet\LedgerService;
use App\Enums\DeliveryDestination;
use App\Enums\LedgerReason;
use App\Enums\ServiceType;
use App\Enums\TransactionStatus;
use App\Models\Disco;
use App\Models\Meter;
use App\Models\Transaction;
use App\Models\TransactionStatusHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local-only sample data so a fresh checkout isn't staring at an empty
 * admin dashboard. Seeds are written directly (not through
 * TransactionService's queued pipeline) so they don't depend on a queue
 * worker running during `db:seed`.
 */
class DemoDataSeeder extends Seeder
{
    public function run(LedgerService $ledger): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@jolaxpay.com'],
            [
                'full_name' => 'JolaxPay Admin',
                'phone_number' => '+2348000000000',
                'password' => 'password',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['super_admin']);
        $ledger->walletFor($admin);

        $customer = User::firstOrCreate(
            ['email' => 'adaeze@example.com'],
            [
                'full_name' => 'Adaeze Okafor',
                'phone_number' => '+2348011111111',
                'password' => 'password',
                'email_verified_at' => now(),
                'phone_verified_at' => now(),
            ]
        );
        $wallet = $ledger->walletFor($customer);

        $disco = Disco::where('code', 'IKEDC')->first();

        if (! $disco) {
            return;
        }

        $meter = Meter::firstOrCreate(
            ['user_id' => $customer->id, 'meter_number' => '04012345678'],
            [
                'disco_id' => $disco->id,
                'label' => 'Home',
                'meter_type' => 'prepaid',
                'is_favorite' => true,
            ]
        );

        if (Transaction::where('user_id', $customer->id)->exists()) {
            return;
        }

        $this->completedPurchase($customer, $meter, $ledger, $wallet->id);
        $this->failedPurchase($customer, $meter, $ledger);
    }

    protected function completedPurchase($customer, Meter $meter, LedgerService $ledger, int $walletId): void
    {
        $transaction = Transaction::create([
            'user_id' => $customer->id,
            'meter_id' => $meter->id,
            'service_type' => ServiceType::Electricity,
            'amount' => '5000.00',
            'fee' => '75.00',
            'currency' => 'NGN',
            'amount_ngn' => '5000.00',
            'payment_method' => 'card',
            'payment_reference' => 'MOCK-PAY-DEMO1',
            'token' => '1234-5678-9012-3456',
            'delivery_destination' => DeliveryDestination::Me,
            'delivery_channel' => 'in_app',
            'recipient_name' => $customer->full_name,
            'recipient_user_id' => $customer->id,
            'delivered_at' => now()->subHour(),
            'outcome_confirmed' => true,
            'outcome_confirmed_at' => now()->subMinutes(50),
        ]);

        // 'status' is deliberately outside Transaction's #[Fillable] list
        // (TransactionStateMachine normally owns it) — this seeder is
        // fabricating already-settled history directly, so it forceFill()s
        // the terminal status rather than replaying the whole pipeline.
        $transaction->forceFill(['status' => TransactionStatus::OutcomeConfirmed])->save();

        $previous = null;

        foreach ([
            TransactionStatus::FeeDisclosed, TransactionStatus::PaymentInitiated, TransactionStatus::PaymentReceived,
            TransactionStatus::PaymentConfirmed, TransactionStatus::GeneratingToken, TransactionStatus::TokenGenerated,
            TransactionStatus::Delivered, TransactionStatus::OutcomeConfirmed,
        ] as $i => $status) {
            TransactionStatusHistory::create([
                'transaction_id' => $transaction->id,
                'from_status' => $previous,
                'to_status' => $status,
                'created_at' => now()->subHours(2)->addMinutes($i * 5),
            ]);
            $previous = $status;
        }
    }

    protected function failedPurchase($customer, Meter $meter, LedgerService $ledger): void
    {
        $transaction = Transaction::create([
            'user_id' => $customer->id,
            'meter_id' => $meter->id,
            'service_type' => ServiceType::Electricity,
            'amount' => '2000.00',
            'fee' => '30.00',
            'currency' => 'NGN',
            'amount_ngn' => '2000.00',
            'payment_method' => 'wallet',
            'delivery_destination' => DeliveryDestination::Me,
            'refunded_to_wallet' => true,
        ]);

        // See the note in completedPurchase() re: 'status' and, here,
        // 'vend_attempts' — neither is fillable, so they're set directly.
        $transaction->forceFill(['status' => TransactionStatus::Failed, 'vend_attempts' => 3])->save();

        TransactionStatusHistory::create([
            'transaction_id' => $transaction->id,
            'from_status' => TransactionStatus::GeneratingToken,
            'to_status' => TransactionStatus::Failed,
            'note' => 'Mock provider: simulated vending failure after 3 attempts.',
            'created_at' => now()->subMinutes(30),
        ]);

        $wallet = $ledger->walletFor($customer);
        $ledger->credit($wallet, '2030.00', LedgerReason::Refund, $transaction, ['reason' => 'Demo seed refund']);
    }
}
