<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\AuthService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(private readonly AuthService $auth)
    {
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            [$user, $token, $apiToken, $hmacSecret] = $this->auth->login(
                (string) $request->validated('email'),
                (string) $request->validated('password'),
                $request
            );
        } catch (RuntimeException $e) {
            return ApiResponse::fail($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : 401, 1002);
        }

        return ApiResponse::ok([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('license.auth.token_ttl_minutes', 720) * 60,
            'hmac_secret' => $hmacSecret,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'must_change_password' => $user->must_change_password,
            ],
        ], '登录成功');
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->attributes->get('auth_user');
        /** @var ApiToken|null $token */
        $token = $request->attributes->get('auth_token');

        if ($user !== null && $token !== null) {
            $this->auth->logout($token, $user, $request);
        }

        return ApiResponse::ok(null, '已退出登录');
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('auth_user');

        return ApiResponse::ok([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'must_change_password' => $user->must_change_password,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
        ]);
    }

    /**
     * 当前登录管理员修改本人密码（首登强制改密闭环，等保 H-02 修复）。
     * 成功后：清除 must_change_password、吊销除当前外全部活跃 Token、保留当前会话。
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('auth_user');
        /** @var ApiToken|null $token */
        $token = $request->attributes->get('auth_token');

        if (! Hash::check((string) $request->validated('current_password'), $user->password)) {
            return ApiResponse::fail('当前密码不正确。', 422, 1601);
        }

        $user->password = (string) $request->validated('password');
        $user->must_change_password = false;
        $user->save();

        // 吊销其它会话（保留当前 Token），降低密码泄露面
        if ($token !== null) {
            ApiToken::query()
                ->where('user_id', $user->id)
                ->where('id', '!=', $token->id)
                ->update(['revoked_at' => now()]);
        }

        $this->auth->auditAdminPasswordChange($user, $request);

        return ApiResponse::ok([
            'must_change_password' => false,
            'hint' => '密码已更新，其他会话已全部下线。',
        ], '密码修改成功');
    }
}
