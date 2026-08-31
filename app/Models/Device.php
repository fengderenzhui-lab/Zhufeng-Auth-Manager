<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AesGcmService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Device extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'license_id',
        'fingerprint_hash',
        'fingerprint_hash_sha256',
        'device_name',
        'is_active',
        'last_ip',
        'last_user_agent',
        'first_seen_at',
        'last_seen_at',
    ];

    protected $hidden = [
        'fingerprint_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /**
     * 指纹写入自动加密：fingerprint_hash 存 AES-256-GCM 密文，
     * fingerprint_hash_sha256 存明文指纹的 sha256（独立索引列，用于精确查询）。
     */
    public function setFingerprintHashAttribute(string $value): void
    {
        $this->attributes['fingerprint_hash'] = app(AesGcmService::class)->encrypt($value);
        $this->attributes['fingerprint_hash_sha256'] = self::sha256Of($value);
    }

    /**
     * 指纹明文 -> 索引列值（sha256）。
     */
    public static function sha256Of(string $fingerprint): string
    {
        return hash('sha256', $fingerprint);
    }

    /**
     * v1.2.5：设备痕迹 AES-256-GCM 加密存储。
     */
    protected function lastIp(): Attribute
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

    protected function lastUserAgent(): Attribute
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
}
