<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

class UserUpdateRequest extends FormRequest
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
        $userId = (int) $this->route('user');

        return [
            'name' => ['sometimes', 'string', 'max:190'],
            // V1.30：email 已 AES 加密存储，唯一约束迁移到 email_sha256 盲索引（排除自身）
            'email' => ['sometimes', 'email', 'max:190', function ($attribute, $value, $fail) use ($userId) {
                if (User::query()
                    ->where('email_sha256', User::sha256Of((string) $value))
                    ->where('id', '!=', $userId)
                    ->exists()) {
                    $fail('该邮箱已被使用。');
                }
            }],
            // 等保 H-02：统一强密码策略（≥12 位 + 至少 3 类字符）
            'password' => ['sometimes', 'string', new StrongPassword(), 'confirmed'],
            'role' => ['sometimes', 'in:super_admin,admin'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
