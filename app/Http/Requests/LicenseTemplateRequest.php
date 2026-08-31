<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LicenseTemplateRequest extends FormRequest
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
        // PATCH/PUT 支持部分更新：除 is_active 外不再强制必填
        $isUpdate = $this->isMethod('PATCH') || $this->isMethod('PUT');

        return [
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:128'],
            'description' => ['nullable', 'string', 'max:2000'],
            'duration_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'max_devices' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'min:1', 'max:1000'],
            'features' => ['nullable', 'array'],
            'scope_ids' => ['nullable', 'array'],
            'scope_ids.*' => ['integer', 'exists:license_scopes,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
