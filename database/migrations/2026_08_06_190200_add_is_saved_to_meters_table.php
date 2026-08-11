<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meters', function (Blueprint $table): void {
            $table->boolean('is_saved')->default(true)->after('is_favorite');
            $table->index(['user_id', 'is_saved']);
        });
    }

    public function down(): void
    {
        Schema::table('meters', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'is_saved']);
            $table->dropColumn('is_saved');
        });
    }
};
