<?php

namespace Database\Factories;

use App\Enums\DeliveryDestination;
use App\Enums\ServiceType;
use App\Enums\TransactionStatus;
use App\Models\Meter;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'reference' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'meter_id' => Meter::factory(),
            'service_type' => ServiceType::Electricity,
            'amount' => fake()->randomElement(['1000.00', '2000.00', '5000.00']),
            'fee' => '30.00',
            'currency' => 'NGN',
            'payment_method' => 'card',
            'delivery_destination' => DeliveryDestination::Me,
        ];
    }

    /**
     * 'status' is deliberately outside Transaction's #[Fillable] list (only
     * TransactionStateMachine sets it in application code), so the factory
     * forceFill()s it directly — always to FeeDisclosed unless a state
     * below (failed()/delivered()) overrides it afterward.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Transaction $transaction) {
            $transaction->forceFill(['status' => TransactionStatus::FeeDisclosed]);
        });
    }

    /**
     * A biller-anchored (non-electricity) purchase — no meter, a Biller +
     * billersCode instead. Chain `->for($biller)` to pin the biller
     * relationship explicitly (as electricity tests do with meters) —
     * this state deliberately doesn't generate its own Biller::factory(),
     * since a nested sub-factory here would create an extra, unwanted row
     * that a chained `for()` can't cleanly override.
     */
    public function forBiller(): static
    {
        return $this->state(fn () => [
            'meter_id' => null,
            'service_type' => ServiceType::Airtime,
            'biller_identifier' => fake()->numerify('080########'),
        ]);
    }

    public function failed(): static
    {
        return $this->afterMaking(fn (Transaction $t) => $t->forceFill(['status' => TransactionStatus::Failed]));
    }

    public function delivered(): static
    {
        return $this->state(fn () => [
            'token' => '1234-5678-9012-3456',
            'delivered_at' => now(),
        ])->afterMaking(fn (Transaction $t) => $t->forceFill(['status' => TransactionStatus::Delivered]));
    }
}
