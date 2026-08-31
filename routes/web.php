<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes（管理后台 UI 页面）
|--------------------------------------------------------------------------
| 说明：
|  - 所有页面为轻交互：页面 HTML 本身不含敏感数据，全部数据经
|    fetch 调用 /api/v1/admin/* 获取；Token 与 HMAC 签名密钥仅存
|    sessionStorage（会话级内存，关闭页面即失效；等保 H-01 修复，不落 localStorage）。
|  - 后台路径前缀由 ZF_ADMIN_PATH 控制（部署时随机生成，如 /zf-8k2x9q），
|    未匹配该前缀的页面一律 404，隐藏管理入口。
*/

$adminPath = (string) config('license.admin.path', 'admin');

// 根路径不暴露后台前缀：统一跳登录页（随机后台路径仅在登录后才能感知）
Route::get('/', fn () => redirect('/login'));

Route::get('/login', fn () => view('auth.login'))->name('login');

Route::middleware('web')->prefix($adminPath)->group(function () {
    Route::get('/dashboard', fn () => view('dashboard'))->name('dashboard');
    // 首登强制改密页（等保 H-02）
    Route::get('/password-change', fn () => view('password-change'))->name('password-change');
    Route::get('/licenses', fn () => view('licenses.index'))->name('licenses.index');
    Route::get('/licenses/{license}', fn (int $license) => view('licenses.show', ['license' => $license]))->name('licenses.show');
    Route::get('/products', fn () => view('products.index'))->name('products.index');
    Route::get('/devices', fn () => view('devices.index'))->name('devices.index');
    Route::get('/users', fn () => view('users.index'))->name('users.index');
    Route::get('/audit', fn () => view('audit.index'))->name('audit.index');
    Route::get('/settings', fn () => view('settings.index'))->name('settings.index');
    Route::get('/keys', fn () => view('keys.index'))->name('keys.index');
    // 6 个新功能模块页面
    Route::get('/license-templates', fn () => view('license-templates.index'))->name('license-templates.index');
    Route::get('/license-scopes', fn () => view('license-scopes.index'))->name('license-scopes.index');
    Route::get('/trials', fn () => view('trials.index'))->name('trials.index');
    Route::get('/transfers', fn () => view('transfers.index'))->name('transfers.index');
    Route::get('/heartbeats', fn () => view('heartbeats.index'))->name('heartbeats.index');
    Route::get('/profile', fn () => view('profile.index'))->name('profile.index');
});

// 兜底：除登录与后台前缀外，其余路径一律 404（隐藏后台入口）
Route::fallback(fn () => abort(404));
