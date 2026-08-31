<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // 授权码：不落明文，仅存服务端密钥派生的 HMAC-SHA256 哈希 + 展示前缀
            // key_hash 用于精确检索与防枚举（服务端密钥参与，无法离线爆破还原明文）
            $table->string('key_prefix', 16)->nullable();
            $table->char('key_hash', 64)->unique();

            // 状态机：pending(待定) / active(有效) / expired(过期) / revoked(已吊销) / blacklisted(已拉黑)
            $table->enum('status', ['pending', 'active', 'expired', 'revoked', 'blacklisted'])
                ->default('pending')->index();

            $table->string('customer', 128)->nullable()->index();
            $table->unsignedInteger('max_devices')->default(1);

            $table->json('meta')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'expires_at']);
            $table->index('created_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
