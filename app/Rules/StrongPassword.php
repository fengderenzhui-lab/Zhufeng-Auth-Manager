<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * 统一强密码策略（等保 H-02 修复）：
 *  - 长度 ≥ LICENSE_AUTH_PASSWORD_MIN_LENGTH（默认 12，与 LICENSE_PASSWORD_MIN_LENGTH 对齐）
 *  - 复杂度 ≥ LICENSE_AUTH_PASSWORD_MIN_CLASSES（默认 3 类：大写/小写/数字/符号）
 *  - 上限 72 字节（bcrypt/Argon2 输入上限）
 *
 * 登录 / 建号 / 改密 / 修改密码四类入口统一使用本规则，消除策略不一致。
 */
class StrongPassword implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $password = (string) $value;

        $min = (int) config('license.auth.password_min_length', 12);
        $max = (int) config('license.auth.password_max_length', 72);
        $minClasses = (int) config('license.auth.password_min_classes', 3);

        if (mb_strlen($password) < $min) {
            $fail("密码长度不得少于 {$min} 位。");

            return;
        }

        if (mb_strlen($password) > $max) {
            $fail("密码长度不得超过 {$max} 位。");

            return;
        }

        $classes = 0;
        if (preg_match('/[A-Z]/', $password) === 1) {
            $classes++;
        }
        if (preg_match('/[a-z]/', $password) === 1) {
            $classes++;
        }
        if (preg_match('/[0-9]/', $password) === 1) {
            $classes++;
        }
        if (preg_match('/[^A-Za-z0-9]/', $password) === 1) {
            $classes++;
        }

        if ($classes < $minClasses) {
            $fail("密码须包含大写字母、小写字母、数字、符号中的至少 {$minClasses} 类。");
        }
    }
}
