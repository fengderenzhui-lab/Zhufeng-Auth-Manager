<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 授权续期表单（基于 licenses 表，改 expires_at）。
 */
class RenewSubmitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'license_id' => ['required', 'integer', 'exists:licenses,id'],
            'new_expires_at' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:512'],
        ];
    }
}
