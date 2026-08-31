@extends('layouts.app', ['title' => '客户端密钥（公钥录入）'])

@section('content')
<div class="card">
    <div class="card-head">
        <span class="card-title">录入 Ed25519 公钥</span>
        <span class="badge badge-purple">超级管理员</span>
    </div>
    <div class="card-body">
        <form id="keyForm" autocomplete="off">
            <div class="form-row">
                <label class="form-label" for="keyName">名称 / 用途</label>
                <input class="input" type="text" id="keyName" name="name" placeholder="如：XX 公司客户端" maxlength="80" required>
            </div>
            <div class="form-row">
                <label class="form-label" for="keyPub">Ed25519 公钥（Base64）</label>
                <textarea class="textarea" id="keyPub" name="public_key" placeholder="粘贴客户端生成的 Base64 公钥，32 字节（Ed25519），支持多行换行自动清理" style="min-height:110px;" required></textarea>
                <div class="form-hint" id="keyHint">格式：Base64 编码的 32 字节 Ed25519 公钥；保存前将自动去除空白并校验。</div>
            </div>
            <div class="form-row">
                <span class="form-hint">导入后默认启用；如需删除，请在下表操作。</span>
            </div>
            <div class="form-row">
                <button class="btn btn-primary" type="submit" id="keySubmit">保存公钥</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <span class="card-title">已录入公钥</span>
        <button class="btn btn-ghost btn-sm" type="button" onclick="ZF.api('/admin/public-keys').then(d=>{window.location.reload()})">刷新</button>
    </div>
    <div class="card-body">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr><th>ID</th><th>名称</th><th>指纹前缀</th><th>状态</th><th>创建时间</th><th>操作</th></tr>
                </thead>
                <tbody id="keyListBody"><tr><td colspan="6" class="loading">加载中…</td></tr></tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/keys.js') }}"></script>
@endpush
