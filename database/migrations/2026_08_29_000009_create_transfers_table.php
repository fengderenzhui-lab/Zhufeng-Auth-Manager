<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 转让与续期记录（transfers）：
 *  - type: transfer(转让改 customer) | renew(续期改 expires_at)
 *  - customer_before / customer_after 客户名快照 AES-256-GCM 加密（+盲索引）
 *  - original_expires_at / new_expires_at 到期时间快照
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('transfer')->comment('transfer/renew');
            $table->foreignId('license_id')->constrained('licenses')->cascadeOnDelete();
            $table->text('customer_before')->nullable()->comment('原客户 AES-GCM 密文');
            $table->char('customer_before_sha256', 64)->nullable();
            $table->text('customer_after')->nullable()->comment('转让后客户 AES-GCM 密文');
            $table->char('customer_after_sha256', 64)->nullable();
            $table->timestamp('original_expires_at')->nullable();
            $table->timestamp('new_expires_at')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 512)->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('license_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
