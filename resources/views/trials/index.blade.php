@extends('layouts.app', ['title' => '试用管理'])

@section('content')
<div class="card">
    <div class="card-head">
        <span class="card-title">试用授权</span>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-primary btn-sm" type="button" id="createBtn">新建试用授权</button>
        </div>
    </div>
    <div class="card-body">
        <div class="toolbar">
            <div class="form-group"><label>关键词</label><input class="input" id="fKeyword" placeholder="客户 / 试用码 / 产品"></div>
            <div class="form-group"><label>状态</label>
                <select class="select" id="fStatus">
                    <option value="">全部状态</option>
                    <option value="pending">待激活</option>
                    <option value="active">使用中</option>
                    <option value="expired">已过期</option>
                    <option value="revoked">已吊销</option>
                </select>
            </div>
            <div class="form-group"><label>产品</label><select class="select" id="fProduct"><option value="">全部产品</option></select></div>
            <button class="btn" type="button" id="searchBtn">查询</button>
            <button class="btn btn-ghost" type="button" id="resetBtn">重置</button>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>产品</th>
                        <th>客户</th>
                        <th>试用码</th>
                        <th>天数</th>
                        <th>开始时间</th>
                        <th>结束时间</th>
                        <th>状态</th>
                        <th>操作人</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="trialBody"><tr><td colspan="10" class="loading">加载中…</td></tr></tbody>
            </table>
        </div>
        <div id="pagerBox"></div>
    </div>
</div>

{{-- 创建/编辑 Modal 内容 --}}
<div id="editModal" style="display:none;">
    <form id="editForm" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-row" style="margin:0;">
            <label class="form-label">产品 *</label>
            <select class="select" id="eProduct" required></select>
        </div>
        <div class="form-row" style="margin:0;">
            <label class="form-label">试用天数 *</label>
            <input class="input" type="number" id="eDays" min="1" max="365" value="7" required>
        </div>
        <div class="form-row" style="margin:0;">
            <label class="form-label">客户名称</label>
            <input class="input" type="text" id="eCustomer" maxlength="128" placeholder="选填">
        </div>
        <div class="form-row" style="margin:0;">
            <label class="form-label">开始时间</label>
            <input class="input" type="datetime-local" id="eStart">
        </div>
        <div class="form-row" style="margin:0;">
            <label class="form-label">状态</label>
            <select class="select" id="eStatus">
                <option value="pending">待激活</option>
                <option value="active">使用中</option>
                <option value="expired">已过期</option>
                <option value="revoked">已吊销</option>
            </select>
        </div>
        <div class="form-row" style="margin:0;">
            <label class="form-label">备注</label>
            <input class="input" type="text" id="eRemark" maxlength="512" placeholder="选填">
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/trials.js') }}"></script>
@endpush
