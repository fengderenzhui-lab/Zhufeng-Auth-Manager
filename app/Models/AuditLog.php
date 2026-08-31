<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AesGcmService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_type',
        'actor_id',
        'action',
        'resource_type',
        'resource_id',
        'ip',
        'user_agent',
        'context',
        'prev_hash',
        'hash',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    /**
     * v1.2.5：审计日志敏感数据 AES-256-GCM 加密存储。
     * 哈希链 canonical 始终基于解密后的明文语义重建，历史行不回算 hash。
     */
    protected function ip(): Attribute
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

    protected function context(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($value === null || $value === '') {
                    return null;
                }
                $decrypted = app(AesGcmService::class)->decrypt($value);
                $decoded = json_decode($decrypted, true);

                return is_array($decoded) ? $decoded : null;
            },
            set: function (mixed $value) {
                if ($value === null || $value === '' || $value === []) {
                    return null;
                }
                $json = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                return app(AesGcmService::class)->encrypt((string) $json);
            },
        );
    }

    public function scopeBetween(Builder $query, ?string $from, ?string $to): Builder
    {
        if ($from !== null && $from !== '') {
            $query->where('created_at', '>=', $from);
        }
        if ($to !== null && $to !== '') {
            $query->where('created_at', '<=', $to);
        }

        return $query;
    }
}
