<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ReplayGuardService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 防重放中间件（SDK 端点）。失败统一返回 400 且不泄露具体细节。
 */
class ReplayProtect
{
    public function __construct(private readonly ReplayGuardService $guard)
    {
    }

    /**
     * 防重放中间件。失败统一返回 400 且不泄露具体细节。
     *
     * @param  string  $mode  'strict'=SDK 强制签名（client_secret）；'admin'=管理端强制签名（管理员 secret）；'loose'=仅时间戳+nonce（登录等公开入口）
     */
    public function handle(Request $request, Closure $next, string $mode = 'strict'): Response
    {
        try {
            $this->guard->validateRequest($request, $mode);
        } catch (\RuntimeException $e) {
            return ApiResponse::fail('请求被拒绝（非法或过期请求）。', 400, 1101);
        }

        return $next($request);
    }
}
