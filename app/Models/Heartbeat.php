<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AesGcmService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Heartbeat extends Model
{
    protected $fillable = [
        'license_id',
        'device_id',
        'client_ip',
        'client_ua',
        'status',
        'checked_at',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
        ];
    }

    /**
     * v1.2.5：心跳痕迹 AES-256-GCM 加密存储。
     */
    protected function clientIp(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null
                ? null
                : app(AesGcmService::class)->decrypt($value),
            set: fn (?string $value) => $value === null || $value === ''
                ? null
                : app(AesGcmService::class)->encrypt($value),
        );
    }

    protected function clientUa(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null
                ? null
                : app(AesGcmService::class)->decrypt($value),
            set: fn (?string $value) => $value === null || $value === ''
                ? null
                : app(AesGcmService::class)->encrypt($value),
        );
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
