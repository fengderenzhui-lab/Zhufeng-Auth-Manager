<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * 客户端对接令牌签发服务。
 *
 * 采用 Ed25519（sodium）自签 payload，claims 字段集与
 * masterix21/laravel-licensing-client 的 PASETO v4 claims 完全对齐：
 * license_id / license_key_hash / status / max_usages / exp / iat / nbf / iss /
 * license_expires_at / force_online_after / grace_until / usage_fingerprint /
 * entitlements（可选 licensable_type / licensable_id）。
 *
 * 令牌格式：v4.public.{base64url(claims_json)}.{base64url(ed25519_signature)}
 * 客户端可用 Ed25519 公钥离线验签；如需与官方 paragonie/paseto 解析器严格互通，
 * 可安装 paragonie/paseto 后替换本实现（接口保持不变）。
 */
final class PasetoService
{
    public function __construct(private readonly Ed25519Service $ed25519)
    {
    }

    /**
     * 签发令牌。
     *
     * @param  array<string, mixed>  $claims  必须含 license 业务字段；iss/iat/nbf/exp 未给时自动补齐
     */
    public function issue(array $claims): string
    {
        $now = time();
        $ttl = ((int) config('license.licensing_v1.token_ttl_days', 7)) * 86400;
        $skew = (int) config('license.licensing_v1.clock_skew_seconds', 60);

        $claims['iat'] = (int) ($claims['iat'] ?? $now);
        $claims['nbf'] = (int) ($claims['nbf'] ?? $now - $skew);
        $claims['exp'] = (int) ($claims['exp'] ?? $now + $ttl);
        $claims['iss'] = (string) ($claims['iss'] ?? config('license.licensing_v1.issuer', 'laravel-licensing'));

        $json = json_encode($claims, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('令牌 claims 序列化失败。');
        }

        // Ed25519Service::signData() 返回 base64 编码签名，先解码为原始二进制再按
        // PASETO v4.public 规范做 base64url 编码（避免双重编码导致验签失败）
        $signatureRaw = base64_decode($this->ed25519->signData($json), true);
        if ($signatureRaw === false) {
            throw new RuntimeException('令牌签名失败。');
        }

        $payload = $this->base64UrlEncode($json).'.'.$this->base64UrlEncode($signatureRaw);

        return 'v4.public.'.$payload;
    }

    /**
     * 校验令牌（验签 + 结构 + 过期），返回 claims；失败抛异常。
     *
     * @return array<string, mixed>
     */
    public function verify(string $token, ?string $expectedFingerprint = null): array
    {
        if (! str_starts_with($token, 'v4.public.')) {
            throw new RuntimeException('令牌格式非法。');
        }

        $body = substr($token, strlen('v4.public.'));
        $parts = explode('.', $body);
        if (count($parts) !== 2) {
            throw new RuntimeException('令牌结构非法。');
        }

        [$payloadB64, $signatureB64] = $parts;

        $json = $this->base64UrlDecode($payloadB64);
        $signature = $this->base64UrlDecode($signatureB64);

        // Ed25519Service::verify() 期望 base64 编码的签名，此处由 base64url 原始二进制回编码
        if ($json === null || $signature === null || ! $this->ed25519->verify($json, base64_encode($signature))) {
            throw new RuntimeException('令牌签名校验失败。');
        }

        $claims = json_decode($json, true);
        if (! is_array($claims)) {
            throw new RuntimeException('令牌 claims 非法。');
        }

        $now = time();
        if (isset($claims['exp']) && (int) $claims['exp'] + (int) config('license.licensing_v1.clock_skew_seconds', 60) < $now) {
            throw new RuntimeException('令牌已过期。');
        }
        if (isset($claims['nbf']) && (int) $claims['nbf'] - (int) config('license.licensing_v1.clock_skew_seconds', 60) > $now) {
            throw new RuntimeException('令牌尚未生效。');
        }

        if ($expectedFingerprint !== null) {
            // 设备指纹必须存在且与调用方期望完全一致：缺失即拒绝，防止令牌跨设备复用
            if (! isset($claims['usage_fingerprint']) || ! is_string($claims['usage_fingerprint'])) {
                throw new RuntimeException('令牌缺少设备指纹绑定。');
            }
            if (! hash_equals((string) $claims['usage_fingerprint'], $expectedFingerprint)) {
                throw new RuntimeException('令牌设备指纹不匹配。');
            }
        }

        return $claims;
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): ?string
    {
        $raw = base64_decode(strtr($data, '-_', '+/'), true);
        return $raw === false ? null : $raw;
    }
}
