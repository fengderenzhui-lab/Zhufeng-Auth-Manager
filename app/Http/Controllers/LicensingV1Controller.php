<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\LicenseStatus;
use App\Models\Device;
use App\Models\Heartbeat;
use App\Models\License;
use App\Services\AuditService;
use App\Services\Ed25519Service;
use App\Services\LicenseKeyGenerator;
use App\Services\PasetoService;
use App\Services\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * 客户端对接端点（对齐 masterix21/laravel-licensing-client）。
 *
 * 前缀 /api/licensing/v1，统一信封 {success,data} / {success:false,error:{code,message}}。
 * 身份 = license_key + fingerprint（客户端指纹 = sha256(hostname|machine_id|...)，
 * 服务端只做比对存储：fingerprint_hash 存 AES-256-GCM 密文 + fingerprint_hash_sha256 索引列）。
 * 令牌 = Ed25519 自签 v4.public.{claims}.{sig}（PasetoService），claims 与客户端
 * TokenValidator 严格对齐；路由层叠加 HMAC strict 防重放（ReplayProtect 中间件）。
 *
 * 错误码对齐（客户端 mapRequestException）：
 *  404  invalidLicenseKey | 403 FINGERPRINT_MISMATCH | 403 其它 invalidLicenseStatus
 *  409 FINGERPRINT_CONFLICT / OFFLINE_TOKEN_DISABLED | 409 其它 usageLimitExceeded
 *  410 过期 | 423 CANCELLED_LICENSE / licenseSuspended | 429 限流
 */
class LicensingV1Controller extends Controller
{
    public function __construct(
        private readonly LicenseKeyGenerator $keys,
        private readonly PasetoService $paseto,
        private readonly Ed25519Service $ed25519,
        private readonly AuditService $audit,
        private readonly SettingsService $settings,
    ) {
    }

    /**
     * GET /api/licensing/v1/health
     * 等保 L-01 修复：不返回 service 标识与版本信息，避免服务指纹识别。
     */
    public function health(): JsonResponse
    {
        return ApiResponse::okV1([
            'status' => 'healthy',
        ]);
    }

    /**
     * POST /api/licensing/v1/activate
     * 校验 key + fingerprint，绑定设备（一机一码 + max_devices），签发令牌。
     */
    public function activate(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $license = $this->findLicense($payload['license_key']);
        if ($license === null) {
            return ApiResponse::failV1('INVALID_LICENSE_KEY', '授权码不存在或无效。', 404);
        }

        $stateError = $this->checkLicenseState($license, allowPending: true);
        if ($stateError !== null) {
            return $stateError;
        }

        $fingerprint = $payload['fingerprint'];

        try {
            $device = DB::transaction(function () use ($license, $fingerprint, $payload) {
                return $this->bindDevice($license, $fingerprint, $payload);
            });
        } catch (RuntimeException $e) {
            return $this->mapDeviceError($e);
        }

        if ($license->status === LicenseStatus::Pending) {
            $license->forceFill([
                'status' => LicenseStatus::Active,
                'activated_at' => now(),
            ])->save();
        }

        // 激活即视为在线起点：初始化心跳时间，避免激活后立即 validate 被判定心跳超时
        if ($license->last_heartbeat_at === null) {
            $license->forceFill(['last_heartbeat_at' => now()])->save();
        }

        $this->audit->clientAction(
            AuditAction::LICENSE_ACTIVATED,
            actorId: substr((string) $license->key_hash, 0, 8),
            resourceType: 'license',
            resourceId: (string) $license->id,
            context: ['device_id' => $device->id, 'channel' => 'licensing-v1'],
            request: $request,
        );

        return ApiResponse::okV1($this->activationPayload($license, $fingerprint, $device));
    }

    /**
     * POST /api/licensing/v1/deactivate
     * 解绑当前设备。
     */
    public function deactivate(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $license = $this->findLicense($payload['license_key']);
        if ($license === null) {
            return ApiResponse::failV1('INVALID_LICENSE_KEY', '授权码不存在或无效。', 404);
        }

        $fingerprint = $payload['fingerprint'];
        $device = $this->findDevice($license, $fingerprint);
        if ($device === null) {
            return ApiResponse::failV1('FINGERPRINT_MISMATCH', '设备指纹未绑定，无需解绑。', 403);
        }

        $device->delete();

        $this->audit->clientAction(
            AuditAction::LICENSE_DEACTIVATED,
            actorId: substr((string) $license->key_hash, 0, 8),
            resourceType: 'license',
            resourceId: (string) $license->id,
            context: ['device_id' => $device->id, 'channel' => 'licensing-v1'],
            request: $request,
        );

        return ApiResponse::okV1(['deactivated' => true, 'license_id' => $license->id]);
    }

    /**
     * POST /api/licensing/v1/refresh
     * 验指纹后重签令牌（响应同 activate）。
     */
    public function refresh(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $license = $this->findLicense($payload['license_key']);
        if ($license === null) {
            return ApiResponse::failV1('INVALID_LICENSE_KEY', '授权码不存在或无效。', 404);
        }

        $stateError = $this->checkLicenseState($license, allowPending: false);
        if ($stateError !== null) {
            return $stateError;
        }

        $fingerprint = $payload['fingerprint'];
        $device = $this->findDevice($license, $fingerprint);
        if ($device === null || ! $device->is_active) {
            return ApiResponse::failV1('FINGERPRINT_MISMATCH', '设备指纹未绑定，请先激活。', 403);
        }

        $device->forceFill(['last_seen_at' => now()])->save();

        return ApiResponse::okV1($this->activationPayload($license, $fingerprint, $device));
    }

    /**
     * POST /api/licensing/v1/heartbeat
     * 心跳上报（data: version/environment）；非激活/未绑定返回失败信封但不抛错。
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $license = $this->findLicense($payload['license_key']);
        if ($license === null) {
            return ApiResponse::failV1('INVALID_LICENSE_KEY', '授权码不存在或无效。', 404);
        }

        $fingerprint = $payload['fingerprint'];
        $device = $this->findDevice($license, $fingerprint);
        if ($device === null || ! $device->is_active) {
            return ApiResponse::failV1('FINGERPRINT_MISMATCH', '设备指纹未绑定，请先激活。', 403);
        }

        $stateError = $this->checkLicenseState($license, allowPending: false);
        if ($stateError !== null) {
            return $stateError;
        }

        $now = now();
        $device->forceFill(['last_seen_at' => $now, 'last_ip' => $request->ip()])->save();
        $license->forceFill(['last_heartbeat_at' => $now])->save();

        Heartbeat::query()->create([
            'license_id' => $license->id,
            'device_id' => $device->id,
            'client_ip' => $request->ip(),
            'client_ua' => mb_substr((string) $request->userAgent(), 0, 255),
            'status' => 'ok',
            'checked_at' => $now,
        ]);

        $this->audit->clientAction(
            AuditAction::LICENSE_HEARTBEAT,
            actorId: substr((string) $license->key_hash, 0, 8),
            resourceType: 'license',
            resourceId: (string) $license->id,
            context: ['device_id' => $device->id, 'channel' => 'licensing-v1'],
            request: $request,
        );

        return ApiResponse::okV1([
            'success' => true,
            'heartbeat' => true,
            'license_id' => $license->id,
            'status' => $license->status->value,
            'last_heartbeat_at' => $now->toIso8601String(),
        ]);
    }

    /**
     * POST /api/licensing/v1/validate
     * 在线校验：状态 + 指纹 + 心跳在线，返回新签令牌。
     */
    public function validate(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $license = $this->findLicense($payload['license_key']);
        if ($license === null) {
            return ApiResponse::failV1('INVALID_LICENSE_KEY', '授权码不存在或无效。', 404);
        }

        $stateError = $this->checkLicenseState($license, allowPending: false);
        if ($stateError !== null) {
            return $stateError;
        }

        $fingerprint = $payload['fingerprint'];
        $device = $this->findDevice($license, $fingerprint);
        if ($device === null || ! $device->is_active) {
            return ApiResponse::failV1('FINGERPRINT_MISMATCH', '设备指纹未绑定，请先激活。', 403);
        }

        if ($this->heartbeatExpired($license)) {
            return ApiResponse::failV1('LICENSE_SUSPENDED', '心跳超时，授权已被暂停，请重新激活。', 423);
        }

        $device->forceFill(['last_seen_at' => now()])->save();

        return ApiResponse::okV1($this->activationPayload($license, $fingerprint, $device));
    }

    /**
     * POST /api/licensing/v1/licenses/show
     * 返回授权详情（客户端只 throw 不解析结构）。
     */
    public function show(Request $request): JsonResponse
    {
        $payload = $this->validatePayload($request);
        $license = $this->findLicense($payload['license_key']);
        if ($license === null) {
            return ApiResponse::failV1('INVALID_LICENSE_KEY', '授权码不存在或无效。', 404);
        }

        $stateError = $this->checkLicenseState($license, allowPending: true);
        if ($stateError !== null) {
            return $stateError;
        }

        return ApiResponse::okV1([
            'id' => $license->id,
            'product' => $license->product?->slug,
            'status' => $license->status->value,
            'max_usages' => $license->max_devices,
            'activated_at' => $license->activated_at?->toIso8601String(),
            'expires_at' => $license->expires_at?->toIso8601String(),
            'entitlements' => $this->entitlements($license),
            'meta' => $license->meta,
        ]);
    }

    // ------------------------------------------------------------------
    // 内部工具
    // ------------------------------------------------------------------

    /**
     * @return array{license_key: string, fingerprint: string, metadata: array<string, mixed>}
     */
    private function validatePayload(Request $request): array
    {
        $data = $request->validate([
            'license_key' => ['required', 'string', 'max:255'],
            // 等保 M-06 中间方案：客户端自报 fingerprint 长度约束 32~128（与最终格式校验双重保障）
            'fingerprint' => ['required', 'string', 'min:32', 'max:128'],
            // ZF-2026-002：可选原始硬件信号（base64(JSON)）。上报时服务端用 DeviceFingerprintService
            // 计算指纹并覆盖客户端 fingerprint（服务端加盐 HMAC，客户端无法伪造/预测）。
            'signals' => ['sometimes', 'string', 'max:4096'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $fingerprint = (string) $data['fingerprint'];

        if (isset($data['signals']) && is_string($data['signals']) && $data['signals'] !== '') {
            $fpService = app(DeviceFingerprintService::class);
            $fingerprint = $fpService->compute($fpService->decodeSignals($data['signals']));
        }

        // 等保 M-06 中间方案：对最终使用的指纹做长度/熵约束（32~128 字符，仅允许 hex/base64 白名单字符集，
        // 且唯一字符数不少于 8），非法即拒绝，遏制"任意值自报"洗白设备身份；不改动服务端计算逻辑。
        if (! $this->assertValidFingerprint($fingerprint)) {
            throw ValidationException::withMessages([
                'fingerprint' => '设备指纹格式非法：需为 32~128 位 hex 或 base64 形式。',
            ]);
        }

        return [
            'license_key' => (string) $data['license_key'],
            'fingerprint' => $fingerprint,
            'metadata' => isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : [],
        ];
    }

    /**
     * 指纹格式/熵约束：长度 32~128，字符集仅允许 hex/base64（[A-Za-z0-9+/=]），
     * 且唯一字符数 >= 8（防止全 0/重复字符等低熵自报值）。
     */
    private function assertValidFingerprint(string $fingerprint): bool
    {
        $len = strlen($fingerprint);
        if ($len < 32 || $len > 128) {
            return false;
        }
        if (preg_match('/^[A-Za-z0-9+\/=]+$/', $fingerprint) !== 1) {
            return false;
        }
        if (count(array_unique(str_split($fingerprint))) < 8) {
            return false;
        }

        return true;
    }

    private function findLicense(string $key): ?License
    {
        $keyHash = $this->keys->hashKey($key);

        return License::query()->where('key_hash', $keyHash)->first();
    }

    /**
     * 状态校验：返回错误响应或 null（放行）。
     */
    private function checkLicenseState(License $license, bool $allowPending): ?JsonResponse
    {
        // V1.35 fix：实时过期判定——expires_at 已过但 status 未被任务置为 Expired 时同样拦截（410）
        if ($license->status !== LicenseStatus::Blacklisted && $license->status !== LicenseStatus::Revoked && $license->hasExpired()) {
            return ApiResponse::failV1('LICENSE_EXPIRED', '授权码已过期。', 410);
        }

        return match ($license->status) {
            LicenseStatus::Blacklisted => ApiResponse::failV1('CANCELLED_LICENSE', '授权码已被拉黑。', 423),
            LicenseStatus::Revoked => ApiResponse::failV1('CANCELLED_LICENSE', '授权码已被吊销。', 423),
            LicenseStatus::Expired => ApiResponse::failV1('LICENSE_EXPIRED', '授权码已过期。', 410),
            LicenseStatus::Pending => $allowPending ? null : ApiResponse::failV1('LICENSE_SUSPENDED', '授权码尚未激活。', 423),
            LicenseStatus::Active => null,
        };
    }

    private function findDevice(License $license, string $fingerprint): ?Device
    {
        return Device::query()
            ->where('license_id', $license->id)
            ->where('fingerprint_hash_sha256', Device::sha256Of($fingerprint))
            ->whereNull('deleted_at')
            ->first();
    }

    /**
     * 绑定设备（事务内）：幂等复用 / 指纹冲突检测 / 设备上限。
     */
    private function bindDevice(License $license, string $fingerprint, array $payload): Device
    {
        $existing = $this->findDevice($license, $fingerprint);
        if ($existing !== null) {
            $existing->forceFill([
                'is_active' => true,
                'last_seen_at' => now(),
                'last_ip' => request()->ip(),
            ])->save();

            return $existing;
        }

        // 指纹是否已被其他授权码绑定（一机一码互斥）
        $conflict = Device::query()
            ->where('fingerprint_hash_sha256', Device::sha256Of($fingerprint))
            ->whereNull('deleted_at')
            ->exists();

        if ($conflict) {
            throw new RuntimeException('FINGERPRINT_CONFLICT', 1);
        }

        // 对 license 行加排他锁（SELECT ... FOR UPDATE），串行化并发激活时的设备计数，
        // 防止同一授权码在高并发下被多个设备同时绑定导致超过 max_devices。
        DB::table('licenses')->whereKey($license->getKey())->lockForUpdate()->first();

        $boundCount = Device::query()->where('license_id', $license->id)->whereNull('deleted_at')->count();
        if ($boundCount >= $license->max_devices) {
            throw new RuntimeException('USAGE_LIMIT_EXCEEDED', 2);
        }

        $metadata = $payload['metadata'] ?? [];
        $deviceName = is_string($metadata['hostname'] ?? null) ? $metadata['hostname'] : null;

        return Device::query()->create([
            'license_id' => $license->id,
            'fingerprint_hash' => $fingerprint,
            'device_name' => $deviceName !== null ? mb_substr($deviceName, 0, 128) : null,
            'is_active' => true,
            'last_ip' => request()->ip(),
            'last_user_agent' => mb_substr((string) request()->userAgent(), 0, 255),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);
    }

    private function mapDeviceError(RuntimeException $e): JsonResponse
    {
        return match (true) {
            str_contains($e->getMessage(), 'FINGERPRINT_CONFLICT') => ApiResponse::failV1('FINGERPRINT_CONFLICT', '该设备指纹已被其他授权码绑定。', 409),
            str_contains($e->getMessage(), 'USAGE_LIMIT_EXCEEDED') => ApiResponse::failV1('USAGE_LIMIT_EXCEEDED', '授权码已达设备绑定上限。', 409),
            default => ApiResponse::failV1('ACTIVATION_FAILED', '激活失败，请稍后重试。', 400),
        };
    }

    private function heartbeatExpired(License $license): bool
    {
        // V1.32：阈值支持设置页 DB 覆盖（license.heartbeat.enforce_online / timeout_seconds），未配置回落 config。
        if (! (bool) $this->settings->get('license.heartbeat.enforce_online', true)) {
            return false;
        }

        if ($license->last_heartbeat_at === null) {
            return true;
        }

        $timeout = (int) $this->settings->get('license.heartbeat.timeout_seconds', 300);

        return $license->last_heartbeat_at->diffInSeconds(now()) > $timeout;
    }

    /**
     * 组装激活/刷新/校验响应。
     *
     * @return array<string, mixed>
     */
    private function activationPayload(License $license, string $fingerprint, Device $device): array
    {
        $now = time();
        $ttlDays = (int) config('license.licensing_v1.token_ttl_days', 7);
        $forceOnlineDays = (int) config('license.licensing_v1.force_online_after_days', 14);

        $claims = [
            'license_id' => $license->id,
            'license_key_hash' => (string) $license->key_hash,
            'status' => LicenseStatus::Active->value,
            'max_usages' => $license->max_devices,
            'license_expires_at' => $license->expires_at?->toIso8601String(),
            'force_online_after' => now()->addDays($forceOnlineDays)->toIso8601String(),
            'grace_until' => null,
            'usage_fingerprint' => $fingerprint,
            'entitlements' => $this->entitlements($license),
            'licensable_type' => 'license',
            'licensable_id' => $license->id,
        ];

        $publicKey = $this->ed25519->publicKey();
        $publicKeyB64 = base64_encode($publicKey);

        return [
            'token' => $this->paseto->issue($claims),
            'license' => [
                'id' => $license->id,
                'status' => $license->status->value,
                'entitlements' => $this->entitlements($license),
                'expires_at' => $license->expires_at?->toIso8601String(),
            ],
            'public_key_bundle' => [
                'signing' => ['public_key' => $publicKeyB64],
                'root' => ['public_key' => $publicKeyB64],
            ],
            'refresh_after' => now()->addSeconds((int) floor($ttlDays * 86400 / 2))->toIso8601String(),
            'device_id' => $device->id,
            'max_usages' => $license->max_devices,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function entitlements(License $license): array
    {
        $meta = is_array($license->meta) ? $license->meta : [];

        if (isset($meta['features']) && is_array($meta['features'])) {
            return array_values(array_map('strval', $meta['features']));
        }

        return [];
    }
}
