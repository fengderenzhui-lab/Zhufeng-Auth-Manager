@php
    // 内联 SVG 图标集（stroke 风格，颜色继承 currentColor；class 由使用方注入）
    $icons = [
        'shield' => '<svg class="brand-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'grid' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>',
        'box' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/></svg>',
        'key' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>',
        'monitor' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>',
        'lock' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
        'users' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'audit' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8M10 9H8"/></svg>',
        'settings' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M19.1 4.9 17 7M7 17l-2.1 2.1"/></svg>',
        'logout' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/></svg>',
        'template' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M16 13H8M16 17H8"/></svg>',
        'scope' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>',
        'trial' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h7l-1 8 10-12h-7z"/></svg>',
        'transfer' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 16V4M7 4 3 8M7 4l4 4"/><path d="M17 8v12m0 0 4-4m-4 4-4-4"/></svg>',
        'heartbeat' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>',
        'profile' => '<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/></svg>',
    ];
@endphp

@php
    $adminPath = (string) config('license.admin.path', 'admin');
    $navGroups = [
        ['title' => '概览', 'items' => [
            ['label' => '数据看板', 'route' => 'dashboard', 'icon' => 'grid'],
        ]],
        ['title' => '授权管理', 'items' => [
            ['label' => '产品管理', 'route' => 'products', 'icon' => 'box'],
            ['label' => '授权码管理', 'route' => 'licenses', 'icon' => 'key'],
            ['label' => '授权模板', 'route' => 'license-templates', 'icon' => 'template', 'require' => 'super'],
            ['label' => '授权范围', 'route' => 'license-scopes', 'icon' => 'scope', 'require' => 'super'],
            ['label' => '试用管理', 'route' => 'trials', 'icon' => 'trial', 'require' => 'super'],
            ['label' => '转让与续期', 'route' => 'transfers', 'icon' => 'transfer', 'require' => 'super'],
        ]],
        ['title' => '设备与客户端', 'items' => [
            ['label' => '设备管理', 'route' => 'devices', 'icon' => 'monitor'],
            ['label' => '客户端密钥', 'route' => 'keys', 'icon' => 'lock', 'require' => 'super'],
            ['label' => '心跳监控', 'route' => 'heartbeats', 'icon' => 'heartbeat'],
        ]],
        ['title' => '系统', 'items' => [
            ['label' => '管理员管理', 'route' => 'users', 'icon' => 'users', 'require' => 'super'],
            ['label' => '审计日志', 'route' => 'audit', 'icon' => 'audit'],
            ['label' => '系统设置', 'route' => 'settings', 'icon' => 'settings', 'require' => 'super'],
            ['label' => '个人中心', 'route' => 'profile', 'icon' => 'profile'],
        ]],
    ];
    $current = request()->path();
    $seg = explode('/', $current);
    $seg0 = $seg[0] === $adminPath ? ($seg[1] ?? '') : $seg[0];
@endphp

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <span class="brand-dot" aria-hidden="true">{!! $icons['shield'] !!}</span>
        <div class="brand-text">
            <span class="brand-name">逐风授权</span>
            <span class="brand-sub">授权码管理平台</span>
        </div>
    </div>

    <nav class="sidebar-nav" aria-label="主导航">
        @foreach ($navGroups as $group)
            <div class="nav-group-title">{{ $group['title'] }}</div>
            @foreach ($group['items'] as $item)
                @php
                    $url = url('/' . $adminPath . '/' . $item['route']);
                    $isActive = $seg0 === $item['route'];
                @endphp
                <a class="nav-item {{ $isActive ? 'active' : '' }}"
                   href="{{ $url }}"
                   data-require="{{ $item['require'] ?? '' }}"
                   title="{{ $item['label'] }}">
                    {!! $icons[$item['icon']] !!}
                    <span class="nav-label">{{ $item['label'] }}</span>
                </a>
            @endforeach
        @endforeach
    </nav>

    <div class="sidebar-foot">
        <button class="nav-item nav-logout" type="button" id="logoutBtn" title="退出登录">
            {!! $icons['logout'] !!}
            <span class="nav-label">退出登录</span>
        </button>
        <div class="sidebar-ver">授权码管理平台</div>
    </div>
</aside>
