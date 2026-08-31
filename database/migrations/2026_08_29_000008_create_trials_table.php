<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 试用管理（trials）：
 *  - trial_code 存授权码 HMAC-SHA256 哈希（不落明文，与 licenses.key_hash 同约定）
 *  - trial_code_preview 存展示用掩码（前4+后4），明文仅创建时返回一次
 *  - customer 客户名称 AES-256-GCM 加密 + sha256 盲索引
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->text('customer')->nullable()->comment('客户名称 AES-GCM 密文');
            $table->char('customer_sha256', 64)->nullable();
            $table->char('trial_code', 64)->unique()->comment('授权码 HMAC-SHA256 哈希');
            $table->string('trial_code_preview', 24)->nullable()->comment('展示掩码 前4…后4');
            $table->unsignedInteger('trial_days')->default(7);
            $table->timestamp('starts_at')->nullable();
            $table->string('status', 20)->default('pending')->comment('pending/active/expired/revoked');
            $table->string('remark', 512)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
            $table->index('status');
            $table->index('customer_sha256');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trials');
    }
};
