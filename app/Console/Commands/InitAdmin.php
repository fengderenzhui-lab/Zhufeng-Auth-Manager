<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * 随机管理员初始化（交付项：随机管理员账号）。
 *
 * 首次部署运行 php artisan zf:init-admin：
 *  - 自动生成随机用户名（adm_8k2x 风格）与高强度随机密码（≥16 位，大小写+数字+符号）
 *  - 凭据输出到终端，并落盘 storage/app/init-admin-credentials.txt（部署人员取走后可删除）
 *  - 已有超级管理员时默认跳过（--force 强制重建）
 *
 * 禁止写死 admin/admin123456；已有固定账号数据不受影响。
 */
class InitAdmin extends Command
{
    protected $signature = 'zf:init-admin
                            {--force : 即使已存在超级管理员也强制执行}
                            {--reset-password= : 重设指定用户名/邮箱的密码为随机强密码}';

    protected $description = '生成随机管理员账号与高强度密码（首次部署初始化）';

    public function handle(): int
    {
        $this->info('逐风授权码管理平台 - 随机管理员初始化');
        $this->line('');

        // 密码字符集：大小写字母 + 数字 + 符号，保证四类均出现
        $passwordLength = (int) config('license.init_admin.password_length', 18);

        // 已存在超级管理员且未强制时直接跳过（保护既有账号）
        $existingSuperAdmin = User::query()->where('role', 'super_admin')->whereNull('deleted_at')->exists();
        if ($existingSuperAdmin && ! $this->option('force')) {
            $this->warn('系统中已存在超级管理员，未做任何变更。');
            $this->line('如需强制生成新账号，请使用 --force；如需重设某账号密码，请使用 --reset-password=<用户名或邮箱>。');

            return self::SUCCESS;
        }

        // 指定账号重设密码
        $resetTarget = (string) $this->option('reset-password');
        if ($resetTarget !== '') {
            return $this->resetPassword($resetTarget, $passwordLength);
        }

        // 生成唯一随机用户名
        $username = $this->uniqueUsername();

        $password = $this->strongPassword($passwordLength);

        $user = User::query()->create([
            'name' => $username,
            'email' => $this->uniqueEmail($username),
            'password' => $password,
            'role' => 'super_admin',
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $credentialFile = storage_path((string) config('license.init_admin.credential_file', 'app/init-admin-credentials.txt'));

        $this->outputCredentials($user, $username, $password, $credentialFile, created: true);

        return self::SUCCESS;
    }

    private function resetPassword(string $target, int $length): int
    {
        $user = User::query()
            ->where(function ($q) use ($target) {
                // V1.30：email 已加密，按 email_sha256 盲索引精确匹配
                $q->where('name', $target)->orWhere('email_sha256', User::sha256Of($target));
            })
            ->whereNull('deleted_at')
            ->first();

        if ($user === null) {
            $this->error('未找到账号：'.$target);

            return self::FAILURE;
        }

        $password = $this->strongPassword($length);
        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
        ])->save();

        $credentialFile = storage_path((string) config('license.init_admin.credential_file', 'app/init-admin-credentials.txt'));

        $this->outputCredentials($user, (string) $user->name, $password, $credentialFile, created: false);

        return self::SUCCESS;
    }

    private function uniqueUsername(): string
    {
        $prefix = (string) config('license.init_admin.username_prefix', 'adm_');
        $suffixLength = (int) config('license.init_admin.suffix_length', 4);

        do {
            $suffix = Str::lower(Str::random($suffixLength, 'abcdefghjkmnpqrstuvwxyz23456789'));
            $candidate = $prefix.$suffix;
        } while (User::query()->where('name', $candidate)->exists());

        return $candidate;
    }

    private function uniqueEmail(string $username): string
    {
        $base = (string) config('license.seed.admin_email', 'admin@example.com');
        $base = str_contains($base, '@') ? $base : 'admin@example.com';
        $domain = substr($base, strpos($base, '@') + 1);

        do {
            $candidate = $username.'+'.Str::lower(Str::random(4, 'abcdefghjkmnpqrstuvwxyz23456789')).'@'.$domain;
        } while (User::query()->where('email_sha256', User::sha256Of($candidate))->exists());

        return $candidate;
    }

    private function strongPassword(int $length): string
    {
        $lower = 'abcdefghjkmnpqrstuvwxyz';
        $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits = '23456789';
        $symbols = '!@#$%^&*()-_=+[]{};:,.<>?';

        $length = max(16, $length);

        $password = $lower[random_int(0, strlen($lower) - 1)]
            .$upper[random_int(0, strlen($upper) - 1)]
            .$digits[random_int(0, strlen($digits) - 1)]
            .$symbols[random_int(0, strlen($symbols) - 1)];

        $all = $lower.$upper.$digits.$symbols;
        while (strlen($password) < $length) {
            $password .= $all[random_int(0, strlen($all) - 1)];
        }

        return str_shuffle($password);
    }

    private function outputCredentials(User $user, string $username, string $password, string $credentialFile, bool $created): void
    {
        $this->info($created ? '随机管理员已创建：' : '账号密码已重置：');
        $this->line('');
        $this->line('  用户名：'.$username);
        $this->line('  邮箱  ：'.$user->email);
        $this->line('  密码  ：'.$password);
        $this->line('');

        $content = implode(PHP_EOL, [
            '# 逐风授权码管理平台 - 初始化管理员凭据（请立即保存并妥善保管，取用后删除本文件）',
            '# 生成时间: '.now()->toIso8601String(),
            '',
            'username='.$username,
            'email='.$user->email,
            'password='.$password,
            '',
        ]);

        try {
            @mkdir(dirname($credentialFile), 0700, true);
            file_put_contents($credentialFile, $content, LOCK_EX);
            @chmod($credentialFile, 0600);
            $this->info('凭据已写入：'.$credentialFile);
            $this->warn('请立即复制并妥善保管，之后删除该文件（权限已设为 0600）。');
        } catch (\Throwable $e) {
            $this->warn('凭据文件写入失败（'.$e->getMessage().'），请从上方终端输出保存。');
        }

        $this->line('');
        $this->warn('首次登录后建议在「个人中心」修改密码。');
    }
}
