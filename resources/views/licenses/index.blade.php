@extends('layouts.app', ['title' => '授权码管理'])

@section('content')
<div class="card">
    <div class="card-head">
        <span class="card-title">授权码列表</span>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-danger btn-sm" type="button" id="batchRevokeBtn">批量吊销</button>
            <button class="btn btn-primary btn-sm" type="button" id="generateBtn">批量生成</button>
        </div>
    </div>
    <div class="card-body">
        <div class="toolbar">
            <div class="form-group"><label>关键词（完整授权码）</label><input class="input" id="fKeyword" placeholder="粘贴完整授权码"></div>
            <div class="form-group"><label>状态</label>
                <select class="select" id="fStatus">
                    <option value="">全部状态</option>
                    <option value="pending">待定</option>
                    <option value="active">有效</option>
                    <option value="expired">已过期</option>
                    <option value="revoked">已吊销</option>
                    <option value="blacklisted">已拉黑</option>
                </select>
            </div>
            <div class="form-group"><label>产品</label><select class="select" id="fProduct"><option value="">全部产品</option></select></div>
            <div class="form-group"><label>客户</label><input class="input" id="fCustomer" placeholder="客户名称（支持模糊搜索）" title="客户名称已加密存储，基于 n-gram 盲索引安全模糊检索"></div>
            <button class="btn" type="button" id="searchBtn">查询</button>
            <button class="btn btn-ghost" type="button" id="resetBtn">重置</button>
            <span class="spacer"></span>
            <span class="form-group" style="flex-direction:row;align-items:center;gap:6px;">
                <input class="checkbox" type="checkbox" id="selectAll"><label for="selectAll">全选</label>
            </span>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:34px;"></th>
                        <th>ID</th>
                        <th>产品</th>
                        <th>状态</th>
                        <th>客户</th>
                        <th>设备数</th>
                        <th>到期时间</th>
                        <th>最后心跳</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="licenseBody"><tr><td colspan="9" class="loading">加载中…</td></tr></tbody>
            </table>
        </div>
        <div id="pagerBox"></div>
    </div>
</div>

{{-- 批量生成 Modal（内容复制进通用弹窗，原容器隐藏） --}}
<div id="generateModal" style="display:none;">
    <form id="generateForm" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-row" style="margin:0;">
            <label class="form-label">产品 *</label>
            <select class="select" id="gProduct" required></select>
        </div>
        <div class="form-row" style="margin:0;">
            <label class="form-label">生成数量 *（1-500）</label>
            <input class="input" type="number" id="gCount" min="1" max="500" value="10" required>
        </div>
        <div class="form-row" style="margin:0;">
            <label class="form-label">有效期至（留空=永久）</label>
            <input class="input" type="datetime-local" id="gExpires">
        </div>
        <div class="form-row" style="margin:0;">
            <label class="form-label">最大绑定设备数 *</label>
            <input class="input" type="number" id="gMaxDev" min="1" max="100" value="1" required>
        </div>
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label">客户名称</label>
            <input class="input" type="text" id="gCustomer" placeholder="选填">
        </div>
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label">特性 meta.features（JSON，选填）</label>
            <input class="input" type="text" id="gMeta" placeholder='如 {"pro":true,"max_users":5}'>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/licenses.js') }}"></script>
@endpush
