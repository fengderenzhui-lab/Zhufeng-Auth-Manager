<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LicenseScopeRequest extends FormRequest
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
        $scopeId = (int) $this->route('id');

        return [
            'name' => ['required', 'string', 'max:128'],
            'slug' => ['required', 'string', 'max:128', 'regex:/^[a-z0-9][a-z0-9._-]*$/i', "unique:license_scopes,slug,{$scopeId}"],
            'description' => ['nullable', 'string', 'max:512'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
