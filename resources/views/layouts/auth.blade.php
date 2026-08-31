@php $adminPath = (string) config('license.admin.path', 'admin'); @endphp
<!DOCTYPE html>
<html lang="zh-CN" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="zf-admin-path" content="{{ $adminPath }}">
    <title>{{ config('license.platform.name', '逐风授权码管理平台') }} - @yield('title', $title ?? '认证')</title>
    {{-- 防 FOUC 主题初始化：外链独立脚本（等保 M-04，支持 CSP script-src 'self'） --}}
    <script src="{{ asset('js/theme-init.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body data-page="password-change" class="login-page">
    <button class="theme-toggle" type="button" id="themeToggle" aria-label="切换主题" title="切换主题">
        <svg class="ico-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
        <svg class="ico-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
    </button>
    <div class="login-wrap">
        @yield('content')
    </div>

    <div id="toast-root"></div>

    <script src="{{ asset('js/app.js') }}"></script>
    <script src="{{ asset('js/theme.js') }}"></script>
    @stack('scripts')
</body>
</html>
