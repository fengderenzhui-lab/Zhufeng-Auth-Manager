<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 角色鉴权（在 AuthenticateAdmin 之后使用）：从 request 属性读取已认证用户。
 */
class AuthorizeRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        /** @var User|null $user */
        $user = $request->attributes->get('auth_user');

        if ($user === null) {
            return ApiResponse::fail('未认证。', 401, 1001);
        }

        $allowed = match ($role) {
            'super_admin' => $user->isSuperAdmin(),
            'admin' => Role::tryFrom((string) $user->role) !== null,
            default => false,
        };

        if (! $allowed) {
            return ApiResponse::fail('权限不足。', 403, 1004);
        }

        return $next($request);
    }
}
