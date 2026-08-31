<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * 设备指纹服务 —— 服务端计算，禁止客户端自定义 fingerprint。
 *
 * 约定：
 *  - 客户端仅采集原始硬件信号（CPU 标识 / 网卡 MAC / 系统 UUID / 主板序列号 / 磁盘序列号），
 *    base64 编码后随激活/心跳请求上报到 signals 字段。
 *  - 服务端对原始信号做白名单过滤 + 规范化 + 排序后，以服务端 salt（LICENSING_FINGERPRINT_SALT）
 *    做 HMAC-SHA256 生成指纹。客户端无法预测/伪造指纹值。
 *  - 请求层的 X-Signature 签名（ReplayGuard）进一步保证 signals 在传输途中不可被篡改。
 */
final class DeviceFingerprintService
{
    public const SIGNALS_FIELD = 'signals';

    public function __construct(private readonly LicenseKeyGenerator $keyGenerator)
    {
    }

    /**
     * @param  array<string, mixed>  $signals  客户端上报的原始硬件信号（已 base64 解码）
     */
    public function compute(array $signals): string
    {
        $salt = (string) config('license.fingerprint.salt', '');
        if ($salt === '' ) {
            throw new RuntimeException('LICENSING_FINGERPRINT_SALT 未配置，无法计算设备指纹。');
        }

        $allowed = (array) config('license.fingerprint.signal_fields', []);
        $minSignals = (int) config('license.fingerprint.min_signals', 2);
        $algo = (string) config('license.fingerprint.algo', 'sha256');

        $clean = [];
        foreach ($allowed as $field) {
            $value = $signals[$field] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                // 字段长度上限，防御异常超长输入
                $clean[$field] = mb_substr(trim((string) $value), 0, 255);
            }
        }

        ksort($clean, SORT_STRING);

        if (count($clean) < $minSignals) {
            throw new RuntimeException('设备信号不足，无法生成可靠指纹。');
        }

        return hash_hmac($algo, json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $salt);
    }

    /**
     * 解析并校验客户端上报的 base64 信号载荷。
     *
     * @return array<string, mixed>
     */
    public function decodeSignals(?string $encoded): array
    {
        if ($encoded === null || $encoded === '') {
            throw new RuntimeException('缺少设备信号（signals）。');
        }

        $decoded = base64_decode($encoded, true);
        if ($decoded === false) {
            throw new RuntimeException('设备信号格式非法。');
        }

        $signals = json_decode($decoded, true);
        if (! is_array($signals)) {
            throw new RuntimeException('设备信号无法解析。');
        }

        return $signals;
    }
}
