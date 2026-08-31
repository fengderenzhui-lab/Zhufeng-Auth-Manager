@extends('layouts.app', ['title' => '心跳监控'])

@section('content')
<div class="card">
    <div class="card-head">
        <span class="card-title">设备心跳监控</span>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-primary btn-sm" type="button" id="refreshBtn">手动刷新</button>
        </div>
    </div>
    <div class="card-body">
        <div class="toolbar">
            <div class="form-group"><label>状态</label>
                <select class="select" id="fStatus">
                    <option value="">全部状态</option>
                    <option value="active">有效</option>
                    <option value="expired">已过期</option>
                    <option value="revoked">已吊销</option>
                </select>
            </div>
            <div class="form-group"><label>关键词</label><input class="input" id="fKeyword" placeholder="授权码 / 产品 / IP"></div>
            <div class="form-group" style="flex-direction:row;align-items:center;gap:6px;">
                <input class="checkbox" type="checkbox" id="fTimeout"><label for="fTimeout">仅看超时设备</label>
            </div>
            <button class="btn" type="button" id="searchBtn">查询</button>
            <button class="btn btn-ghost" type="button" id="resetBtn">重置</button>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>授权码</th>
                        <th>产品</th>
                        <th>状态</th>
                        <th>设备数</th>
                        <th>最后心跳</th>
                        <th>IP</th>
                        <th>最近心跳负载</th>
                        <th>到期时间</th>
                    </tr>
                </thead>
                <tbody id="hbBody"><tr><td colspan="9" class="loading">加载中…</td></tr></tbody>
            </table>
        </div>
        <div id="pagerBox"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/heartbeats.js') }}"></script>
@endpush
