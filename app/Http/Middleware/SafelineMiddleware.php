<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SettingsService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 雷池（SafeLine）人机验证中间件（V1.32 补全）。
 *
 * 接入语义：
 *  - 开关 LICENSING_SAFELINE_ENABLED（默认关闭）：关闭时完全放行，不改变任何现有行为；
 *  - 开启时：校验雷池 WAF 注入的可信请求头（默认 X-SafeLine-Checked，可用
 *    LICENSING_SAFELINE_TRUSTED_HEADER 配置头名）；头缺失或值不匹配 → 403
 *    + 统一错误信封 {success:false,error:{code,message}}，错误码 SAFELINE_BLOCKED；
 *  - 开关可经设置页（license.safeline.enabled，DB 优先）或 .env 控制，无需重启即生效；
 *  - 仅挂载公网防刷端点（/api/licensing/v1 业务六端点、公开查询 public-key）；
 *    health 探活、admin/SDK 端点不挂载（默认关闭时天然不拦截，开启后亦不影响内部链路）。
 */
class SafelineMiddleware
{
    /** 雷池可信头通过值集合（雷池固定注入值，兼容常见取值） */
    private const PASS_VALUES = ['1', 'true', 'yes', 'on', 'ok'];

    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        // 开关：settings 表 DB 值优先，无记录回落 config（.env LICENSING_SAFELINE_ENABLED）。
        $enabled = (bool) $this->settings->get('license.safeline.enabled', false);
        if (! $enabled) {
            return $next($request);
        }

        $header = (string) $this->settings->get('license.safeline.trusted_header', 'X-SafeLine-Checked');
        $value = trim((string) $request->headers->get($header, ''));

        if ($value === '' || ! in_array(strtolower($value), self::PASS_VALUES, true)) {
            return ApiResponse::failV1('SAFELINE_BLOCKED', '请求未通过人机验证。', 403);
        }

        return $next($request);
    }
}
