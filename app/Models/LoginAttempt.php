<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AesGcmService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * 登录尝试（防爆破）。每次登录成功/失败均落一条。
 * v1.2.5：email / ip / user_agent 改 AES-256-GCM 密文存储，
 * 新增 email_sha256 / ip_sha256 盲索引列，锁定计数等值查询迁移到索引列。
 */
class LoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'email',
        'ip',
        'user_agent',
        'success',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'success' => 'boolean',
            'attempted_at' => 'datetime',
        ];
    }

    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null
                ? null
                : app(AesGcmService::class)->decrypt($value),
            set: function (?string $value) {
                if ($value === null || $value === '') {
                    $this->attributes['email_sha256'] = null;

                    return null;
                }
                $this->attributes['email_sha256'] = self::sha256Of($value);

                return app(AesGcmService::class)->encrypt($value);
            },
        );
    }

    protected function ip(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null
                ? null
                : app(AesGcmService::class)->decrypt($value),
            set: function (?string $value) {
                if ($value === null || $value === '') {
                    return ['ip' => null, 'ip_sha256' => null];
                }

                return [
                    'ip' => app(AesGcmService::class)->encrypt($value),
                    'ip_sha256' => self::sha256Of($value),
                ];
            },
        );
    }

    protected function userAgent(): Attribute
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

    public static function sha256Of(string $value): string
    {
        return hash('sha256', $value);
    }
}
