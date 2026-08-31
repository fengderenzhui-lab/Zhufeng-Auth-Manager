<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ed25519Service;
use Illuminate\Console\Command;

class GenerateLicenseKeys extends Command
{
    protected $signature = 'license:keys
                            {--show : 输出私钥到终端（默认仅提示）}
                            {--write : 将缺失的密钥写入 .env（Ed25519 密钥对 / HMAC 索引密钥 / 指纹盐，已有项跳过）}';

    protected $description = '生成 Ed25519 签名密钥对与授权码 HMAC 密钥，输出或写入 .env';

    public function handle(Ed25519Service $ed25519): int
    {
        $pair = $ed25519->generateKeyPair();
        $hmacSecret = bin2hex(random_bytes(32));
        $fingerprintSalt = bin2hex(random_bytes(32));

        $show = (bool) $this->option('show');
        $write = (bool) $this->option('write');

        $this->info('Ed25519 密钥对已生成：');
        $this->line('');
        $this->line('LICENSING_ED25519_PRIVATE_KEY='.($show ? $pair['private_key'] : '<已隐藏，加 --show 查看>'));
        $this->line('LICENSING_ED25519_PUBLIC_KEY='.$pair['public_key']);
        $this->line('');
        $this->line('授权码 HMAC 索引密钥（可选，不配置时回退 APP_KEY）：');
        $this->line('LICENSING_KEY_HMAC_SECRET='.$hmacSecret);
        $this->line('');
        $this->line('指纹加盐（必须配置）：');
        $this->line('LICENSING_FINGERPRINT_SALT='.$fingerprintSalt);
        $this->line('');

        if ($write) {
            $written = $this->writeToEnv([
                'LICENSING_ED25519_PRIVATE_KEY' => $pair['private_key'],
                'LICENSING_ED25519_PUBLIC_KEY' => $pair['public_key'],
                'LICENSING_KEY_HMAC_SECRET' => $hmacSecret,
                'LICENSING_FINGERPRINT_SALT' => $fingerprintSalt,
            ]);

            $this->info('已写入 .env：'.implode('、', $written));
            if ($written === []) {
                $this->warn('所有密钥均已存在，本次未覆盖任何配置。');
            }
        }

        if ($show && ! $write) {
            $this->warn('⚠️ 私钥已输出到终端，请立即复制到 .env 并清理终端记录。');
        }

        $this->info('提示：LICENSING_ED25519_PRIVATE_KEY 与 LICENSING_KEY_HMAC_SECRET、LICENSING_FINGERPRINT_SALT 必须写入 .env，且私钥文件权限建议 0600。');

        return self::SUCCESS;
    }

    /**
     * 仅补写缺失的配置项，绝不覆盖已有值（幂等）。
     *
     * @param  array<string, string>  $values
     * @return list<string> 实际写入的键名
     */
    private function writeToEnv(array $values): array
    {
        $envPath = base_path('.env');
        if (! is_file($envPath)) {
            $this->error('.env 不存在，无法写入。');

            return [];
        }

        $content = (string) file_get_contents($envPath);
        $written = [];

        foreach ($values as $key => $value) {
            if (preg_match('/^'.$key.'=.*$/m', $content) === 1) {
                continue; // 已存在则跳过，保护用户已有密钥
            }
            $content .= PHP_EOL.$key.'='.$value.PHP_EOL;
            $written[] = $key;
        }

        if ($written !== []) {
            file_put_contents($envPath, $content, LOCK_EX);
        }

        return $written;
    }
}
