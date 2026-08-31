<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateLicenseRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'count' => ['required', 'integer', 'min:1', 'max:'.config('license.generate.max_batch', 1000)],
            'expires_at' => ['nullable', 'date'],
            'max_devices' => ['required', 'integer', 'min:1', 'max:100'],
            'customer' => ['nullable', 'string', 'max:190'],
            'meta' => ['nullable', 'array'],
            'meta.features' => ['nullable', 'array'],
        ];
    }
}
