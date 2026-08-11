<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Admin-panel roles (TRD §7: "Admin routes protected by role/permission
 * middleware ... fully separate from the mobile Sanctum guard").
 * `super_admin` is granted every permission below rather than being
 * special-cased in middleware, so `permission:*` checks stay meaningful.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        Cache::forget('spatie.permission.cache');

        $permissions = [
            'view-dashboard',
            'manage-transactions',   // retry / refund / escalate
            'manage-providers',      // DisCo health, failover
            'manage-support',        // tickets
            'manage-referrals',      // reward issuance, abuse flags
            'manage-users',          // password reset, device de-auth, fraud flags
            'view-reconciliation',   // ledger vs. processor/provider settlement
            'manage-fraud',          // review/dismiss auto-generated FraudFlag rows
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $superAdmin = Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions($permissions);

        $ops = Role::firstOrCreate(['name' => 'ops', 'guard_name' => 'web']);
        $ops->syncPermissions(['view-dashboard', 'manage-transactions', 'manage-providers', 'view-reconciliation', 'manage-fraud']);

        $support = Role::firstOrCreate(['name' => 'support', 'guard_name' => 'web']);
        $support->syncPermissions(['view-dashboard', 'manage-support']);
    }
}
