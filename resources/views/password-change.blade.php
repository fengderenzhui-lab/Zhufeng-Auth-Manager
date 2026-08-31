@extends('layouts.auth', ['title' => '修改密码'])

@section('content')
<div class="auth-card">
    <div class="auth-brand">
        <div class="auth-logo">逐风</div>
        <h1>修改初始密码</h1>
        <p class="auth-sub">出于安全要求，首次登录（或密码被管理员重置）后必须先修改密码才能继续使用。</p>
    </div>

    <form id="passwordChangeForm" autocomplete="off">
        <div class="field">
            <label for="current_password">当前密码</label>
            <input type="password" id="current_password" name="current_password" class="input" placeholder="输入当前密码" required autofocus>
        </div>

        <div class="field">
            <label for="password">新密码</label>
            <input type="password" id="password" name="password" class="input" placeholder="≥12 位，含大写/小写/数字/符号至少 3 类" required>
            <div class="field-hint" id="passwordHint"></div>
        </div>

        <div class="field">
            <label for="password_confirmation">确认新密码</label>
            <input type="password" id="password_confirmation" name="password_confirmation" class="input" placeholder="再次输入新密码" required>
        </div>

        <button type="submit" class="btn btn-primary btn-block" id="submitBtn">确认修改</button>
    </form>

    <div class="auth-foot">
        <button type="button" class="btn btn-ghost" id="logoutBtn">退出登录</button>
    </div>
</div>
@endsection

@push('scripts')
<script src="{{ asset('js/pages/password-change.js') }}"></script>
@endpush
