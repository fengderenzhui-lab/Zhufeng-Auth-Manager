<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Role;
use App\Services\AesGcmService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;

class User extends Model
{
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'must_change_password',
        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'hmac_secret_encrypted',
        'email_sha256',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // 创建管理员时自动生成其独立 HMAC 签名密钥（AES-256-GCM 加密存储）
        static::creating(function (User $user) {
            if (blank($user->hmac_secret_encrypted)) {
                $user->hmac_secret_encrypted = app(AesGcmService::class)->encrypt(self::generateHmacSecret());
            }
        });
    }

    /**
     * email 加密访问器（V1.30）：
     *  - 写入：规范化（trim + 小写）后 AES-256-GCM 加密，并自动维护 email_sha256 盲索引；
     *  - 读取：自动解密，管理员列表 / 详情仍展示明文邮箱；
     *  - 登录 / 创建 / 校验邮箱等按 email 查询统一迁移到 email_sha256 盲索引精确匹配。
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : app(AesGcmService::class)->decrypt($value),
            set: function (?string $value) {
                if ($value === null || $value === '') {
                    return ['email' => null, 'email_sha256' => null];
                }

                $normalized = mb_strtolower(trim($value));

                return [
                    'email' => app(AesGcmService::class)->encrypt($normalized),
                    'email_sha256' => self::sha256Of($normalized),
                ];
            },
        );
    }

    /**
     * email 盲索引值（SHA-256，规范化 trim + 小写后计算）。
     */
    public static function sha256Of(string $email): string
    {
        return hash('sha256', mb_strtolower(trim($email)));
    }

    /**
     * 生成随机 HMAC 签名密钥（64 hex，熵 256bit）。
     */
    public static function generateHmacSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * 获取当前管理员的 HMAC 签名密钥（解密明文，仅内存使用）。
     */
    public function hmacSecret(): string
    {
        return (string) app(AesGcmService::class)->decrypt($this->hmac_secret_encrypted);
    }

    /**
     * 轮换 HMAC 签名密钥，返回新密钥（调用方应立即下发前端并提示旧密钥失效）。
     */
    public function rotateHmacSecret(): string
    {
        $secret = self::generateHmacSecret();
        $this->forceFill([
            'hmac_secret_encrypted' => app(AesGcmService::class)->encrypt($secret),
        ])->save();

        return $secret;
    }

    /**
     * 最近登录 IP：AES-256-GCM 加密存储（v1.2.5 起）。
     * 读取兼容历史明文：解密失败时原样返回，避免既有数据不可读。
     */
    protected function lastLoginIp(): Attribute
    {
        return Attribute::make(
            get: function (?string $value) {
                if ($value === null || $value === '') {
                    return null;
                }
                try {
                    $plain = app(AesGcmService::class)->decrypt($value);

                    return is_string($plain) && $plain !== '' ? $plain : $value;
                } catch (\Throwable) {
                    return $value; // 历史明文兼容
                }
            },
            set: fn (?string $value) => $value === null || $value === ''
                ? null
                : app(AesGcmService::class)->encrypt($value),
        );
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ApiToken::class);
    }

    public function isSuperAdmin(): bool
    {
        return Role::tryFrom((string) $this->role)?->isSuperAdmin() ?? false;
    }
}
