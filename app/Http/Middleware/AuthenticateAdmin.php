<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiToken;
use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 管理端 Bearer Token 鉴权（状态化 Token）。
 *
 * 支持与角色解耦：
 *  - 默认仅校验登录有效性
 *  - 传入 'super_admin' 参数时额外要求超级管理员
 */
class AuthenticateAdmin
{
    public function handle(Request $request, Closure $next, string $require = 'admin'): Response
    {
        $authorization = $request->header('Authorization');
        if ($authorization === null || ! str_starts_with($authorization, 'Bearer ')) {
            return ApiResponse::fail('未认证，请先登录。', 401, 1001);
        }

        $token = trim(substr($authorization, 7));
        $tokenHash = hash('sha256', $token);

        /** @var ApiToken|null $apiToken */
        $apiToken = ApiToken::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if ($apiToken === null) {
            return ApiResponse::fail('凭证无效或已过期。', 401, 1001);
        }

        /** @var User|null $user */
        $user = $apiToken->user()->withoutGlobalScopes()->first();

        if ($user === null || ! $user->is_active) {
            return ApiResponse::fail('账号不可用。', 403, 1003);
        }

        // 角色校验
        if ($require === 'super_admin' && ! $user->isSuperAdmin()) {
            return ApiResponse::fail('权限不足，需要超级管理员。', 403, 1004);
        }

        // 刷新最后使用时间（节流写库：60 秒内不重复写）
        $lastUsed = $apiToken->last_used_at;
        if ($lastUsed === null || $lastUsed->getTimestamp() < now()->subSeconds(60)->getTimestamp()) {
            $apiToken->forceFill(['last_used_at' => now()])->save();
        }

        $request->attributes->set('auth_user', $user);
        $request->attributes->set('auth_token', $apiToken);

        return $next($request);
    }
}
