<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\CanonicalJson;
use RuntimeException;
use SodiumException;

/**
 * Ed25519 非对称签名服务（PHP sodium 扩展）。
 *
 * - 私钥仅保存在服务端 .env（LICENSING_ED25519_PRIVATE_KEY，Base64），绝不入库/入日志。
 * - 公钥可安全对外发布，客户端用于验证激活/心跳响应。
 * - 生成命令：php artisan license:keys
 */
final class Ed25519Service
{
    private ?string $privateKey = null;
    private ?string $publicKey = null;

    public function __construct()
    {
        if (! extension_loaded('sodium')) {
            throw new RuntimeException('PHP sodium 扩展未启用，无法进行 Ed25519 签名。');
        }
    }

    /**
     * 生成密钥对，返回 Base64 编码的私钥与公钥。
     */
    public function generateKeyPair(): array
    {
        $keyPair = sodium_crypto_sign_keypair();

        return [
            'private_key' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
            'public_key' => base64_encode(sodium_crypto_sign_publickey($keyPair)),
        ];
    }


    /**
     * 将键值对写入项目根 .env（存在则替换，缺失则追加），文件不存在时忽略。
     * 等保 L-03 修复：原子写（tmp + rename）+ 权限校验 + 失败抛错，避免
     * 异常时损坏配置或注入。
     *
     * @param  array<string, string>  $pairs
     */
    public function writeEnv(array $pairs): void
    {
        $envFile = base_path('.env');
        if (! is_file($envFile)) {
            return;
        }
        if (! is_writable($envFile)) {
            throw new RuntimeException('.env 文件不可写，请检查文件权限后重试。');
        }

        $content = (string) file_get_contents($envFile);
        foreach ($pairs as $key => $value) {
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $line = $key.'='.$value;
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, $line, $content);
            } else {
                $content .= PHP_EOL.$line.PHP_EOL;
            }
        }

        // 原子写：先写同目录临时文件，再 rename 覆盖（等保 L-03）
        $tmpFile = $envFile.'.tmp.'.bin2hex(random_bytes(6));
        $written = @file_put_contents($tmpFile, $content, LOCK_EX);
        if ($written === false) {
            throw new RuntimeException('写入 .env 临时文件失败。');
        }

        // 保持与原文件一致的文件权限（默认 0600）
        $perms = (int) (fileperms($envFile) & 0777);
        @chmod($tmpFile, $perms !== 0 ? $perms : 0600);

        if (! @rename($tmpFile, $envFile)) {
            @unlink($tmpFile);
            throw new RuntimeException('原子替换 .env 失败，原文件未被修改。');
        }
    }

    public function privateKey(): string
    {
        if ($this->privateKey === null) {
            $encoded = (string) config('license.ed25519.private_key', '');
            if ($encoded === '') {
                throw new RuntimeException('未配置 LICENSING_ED25519_PRIVATE_KEY，请运行 php artisan license:keys 生成。');
            }
            $this->privateKey = base64_decode($encoded);
            if ($this->privateKey === false || mb_strlen($this->privateKey, '8bit') !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
                throw new RuntimeException('LICENSING_ED25519_PRIVATE_KEY 格式非法。');
            }
        }

        return $this->privateKey;
    }

    public function publicKey(): string
    {
        if ($this->publicKey === null) {
            // 优先使用配置的公钥；缺失时由私钥派生
            $encoded = (string) config('license.ed25519.public_key', '');
            if ($encoded !== '') {
                $decoded = base64_decode($encoded);
                if ($decoded === false || mb_strlen($decoded, '8bit') !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                    throw new RuntimeException('LICENSING_ED25519_PUBLIC_KEY 格式非法。');
                }

                return $this->publicKey = $decoded;
            }

            $this->publicKey = sodium_crypto_sign_publickey_from_secretkey($this->privateKey());
        }

        return $this->publicKey;
    }

    /**
     * 将 data 数组规范化为可签名/可验签的确定性字符串。
     */
    public function prepareSignable(array $data): string
    {
        return CanonicalJson::encode($data);
    }

    /**
     * 对规范化数据签名，返回 Base64 签名。
     */
    public function signData(string $canonical): string
    {
        try {
            return base64_encode(sodium_crypto_sign_detached($canonical, $this->privateKey()));
        } catch (SodiumException $e) {
            throw new RuntimeException('Ed25519 签名失败: '.$e->getMessage());
        }
    }

    /**
     * 使用给定公钥验证签名（Base64 签名）。
     */
    public function verify(string $canonical, string $signatureB64, ?string $publicKeyB64 = null): bool
    {
        try {
            $pub = $publicKeyB64 !== null
                ? base64_decode($publicKeyB64)
                : $this->publicKey();

            if ($pub === false || mb_strlen($pub, '8bit') !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                return false;
            }

            $sig = base64_decode($signatureB64);

            return $sig !== false
                && sodium_crypto_sign_verify_detached($sig, $canonical, $pub);
        } catch (\Throwable) {
            return false;
        }
    }
}
