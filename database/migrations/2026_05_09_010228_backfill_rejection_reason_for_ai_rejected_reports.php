<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 有上傳照片但沒有填寫拒絕原因的 rejected 通報 → AI 自動審核攔截
        DB::table('reports')
            ->where('status', 'rejected')
            ->whereNull('rejection_reason')
            ->whereNotNull('image_path')
            ->update(['rejection_reason' => 'ai_auto']);

        // 沒有照片也沒有填寫原因的 rejected 通報 → 其他（人工拒絕但未記錄理由）
        DB::table('reports')
            ->where('status', 'rejected')
            ->whereNull('rejection_reason')
            ->whereNull('image_path')
            ->update(['rejection_reason' => 'other']);
    }

    public function down(): void
    {
        // 回滾時清除由此 migration 填入的預設值（不影響其他有值的紀錄）
        // 注意：down() 無法完美還原，因為無法區分哪些 'ai_auto'/'other' 是此 migration 填的
    }
};
