<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 客户端 Ed25519 公钥库：管理端可视化录入（导入/列表/详情/删除）。
 * 录入的公钥用于客户端 SDK 凭证（PASETO v4.public / Ed25519 载荷）验签。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_keys', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128)->nullable()->index();
            $table->text('public_key');                  // base64 Ed25519 公钥（导入原文）
            $table->char('fingerprint', 64)->unique();   // sha256(base64 公钥)，防重复导入
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_keys');
    }
};
