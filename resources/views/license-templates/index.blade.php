@extends('layouts.app', ['title' => '授权模板'])

@section('content')
<div class="card">
    <div class="card-head">
        <span class="card-title">授权模板</span>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-primary btn-sm" type="button" id="createBtn">新增模板</button>
        </div>
    </div>
    <div class="card-body">
        <div class="toolbar">
            <div class="form-group"><label>关键词</label><input class="input" id="fKeyword" placeholder="名称 / 说明"></div>
            <div class="form-group"><label>状态</label>
                <select class="select" id="fStatus">
                    <option value="">全部状态</option>
                    <option value="1">启用</option>
                    <option value="0">停用</option>
                </select>
            </div>
            <button class="btn" type="button" id="searchBtn">查询</button>
            <button class="btn btn-ghost" type="button" id="resetBtn">重置</button>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>名称</th>
                        <th>说明</th>
                        <th>时长（天）</th>
                        <th>设备上限</th>
                        <th>授权范围</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="tplBody"><tr><td colspan="8" class="loading">加载中…</td></tr></tbody>
            </table>
        </div>
        <div id="pagerBox"></div>
    </div>
</div>

{{-- 创建/编辑 Modal 内容 --}}
<div id="editModal" style="display:none;">
    <form id="editForm" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label">名称 *</label>
            <input class="input" type="text" id="eName" maxlength="128" required>
        </div>
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label">说明</label>
            <textarea class="input" id="eDesc" rows="2" maxlength="2000" placeholder="选填"></textarea>
        </div>
        <div class="form-row" style="margin:0;">
            <label class="form-label">授权时长（天，留空=永久）</label>
            <input class="input" type="number" id="eDuration" min="1" max="3650" placeholder="留空表示永久">
        </div>
        <div class="form-row" style="margin:0;">
            <label class="form-label">设备数上限 *</label>
            <input class="input" type="number" id="eMaxDev" min="1" max="1000" value="1" required>
        </div>
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label">功能范围 features（JSON，选填）</label>
            <input class="input" type="text" id="eFeatures" placeholder='如 {"pro":true,"max_users":5}'>
        </div>
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label">授权范围（可多选）</label>
            <div id="scopeChips" style="display:flex;flex-wrap:wrap;gap:8px;padding:6px 0;">
                <span class="badge badge-gray">加载中…</span>
            </div>
        </div>
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input class="checkbox" type="checkbox" id="eActive" checked> 启用该模板
            </label>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/license-templates.js') }}"></script>
@endpush
