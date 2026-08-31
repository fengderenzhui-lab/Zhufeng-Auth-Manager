<?php

declare(strict_types=1);

use App\Http\Controllers\AuditController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HeartbeatController;
use App\Http\Controllers\LicenseAdminController;
use App\Http\Controllers\LicenseScopeController;
use App\Http\Controllers\LicenseTemplateController;
use App\Http\Controllers\LicensingV1Controller;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicKeyController;
use App\Http\Controllers\SdkController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StatsController;
use App\Http\Controllers\TransferController;
use App\Http\Controllers\TrialController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - 逐风授权码管理平台
|--------------------------------------------------------------------------
|
| 版本前缀：/api/v1
| 安全约定：
|  - 公开 SDK 端点错误统一归一化，严禁暴露 key 是否存在（防枚举）
|  - 管理端全部走 Bearer 状态化 Token + 角色鉴权 + 防重放 + 限流
|
*/

// ---------------------------------------------------------------
// 全局 API 兜底限流（等保 L-06 修复）：挂载 throttle:api 到整个
// /api/v1 组，与端点级限流叠加，取更严者生效，纵深加固。
// ---------------------------------------------------------------
Route::middleware('throttle:api')->prefix('v1')->group(function () {

    // ---------------------------------------------------------------
    // 公开端点
    // ---------------------------------------------------------------
    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware(['throttle:login', 'replay:loose'])
        ->name('auth.login');

    Route::get('public-key', [PublicKeyController::class, 'show'])
        ->middleware(['throttle:client', 'safeline'])
        ->name('public-key.show');

    // 客户端 SDK（授权码端）：错误已归一化，strict 防重放（HMAC 签名）+ 限流
    Route::post('licenses/activate', [SdkController::class, 'activate'])
        ->middleware(['throttle:client', 'replay'])
        ->name('sdk.activate');
    Route::post('licenses/heartbeat', [SdkController::class, 'heartbeat'])
        ->middleware(['throttle:client', 'replay'])
        ->name('sdk.heartbeat');
    Route::post('licenses/verify', [SdkController::class, 'verify'])
        ->middleware(['throttle:client', 'replay'])
        ->name('sdk.verify');
    Route::post('licenses/deactivate', [SdkController::class, 'deactivate'])
        ->middleware(['throttle:client', 'replay'])
        ->name('sdk.deactivate');

    // ---------------------------------------------------------------
    // 客户端对接（对齐 masterix21/laravel-licensing-client）：
    // 前缀 /api/licensing/v1，统一信封 {success,data}/{success:false,error:{code,message}}
    // HMAC strict 防重放 + PASETO/Ed25519 双层校验（见 LicensingV1Controller）
    // ---------------------------------------------------------------
    Route::prefix('licensing/v1')->group(function () {
        // health 为健康探活端点，不挂签名守护，保证监控/负载均衡探活可用
        Route::get('health', [LicensingV1Controller::class, 'health'])
            ->middleware(['throttle:licensing'])
            ->name('licensing-v1.health');

        // V1.30：核心业务路由前置版权签名守护（verifyGuard 失败 → 503 code=5900）
        // V1.32：前置雷池人机验证（默认关闭；开启后未通过 WAF 校验的请求 403 SAFELINE_BLOCKED）
        Route::middleware(['safeline', 'signature.guard'])->group(function () {
            Route::post('activate', [LicensingV1Controller::class, 'activate'])
                ->middleware(['throttle:licensing-register', 'replay'])
                ->name('licensing-v1.activate');
            Route::post('deactivate', [LicensingV1Controller::class, 'deactivate'])
                ->middleware(['throttle:licensing-register', 'replay'])
                ->name('licensing-v1.deactivate');

            Route::post('refresh', [LicensingV1Controller::class, 'refresh'])
                ->middleware(['throttle:licensing-token', 'replay'])
                ->name('licensing-v1.refresh');

            Route::post('heartbeat', [LicensingV1Controller::class, 'heartbeat'])
                ->middleware(['throttle:licensing-validate', 'replay'])
                ->name('licensing-v1.heartbeat');
            Route::post('validate', [LicensingV1Controller::class, 'validate'])
                ->middleware(['throttle:licensing-validate', 'replay'])
                ->name('licensing-v1.validate');
            Route::post('licenses/show', [LicensingV1Controller::class, 'show'])
                ->middleware(['throttle:licensing-validate', 'replay'])
                ->name('licensing-v1.show');
        });
    });

    // ---------------------------------------------------------------
    // 管理端（Bearer Token + 限流 + 时间戳/nonce/签名防重放；
    // HMAC secret 每管理员独立，登录响应下发，可在设置中查看/轮换；
    // require.password.change：首登强制改密拦截（等保 H-02））
    // ---------------------------------------------------------------
    Route::prefix('admin')->middleware(['auth.api', 'throttle:admin', 'replay:admin', 'require.password.change', 'signature.guard'])->group(function () {

        // 当前登录人 / 登出
        Route::get('me', [AuthController::class, 'me'])->name('admin.me');
        Route::post('logout', [AuthController::class, 'logout'])->name('admin.logout');

        // 管理端 HMAC 签名密钥（本人查看/轮换）
        Route::get('security/hmac-secret', [UserController::class, 'hmacSecret'])->name('admin.security.hmac-secret');
        Route::post('security/hmac-secret/rotate', [UserController::class, 'rotateHmacSecret'])->name('admin.security.hmac-secret.rotate');

        // 修改本人密码（首登强制改密闭环）
        Route::post('security/password', [AuthController::class, 'changePassword'])->name('admin.security.password');

        // 个人中心 / 心跳监控（超级管理员 + 普通管理员）
        Route::get('profile', [ProfileController::class, 'show'])->name('admin.profile.show');
        Route::get('heartbeats', [HeartbeatController::class, 'index'])->name('admin.heartbeats.index');

        // 授权码管理（超级管理员 + 普通管理员）
        Route::post('licenses/generate', [LicenseAdminController::class, 'generate'])->name('admin.licenses.generate');
        Route::get('licenses', [LicenseAdminController::class, 'index'])->name('admin.licenses.index');
        Route::get('licenses/{license}', [LicenseAdminController::class, 'show'])->name('admin.licenses.show');
        Route::get('licenses/{license}/devices', [LicenseAdminController::class, 'devices'])->name('admin.licenses.devices');
        Route::post('licenses/{license}/renew', [LicenseAdminController::class, 'renew'])->name('admin.licenses.renew');
        Route::post('licenses/batch/revoke', [LicenseAdminController::class, 'batchRevoke'])->middleware('role:super_admin')->name('admin.licenses.batch-revoke');
        Route::post('licenses/{license}/revoke', [LicenseAdminController::class, 'revoke'])->name('admin.licenses.revoke');
        Route::post('licenses/{license}/restore', [LicenseAdminController::class, 'restore'])->name('admin.licenses.restore');

        // 产品管理（查看 + 写入）
        Route::get('products', [ProductController::class, 'index'])->name('admin.products.index');
        Route::post('products', [ProductController::class, 'store'])->middleware('role:super_admin')->name('admin.products.store');
        Route::patch('products/{product}', [ProductController::class, 'update'])->middleware('role:super_admin')->name('admin.products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])->middleware('role:super_admin')->name('admin.products.destroy');

        // 仪表盘统计（超级管理员 + 普通管理员；recent-audits 含审计日志，仅超管）
        Route::get('stats', [StatsController::class, 'dashboard'])->name('admin.stats');

        // 以下仅超级管理员
        Route::middleware('role:super_admin')->group(function () {
            // 审计日志摘要（普通管理员不可见，遵循最小权限）
            Route::get('stats/recent-audits', [StatsController::class, 'recentAudits'])->name('admin.stats.recent-audits');
            // 拉黑（永久，仅超管）
            Route::post('licenses/{license}/blacklist', [LicenseAdminController::class, 'blacklist'])->name('admin.licenses.blacklist');

            // 账号管理
            Route::get('users', [UserController::class, 'index'])->name('admin.users.index');
            Route::post('users', [UserController::class, 'store'])->name('admin.users.store');
            Route::patch('users/{user}', [UserController::class, 'update'])->name('admin.users.update');
            Route::delete('users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');

            // 审计日志
            Route::get('audit-logs', [AuditController::class, 'index'])->name('admin.audit-logs.index');
            Route::get('audit-logs/actions', [AuditController::class, 'actions'])->name('admin.audit-logs.actions');

            // 系统配置
            Route::get('settings', [SettingController::class, 'index'])->name('admin.settings.index');
            Route::post('settings', [SettingController::class, 'store'])->name('admin.settings.store');
            Route::delete('settings/{setting}', [SettingController::class, 'destroy'])->name('admin.settings.destroy');

            // Ed25519 公钥管理（可视化录入/列表/详情/删除，super_admin）
            Route::get('public-keys', [PublicKeyController::class, 'index'])->name('admin.public-keys.index');
            Route::post('public-keys', [PublicKeyController::class, 'store'])->name('admin.public-keys.store');
            Route::get('public-keys/{id}', [PublicKeyController::class, 'detail'])->name('admin.public-keys.detail');
            Route::delete('public-keys/{id}', [PublicKeyController::class, 'destroy'])->name('admin.public-keys.destroy');

            // 授权模板（超管专属 CRUD + 启停）
            Route::get('license-templates', [LicenseTemplateController::class, 'index'])->name('admin.license-templates.index');
            Route::get('license-templates/{id}', [LicenseTemplateController::class, 'show'])->name('admin.license-templates.show');
            Route::post('license-templates', [LicenseTemplateController::class, 'store'])->name('admin.license-templates.store');
            Route::match(['put', 'patch'], 'license-templates/{id}', [LicenseTemplateController::class, 'update'])->name('admin.license-templates.update');
            Route::delete('license-templates/{id}', [LicenseTemplateController::class, 'destroy'])->name('admin.license-templates.destroy');
            Route::post('license-templates/{id}/toggle', [LicenseTemplateController::class, 'toggle'])->name('admin.license-templates.toggle');

            // 授权范围（超管专属 CRUD + 启停）
            Route::get('license-scopes', [LicenseScopeController::class, 'index'])->name('admin.license-scopes.index');
            Route::get('license-scopes/{id}', [LicenseScopeController::class, 'show'])->name('admin.license-scopes.show');
            Route::post('license-scopes', [LicenseScopeController::class, 'store'])->name('admin.license-scopes.store');
            Route::match(['put', 'patch'], 'license-scopes/{id}', [LicenseScopeController::class, 'update'])->name('admin.license-scopes.update');
            Route::delete('license-scopes/{id}', [LicenseScopeController::class, 'destroy'])->name('admin.license-scopes.destroy');
            Route::post('license-scopes/{id}/toggle', [LicenseScopeController::class, 'toggle'])->name('admin.license-scopes.toggle');

            // 试用管理（超管专属 CRUD + 吊销）
            Route::get('trials', [TrialController::class, 'index'])->name('admin.trials.index');
            Route::get('trials/{id}', [TrialController::class, 'show'])->name('admin.trials.show');
            Route::post('trials', [TrialController::class, 'store'])->name('admin.trials.store');
            Route::match(['put', 'patch'], 'trials/{id}', [TrialController::class, 'update'])->name('admin.trials.update');
            Route::delete('trials/{id}', [TrialController::class, 'destroy'])->name('admin.trials.destroy');
            Route::post('trials/{id}/revoke', [TrialController::class, 'revoke'])->name('admin.trials.revoke');

            // 转让与续期（超管专属：转让记录 + 执行转让/续期）
            Route::get('transfers', [TransferController::class, 'index'])->name('admin.transfers.index');
            Route::post('transfers/transfer', [TransferController::class, 'transfer'])->name('admin.transfers.transfer');
            Route::post('transfers/renew', [TransferController::class, 'renew'])->name('admin.transfers.renew');
        });
    });
});
