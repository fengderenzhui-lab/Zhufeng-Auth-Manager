@extends('layouts.app', ['title' => '转让与续期'])

@section('content')
<div class="card">
    <div class="card-head">
        <span class="card-title">转让与续期记录</span>
        <div style="display:flex;gap:10px;">
            <button class="btn btn-ghost btn-sm" type="button" id="transferBtn">转让授权</button>
            <button class="btn btn-primary btn-sm" type="button" id="renewBtn">续期授权</button>
        </div>
    </div>
    <div class="card-body">
        <div class="toolbar">
            <div class="form-group"><label>类型</label>
                <select class="select" id="fType">
                    <option value="">全部类型</option>
                    <option value="transfer">转让</option>
                    <option value="renew">续期</option>
                </select>
            </div>
            <div class="form-group"><label>授权码（完整）</label><input class="input" id="fKeyword" placeholder="粘贴完整授权码"></div>
            <button class="btn" type="button" id="searchBtn">查询</button>
            <button class="btn btn-ghost" type="button" id="resetBtn">重置</button>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>类型</th>
                        <th>授权码</th>
                        <th>产品</th>
                        <th>原客户</th>
                        <th>新客户</th>
                        <th>原到期时间</th>
                        <th>新到期时间</th>
                        <th>操作人</th>
                        <th>原因</th>
                        <th>创建时间</th>
                    </tr>
                </thead>
                <tbody id="transferBody"><tr><td colspan="11" class="loading">加载中…</td></tr></tbody>
            </table>
        </div>
        <div id="pagerBox"></div>
    </div>
</div>

{{-- 转让 Modal 内容 --}}
<div id="transferModal" style="display:none;">
    <form id="transferForm" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label">授权码 ID *</label>
            <input class="input" type="number" id="tLicenseId" min="1" placeholder="授权码列表中的 ID 数字" required>
        </div>
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label">新客户名称 *</label>
            <input class="input" type="text" id="tCustomer" maxlength="128" required>
        </div>
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label">原因（选填）</label>
            <input class="input" type="text" id="tReason" maxlength="512" placeholder="选填">
        </div>
    </form>
</div>

{{-- 续期 Modal 内容 --}}
<div id="renewModal" style="display:none;">
    <form id="renewForm" style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label">授权码 ID *</label>
            <input class="input" type="number" id="rLicenseId" min="1" placeholder="授权码列表中的 ID 数字" required>
        </div>
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label">新的有效期至 *</label>
            <input class="input" type="datetime-local" id="rExpires" required>
        </div>
        <div class="form-row" style="margin:0;grid-column:1 / -1;">
            <label class="form-label">原因（选填）</label>
            <input class="input" type="text" id="rReason" maxlength="512" placeholder="选填">
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/transfers.js') }}"></script>
@endpush
