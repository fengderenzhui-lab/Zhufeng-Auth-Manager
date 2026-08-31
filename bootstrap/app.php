<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\ForceJson::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Web 页面组同样下发安全响应头（nginx 层兜底之外的应用层保障）
        $middleware->web(prepend: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'auth.api' => \App\Http\Middleware\AuthenticateAdmin::class,
            'role' => \App\Http\Middleware\AuthorizeRole::class,
            'replay' => \App\Http\Middleware\ReplayProtect::class,
            'require.password.change' => \App\Http\Middleware\RequirePasswordChange::class,
            // V1.30：版权动态签名守护（核心接口前置 503 fail-closed）
            'signature.guard' => \App\Http\Middleware\CopyrightSignatureGuard::class,
            // V1.32：雷池（SafeLine）人机验证（默认关闭，仅挂公网防刷端点）
            'safeline' => \App\Http\Middleware\SafelineMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Illuminate\Http\Request $request): bool => $request->is('api/*') || $request->expectsJson()
        );
    })
    ->create();
