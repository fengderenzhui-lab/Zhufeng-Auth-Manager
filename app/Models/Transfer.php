<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AesGcmService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 转让与续期记录：基于 licenses 表的业务变更快照（含审计语义）。
 */
class Transfer extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'type',
        'license_id',
        'customer_before',
        'customer_after',
        'original_expires_at',
        'new_expires_at',
        'operator_id',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'original_expires_at' => 'datetime',
            'new_expires_at' => 'datetime',
        ];
    }

    protected function customerBefore(): Attribute
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

    protected function customerAfter(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null
                ? null
                : app(AesGcmService::class)->decrypt($value),
            set: function (?string $value) {
                if ($value === null || $value === '') {
                    return ['customer_after' => null, 'customer_after_sha256' => null];
                }

                return [
                    'customer_after' => app(AesGcmService::class)->encrypt($value),
                    'customer_after_sha256' => hash('sha256', $value),
                ];
            },
        );
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
