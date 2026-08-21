<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $billerIds = DB::table('billers')
            ->whereIn('service_type', ['data', 'cable_tv'])
            ->pluck('id');

        DB::table('biller_variations')
            ->whereIn('biller_id', $billerIds)
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Provider plan IDs cannot be safely distinguished after cutover.
        // Deliberately do not reactivate legacy VTpass plans on rollback.
    }
};
