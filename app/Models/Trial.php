<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\AesGcmService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 试用授权登记：
 *  - trial_code 存授权码 HMAC-SHA256 哈希（明文仅创建时展示一次）
 *  - customer 客户名称 AES-256-GCM 加密 + sha256 盲索引
 */
class Trial extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'customer',
        'trial_code',
        'trial_code_preview',
        'trial_days',
        'starts_at',
        'status',
        'remark',
        'created_by',
    ];

    protected $hidden = [
        'trial_code',
    ];

    protected function casts(): array
    {
        return [
            'trial_days' => 'integer',
            'starts_at' => 'datetime',
        ];
    }

    /**
     * 客户名称：AES-256-GCM 密文存储 + sha256 盲索引。
     */
    protected function customer(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null
                ? null
                : app(AesGcmService::class)->decrypt($value),
            set: function (?string $value) {
                if ($value === null || $value === '') {
                    return ['customer' => null, 'customer_sha256' => null];
                }

                return [
                    'customer' => app(AesGcmService::class)->encrypt($value),
                    'customer_sha256' => self::sha256Of($value),
                ];
            },
        );
    }

    public static function sha256Of(string $customer): string
    {
        return hash('sha256', $customer);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
