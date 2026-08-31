<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'updated_by' => 'integer',
        ];
    }

    public function castValue(): mixed
    {
        return match ($this->type) {
            'int' => (int) $this->value,
            'bool' => in_array($this->value, ['1', 'true', 'on', 'yes', 'true'], true),
            'json' => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }
}
