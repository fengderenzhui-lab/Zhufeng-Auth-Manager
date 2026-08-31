@php
    // ZF-2026-008：品牌化 404 页（黑白企业主题，无敏感信息泄露）
    $adminPath = (string) config('license.admin.path', 'admin');
    $platform  = config('license.platform.name', '逐风授权码管理平台');
@endphp
<!DOCTYPE html>
<html lang="zh-CN" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $platform }} - 页面不存在</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body data-page="error">
<div class="error-page">
    <div class="error-card">
        <div class="error-code">404</div>
        <h1 class="error-title">页面不存在</h1>
        <p class="error-desc">您访问的页面不存在或已被移动，请检查地址后重试。</p>
        <div class="error-actions">
            <a class="btn btn-primary" href="{{ url('/' . $adminPath) }}">返回管理后台</a>
        </div>
    </div>
</div>
<style>
    .error-page{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--bg,#fafafa)}
    .error-card{max-width:420px;width:100%;text-align:center;padding:48px 32px;border:1px solid var(--border,#e5e5e5);background:var(--card,#ffffff);border-radius:12px;box-shadow:0 8px 32px rgba(0,0,0,.06)}
    .error-code{font-size:72px;font-weight:700;line-height:1;letter-spacing:.04em;color:var(--text,#111);font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
    .error-title{margin:20px 0 8px;font-size:20px;font-weight:600;color:var(--text,#111)}
    .error-desc{margin:0 0 28px;font-size:14px;color:var(--muted,#666);line-height:1.7}
    .error-actions .btn{min-width:160px}
    [data-theme="dark"] .error-page{background:var(--bg,#0d0d0d)}
    [data-theme="dark"] .error-card{background:var(--card,#161616);border-color:var(--border,#2a2a2a);box-shadow:0 8px 32px rgba(0,0,0,.5)}
    [data-theme="dark"] .error-code{color:var(--text,#f5f5f5)}
    [data-theme="dark"] .error-title{color:var(--text,#f5f5f5)}
    [data-theme="dark"] .error-desc{color:var(--muted,#999)}
</style>
</body>
</html>
