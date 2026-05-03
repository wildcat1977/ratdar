<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 新增 type 欄位以區分通報類型：
     *   - rat:    發現鼠蹤（既有功能，預設值）
     *   - poison: 發現隨意放置的毒餌 / 老鼠藥
     */
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->string('type', 16)->default('rat')->after('user_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('type');
        });
    }
};
