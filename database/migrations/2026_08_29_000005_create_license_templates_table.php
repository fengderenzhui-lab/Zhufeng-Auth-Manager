<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 授权模板（license_templates）：
 *  - duration_days 为空表示永久授权
 *  - features 为功能范围 JSON（功能特性开关，可含 scopes 勾选快照）
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_days')->nullable()->comment('授权时长（天），null=永久');
            $table->unsignedInteger('max_devices')->default(1);
            $table->json('features')->nullable()->comment('功能范围 JSON');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_templates');
    }
};
