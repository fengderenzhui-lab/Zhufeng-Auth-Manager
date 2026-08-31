<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserUpdateRequest;
use App\Models\ApiToken;
use App\Models\User;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 账号管理（仅超级管理员）。不能删除自己；不允许删除最后一个超级管理员。
 */
class UserController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'is_active', 'last_login_at', 'created_at'])
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::ok($users->items(), 'ok', [
            'total' => $users->total(),
            'per_page' => $users->perPage(),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
        ]);
    }

    public function store(UserStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['is_active'] = true;
        $data['must_change_password'] = true;

        $user = User::query()->create($data);

        $this->audit->adminAction(
            $request->attributes->get('auth_user'),
            AuditAction::USER_CREATED,
            resourceType: 'user',
            resourceId: (string) $user->id,
            request: $request,
        );

        return ApiResponse::ok(['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role], '创建成功');
    }

    public function update(int $id, UserUpdateRequest $request): JsonResponse
    {
        $user = User::find($id);
        if ($user === null) {
            return ApiResponse::fail('账号不存在。', 404, 1501);
        }

        $data = $request->validated();

        // 不允许降级/停用最后一个超级管理员
        if (($user->isSuperAdmin() && isset($data['role']) && $data['role'] !== 'super_admin')
            || ($user->isSuperAdmin() && array_key_exists('is_active', $data) && ! $data['is_active'])) {
            $superCount = User::query()->where('role', 'super_admin')->whereNull('deleted_at')->count();
            if ($superCount <= 1) {
                return ApiResponse::fail('系统必须保留至少一个超级管理员。', 422, 1502);
            }
        }

        $user->update($data);

        // 密码变更后吊销其全部活跃 Token（强制重新登录）
        if (isset($data['password'])) {
            ApiToken::query()->where('user_id', $user->id)->update(['revoked_at' => now()]);
            // 等保 H-02：管理员重置他人密码后，要求该账号首次登录强制改密
            $user->forceFill(['must_change_password' => true])->save();
        }

        $this->audit->adminAction(
            $request->attributes->get('auth_user'),
            AuditAction::USER_UPDATED,
            resourceType: 'user',
            resourceId: (string) $user->id,
            request: $request,
        );

        return ApiResponse::ok(null, '更新成功');
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        /** @var User $me */
        $me = $request->attributes->get('auth_user');

        if ($me->id === $id) {
            return ApiResponse::fail('不能删除当前登录账号。', 422, 1503);
        }

        $user = User::find($id);
        if ($user === null) {
            return ApiResponse::fail('账号不存在。', 404, 1501);
        }

        if ($user->isSuperAdmin()) {
            $superCount = User::query()->where('role', 'super_admin')->whereNull('deleted_at')->count();
            if ($superCount <= 1) {
                return ApiResponse::fail('系统必须保留至少一个超级管理员。', 422, 1502);
            }
        }

        $user->delete();
        ApiToken::query()->where('user_id', $user->id)->update(['revoked_at' => now()]);

        $this->audit->adminAction(
            $me,
            AuditAction::USER_DELETED,
            resourceType: 'user',
            resourceId: (string) $user->id,
            request: $request,
        );

        return ApiResponse::ok(null, '已删除');
    }

    /**
     * 当前登录管理员：查看自己的 HMAC 签名密钥（仅本人可见）。
     */
    public function hmacSecret(Request $request): JsonResponse
    {
        /** @var User $me */
        $me = $request->attributes->get('auth_user');

        return ApiResponse::ok([
            'hmac_secret' => $me->hmacSecret(),
            'created_at' => $me->created_at?->toIso8601String(),
            'hint' => '该密钥用于管理端 API 的 X-Signature 签名，请妥善保管，不要提交到版本库。',
        ]);
    }

    /**
     * 当前登录管理员：轮换自己的 HMAC 签名密钥（旧密钥立即失效，需重新获取）。
     */
    public function rotateHmacSecret(Request $request): JsonResponse
    {
        /** @var User $me */
        $me = $request->attributes->get('auth_user');

        $secret = $me->rotateHmacSecret();

        $this->audit->adminAction(
            $me,
            AuditAction::HMAC_SECRET_ROTATED,
            resourceType: 'user',
            resourceId: (string) $me->id,
            request: $request,
        );

        return ApiResponse::ok([
            'hmac_secret' => $secret,
            'hint' => '旧密钥已失效，请立即更新你的请求签名配置。',
        ], '已轮换');
    }
}
