<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 授权模板：可复用的授权方案（时长/设备数/功能范围/启停）。
 */
class LicenseTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'duration_days',
        'max_devices',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_days' => 'integer',
            'max_devices' => 'integer',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopes(): BelongsToMany
    {
        return $this->belongsToMany(LicenseScope::class, 'license_template_scope', 'template_id', 'scope_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
