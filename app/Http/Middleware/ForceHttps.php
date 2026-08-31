<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 应用层强制 HTTPS 中间件。
 *
 * 在 nginx 80→443 跳转之外提供应用层兜底防护：
 *  - API / JSON 请求：非 HTTPS 直接返回 403 JSON（防反代跳过、防降级访问）
 *  - 页面请求：301 跳转到 https 同 URL
 *  - 支持信任代理头 X-Forwarded-Proto（config('license.tls.trust_proxies') 控制，
 *    适配雷池 SafeLine / Cloudflare / 自建 CDN 反向代理场景）
 *  - 已处于 HTTPS、本地 CLI（artisan）环境直接放行
 *  - force_https 为 false（本地开发 / 纯内网）时整体跳过
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        // 开关关闭：本地开发 / 纯内网场景直接放行
        if (! (bool) config('license.tls.force_https', false)) {
            return $next($request);
        }

        // CLI 环境（artisan 命令）不经 HTTP，直接放行
        if (app()->runningInConsole()) {
            return $next($request);
        }

        // 已处于 HTTPS（含信任代理头）直接放行
        if ($this->isHttps($request)) {
            return $next($request);
        }

        // 非 HTTPS：API/JSON 返回 403，页面 301 跳转 https 同 URL
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'HTTPS_REQUIRED',
                    'message' => '仅支持 HTTPS 访问',
                ],
            ], 403);
        }

        return redirect()->secure($request->getRequestUri(), 301);
    }

    /**
     * 判断请求是否实际处于 HTTPS。
     * 等保 M-05 修复：仅当来源 IP 命中可信代理白名单（license.tls.trusted_proxies，
     * env LICENSING_TRUSTED_PROXIES，逗号分隔 IP，默认空）时才信任 X-Forwarded-Proto；
     * 白名单为空时仅信任自身连接（TLS 终止在应用层直连），杜绝伪造代理头绕过 HTTPS 强制。
     */
    protected function isHttps(Request $request): bool
    {
        if ($request->isSecure()) {
            return true;
        }

        $trustedProxies = $this->trustedProxies();
        if ($trustedProxies !== []) {
            $ip = (string) $request->getClientIp();
            if (in_array($ip, $trustedProxies, true)) {
                $proto = $request->header('X-Forwarded-Proto');
                return $proto !== null && strtolower((string) $proto) === 'https';
            }
        }

        return false;
    }

    /**
     * 可信代理 IP 白名单（逗号分隔，来自 env LICENSING_TRUSTED_PROXIES）。
     */
    protected function trustedProxies(): array
    {
        $raw = trim((string) config('license.tls.trusted_proxies', ''));

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }
}
