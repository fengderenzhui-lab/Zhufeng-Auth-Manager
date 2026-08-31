<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * 审计日志服务 —— 与模型事件解耦，统一显式调用，避免双写。
 */
final class AuditService
{
    /**
     * 记录一条审计日志。
     *
     * @param  array<string, mixed>  $context
     */
    public function record(
        string $action,
        string $actorType = 'admin',
        ?string $actorId = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        array $context = [],
        ?Request $request = null,
    ): void {
        // 心跳类高频审计按配置节流
        if ($action === \App\Enums\AuditAction::LICENSE_HEARTBEAT
            && ! (bool) config('license.audit.record_heartbeat_audit', false)) {
            return;
        }

        $ip = $request?->ip();
        $ua = $request?->userAgent();

        $createdAt = now()->format('Y-m-d H:i:s');

        // 等保 M-02 修复：审计哈希链防篡改。
        // 每条记录写入 prev_hash/hash（HMAC-SHA256，密钥取 LICENSING_AUDIT_HMAC_SECRET），
        // 与迁移回填逻辑保持一致；任一历史行被篡改，其后所有行校验即失败。
        $hmacKey = (string) config('license.audit.hmac_secret', '');
        if ($hmacKey === '') {
            throw new \RuntimeException('未配置 LICENSING_AUDIT_HMAC_SECRET，审计哈希链无法生成（fail-closed）。');
        }

        $prevHash = AuditLog::query()->orderByDesc('id')->value('hash');

        $contextJson = $context === [] ? null : json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $canonical = implode("\n", [
            (string) $prevHash,
            $actorType,
            $actorId !== null ? mb_substr($actorId, 0, 64) : '',
            mb_substr($action, 0, 64),
            $resourceType !== null ? mb_substr($resourceType, 0, 32) : '',
            $resourceId !== null ? mb_substr($resourceId, 0, 64) : '',
            $ip !== null ? mb_substr($ip, 0, 45) : '',
            $ua !== null ? mb_substr($ua, 0, 255) : '',
            (string) $contextJson,
            $createdAt,
        ]);

        $hash = hash_hmac('sha256', $canonical, $hmacKey);

        AuditLog::query()->create([
            'actor_type' => $actorType,
            'actor_id' => $actorId !== null ? mb_substr($actorId, 0, 64) : null,
            'action' => mb_substr($action, 0, 64),
            'resource_type' => $resourceType !== null ? mb_substr($resourceType, 0, 32) : null,
            'resource_id' => $resourceId !== null ? mb_substr($resourceId, 0, 64) : null,
            'ip' => $ip !== null ? mb_substr($ip, 0, 45) : null,
            'user_agent' => $ua !== null ? mb_substr($ua, 0, 255) : null,
            'context' => $context === [] ? null : $context,
            'prev_hash' => $prevHash,
            'hash' => $hash,
            'created_at' => $createdAt,
        ]);
    }

    public function adminAction(
        User $user,
        string $action,
        ?string $resourceType = null,
        ?string $resourceId = null,
        array $context = [],
        ?Request $request = null,
    ): void {
        $this->record(
            $action,
            actorType: 'admin',
            actorId: (string) $user->id,
            resourceType: $resourceType,
            resourceId: $resourceId,
            context: $context,
            request: $request,
        );
    }

    public function clientAction(
        string $action,
        ?string $actorId = null,
        ?string $resourceType = null,
        ?string $resourceId = null,
        array $context = [],
        ?Request $request = null,
    ): void {
        $this->record(
            $action,
            actorType: 'client',
            actorId: $actorId,
            resourceType: $resourceType,
            resourceId: $resourceId,
            context: $context,
            request: $request,
        );
    }

    public function systemAction(
        string $action,
        ?string $resourceType = null,
        ?string $resourceId = null,
        array $context = [],
    ): void {
        $this->record($action, actorType: 'system', resourceType: $resourceType, resourceId: $resourceId, context: $context);
    }
}
