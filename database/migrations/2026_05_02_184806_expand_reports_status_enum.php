<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // PostgreSQL 以 CHECK constraint 實作 enum，需手動重建以加入新值
        DB::statement('ALTER TABLE reports DROP CONSTRAINT IF EXISTS reports_status_check');
        DB::statement('ALTER TABLE reports ALTER COLUMN status TYPE VARCHAR(20)');
        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_status_check CHECK (status IN ('pending','approved','rejected','reported_1999','resolved'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("UPDATE reports SET status = 'approved' WHERE status IN ('reported_1999','resolved')");
        DB::statement('ALTER TABLE reports DROP CONSTRAINT IF EXISTS reports_status_check');
        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_status_check CHECK (status IN ('pending','approved','rejected'))");
    }
};
