<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 授权模板-授权范围 多对多中间表。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_template_scope', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('license_templates')->cascadeOnDelete();
            $table->foreignId('scope_id')->constrained('license_scopes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['template_id', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_template_scope');
    }
};
