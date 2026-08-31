<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1.34 fix: PublicKey 模型使用 SoftDeletes，但建表迁移缺少 deleted_at 列，
 * 导致公钥列表/录入接口 500（SQLSTATE no such column: public_keys.deleted_at）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_keys', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('public_keys', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
