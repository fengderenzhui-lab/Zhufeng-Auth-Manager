<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\LicenseScopeRequest;
use App\Models\LicenseScope;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 授权范围管理（仅超级管理员可写入）。
 */
class LicenseScopeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LicenseScope::query()->orderByDesc('id');

        $keyword = trim((string) $request->input('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('slug', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', (bool) $request->boolean('is_active'));
        }

        $page = $query->paginate($request->integer('per_page', 15));

        $items = collect($page->items())->map(fn (LicenseScope $scope) => [
            'id' => $scope->id,
            'name' => $scope->name,
            'slug' => $scope->slug,
            'description' => $scope->description,
            'is_active' => (bool) $scope->is_active,
            'template_count' => $scope->templates()->count(),
            'created_at' => $scope->created_at?->toIso8601String(),
        ]);

        return ApiResponse::ok($items, 'ok', [
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $scope = LicenseScope::query()->findOrFail($id);

        return ApiResponse::ok($this->present($scope));
    }

    public function store(LicenseScopeRequest $request): JsonResponse
    {
        $user = $this->authUser($request);

        $scope = LicenseScope::query()->create([
            'name' => $request->string('name')->toString(),
            'slug' => $request->string('slug')->toString(),
            'description' => $request->string('description')->trim()->toString() ?: null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->audit($user, AuditAction::SCOPE_CREATED, $scope, $request);

        return ApiResponse::ok($this->present($scope), '创建成功');
    }

    public function update(LicenseScopeRequest $request, int $id): JsonResponse
    {
        $scope = LicenseScope::query()->findOrFail($id);
        $user = $this->authUser($request);

        $scope->update([
            'name' => $request->string('name')->toString(),
            'slug' => $request->string('slug')->toString(),
            'description' => $request->string('description')->trim()->toString() ?: null,
            'is_active' => $request->boolean('is_active', (bool) $scope->is_active),
        ]);

        $this->audit($user, AuditAction::SCOPE_UPDATED, $scope, $request);

        return ApiResponse::ok($this->present($scope), '已保存');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $scope = LicenseScope::query()->findOrFail($id);
        $user = $this->authUser($request);

        $scope->templates()->detach();
        $scope->delete();

        $this->audit($user, AuditAction::SCOPE_DELETED, $scope, $request);

        return ApiResponse::ok(null, '已删除');
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $scope = LicenseScope::query()->findOrFail($id);
        $user = $this->authUser($request);

        $scope->update(['is_active' => ! $scope->is_active]);

        $this->audit($user, AuditAction::SCOPE_TOGGLED, $scope, $request, [
            'is_active' => (bool) $scope->is_active,
        ]);

        return ApiResponse::ok(['id' => $scope->id, 'is_active' => (bool) $scope->is_active], $scope->is_active ? '已启用' : '已停用');
    }

    private function authUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->attributes->get('auth_user');

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(LicenseScope $scope): array
    {
        return [
            'id' => $scope->id,
            'name' => $scope->name,
            'slug' => $scope->slug,
            'description' => $scope->description,
            'is_active' => (bool) $scope->is_active,
            'created_at' => $scope->created_at?->toIso8601String(),
        ];
    }

    private function audit(User $user, string $action, LicenseScope $scope, Request $request, array $extra = []): void
    {
        app(\App\Services\AuditService::class)->adminAction(
            $user,
            $action,
            resourceType: 'license_scope',
            resourceId: (string) $scope->id,
            context: ['name' => $scope->name, 'slug' => $scope->slug, ...$extra],
            request: $request,
        );
    }
}
