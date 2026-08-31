<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\LicenseStatus;
use App\Models\Device;
use App\Models\Heartbeat;
use App\Models\License;
use Illuminate\Http\Request;

/**
 * 心跳服务 —— 强制在线模式（无离线宽限期）。
 *
 * 语义：
 *  - 客户端按 heartbeat.interval_seconds 周期性上报；本次上报本身证明在线，服务端刷新 last_heartbeat_at。
 *  - 离线超时判定由 verify 端点实时判定 + license:expire 命令兜底批量失效：
 *    last_heartbeat_at 距今超过 heartbeat.timeout_seconds 即视为失效。
 */
final class HeartbeatService
{
    public function __construct(
        private readonly DeviceFingerprintService $fingerprint,
        private readonly AuditService $audit,
        // V1.32：心跳载荷接入 settings 表（DB 优先、config 兜底，未配置时行为不变）
        private readonly SettingsService $settings,
    ) {
    }

    /**
     * 处理一次心跳上报。
     *
     * @param  array<string, mixed>  $signals
     */
    public function beat(License $license, array $signals, Request $request): array
    {
        $now = now();

        // 状态与有效期校验
        if ($license->status !== LicenseStatus::Active) {
            return $this->response(false, 'license_not_active', $license->status->value, $license, null);
        }

        if ($license->hasExpired()) {
            return $this->response(false, 'license_expired', LicenseStatus::Expired->value, $license, null);
        }

        $fingerprintHash = $this->fingerprint->compute($signals);

        $device = Device::query()
            ->where('license_id', $license->id)
            ->where('fingerprint_hash_sha256', Device::sha256Of($fingerprintHash))
            ->whereNull('deleted_at')
            ->first();

        if ($device === null || ! $device->is_active) {
            return $this->response(false, 'device_not_bound', $license->status->value, $license, null);
        }

        // 原子刷新时间戳（同一时刻大概率单客户端，行锁保护）
        $device->forceFill([
            'last_seen_at' => $now,
            'last_ip' => $request->ip(),
        ])->save();

        $license->forceFill(['last_heartbeat_at' => $now])->save();

        Heartbeat::query()->create([
            'license_id' => $license->id,
            'device_id' => $device->id,
            'client_ip' => $request->ip(),
            'client_ua' => mb_substr((string) $request->userAgent(), 0, 255),
            'status' => 'ok',
            'checked_at' => $now,
        ]);

        $this->audit->clientAction(AuditAction::LICENSE_HEARTBEAT, actorId: substr($license->key_hash, 0, 8), resourceType: 'license', resourceId: (string) $license->id, context: ['device_id' => $device->id], request: $request);

        return $this->response(true, 'ok', LicenseStatus::Active->value, $license, $device);
    }

    /**
     * 组装心跳响应载荷（供控制器签名下发）。
     *
     * @return array<string, mixed>
     */
    public function buildPayload(License $license): array
    {
        $boundCount = Device::query()->where('license_id', $license->id)->whereNull('deleted_at')->count();

        return [
            'license_id' => $license->id,
            'status' => $license->status->value,
            'product' => $license->product?->slug,
            'expires_at' => $license->expires_at?->toIso8601String(),
            'max_devices' => $license->max_devices,
            'bound_devices' => $boundCount,
            'enforce_online' => (bool) $this->settings->get('license.heartbeat.enforce_online', true),
            'heartbeat_interval' => (int) $this->settings->get('license.heartbeat.interval_seconds', 60),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function response(bool $ok, string $reason, string $status, License $license, ?Device $device): array
    {
        $result = [
            'valid' => $ok,
            'reason' => $reason,
            'status' => $status,
            'license_id' => $license->id,
        ];

        if ($device !== null) {
            $result['device_id'] = $device->id;
        }

        return $result;
    }
}
