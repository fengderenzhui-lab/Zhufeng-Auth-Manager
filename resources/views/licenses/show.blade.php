@extends('layouts.app', ['title' => '授权码详情'])

@section('content')
<div class="card">
    <div class="card-head">
        <span class="card-title">授权码 #<span id="licId">-</span></span>
        <div style="display:flex;gap:10px;flex-wrap:wrap;">
            <button class="btn btn-sm" type="button" id="renewBtn">续期</button>
            <button class="btn btn-danger btn-sm" type="button" id="revokeBtn">吊销</button>
            <button class="btn btn-sm" type="button" id="restoreBtn">恢复</button>
            <button class="btn btn-danger btn-sm" type="button" id="blacklistBtn">拉黑</button>
            <button class="btn btn-ghost btn-sm" type="button" onclick="location.href='/licenses'">返回列表</button>
        </div>
    </div>
    <div class="card-body">
        <div class="loading" id="loadingBox">加载中…</div>
        <div id="detailBody" style="display:none;">
            <div class="detail-grid" id="detailGrid"></div>
            <div style="margin-top:20px;">
                <div class="card-title" style="margin-bottom:10px;">绑定设备</div>
                <div class="table-wrap">
                    <table class="table">
                        <thead><tr><th>ID</th><th>设备名称</th><th>状态</th><th>最近 IP</th></tr></thead>
                        <tbody id="deviceBody"></tbody>
                    </table>
                </div>
                <p style="margin-top:8px;font-size:12px;color:var(--text-faint);">
                    说明：授权码明文按安全设计不落库、不在后台展示（仅生成时一次性返回）。"激活记录"对应上表绑定设备与审计日志。
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/licenses-show.js') }}"></script>
@endpush
