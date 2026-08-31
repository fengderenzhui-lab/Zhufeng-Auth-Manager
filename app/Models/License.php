<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LicenseStatus;
use App\Services\AesGcmService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class License extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'product_id',
        'key_prefix',
        'key_hash',
        'status',
        'customer',
        'max_devices',
        'meta',
        'expires_at',
        'activated_at',
        'revoked_at',
        'last_heartbeat_at',
        'created_by',
    ];

    protected $hidden = [
        'key_hash',
    ];

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'max_devices' => 'integer',
            'expires_at' => 'datetime',
            'activated_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_heartbeat_at' => 'datetime',
        ];
    }

    /**
     * 客户名称：AES-256-GCM 密文存储，明文 sha256 盲索引列用于精确检索。
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

    /**
     * 客户元数据：加密 JSON 存储，读取时解密还原数组（保持 API 结构不变）。
     */
    protected function meta(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($value === null || $value === '') {
                    return [];
                }
                $decrypted = app(AesGcmService::class)->decrypt($value);
                $decoded = json_decode($decrypted, true);

                return is_array($decoded) ? $decoded : [];
            },
            set: function (mixed $value) {
                if ($value === null || $value === [] || $value === '') {
                    return null;
                }
                $json = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                return app(AesGcmService::class)->encrypt((string) $json);
            },
        );
    }

    /**
     * 客户名称明文 -> 盲索引列值（sha256）。
     */
    public static function sha256Of(string $customer): string
    {
        return hash('sha256', $customer);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function heartbeats(): HasMany
    {
        return $this->hasMany(Heartbeat::class);
    }

    /**
     * 最近一次心跳（心跳监控页使用）。
     */
    public function latestHeartbeat(): HasOne
    {
        // V1.34 fix: latestOfMany 在 SQLite 生成 inner join 子查询导致 license_id 歧义，改显式关联子查询
        return $this->hasOne(Heartbeat::class)
            ->whereRaw('heartbeats.id = (SELECT h2.id FROM heartbeats h2 WHERE h2.license_id = heartbeats.license_id ORDER BY h2.checked_at DESC, h2.id DESC LIMIT 1)');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', LicenseStatus::Active);
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
