@extends('layouts.app', ['title' => '设备管理'])

@section('content')
<div class="card">
    <div class="card-head"><span class="card-title">按授权码查询绑定设备</span></div>
    <div class="card-body">
        <div class="toolbar">
            <div class="form-group"><label>授权码 ID</label><input class="input" id="licId" type="number" min="1" placeholder="输入授权码 ID，如 12"></div>
            <button class="btn btn-primary" type="button" id="queryBtn">查询</button>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>设备 ID</th><th>设备名称</th><th>状态</th><th>最近 IP</th><th>首次绑定</th><th>最后活跃</th></tr></thead>
                <tbody id="deviceBody"><tr><td colspan="6" style="text-align:center;color:var(--text-faint);padding:30px;">输入授权码 ID 后查询</td></tr></tbody>
            </table>
        </div>
        <p style="margin-top:10px;font-size:12px;color:var(--text-faint);">
            说明：后端未提供全局设备列表接口，仅支持按授权码查看绑定设备（GET /admin/licenses/{id}/devices）。
        </p>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/devices.js') }}"></script>
@endpush
