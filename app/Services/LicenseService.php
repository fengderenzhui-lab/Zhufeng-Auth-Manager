<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\LicenseStatus;
use App\Models\Device;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * 授权码核心业务：生成 / 激活 / 验证 / 吊销 / 续期 / 拉黑。
 *
 * 安全要点：
 *  - 公开端点按被折叠的 key_hash 检索，不存在时返回统一语义（防枚举）
 *  - 激活与绑定数务必在 DB 事务 + 行锁内执行，杜绝并发竞态
 *  - 设备指纹一律服务端计算，不信任客户端值
 *  - 设备被其他授权码绑定时拒绝（一机一码互斥）
 */
final class LicenseService
{
    public function __construct(
        private readonly LicenseKeyGenerator $keyGenerator,
        private readonly DeviceFingerprintService $fingerprint,
        private readonly AuditService $audit,
        private readonly SettingsService $settings,
    ) {
    }

    /**
     * 批量生成授权码（仅返回一次明文 key）。
     *
     * @return array{list: array<int, array{id:int, key:string, status:string, expires_at:?string}>}
     */
    public function generate(
        Product $product,
        int $count,
        ?string $expiresAt,
        int $maxDevices,
        ?string $customer,
        array $meta,
        User $creator,
        ?Request $request = null,
    ): array {
        $maxBatch = (int) config('license.generate.max_batch', 500);
        if ($count < 1 || $count > $maxBatch) {
            throw new RuntimeException("单次生成数量需在 1-{$maxBatch} 之间。", 422);
        }

        $now = now();
        $expires = $expiresAt !== null && $expiresAt !== '' ? \Illuminate\Support\Carbon::parse($expiresAt) : null;
        if ($expires !== null && $expires->isPast()) {
            throw new RuntimeException('过期时间不能早于当前时间。', 422);
        }

        $created = [];
        $attempts = 0;
        $maxAttempts = $count * 20;

        // 批量插入：每批 500 行（customer/meta 先经 AES-256-GCM 加密，避开模型 mutator 的批量路径）
        $aes = app(\App\Services\AesGcmService::class);
        $rows = [];
        while (count($created) < $count && $attempts < $maxAttempts) {
            $attempts++;
            $key = (string) config('license.key.default_prefix', 'ZF-').$this->randomBody();
            $keyHash = $this->keyGenerator->hashKey($key);

            // key_hash 唯一性（数据库唯一索引兜底）
            $exist = License::query()->where('key_hash', $keyHash)->exists();
            if ($exist) {
                continue;
            }

            $rows[] = [
                'product_id' => $product->id,
                'key_prefix' => (string) $product->slug,
                'key_hash' => $keyHash,
                'status' => LicenseStatus::Pending->value,
                'customer' => $customer !== null && $customer !== '' ? $aes->encrypt($customer) : null,
                'customer_sha256' => $customer !== null && $customer !== '' ? License::sha256Of($customer) : null,
                'max_devices' => max(1, $maxDevices),
                'meta' => $meta === [] ? null : $aes->encrypt(json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                'expires_at' => $expires,
                'activated_at' => null,
                'revoked_at' => null,
                'last_heartbeat_at' => null,
                'created_by' => $creator->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $created[] = $key;

            if (count($rows) >= 500) {
                DB::table('licenses')->insert($rows);
                $rows = [];
            }
        }

        if (count($rows) > 0) {
            DB::table('licenses')->insert($rows);
        }

        if (count($created) < $count) {
            throw new RuntimeException('生成授权码时发生冲突，请重试。');
        }

        // 回读 id（按 created_at + key_hash 同批定位）
        $list = [];
        $ngram = app(CustomerNgramService::class);
        $ngramRows = [];
        foreach ($created as $i => $plainKey) {
            $record = License::query()->where('key_hash', $this->keyGenerator->hashKey($plainKey))->first();
            $list[] = [
                'id' => $record->id,
                'key' => $plainKey,
                'status' => $record->status->value,
                'expires_at' => $record->expires_at?->toIso8601String(),
            ];

            // V1.30：同步写入客户名称 n-gram 盲索引（模糊检索专用）
            if ($customer !== null && $customer !== '') {
                foreach ($ngram->gramsOf($customer) as $gram) {
                    $ngramRows[] = [
                        'license_id' => $record->id,
                        'gram_sha256' => $ngram->gramHash($gram),
                    ];
                }
            }
        }

        if ($ngramRows !== []) {
            DB::table('license_customer_ngrams')->insert($ngramRows);
        }

        $this->audit->adminAction(
            $creator,
            AuditAction::LICENSE_GENERATED,
            resourceType: 'license',
            resourceId: (string) $product->id,
            context: ['count' => $count, 'product' => $product->slug],
            request: $request,
        );

        return ['list' => $list];
    }

    /**
     * 按明文 key 查找授权码（HMAC 索引，防离线爆破）。
     */
    public function findByKey(string $key): ?License
    {
        $normalized = $this->keyGenerator->normalizeWithSeparator($key);

        return License::query()
            ->where('key_hash', $this->keyGenerator->hashKey($normalized))
            ->first();
    }

    /**
     * 激活授权码并绑定设备。
     *
     * @param  array<string, mixed>  $signals 已解码的原始硬件信号
     */
    public function activate(License $license, array $signals, ?string $deviceName, Request $request): array
    {
        return DB::transaction(function () use ($license, $signals, $deviceName, $request) {
            /** @var License $locked */
            $locked = License::query()->whereKey($license->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new RuntimeException('授权码不存在。', 404);
            }

            // 状态机校验（黑名单 / 吊销 / 过期）
            $this->assertActivatable($locked);

            $fingerprintHash = $this->fingerprint->compute($signals);
            $now = now();

            // 1) 一机一码互斥：该指纹已被其他未失效授权码绑定
            $otherBinding = Device::query()
                ->where('fingerprint_hash_sha256', Device::sha256Of($fingerprintHash))
                ->where('license_id', '!=', $locked->id)
                ->whereNull('deleted_at')
                ->whereHas('license', function ($q) {
                    $q->whereIn('status', [
                        LicenseStatus::Active->value,
                        LicenseStatus::Pending->value,
                    ])->whereNull('deleted_at');
                })
                ->exists();

            if ($otherBinding) {
                $this->audit->clientAction(AuditAction::LICENSE_ACTIVATED, actorId: substr($locked->key_hash, 0, 8), context: ['error' => 'device_bound_elsewhere'], request: $request);

                throw new RuntimeException('设备已被其他授权码绑定，无法重复激活。', 400);
            }

            // 2) 查询当前授权码下该指纹设备
            $device = Device::query()
                ->where('license_id', $locked->id)
                ->where('fingerprint_hash_sha256', Device::sha256Of($fingerprintHash))
                ->orderByDesc('id')
                ->first();

            if ($device === null) {
                // 3) 绑定数上限检查（原子：锁内计数）
                $activeCount = Device::query()
                    ->where('license_id', $locked->id)
                    ->whereNull('deleted_at')
                    ->count();

                if ($activeCount >= $locked->max_devices) {
                    $this->audit->clientAction(AuditAction::LICENSE_ACTIVATED, actorId: substr($locked->key_hash, 0, 8), context: ['error' => 'over_limit'], request: $request);

                    throw new RuntimeException('授权码已达设备绑定上限。', 400);
                }

                $device = Device::query()->create([
                    'license_id' => $locked->id,
                    'fingerprint_hash' => $fingerprintHash,
                    'device_name' => $deviceName !== null ? mb_substr($deviceName, 0, 120) : null,
                    'is_active' => true,
                    'last_ip' => $request->ip(),
                    'last_user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ]);
            } elseif (! $device->is_active) {
                // 设备之前被解绑，恢复
                $device->forceFill([
                    'is_active' => true,
                    'last_seen_at' => $now,
                    'last_ip' => $request->ip(),
                ])->save();
            } else {
                // 已绑定（同一设备重新激活），仅刷新痕迹
                $device->forceFill(['last_seen_at' => $now])->save();
            }

            // 4) 授权码置为有效
            $payload = [
                'activated_at' => $locked->activated_at ?? $now,
                'status' => LicenseStatus::Active,
                'last_heartbeat_at' => $now,
            ];
            $locked->forceFill($payload)->save();

            $this->audit->clientAction(AuditAction::LICENSE_ACTIVATED, actorId: substr($locked->key_hash, 0, 8), resourceType: 'license', context: ['device_id' => $device->id], request: $request);

            return [
                'license_id' => $locked->id,
                'device_id' => $device->id,
                'device_name' => $device->device_name,
                'activated_at' => $locked->activated_at?->toIso8601String(),
            ];
        });
    }

    /**
     * 验证授权码有效性（只读，用于心跳 / 查询）。
     *
     * @param  array<string, mixed>  $signals
     */
    public function verify(License $license, array $signals, Request $request): array
    {
        $now = now();

        if ($license->status !== LicenseStatus::Active) {
            return $this->deny('license_not_active', $license->status->value);
        }

        if ($license->hasExpired()) {
            return $this->deny('license_expired', 'expired');
        }

        $fingerprintHash = $this->fingerprint->compute($signals);
        $device = Device::query()
            ->where('license_id', $license->id)
            ->where('fingerprint_hash_sha256', Device::sha256Of($fingerprintHash))
            ->whereNull('deleted_at')
            ->first();

        if ($device === null || ! $device->is_active) {
            return $this->deny('device_not_bound', 'ok');
        }

        // 强制在线：心跳超时即失效（无离线宽限）
        // V1.32：阈值支持设置页 DB 覆盖（license.heartbeat.timeout_seconds / enforce_online），未配置回落 config。
        if ((bool) $this->settings->get('license.heartbeat.enforce_online', true)) {
            $timeoutSeconds = (int) $this->settings->get('license.heartbeat.timeout_seconds', 300);
            $missedAt = optional($license->last_heartbeat_at)->getTimestamp() ?? $now->getTimestamp();
            if (($now->getTimestamp() - $missedAt) > $timeoutSeconds) {
                return $this->deny('heartbeat_timeout', 'ok');
            }
        }

        $boundCount = Device::query()->where('license_id', $license->id)->whereNull('deleted_at')->count();

        $this->audit->clientAction(AuditAction::LICENSE_VERIFIED, actorId: substr($license->key_hash, 0, 8), resourceType: 'license', request: $request);

        return [
            'valid' => true,
            'reason' => 'ok',
            'license_id' => $license->id,
            'status' => $license->status->value,
            'product' => $license->product?->slug,
            // ZF-2026-013：verify 响应不再下发明文 customer（客户名属敏感信息，泄露可被用于社工/撞库）；
            // 改为布尔 unverified_customer 标识，客户端仅能感知"客户名是否已登记"。
            'unverified_customer' => $license->customer === null || $license->customer === '',
            'expires_at' => $license->expires_at?->toIso8601String(),
            'max_devices' => $license->max_devices,
            'bound_devices' => $boundCount,
            'features' => $this->extractFeatures($license),
        ];
    }

    /**
     * 解绑设备（解除当前授权码与设备的绑定）。
     *
     * @param  array<string, mixed>  $signals
     */
    public function deactivate(License $license, array $signals, Request $request): array
    {
        $fingerprintHash = $this->fingerprint->compute($signals);

        $device = Device::query()
            ->where('license_id', $license->id)
            ->where('fingerprint_hash_sha256', Device::sha256Of($fingerprintHash))
            ->whereNull('deleted_at')
            ->first();

        if ($device === null || ! $device->is_active) {
            return ['deactivated' => false, 'reason' => 'not_bound'];
        }

        $device->update(['is_active' => false]);

        $this->audit->clientAction(AuditAction::DEVICE_UNBOUND, actorId: substr($license->key_hash, 0, 8), resourceType: 'license', context: ['device_id' => $device->id], request: $request);

        return ['deactivated' => true, 'device_id' => $device->id];
    }

    /**
     * 远程吊销（可恢复）。
     */
    public function revoke(License $license, User $operator, ?Request $request = null): void
    {
        if ($license->status === LicenseStatus::Blacklisted) {
            throw new RuntimeException('已拉黑的授权码不可吊销，需先恢复。', 422);
        }

        $license->forceFill([
            'status' => LicenseStatus::Revoked,
            'revoked_at' => now(),
        ])->save();

        Device::query()->where('license_id', $license->id)->update(['is_active' => false]);

        $this->audit->adminAction($operator, AuditAction::LICENSE_REVOKED, resourceType: 'license', resourceId: (string) $license->id, request: $request);
    }

    /**
     * 恢复已吊销的授权码。
     */
    public function restore(License $license, User $operator, ?Request $request = null): void
    {
        if ($license->status !== LicenseStatus::Revoked) {
            throw new RuntimeException('仅已吊销的授权码可恢复。', 422);
        }

        $status = $license->hasExpired() ? LicenseStatus::Expired : LicenseStatus::Active;

        $license->forceFill([
            'status' => $status,
            'revoked_at' => null,
        ])->save();

        // 与吊销对称：恢复吊销前绑定的设备为活跃状态
        Device::query()->where('license_id', $license->id)->update(['is_active' => true]);

        $this->audit->adminAction($operator, AuditAction::LICENSE_RESTORED, resourceType: 'license', resourceId: (string) $license->id, request: $request);
    }

    /**
     * 永久拉黑（仅超级管理员可操作）。
     */
    public function blacklist(License $license, User $operator, ?Request $request = null): void
    {
        $license->forceFill([
            'status' => LicenseStatus::Blacklisted,
            'revoked_at' => now(),
        ])->save();

        Device::query()->where('license_id', $license->id)->update(['is_active' => false]);

        $this->audit->adminAction($operator, AuditAction::LICENSE_BLACKLISTED, resourceType: 'license', resourceId: (string) $license->id, request: $request);
    }

    /**
     * 续期。
     */
    public function renew(License $license, ?string $newExpiresAt, User $operator, ?Request $request = null): void
    {
        if ($license->status === LicenseStatus::Blacklisted || $license->status === LicenseStatus::Revoked) {
            throw new RuntimeException('拉黑/吊销状态不可续期，请先恢复。', 422);
        }

        $expires = $newExpiresAt !== null && $newExpiresAt !== ''
            ? \Illuminate\Support\Carbon::parse($newExpiresAt)
            : null;

        if ($expires !== null && $expires->isPast()) {
            throw new RuntimeException('续期后的过期时间不能早于当前时间。', 422);
        }

        $nextStatus = $expires !== null && $expires->isPast()
            ? LicenseStatus::Expired
            : ($license->activated_at !== null ? LicenseStatus::Active : LicenseStatus::Pending);

        $license->forceFill([
            'status' => $nextStatus,
            'expires_at' => $expires,
            'revoked_at' => null,
        ])->save();

        $this->audit->adminAction($operator, AuditAction::LICENSE_RENEWED, resourceType: 'license', resourceId: (string) $license->id, context: ['expires_at' => $expires?->toIso8601String()], request: $request);
    }

    /**
     * 过期自动失效（由 license:expire 命令批量调用）。
     *
     * @return array{license_id:int, from:string, to:string}
     */
    public function expire(License $license): ?array
    {
        if ($license->status !== LicenseStatus::Active) {
            return null;
        }

        $expiredByTime = $license->hasExpired();
        $expiredByMissedHeartbeat = false;

        // V1.32：阈值支持设置页 DB 覆盖（license.heartbeat.enforce_online / timeout_seconds），未配置回落 config。
        if ((bool) $this->settings->get('license.heartbeat.enforce_online', true)
            && ((int) $this->settings->get('license.heartbeat.timeout_seconds', 300)) > 0) {
            $timeoutSeconds = (int) $this->settings->get('license.heartbeat.timeout_seconds', 300);
            $missedAt = optional($license->last_heartbeat_at)->getTimestamp() ?? $license->created_at?->getTimestamp() ?? time();
            $expiredByMissedHeartbeat = (time() - $missedAt) > $timeoutSeconds;
        }

        if (! $expiredByTime && ! $expiredByMissedHeartbeat) {
            return null;
        }

        $license->forceFill([
            'status' => LicenseStatus::Expired,
            'revoked_at' => now(),
        ])->save();

        Device::query()->where('license_id', $license->id)->update(['is_active' => false]);

        $this->audit->systemAction(AuditAction::LICENSE_EXPIRED, resourceType: 'license', resourceId: (string) $license->id, context: ['by_time' => $expiredByTime, 'by_heartbeat' => $expiredByMissedHeartbeat]);

        return [
            'license_id' => $license->id,
            'from' => LicenseStatus::Active->value,
            'to' => LicenseStatus::Expired->value,
            'by_time' => $expiredByTime,
            'by_heartbeat' => $expiredByMissedHeartbeat,
        ];
    }

    private function assertActivatable(License $license): void
    {
        if ($license->status === LicenseStatus::Blacklisted) {
            throw new RuntimeException('授权码已被拉黑，无法激活。', 403);
        }

        if ($license->status === LicenseStatus::Revoked) {
            throw new RuntimeException('授权码已被吊销，无法激活。', 403);
        }

        if ($license->hasExpired()) {
            throw new RuntimeException('授权码已过期，无法激活。', 403);
        }
    }

    private function deny(string $reason, string $status): array
    {
        return [
            'valid' => false,
            'reason' => $reason,
            'status' => $status,
        ];
    }

    /**
     * 提取授权码功能特性（从 meta.features 读取，写入签名 payload 供客户端离线可用）。
     *
     * @return array<string, mixed>
     */
    private function extractFeatures(License $license): array
    {
        $meta = is_array($license->meta) ? $license->meta : [];

        return is_array($meta['features'] ?? null) ? $meta['features'] : [];
    }

    private function randomBody(): string
    {
        $alphabet = (string) config('license.key.alphabet', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789');
        $segments = max(1, (int) config('license.key.segments', 5));
        $segmentLength = max(1, (int) config('license.key.segment_length', 5));

        $body = \Illuminate\Support\Str::random($segments * $segmentLength, $alphabet);

        return trim(chunk_split($body, $segmentLength, '-'), '-');
    }
}
