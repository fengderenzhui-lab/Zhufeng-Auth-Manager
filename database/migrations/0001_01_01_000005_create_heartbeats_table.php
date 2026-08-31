<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heartbeats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained('licenses')->cascadeOnDelete();
            $table->foreignId('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('client_ip', 45)->nullable();
            $table->string('client_ua', 255)->nullable();
            // ok / invalid / expired / revoked / blacklisted / over_limit
            $table->string('status', 24)->default('ok')->index();
            $table->timestamp('checked_at')->index();
            $table->timestamps();

            $table->index(['license_id', 'checked_at']);
            $table->index(['device_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heartbeats');
    }
};
