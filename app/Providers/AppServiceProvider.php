<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Bind services as singletons for stable state across requests.
        $this->app->singleton(\App\Services\AesGcmService::class);
        $this->app->singleton(\App\Services\AuditService::class);
        $this->app->singleton(\App\Services\AuthService::class);
        $this->app->singleton(\App\Services\CopyrightSignatureService::class);
        $this->app->singleton(\App\Services\DeviceFingerprintService::class);
        $this->app->singleton(\App\Services\Ed25519Service::class);
        $this->app->singleton(\App\Services\HeartbeatService::class);
        $this->app->singleton(\App\Services\LicenseService::class);
        $this->app->singleton(\App\Services\PasetoService::class);
        $this->app->singleton(\App\Services\ReplayGuardService::class);
        // V1.32：系统配置服务（settings 表 DB 优先、config 兜底，请求内缓存）
        $this->app->singleton(\App\Services\SettingsService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->assertNoPlaceholderSecrets();
        $this->configureRateLimiting();
        $this->configureForceHttps();

        // V1.30 版权动态签名守护：程序启动时执行一次版权完整性校验。
        // 等保 N-02 修复：校验失败仅记录日志（告警），不再抛异常拒绝启动（fail-open 于可用性），
        // 避免任何部署差异（CDN/构建工具改动文件、误删公钥等）导致整站不可用。
        try {
            $this->app->make(\App\Services\CopyrightSignatureService::class)->verifyGuard();
        } catch (\RuntimeException $e) {
            \Illuminate\Support\Facades\Log::warning('[signature-guard] 启动版权签名校验失败（已降级为告警，不阻断启动）：'.$e->getMessage());
        }
    }

    /**
     * 等保 H-03 / ZF-2026-014 修复：占位符密钥 fail-closed。
     * 生产环境检测到 change-me-* 占位符或关键 HMAC 密钥缺失时直接抛错拒绝启动，
     * 避免防重放签名/设备指纹体系使用公开可预测的密钥而实质失效。
     * ZF-2026-014：LICENSING_KEY_HMAC_SECRET 缺失或为 change-me 时同样拒绝启动，
     * 不再静默回退 APP_KEY（历史授权码 key_hash 派生密钥被换会导致校验失败）。
     * （Docker 部署由 entrypoint.sh 自动替换占位符；手动部署必须显式配置。）
     */
    protected function assertNoPlaceholderSecrets(): void
    {
        if ((string) config('app.env') !== 'production') {
            return;
        }

        $placeholders = [
            'LICENSING_REPLAY_CLIENT_SECRET' => config('license.security.replay.client_secret', ''),
            'LICENSING_FINGERPRINT_SALT' => config('license.fingerprint.salt', ''),
            'LICENSING_KEY_HMAC_SECRET' => config('license.key.hmac_secret', ''),
        ];
        foreach ($placeholders as $name => $value) {
            if (! is_string($value) || $value === '' || str_contains($value, 'change-me-')) {
                throw new \RuntimeException(sprintf(
                    '生产环境必须配置 %s（缺失或为 change-me-* 占位符，服务拒绝启动）。请运行 php artisan license:keys --check 或配置真实密钥。',
                    $name
                ));
            }
        }

        // 审计哈希链密钥缺失将导致审计写入 fail-closed，这里提前暴露配置问题
        if ((string) config('license.audit.hmac_secret', '') === '') {
            throw new \RuntimeException('生产环境必须配置 LICENSING_AUDIT_HMAC_SECRET（审计哈希链密钥），服务拒绝启动。');
        }
    }

    /**
     * 应用层强制 HTTPS：
     *  1) 将 ForceHttps 中间件挂载到 web/api 路由组（Laravel 11+ 无 Http Kernel，
     *     通过在 ServiceProvider 中向路由组追加实现等效的全局中间件，覆盖所有 HTTP 请求）；
     *  2) force_https 为 true 时强制 URL 生成使用 https（forceScheme + Laravel 12 的 forceHttps）。
     * force_https 为 false（本地开发 / 纯内网）时整体跳过。
     */
    protected function configureForceHttps(): void
    {
        $forceHttps = (bool) config('license.tls.force_https', false);

        // 中间件始终挂载（内部自行判断开关），保证配置变更无需重启即可生效
        $router = $this->app['router'];
        $router->pushMiddlewareToGroup('web', \App\Http\Middleware\ForceHttps::class);
        $router->pushMiddlewareToGroup('api', \App\Http\Middleware\ForceHttps::class);

        if (! $forceHttps) {
            return;
        }

        URL::forceScheme('https');

        // Laravel 12+ 提供 URL::forceHttps()，优先使用；低版本仅 forceScheme
        if (method_exists(URL::class, 'forceHttps')) {
            URL::forceHttps();
        }
    }

    /**
     * Global rate limiters used by routes and controllers.
     */
    protected function configureRateLimiting(): void
    {
        // 1) Global API limiter (per client IP).
        // ZF-2026-012：全局 throttle 限流 key 仅用 IP（去掉 UA），避免 UA 可被伪造导致限流失效；
        // 端点专属限流（login/client/licensing-*）保留 IP+UA 双因子。
        RateLimiter::for('api', function ($request) {
            return Limit::perMinute((int) config('license.security.rate_limit.global_per_minute', 120))
                ->by((string) $request->ip());
        });

        // 2) Login limiter: per IP + per account.
        // ZF-2026-005：邮箱 key 统一 mb_strtolower(trim())，与 AuthService 登录归一化一致，防大小写绕过。
        RateLimiter::for('login', function ($request) {
            $email = mb_strtolower(trim((string) $request->input('email', '')));
            $key = 'login:'.sha1((string) $request->ip().'|'.$email);

            return [
                Limit::perMinute((int) config('license.security.rate_limit.login_per_minute', 10))->by($key),
                Limit::perDay((int) config('license.security.rate_limit.login_per_day', 50))->by($key),
            ];
        });

        // 3) License client endpoints (activate / heartbeat / verify): per IP.
        RateLimiter::for('client', function ($request) {
            return Limit::perMinute((int) config('license.security.rate_limit.client_per_minute', 60))
                ->by($request->ip().'|'.($request->userAgent() ?: 'ua'));
        });

        // 4) Admin API limiter: per authenticated user id (or IP fallback).
        RateLimiter::for('admin', function ($request) {
            $user = $request->attributes->get('auth_user');
            $key = $user !== null ? 'admin:u'.$user->id : 'admin:ip'.$request->ip();

            return Limit::perMinute((int) config('license.security.rate_limit.admin_per_minute', 300))->by($key);
        });

        // 5) 客户端对接（/api/licensing/v1）：按端点语义分桶，对齐 masterix21 服务端设计
        RateLimiter::for('licensing-register', function ($request) {
            return Limit::perMinute(10)->by($request->ip().'|'.($request->userAgent() ?: 'ua'));
        });
        RateLimiter::for('licensing-token', function ($request) {
            return Limit::perMinute(30)->by($request->ip().'|'.($request->userAgent() ?: 'ua'));
        });
        RateLimiter::for('licensing-validate', function ($request) {
            return Limit::perMinute(120)->by($request->ip().'|'.($request->userAgent() ?: 'ua'));
        });
        RateLimiter::for('licensing', function ($request) {
            return Limit::perMinute(120)->by($request->ip().'|'.($request->userAgent() ?: 'ua'));
        });
    }
}
