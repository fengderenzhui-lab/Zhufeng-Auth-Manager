<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\CopyrightSignatureService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 版权签名守护中间件（V1.30 新增）
 *
 * 挂载位置：核心接口前置（licensing/v1 核心路由、后台管理 auth.api 组）。
 * 行为：调用 CopyrightSignatureService::verifyGuard()，
 *   - 校验通过 → 放行；
 *   - 校验失败（RuntimeException）→ 等保 N-02 修复：仅记录日志/审计并放行（fail-open 于可用性），
 *     不再返回 503，避免签名守护成为核心接口的可用性单点故障。
 */
class CopyrightSignatureGuard
{
    public function __construct(
        private readonly CopyrightSignatureService $signatureService,
    ) {
    }

    /**
     * 处理请求：前置版权完整性校验（失败仅告警，不阻断）。
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->signatureService->verifyGuard();
        } catch (\RuntimeException $e) {
            // 等保 N-02：降级为告警不阻断——verifyGuard 内部已写审计日志，此处仅记录并放行
            \Illuminate\Support\Facades\Log::warning('[signature-guard] 版权签名校验失败（已降级为告警，放行请求）：'.$e->getMessage());
        }

        return $next($request);
    }
}
