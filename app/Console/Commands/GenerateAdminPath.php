<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * 随机后台路径生成（交付项：随机后台访问路径）。
 *
 * 运行 php artisan zf:admin-path 生成新随机前缀（如 /zf-8k2x9q），
 * 输出写入 .env 所需的 ZF_ADMIN_PATH 配置行；使用者可自行修改。
 * 修改后需重启应用生效；后台所有路由统一走该前缀，未匹配返回 404。
 */
class GenerateAdminPath extends Command
{
    protected $signature = 'zf:admin-path
                            {--length=6 : 随机段长度（默认 6）}
                            {--write : 自动写入 .env（如 .env 存在且可写）}';

    protected $description = '生成随机后台访问路径前缀（ZF_ADMIN_PATH）';

    public function handle(): int
    {
        $length = max(4, min(12, (int) $this->option('length')));
        $random = Str::lower(Str::random($length, 'abcdefghjkmnpqrstuvwxyz23456789'));

        $path = 'zf-'.$random;
        $current = (string) config('license.admin.path', 'admin');

        $this->info('随机后台路径前缀已生成：');
        $this->line('');
        $this->line('  ZF_ADMIN_PATH='.$path);
        $this->line('  后台访问地址：'.rtrim((string) config('app.url', 'https://your-domain.com'), '/').'/'.$path);
        $this->line('');
        $this->warn('当前生效值：'.$current.'（修改后需重启应用）。');

        if ($this->option('write')) {
            $envFile = base_path('.env');
            if (! is_file($envFile) || ! is_writable($envFile)) {
                $this->error('.env 不存在或不可写，请手动在 .env 中添加 ZF_ADMIN_PATH='.$path);

                return self::FAILURE;
            }

            $content = file_get_contents($envFile);
            if (preg_match('/^ZF_ADMIN_PATH=.*$/m', (string) $content) === 1) {
                $content = preg_replace('/^ZF_ADMIN_PATH=.*$/m', 'ZF_ADMIN_PATH='.$path, (string) $content);
            } else {
                $content .= PHP_EOL.'ZF_ADMIN_PATH='.$path.PHP_EOL;
            }
            file_put_contents($envFile, $content, LOCK_EX);

            $this->info('已写入 .env（ZF_ADMIN_PATH='.$path.'）。');
        } else {
            $this->info('提示：将上方 ZF_ADMIN_PATH 配置行加入 .env（或使用 --write 自动写入）。');
        }

        return self::SUCCESS;
    }
}
