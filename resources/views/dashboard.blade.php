@extends('layouts.app', ['title' => '数据看板'])

@section('content')
{{-- 顶部统计卡（雷池风格，数据来自 /api/v1/admin/stats） --}}
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-label">授权总量</div>
        <div class="stat-value olive" id="statTotal">-</div>
        <div class="stat-foot">全部产品累计签发</div>
    </div>
    <div class="stat-card orange">
        <div class="stat-label">已激活授权</div>
        <div class="stat-value orange" id="statActive">-</div>
        <div class="stat-foot">当前有效状态</div>
    </div>
    <div class="stat-card red">
        <div class="stat-label">到期授权</div>
        <div class="stat-value red" id="statExpired">-</div>
        <div class="stat-foot">已过期（即将到期未纳入统计）</div>
    </div>
    <div class="stat-card purple">
        <div class="stat-label">设备与心跳</div>
        <div class="stat-value purple" id="statDevices">-</div>
        <div class="stat-foot">绑定设备总数 / 今日心跳 <span id="statHeartbeats">-</span> 次</div>
    </div>
</div>

{{-- 图表区（Chart.js 本地 vendor，双主题联动重绘） --}}
<div class="chart-grid">
    <div class="card">
        <div class="card-head"><span class="card-title">授权状态分布</span></div>
        <div class="card-body">
            <div class="chart-box"><canvas id="statusChart"></canvas></div>
        </div>
    </div>
    <div class="card">
        <div class="card-head"><span class="card-title">产品授权分布</span></div>
        <div class="card-body">
            <div class="chart-box"><canvas id="productChart"></canvas></div>
        </div>
    </div>
</div>

{{-- 最近动态 --}}
<div class="card">
    <div class="card-head">
        <span class="card-title">最近审计动态</span>
        <button class="btn btn-ghost btn-sm" type="button" onclick="location.href=ZF.adminBase+'/audit'">查看全部</button>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>动作</th><th>操作者</th><th>对象</th><th>IP</th><th>时间</th></tr>
                </thead>
                <tbody id="recentAuditBody"><tr><td colspan="6" class="loading">加载中…</td></tr></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/chartjs/chart.umd.min.js') }}"></script>
<script src="{{ asset('js/pages/dashboard.js') }}"></script>
@endpush
