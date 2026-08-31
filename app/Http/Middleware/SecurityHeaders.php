<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 安全相关响应头（等保 2.0 一级基础项）。
 */
class SecurityHeaders
{
    /**
     * @var array<string, string>
     */
    private array $headers = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        // 等保 M-04：nginx 已下发，应用层补齐同基线（双保险）
        'X-XSS-Protection' => '1; mode=block',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        foreach ($this->headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        // CSP：管理后台将引入 Vue 资源，此处为保守基线，后台页面可按需放宽。
        // ZF-2026-009：CSP 改读 config('license.security.headers.csp')，使 LICENSING_CSP 环境变量真正生效。
        // ZF-2026-010：API 请求（X-Requested-With 或 /api/ 路径）跳过应用层 CSP，保留 nginx 侧 strict CSP
        // （API JSON 响应无需脚本/样式，nginx map 已按 URI 区分下发 default-src 'none'）。
        $isApi = $request->is('api/*') || $request->headers->has('X-Requested-With');
        if (! $isApi && ! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set(
                'Content-Security-Policy',
                (string) config('license.security.headers.csp', "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'none'")
            );
        }

        // HSTS：仅当请求为 HTTPS（或配置强制）时下发，避免 HTTP 场景被缓存误伤
        $forceHttps = (bool) config('license.tls.force_https', true);
        if (config('license.tls.hsts', true) && ($request->isSecure() || $forceHttps)) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        return $response;
    }
}
