<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\ApiToken;
use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * 登录安全服务：
 *  - 错误次数锁定（持久化 LoginAttempt 计数，DB 级防绕过）
 *  - 状态化 Token 鉴权（仅存 SHA-256 哈希、有效期、主动登出销毁）
 *  - 单用户活跃 Token 数上限，防止凭证堆积
 */
final class AuthService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    /**
     * 管理员登录。成功返回 [user, token(明文), ApiToken, hmacSecret(管理员独立签名密钥)]。
     *
     * @throws RuntimeException 失败时抛出，message 已归一化防枚举
     */
    public function login(string $email, string $password, Request $request): array
    {
        $email = mb_strtolower(trim($email));
        $ip = (string) $request->ip();

        // 1) 持久化锁定：窗口内连续失败次数（email/ip 已加密存储，等值查询走 sha256 盲索引列）
        $threshold = (int) config('license.auth.lockout_threshold', 5);
        // 全局锁定阈值（按 email，不区分 IP）：防攻击者轮换 IP 绕过；等保 M-01 修复
        $globalThreshold = (int) config('license.auth.lockout_global_threshold', 15);
        $lockMinutes = (int) config('license.auth.lockout_minutes', 15);

        $recentFailures = LoginAttempt::query()
            ->where('email_sha256', LoginAttempt::sha256Of($email))
            ->where('ip_sha256', LoginAttempt::sha256Of($ip))
            ->where('success', false)
            ->where('attempted_at', '>=', now()->subMinutes($lockMinutes))
            ->count();

        $recentFailuresGlobal = LoginAttempt::query()
            ->where('email_sha256', LoginAttempt::sha256Of($email))
            ->where('success', false)
            ->where('attempted_at', '>=', now()->subMinutes($lockMinutes))
            ->count();

        // 命中任一锁定维度即拒绝，且响应统一为 401（与凭据错误同语义，防邮箱枚举探测；等保 L-02 修复）
        if ($recentFailures >= $threshold || $recentFailuresGlobal >= $globalThreshold) {
            $this->logAttempt($email, $request, false);

            throw new RuntimeException('邮箱或密码错误。', 401);
        }

        $user = User::query()
            // V1.30：email 已 AES 加密存储，登录查询迁移到 email_sha256 盲索引精确匹配
            ->where('email_sha256', User::sha256Of($email))
            ->whereNull('deleted_at')
            ->first();

        // 统一失败语义：不存在 / 密码错误 / 未激活 / 已锁定 折叠为同一响应，防账号枚举
        $credentialOk = $user !== null && Hash::check($password, $user->password);

        if ($user === null || ! $credentialOk) {
            // 失败响应增加随机延时（10-250ms），降低批量枚举速率；等保 M-01 修复
            usleep(random_int(10_000, 250_000));

            $this->logAttempt($email, $request, false);
            $this->audit->clientAction(AuditAction::LOGIN_FAILED, actorId: $email, context: ['ip' => $ip], request: $request);

            throw new RuntimeException('邮箱或密码错误。', 401);
        }

        // 2.1) 旧 bcrypt 哈希自动升级为 Argon2id（hashed cast 自动对新值哈希）
        if (Hash::needsRehash($user->password)) {
            $user->password = $password;
            $user->save();
        }

        if (! $user->is_active) {
            $this->logAttempt($email, $request, false);
            // ZF-2026-003：审计 actorId 使用掩码邮箱（中间打星）
            $this->audit->clientAction(AuditAction::LOGIN_FAILED, actorId: self::maskEmail($email), context: ['ip' => $ip, 'reason' => 'inactive'], request: $request);

            throw new RuntimeException('邮箱或密码错误。', 401);
        }

        // 2) 登录成功
        $token = bin2hex(random_bytes(32)); // 64 hex
        $tokenHash = hash('sha256', $token);
        $ttlMinutes = (int) config('license.auth.token_ttl_minutes', 720);

        // Token 名称带上客户端名 + UA 前缀（前端标识 + 浏览器/客户端类别），便于管理端日志区分会话来源；等保 L-07 修复
        $ua = trim((string) $request->userAgent());
        $clientName = trim((string) $request->input('client_name', ''));
        $tokenName = $clientName !== '' ? $clientName.'|'.Str::limit($ua, 120) : (Str::limit($ua, 120) ?: 'api');

        $apiToken = ApiToken::query()->create([
            'user_id' => $user->id,
            'name' => $tokenName,
            'token_hash' => $tokenHash,
            'last_used_at' => now(),
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        // 3) 清理超限活跃 Token（最旧优先）
        $maxActive = (int) config('license.auth.max_active_tokens', 5);
        $excess = ApiToken::query()
            ->where('user_id', $user->id)
            ->valid()
            ->orderByDesc('id')
            ->get()
            ->slice($maxActive);

        foreach ($excess as $old) {
            $old->update(['revoked_at' => now()]);
        }

        // 4) 更新登录痕迹
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $ip,
        ])->save();

        $this->logAttempt($email, $request, true);
        $this->audit->adminAction($user, AuditAction::LOGIN_SUCCESS, context: ['ip' => $ip], request: $request);

        // 返回管理员独立 HMAC 签名密钥（登录后前端用于管理端 API 的 X-Signature）
        return [$user, $token, $apiToken, $user->hmacSecret()];
    }

    /**
     * 主动登出：吊销当前 Token（立即失效）。
     */
    public function logout(ApiToken $token, User $user, ?Request $request = null): void
    {
        if ($token->revoked_at === null) {
            $token->update(['revoked_at' => now()]);
            $this->audit->adminAction($user, AuditAction::LOGOUT, request: $request);
        }
    }

    /**
     * 审计：管理员修改本人密码（等保 H-02 首登强制改密闭环）。
     */
    public function auditAdminPasswordChange(User $user, ?Request $request = null): void
    {
        $this->audit->adminAction($user, AuditAction::PASSWORD_CHANGED, request: $request);
    }

    /**
     * 邮箱脱敏：保留本地部分首尾字符，中间打星，域名保持完整（等保 N-03 / ZF-2026-003）。
     */
    private static function maskEmail(string $email): string
    {
        $email = trim($email);
        if ($email === '') {
            return '';
        }

        $at = strrpos($email, '@');
        if ($at === false) {
            $len = strlen($email);
            if ($len <= 2) {
                return str_repeat('*', $len);
            }

            return substr($email, 0, 1).str_repeat('*', $len - 2).substr($email, -1);
        }

        $local = substr($email, 0, $at);
        $domain = substr($email, $at);
        $len = strlen($local);
        if ($len <= 2) {
            return str_repeat('*', $len).$domain;
        }

        return substr($local, 0, 1).str_repeat('*', $len - 2).substr($local, -1).$domain;
    }

    private function logAttempt(string $email, Request $request, bool $success): void
    {
        LoginAttempt::query()->create([
            'email' => $email,
            'ip' => (string) $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 255),
            'success' => $success,
            'attempted_at' => now(),
        ]);
    }
}
