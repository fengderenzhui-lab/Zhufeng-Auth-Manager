@extends('layouts.app', ['title' => '用户管理'])

@section('content')
<div class="card">
    <div class="card-head">
        <span class="card-title">管理员账号</span>
        <button class="btn btn-primary btn-sm" type="button" id="addBtn">新增管理员</button>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>ID</th><th>名称</th><th>邮箱</th><th>角色</th><th>状态</th><th>最近登录</th><th>创建时间</th><th>操作</th></tr></thead>
                <tbody id="userBody"><tr><td colspan="8" class="loading">加载中…</td></tr></tbody>
            </table>
        </div>
        <div id="pagerBox"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/users.js') }}"></script>
@endpush
