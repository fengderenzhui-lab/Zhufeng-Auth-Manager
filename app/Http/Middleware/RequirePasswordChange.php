<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 首登强制改密拦截（等保 H-02 修复）：
 *  - 用户 must_change_password = true 时，除「改密 / me / logout」外的一切管理端 API 一律 403。
 *  - 该中间件挂在 admin 路由组最内层（auth.api 之后），保证未登录请求不受影响。
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var \App\Models\User|null $user */
        $user = $request->attributes->get('auth_user');
        if ($user === null || ! (bool) $user->must_change_password) {
            return $next($request);
        }

        // 放行：查看本人 / 登出 / 修改本人密码（首登改密闭环）
        $allowed = $request->routeIs('admin.me', 'admin.logout', 'admin.security.password')
            || $request->is('api/v1/admin/security/password');

        if (! $allowed) {
            return ApiResponse::fail('首次登录须修改初始密码后才能继续操作。', 403, 1701);
        }

        return $next($request);
    }
}
