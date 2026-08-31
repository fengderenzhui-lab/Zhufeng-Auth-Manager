<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrialRequest extends FormRequest
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
            'customer' => ['nullable', 'string', 'max:128'],
            'trial_days' => ['required', 'integer', 'min:1', 'max:365'],
            'starts_at' => ['nullable', 'date'],
            'status' => ['sometimes', 'in:pending,active,expired,revoked'],
            'remark' => ['nullable', 'string', 'max:512'],
        ];
    }
}
