<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 管理端状态化 API Token（仅存 SHA-256 哈希，登出即删除）
        Schema::create('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 64)->default('api');
            $table->string('token_hash', 64)->unique();
            $table->json('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'revoked_at']);
        });

        // 登录防爆破：记录每次登录尝试（成功/失败）
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->index();
            $table->string('ip', 45);
            $table->string('user_agent', 255)->nullable();
            $table->boolean('success')->default(false);
            $table->timestamp('attempted_at')->index();
            $table->index(['email', 'ip', 'attempted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('api_tokens');
    }
};
