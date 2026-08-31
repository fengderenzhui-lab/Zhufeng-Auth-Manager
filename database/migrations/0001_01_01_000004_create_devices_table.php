<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 设备绑定（一机一码）：指纹为服务端计算后的哈希，不存原始硬件信息
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('licenses')->cascadeOnDelete();

            // 服务端加盐 HMAC 计算的指纹哈希（固定 64 hex）
            $table->char('fingerprint_hash', 64);
            $table->string('device_name', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('last_ip', 45)->nullable();
            $table->string('last_user_agent', 255)->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // 同一授权码下不允许重复绑定同一设备指纹
            $table->unique(['license_id', 'fingerprint_hash']);
            $table->index('fingerprint_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
