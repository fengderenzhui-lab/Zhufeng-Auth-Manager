<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Str;

/**
 * 授权码生成器。
 *
 * 格式：{PREFIX}-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX-C
 *  - body：25 位随机字符（易混淆字符 0O1lI 已剔除）
 *  - C   ：基于服务端密钥 HMAC 派生的校验字符（防随意构造，弱校验友好）
 *
 * 存储安全：数据库仅存 key_hash = HMAC-SHA256(服务端密钥, 完整 key)，
 * 不落明文；明文 key 仅在生成接口/激活回执中一次性返回。
 */
final class LicenseKeyGenerator
{
    public const DEFAULT_PREFIX = 'ZF-';

    /**
     * 生成一个完整授权码（含前缀与校验字符）。
     */
    public function generate(string $prefix = self::DEFAULT_PREFIX): string
    {
        $alphabet = (string) config('license.key.alphabet', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
        $segments = max(1, (int) config('license.key.segments', 5));
        $segmentLength = max(1, (int) config('license.key.segment_length', 5));

        $body = Str::random($segments * $segmentLength, $alphabet);
        $body = trim(chunk_split($body, $segmentLength, '-'), '-');

        $key = $prefix.$body;

        // 追加 HMAC 校验字符（服务端密钥参与，用于本地弱校验 + 低效爆破防护）
        $check = hash_hmac('sha256', $key, $this->hmacSecret());

        return $key.'-'.$alphabet[ord($check[0]) % mb_strlen($alphabet)];
    }

    /**
     * 计算授权码的存储哈希（HMAC-SHA256，服务端密钥派生）。
     */
    public function hashKey(string $key): string
    {
        // 归一化：去空白、统一大写
        $normalized = mb_strtoupper(preg_replace('/\s+/', '', $key) ?? '');

        return hash_hmac('sha256', $normalized, $this->hmacSecret());
    }

    /**
     * 归一化用户输入的授权码（忽略大小写与空白/连字符差异）。
     */
    public function normalize(string $key): string
    {
        return mb_strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $key) ?? '');
    }

    public function normalizeWithSeparator(string $key): string
    {
        return mb_strtoupper(preg_replace('/\s+/', '', $key) ?? '');
    }


    private function hmacSecret(): string
    {
        // ZF-2026-014：不再静默回退 APP_KEY（生产已由 AppServiceProvider fail-closed 拒绝启动）。
        // 此处全环境强制：缺失即抛错，避免历史授权码 key_hash 派生密钥被 APP_KEY 轮换破坏。
        $configured = (string) config('license.key.hmac_secret', '');

        if ($configured === '') {
            throw new \RuntimeException('LICENSING_KEY_HMAC_SECRET 未配置，授权码 HMAC 校验无法执行。请运行 php artisan license:keys --check。');
        }

        return $configured;
    }
}
