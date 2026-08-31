<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 完整审计日志：管理员操作 / 客户端激活 / 心跳上报
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            // admin / client / system
            $table->enum('actor_type', ['admin', 'client', 'system'])->default('admin')->index();
            $table->string('actor_id', 64)->nullable(); // 管理员 id 或授权码 key_hash 前缀
            $table->string('action', 64)->index();
            $table->string('resource_type', 32)->nullable();
            $table->string('resource_id', 64)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->index();

            $table->index(['actor_type', 'actor_id']);
            $table->index(['resource_type', 'resource_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
