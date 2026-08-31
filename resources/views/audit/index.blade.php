@extends('layouts.app', ['title' => '审计日志'])

@section('content')
<div class="card">
    <div class="card-head"><span class="card-title">审计日志</span></div>
    <div class="card-body">
        <div class="toolbar">
            <div class="form-group"><label>操作类型 action</label><input class="input" id="fAction" placeholder="如 license_generated"></div>
            <div class="form-group"><label>主体类型</label>
                <select class="select" id="fActor"><option value="">全部</option><option value="admin">管理员</option><option value="client">客户端</option><option value="system">系统</option></select>
            </div>
            <div class="form-group"><label>起始时间</label><input class="input" type="datetime-local" id="fFrom"></div>
            <div class="form-group"><label>结束时间</label><input class="input" type="datetime-local" id="fTo"></div>
            <button class="btn" type="button" id="searchBtn">查询</button>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>ID</th><th>动作</th><th>主体</th><th>对象</th><th>IP</th><th>时间</th><th>上下文</th></tr></thead>
                <tbody id="auditBody"><tr><td colspan="7" class="loading">加载中…</td></tr></tbody>
            </table>
        </div>
        <div id="pagerBox"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/audit.js') }}"></script>
@endpush
