<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 授权范围（license_scopes）：可被授权模板引用的功能/权限范围清单。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_scopes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 128);
            $table->string('slug', 128)->unique()->comment('唯一标识');
            $table->string('description', 512)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_scopes');
    }
};
