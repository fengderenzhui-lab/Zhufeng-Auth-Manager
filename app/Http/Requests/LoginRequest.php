<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:190'],
            // 仅做格式校验：存在性/密码强度由 AuthService 统一处理（错误语义防枚举）
            'password' => ['required', 'string', 'max:72'],
        ];
    }
}
