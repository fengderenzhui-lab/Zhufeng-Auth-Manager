<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 客户端 Ed25519 公钥库（管理端可视化录入）。
 */
class PublicKey extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'public_key',
        'fingerprint',
        'is_active',
        'created_by',
    ];

    protected $hidden = [
        'public_key',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * 公钥指纹：sha256(base64 公钥原文)，用于防重复导入与展示。
     */
    public static function fingerprintOf(string $publicKeyBase64): string
    {
        return hash('sha256', $publicKeyBase64);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
