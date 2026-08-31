@extends('layouts.app', ['title' => '授权范围'])

@section('content')
<div class="card">
    <div class="card-head">
        <span class="card-title">授权范围</span>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-primary btn-sm" type="button" id="createBtn">新增范围</button>
        </div>
    </div>
    <div class="card-body">
        <div class="toolbar">
            <div class="form-group"><label>关键词</label><input class="input" id="fKeyword" placeholder="名称 / slug / 描述"></div>
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
                        <th>标识 slug</th>
                        <th>描述</th>
                        <th>被引用模板数</th>
                        <th>状态</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody id="scopeBody"><tr><td colspan="7" class="loading">加载中…</td></tr></tbody>
            </table>
        </div>
        <div id="pagerBox"></div>
    </div>
</div>

{{-- 创建/编辑 Modal 内容 --}}
<div id="editModal" style="display:none;">
    <form id="editForm" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-row" style="margin:0;">
            <label class="form-label">名称 *</label>
            <input class="input" type="text" id="eName" maxlength="128" required>
        </div>
        <div class="form-row" style="margin:0;">
            <label class="form-label">标识 slug *（唯一）</label>
            <input class="input" type="text" id="eSlug" maxlength="128" placeholder="如 full-access" required>
        </div>
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label">描述</label>
            <textarea class="input" id="eDesc" rows="2" maxlength="512" placeholder="选填"></textarea>
        </div>
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input class="checkbox" type="checkbox" id="eActive" checked> 启用该范围
            </label>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/license-scopes.js') }}"></script>
@endpush
