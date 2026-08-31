<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * AES-256-GCM 敏感字段加密服务。
 *
 * - 密钥统一从环境变量 ZF_APP_ENCRYPT_KEY 读取（32 字节 Base64），禁止硬编码、禁止入库。
 * - 密文格式：base64(iv(12) || tag(16) || ciphertext)，自带随机 IV，语义安全。
 * - 查询场景不直接解密比对：由业务层把明文指纹的 sha256 写入独立索引列。
 */
final class AesGcmService
{
    private const CIPHER = 'aes-256-gcm';
    private const IV_LEN = 12;
    private const TAG_LEN = 16;

    public function __construct()
    {
        if (! in_array(self::CIPHER, openssl_get_cipher_methods(), true)) {
            throw new RuntimeException('当前 PHP 环境缺少 aes-256-gcm 支持。');
        }
    }

    /**
     * 解析 ZF_APP_ENCRYPT_KEY：必须为 32 字节明文经 Base64 编码。
     */
    public function key(): string
    {
        $encoded = (string) config('license.aes.encrypt_key', '');
        if ($encoded === '') {
            throw new RuntimeException('ZF_APP_ENCRYPT_KEY 未配置，请写入 .env（php artisan license:keys --write 可自动生成）。');
        }

        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException('ZF_APP_ENCRYPT_KEY 必须为 32 字节密钥的 Base64 编码。');
        }

        return $raw;
    }

    /**
     * ZF-2026-006：解析旧密钥 ZF_APP_ENCRYPT_KEY_PREV（密钥轮换期间的旧密钥，可缺省）。
     */
    private function prevKey(): ?string
    {
        $encoded = (string) config('license.aes.encrypt_key_prev', '');
        if ($encoded === '') {
            return null;
        }

        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) !== 32) {
            throw new RuntimeException('ZF_APP_ENCRYPT_KEY_PREV 必须为 32 字节密钥的 Base64 编码。');
        }

        return $raw;
    }

    /**
     * 加密字符串；null/空字符串原样返回（保持语义一致）。
     */
    public function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return $plain;
        }

        $iv = random_bytes(self::IV_LEN);
        $tag = '';

        $ciphertext = openssl_encrypt($plain, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_LEN);

        if ($ciphertext === false) {
            throw new RuntimeException('AES-256-GCM 加密失败。');
        }

        return base64_encode($iv.$tag.$ciphertext);
    }

    /**
     * 解密字符串；null/空字符串原样返回。
     *
     * ZF-2026-006：密钥轮换后，旧密钥（ZF_APP_ENCRYPT_KEY_PREV）加密的历史数据仍可解密：
     * 当前密钥失败时自动尝试旧密钥，解密成功则通过 $reencrypted 回传新密文（供业务层回写迁移）。
     */
    public function decrypt(?string $payload, ?string &$reencrypted = null): ?string
    {
        $reencrypted = null;

        if ($payload === null || $payload === '') {
            return $payload;
        }

        $raw = base64_decode($payload, true);
        if ($raw === false || strlen($raw) < self::IV_LEN + self::TAG_LEN) {
            throw new RuntimeException('AES-256-GCM 密文格式非法。');
        }

        $iv = substr($raw, 0, self::IV_LEN);
        $tag = substr($raw, self::IV_LEN, self::TAG_LEN);
        $ciphertext = substr($raw, self::IV_LEN + self::TAG_LEN);

        $plain = openssl_decrypt($ciphertext, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($plain === false) {
            // 旧密钥回退：仅当轮换期旧密钥已配置时尝试
            $prev = $this->prevKey();
            if ($prev !== null) {
                $plain = openssl_decrypt($ciphertext, self::CIPHER, $prev, OPENSSL_RAW_DATA, $iv, $tag);
                if ($plain !== false) {
                    // 旧密钥解密成功：返回明文，并回传"用当前密钥重加密后的密文"供调用方回写
                    $reencrypted = $this->encrypt($plain);

                    return $plain;
                }
            }

            throw new RuntimeException('AES-256-GCM 解密失败（当前密钥与旧密钥均无法解密，请检查 ZF_APP_ENCRYPT_KEY / ZF_APP_ENCRYPT_KEY_PREV）。');
        }

        return $plain;
    }

}
