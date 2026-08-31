<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 授权转让表单（基于 licenses 表，改 customer）。
 */
class TransferSubmitRequest extends FormRequest
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
            'new_customer' => ['required', 'string', 'max:128'],
            'reason' => ['nullable', 'string', 'max:512'],
        ];
    }
}
