<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * 初始化超级管理员账号。
     * 密码通过 .env 的 LICENSING_INITIAL_ADMIN_* 配置注入，避免硬编码。
     */
    public function run(): void
    {
        $email = config('license.seed.admin_email', 'admin@example.com');
        $password = config('license.seed.admin_password', '');

        if (strlen($password) < 12) {
            $this->command?->warn('LICENSING_INITIAL_ADMIN_PASSWORD 未配置或过短，已跳过创建超管账号。');

            return;
        }

        User::firstOrCreate(
            ['email' => strtolower($email)],
            [
                'name' => 'Administrator',
                'password' => Hash::make($password),
                'role' => 'super_admin',
                'is_active' => true,
                'must_change_password' => true,
            ]
        );
    }
}
