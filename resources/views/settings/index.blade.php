@extends('layouts.app', ['title' => '系统设置'])

@php
    $adminPath = (string) config('license.admin.path', 'admin');
    $cryptoStatus = [
        'Argon2id 密码哈希' => [
            'on'  => config('hashing.driver') === 'argon2id',
            'desc'=> 'config/hashing.php 驱动（memory=65536, threads=4, time=4）',
        ],
        'AES-256-GCM 敏感字段加密' => [
            'on'  => (bool) config('license.aes.encrypt_key'),
            'desc'=> 'ZF_APP_ENCRYPT_KEY 已配置（32 字节 Base64）',
        ],
        'Ed25519 服务端密钥' => [
            'on'  => (bool) config('license.ed25519.private_key') && (bool) config('license.ed25519.public_key'),
            'desc'=> 'LICENSING_ED25519_PRIVATE_KEY / PUBLIC_KEY 已配置',
        ],
        '管理端 HMAC 签名要求' => [
            'on'  => (bool) config('license.admin_security.require_signature'),
            'desc'=> 'LICENSING_ADMIN_SIGNATURE_REQUIRED（登录端点除外）',
        ],
        '强制 HTTPS (TLS)' => [
            'on'  => (bool) config('license.tls.force_https'),
            'desc'=> 'APP_FORCE_HTTPS',
        ],
        'HSTS 头' => [
            'on'  => (bool) config('license.tls.hsts'),
            'desc'=> 'LICENSING_HSTS',
        ],
    ];
@endphp

@section('content')
<div class="card">
    <div class="card-head"><span class="card-title">后台访问路径</span></div>
    <div class="card-body">
        <div class="detail-grid">
            <div class="detail-item">
                <div class="k">当前后台前缀</div>
                <div class="v mono">/{{ $adminPath }}</div>
            </div>
            <div class="detail-item">
                <div class="k">配置项</div>
                <div class="v mono">ZF_ADMIN_PATH（.env）</div>
            </div>
        </div>
        <div class="sec-note">
            修改后台路径：编辑项目根目录 <code>.env</code> 中 <code>ZF_ADMIN_PATH=你的新前缀</code>（如
            <code>zf-8k2x9q</code>），执行 <code>php artisan config:clear</code> 后重启 PHP-FPM / 服务；
            页面与 API 前缀（/api/v1/{新前缀}/*）将同步生效，旧路径立即失效。
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <span class="card-title">管理端 HMAC 签名密钥</span>
        <button class="btn btn-orange btn-sm" type="button" id="rotateHmacBtn">轮换密钥</button>
    </div>
    <div class="card-body">
        <div class="form-row">
            <label class="form-label">当前密钥（每个管理员独立，点击「显示」查看）</label>
            <div class="key-box masked" id="hmacSecretBox">••••••••••••••••••••••••••••••••</div>
        </div>
        <div class="form-row">
            <button class="btn btn-ghost btn-sm" type="button" id="showHmacBtn">显示密钥</button>
        </div>
        <div class="sec-note">
            所有管理端请求需携带 <code>X-Signature</code>（HMAC-SHA256）。登录后由后端下发、
            前端保存在当前浏览器会话（sessionStorage，等保 H-01：不落 localStorage
            <code>zf_hmac_secret</code>），关闭标签页即清除。轮换后旧签名立即失效，
            浏览器会自动使用新密钥继续请求；若其他端正在使用旧密钥请同步更新。
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head"><span class="card-title">运行参数（可编辑，settings 表优先，.env / config 兜底）</span></div>
    <div class="card-body">
        <div class="sec-note">
            保存后写入 settings 表并立即生效（无需重启，请求内缓存自动失效）；未配置时回落
            <code>config/license.php</code> / <code>.env</code> 默认值，行为与现状一致。敏感项（密钥、
            WAF endpoint/api_key、可信头名等）不在可写白名单内，请到 <code>.env</code> 修改。
        </div>

        <div class="form-row">
            <label class="form-label">心跳上报周期（秒）</label>
            <input class="input" type="number" min="10" step="1"
                   data-key="license.heartbeat.interval_seconds" data-type="int"
                   data-desc="客户端心跳上报间隔（LICENSING_HEARTBEAT_INTERVAL_SECONDS）"
                   data-default="{{ (int) config('license.heartbeat.interval_seconds', 60) }}">
            <div class="form-hint">客户端按该周期上报心跳；修改后下次上报载荷中的 heartbeat_interval 即更新。</div>
        </div>

        <div class="form-row">
            <label class="form-label">心跳离线超时（秒）</label>
            <input class="input" type="number" min="30" step="1"
                   data-key="license.heartbeat.timeout_seconds" data-type="int"
                   data-desc="心跳离线判定阈值（LICENSING_HEARTBEAT_TIMEOUT_SECONDS）"
                   data-default="{{ (int) config('license.heartbeat.timeout_seconds', 300) }}">
            <div class="form-hint">强制在线模式下，last_heartbeat_at 距今超过该阈值即判定离线并失效（verify 实时判定 + license:expire 兜底）。</div>
        </div>

        <div class="form-row">
            <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input class="checkbox" type="checkbox"
                       data-key="license.heartbeat.enforce_online" data-type="bool"
                       data-desc="心跳强制在线开关（LICENSING_ENFORCE_ONLINE）"
                       data-default="{{ config('license.heartbeat.enforce_online', true) ? '1' : '0' }}">
                心跳强制在线（关闭后允许离线宽限，仅按有效期判定）
            </label>
        </div>

        <div class="form-row">
            <label class="form-label">审计日志保留天数</label>
            <input class="input" type="number" min="1" step="1"
                   data-key="license.audit.retention_days" data-type="int"
                   data-desc="审计日志保留天数（LICENSING_AUDIT_RETENTION_DAYS）"
                   data-default="{{ (int) config('license.audit.retention_days', 365) }}">
            <div class="form-hint">每日 license:clean 按该值清理超期审计日志（audit-days=0 自动取此值；未配置回落 config，默认 365 天）。</div>
        </div>

        <div class="form-row">
            <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input class="checkbox" type="checkbox"
                       data-key="license.safeline.enabled" data-type="bool"
                       data-desc="雷池人机验证开关（LICENSING_SAFELINE_ENABLED）"
                       data-default="{{ (bool) config('license.safeline.enabled', false) ? '1' : '0' }}">
                雷池（SafeLine）人机验证开关
            </label>
            <div class="form-hint">开启后，/api/licensing/v1 业务端点与 public-key 将校验雷池注入的可信头
                <code>X-SafeLine-Checked</code>（头名可用 LICENSING_SAFELINE_TRUSTED_HEADER 配置）；缺失或不匹配返回
                403 <code>SAFELINE_BLOCKED</code>。health / admin / SDK 端点不受影响。需配合雷池 WAF 接入并放行上述端点。</div>
        </div>

        <div class="form-row" style="margin-top:6px;">
            <button class="btn btn-primary btn-sm" type="button" id="saveRuntimeBtn">保存运行参数</button>
            <span class="form-hint" style="margin-left:10px;" id="runtimeSaveState"></span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head"><span class="card-title">加密与安全配置状态（只读）</span></div>
    <div class="card-body">
        <div class="sec-grid">
            @foreach ($cryptoStatus as $name => $st)
                <div class="sec-item">
                    <div class="k">{{ $name }}</div>
                    <div class="v {{ $st['on'] ? 'ok' : 'warn' }}">{{ $st['on'] ? '已启用' : '未启用 / 未配置' }}</div>
                    <div class="form-hint">{{ $st['desc'] }}</div>
                </div>
            @endforeach
        </div>
        <div class="form-hint" style="margin-top:12px;">以上状态由服务端 config 渲染，仅作展示；修改请在 .env / config 中进行。</div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/settings.js') }}"></script>
@endpush
