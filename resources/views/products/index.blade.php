@extends('layouts.app', ['title' => '产品管理'])

@section('content')
<div class="card">
    <div class="card-head">
        <span class="card-title">产品列表</span>
        <button class="btn btn-primary btn-sm" type="button" id="addBtn" style="display:none;">新增产品</button>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>ID</th><th>标识 slug</th><th>名称</th><th>状态</th><th>授权码数</th><th>操作</th></tr></thead>
                <tbody id="productBody"><tr><td colspan="6" class="loading">加载中…</td></tr></tbody>
            </table>
        </div>
        <div id="pagerBox"></div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/products.js') }}"></script>
@endpush
