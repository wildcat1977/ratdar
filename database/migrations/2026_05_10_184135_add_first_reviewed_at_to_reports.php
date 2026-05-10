<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->timestamp('first_reviewed_at')->nullable()->after('reviewed_at');
        });

        // 回填：以現有 reviewed_at 作為首次審核時間的最佳近似值
        DB::statement('UPDATE reports SET first_reviewed_at = reviewed_at WHERE reviewed_at IS NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn('first_reviewed_at');
        });
    }
};
