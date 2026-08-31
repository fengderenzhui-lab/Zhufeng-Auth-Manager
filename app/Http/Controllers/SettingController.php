<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\SettingsRequest;
use App\Services\AuditService;
use App\Services\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 系统配置（仅超级管理员）。敏感配置建议直接走 .env，此处仅开放非敏感运行参数。
 * V1.32：写入统一走 SettingsService（DB 优先、config 兜底、请求内缓存即时失效），
 * 保存后立即被业务代码（心跳超时/审计保留/雷池开关等）消费。
 */
class SettingController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly SettingsService $settings,
    ) {
    }

    public function index(): JsonResponse
    {
        $settings = $this->settings->all()->map(fn ($s) => [
            'key' => $s->key,
            'value' => $s->castValue(),
            'type' => $s->type,
            'description' => $s->description,
            'updated_at' => $s->updated_at?->toIso8601String(),
        ])->values();

        return ApiResponse::ok($settings);
    }

    public function store(SettingsRequest $request): JsonResponse
    {
        $key = $request->validated('key');

        // 等保 M-07 修复：敏感配置键由「黑名单」升级为「白名单」。
        // 仅允许写入预设的非敏感业务运行参数；密钥、TLS、数据库、缓存、邮件等一律禁止写 DB。
        // （当前代码无 config 从 DB 消费 settings 表，此处为前景性风险收敛）
        $allowedKeys = [
            // 心跳运行参数（业务可调）
            'license.heartbeat.interval_seconds',
            'license.heartbeat.timeout_seconds',
            'license.heartbeat.enforce_online',
            'license.heartbeat.retention_days',
            // 授权码生成参数（业务可调）
            'license.key.prefix',
            'license.key.default_prefix',
            'license.key.max_batch_size',
            // 设备绑定业务参数
            'license.fingerprint.default_max_devices',
            'license.fingerprint.min_signals',
            // 保留策略
            'license.audit.retention_days',
            'license.backup.retention_days',
            // V1.32：雷池人机验证开关（非敏感运行参数；endpoint/api_key/可信头名仍走 .env）
            'license.safeline.enabled',
        ];
        if (! in_array($key, $allowedKeys, true)) {
            return ApiResponse::fail('该配置项不在可写白名单内（等保 M-07），敏感/密钥类配置请到 .env 修改。', 422, 1601);
        }

        // 纵深防御：即便命中白名单，也再次拦截敏感前缀（防后续白名单误加）
        $forbiddenPrefixes = [
            'license.ed25519',
            'license.fingerprint.salt',
            'license.key.hmac_secret',
            'license.replay',
            'license.security',
            'license.tls',
            'license.audit.hmac_secret',
            'license.admin_security',
            // V1.32：雷池仅放行「开关」写入；endpoint/api_key/可信头名属于安全凭据/拓扑，仍走 .env
            'license.safeline.endpoint',
            'license.safeline.api_key',
            'license.safeline.trusted_header',
            'zf_app_encrypt',
            'app.key',
            'app.debug',
            'app.env',
            'db.',
            'session.',
            'cache.',
            'mail.',
            'queue.',
            'redis.',
        ];
        foreach ($forbiddenPrefixes as $prefix) {
            if (str_starts_with($key, $prefix)) {
                return ApiResponse::fail('该配置项属于敏感密钥，禁止写入数据库，请配置到 .env。', 422, 1601);
            }
        }

        // V1.32：统一走 SettingsService 写入（updateOrCreate + 请求内缓存即时失效，
        // 保存后立即被心跳/审计/雷池等业务代码消费，无需清 config 缓存/重启）。
        $setting = $this->settings->set(
            $key,
            (string) $request->validated('value'),
            (string) $request->validated('type'),
            $request->validated('description'),
            $request->attributes->get('auth_user')->id,
        );

        $this->audit->adminAction(
            $request->attributes->get('auth_user'),
            AuditAction::SETTINGS_UPDATED,
            resourceType: 'setting',
            resourceId: $key,
            request: $request,
        );

        return ApiResponse::ok($setting, '保存成功');
    }

    public function destroy(string $key, Request $request): JsonResponse
    {
        $setting = $this->settings->find($key);
        if ($setting === null) {
            return ApiResponse::fail('配置项不存在。', 404, 1602);
        }

        $setting->delete();
        $this->settings->forget($key);

        $this->audit->adminAction(
            $request->attributes->get('auth_user'),
            AuditAction::SETTINGS_UPDATED,
            resourceType: 'setting',
            resourceId: $key,
            request: $request,
        );

        return ApiResponse::ok(null, '已删除');
    }
}
