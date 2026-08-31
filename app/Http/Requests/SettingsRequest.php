<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
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
            'key' => ['required', 'string', 'max:190', 'regex:/^[a-z][a-z0-9_.]*$/'],
            'value' => ['required', 'string', 'max:1000'],
            'type' => ['required', 'in:string,int,bool,json'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
