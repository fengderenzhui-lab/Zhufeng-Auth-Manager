@extends('layouts.app', ['title' => '个人中心'])

@section('content')
<div class="card" style="max-width:640px;">
    <div class="card-head">
        <span class="card-title">个人中心</span>
        <div style="display:flex;gap:10px;">
            <a class="btn btn-ghost btn-sm" id="goPwd" href="#">修改密码</a>
        </div>
    </div>
    <div class="card-body">
        <div class="profile-avatar" id="profileAvatar">?</div>
        <table class="table profile-table">
            <tbody>
                <tr><th>姓名</th><td id="pName">-</td></tr>
                <tr><th>邮箱</th><td id="pEmail">-</td></tr>
                <tr><th>角色</th><td id="pRole">-</td></tr>
                <tr><th>最近登录时间</th><td id="pLastLogin">-</td></tr>
                <tr><th>最近登录 IP</th><td id="pLastIp">-</td></tr>
                <tr><th>注册时间</th><td id="pCreated">-</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/profile.js') }}"></script>
@endpush
