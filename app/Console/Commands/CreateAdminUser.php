<?php

namespace App\Console\Commands;

use App\Domain\Wallet\LedgerService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * The admin panel has no public registration (TRD §7) — this is how the
 * first super_admin (and any subsequent staff account) gets created.
 */
class CreateAdminUser extends Command
{
    protected $signature = 'admin:create-user
        {--name= : Full name}
        {--email= : Email address}
        {--phone= : Phone number}
        {--password= : Password (prompted if omitted)}
        {--role=super_admin : super_admin, ops, or support}';

    protected $description = 'Create a staff (admin/ops/support) user for the admin panel.';

    public function handle(LedgerService $ledger): int
    {
        $name = $this->option('name') ?? $this->ask('Full name');
        $email = $this->option('email') ?? $this->ask('Email');
        $phone = $this->option('phone') ?? $this->ask('Phone number');
        $password = $this->option('password') ?? $this->secret('Password');
        $role = $this->option('role');

        $validator = Validator::make(
            compact('name', 'email', 'phone', 'password', 'role'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'unique:users,email'],
                'phone' => ['required', 'string', 'unique:users,phone_number'],
                'password' => ['required', 'string', 'min:8'],
                'role' => ['required', 'in:super_admin,ops,support'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'full_name' => $name,
            'email' => $email,
            'phone_number' => $phone,
            'password' => $password,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
        ]);

        $user->assignRole($role);
        $ledger->walletFor($user);

        $this->info("Created {$role} user: {$user->email}");

        return self::SUCCESS;
    }
}
