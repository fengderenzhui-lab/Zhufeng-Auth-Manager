@php $adminPath = (string) config('license.admin.path', 'admin'); @endphp
<!DOCTYPE html>
<html lang="zh-CN" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- 管理端路径前缀：供前端拼接页面链接与跳转登录 --}}
    <meta name="zf-admin-path" content="{{ $adminPath }}">
    <title>{{ config('license.platform.name', '逐风授权码管理平台') }} - @yield('title', $title ?? '管理后台')</title>
    {{-- 防 FOUC 主题初始化：外链独立脚本（等保 M-04，支持 CSP script-src 'self'） --}}
    <script src="{{ asset('js/theme-init.js') }}"></script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body data-page="app">
<div class="layout">
    @include('layouts.partials.sidebar')

    <div class="main">
        <header class="topbar">
            <div class="topbar-left">
                <button class="btn btn-ghost menu-toggle" type="button" id="menuToggle" aria-label="菜单" title="折叠/展开侧边栏">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
                </button>
                <span class="topbar-title">@yield('title', $title ?? '管理后台')</span>
            </div>
            <div class="topbar-right">
                <button class="theme-toggle" type="button" id="themeToggle" aria-label="切换主题" title="切换主题">
                    <svg class="ico-sun" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
                    <svg class="ico-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
                </button>

                {{-- 右上角用户下拉（修改密码 / 新增管理员[超管] / 退出登录） --}}
                <div class="user-dropdown" id="userDropdown">
                    <button class="user-trigger" type="button" id="userTrigger" aria-haspopup="true" aria-expanded="false">
                        <span class="avatar" id="topbar-avatar">?</span>
                        <span class="user-meta">
                            <span class="user-name" id="topbar-name">-</span>
                            <span class="user-role" id="topbar-role">管理员</span>
                        </span>
                        <svg class="user-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </button>
                    <div class="dropdown-panel" id="userPanel" role="menu">
                        <a class="dropdown-item" id="ddPassword" href="{{ url('/' . $adminPath . '/password-change') }}" role="menuitem">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <span>修改密码</span>
                        </a>
                        <a class="dropdown-item" id="ddAddUser" href="{{ url('/' . $adminPath . '/users') }}" role="menuitem" data-require="super">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
                            <span>新增管理员</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <button class="dropdown-item dropdown-danger" id="ddLogout" type="button" role="menuitem">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>
                            <span>退出登录</span>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main class="content">
            @yield('content')
        </main>
    </div>
</div>

{{-- Modal 容器 --}}
<div class="modal-mask" id="modalMask">
    <div class="modal" id="modalBox">
        <div class="modal-head">
            <span class="modal-title" id="modalTitle">提示</span>
            <button class="modal-close" type="button" id="modalClose">&times;</button>
        </div>
        <div class="modal-body" id="modalBody"></div>
        <div class="modal-foot" id="modalFoot"></div>
    </div>
</div>

{{-- Toast 容器 --}}
<div id="toast-root"></div>

<script src="{{ asset('js/app.js') }}"></script>
<script src="{{ asset('js/theme.js') }}"></script>
@stack('scripts')
</body>
</html>
