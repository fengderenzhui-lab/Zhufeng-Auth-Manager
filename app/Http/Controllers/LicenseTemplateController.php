<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\LicenseTemplateRequest;
use App\Models\LicenseTemplate;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 授权模板管理（仅超级管理员可写入）。
 */
class LicenseTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = LicenseTemplate::query()->with('scopes:id,name,slug')->orderByDesc('id');

        $keyword = trim((string) $request->input('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                    ->orWhere('description', 'like', "%{$keyword}%");
            });
        }

        if ($request->has('is_active') && $request->input('is_active') !== '') {
            $query->where('is_active', (bool) $request->boolean('is_active'));
        }

        $page = $query->paginate($request->integer('per_page', 15));

        $items = collect($page->items())->map(fn (LicenseTemplate $tpl) => [
            'id' => $tpl->id,
            'name' => $tpl->name,
            'description' => $tpl->description,
            'duration_days' => $tpl->duration_days,
            'max_devices' => $tpl->max_devices,
            'features' => $tpl->features ?? [],
            'scopes' => $tpl->scopes->map(fn ($scope) => [
                'id' => $scope->id,
                'name' => $scope->name,
                'slug' => $scope->slug,
            ]),
            'is_active' => (bool) $tpl->is_active,
            'created_at' => $tpl->created_at?->toIso8601String(),
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
        $template = LicenseTemplate::query()->findOrFail($id);

        return ApiResponse::ok($this->present($template));
    }

    public function store(LicenseTemplateRequest $request): JsonResponse
    {
        $user = $this->authUser($request);

        $template = LicenseTemplate::query()->create([
            'name' => $request->string('name')->toString(),
            'description' => $request->string('description')->trim()->toString() ?: null,
            'duration_days' => $request->input('duration_days') !== null && $request->input('duration_days') !== ''
                ? (int) $request->input('duration_days') : null,
            'max_devices' => (int) $request->integer('max_devices', 1),
            'features' => $this->parseFeatures($request->input('features')),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->syncScopes($template, $request->input('scope_ids'));

        $this->audit($user, AuditAction::TEMPLATE_CREATED, $template, $request);

        return ApiResponse::ok($this->present($template), '创建成功');
    }

    public function update(LicenseTemplateRequest $request, int $id): JsonResponse
    {
        $template = LicenseTemplate::query()->findOrFail($id);
        $user = $this->authUser($request);

        $template->update([
            'name' => $request->string('name')->toString(),
            'description' => $request->string('description')->trim()->toString() ?: null,
            'duration_days' => $request->input('duration_days') !== null && $request->input('duration_days') !== ''
                ? (int) $request->input('duration_days') : null,
            'max_devices' => (int) $request->integer('max_devices', 1),
            'features' => $this->parseFeatures($request->input('features')),
            'is_active' => $request->boolean('is_active', (bool) $template->is_active),
        ]);

        $this->syncScopes($template, $request->input('scope_ids'));

        $this->audit($user, AuditAction::TEMPLATE_UPDATED, $template, $request);

        return ApiResponse::ok($this->present($template), '已保存');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $template = LicenseTemplate::query()->findOrFail($id);
        $user = $this->authUser($request);

        $template->scopes()->detach();
        $template->delete();

        $this->audit($user, AuditAction::TEMPLATE_DELETED, $template, $request);

        return ApiResponse::ok(null, '已删除');
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $template = LicenseTemplate::query()->findOrFail($id);
        $user = $this->authUser($request);

        $template->update(['is_active' => ! $template->is_active]);

        $this->audit($user, AuditAction::TEMPLATE_TOGGLED, $template, $request, [
            'is_active' => (bool) $template->is_active,
        ]);

        return ApiResponse::ok(['id' => $template->id, 'is_active' => (bool) $template->is_active], $template->is_active ? '已启用' : '已停用');
    }

    private function authUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->attributes->get('auth_user');

        return $user;
    }

    private function syncScopes(LicenseTemplate $template, mixed $scopeIds): void
    {
        $ids = is_array($scopeIds) ? array_values(array_filter(array_map('intval', $scopeIds))) : [];
        $template->scopes()->sync($ids);
    }

    private function parseFeatures(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value === [] ? null : $value;
        }
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(LicenseTemplate $template): array
    {
        $template->load('scopes:id,name,slug');

        return [
            'id' => $template->id,
            'name' => $template->name,
            'description' => $template->description,
            'duration_days' => $template->duration_days,
            'max_devices' => $template->max_devices,
            'features' => $template->features ?? [],
            'scopes' => $template->scopes->map(fn ($scope) => [
                'id' => $scope->id,
                'name' => $scope->name,
                'slug' => $scope->slug,
            ]),
            'scope_ids' => $template->scopes->pluck('id')->all(),
            'is_active' => (bool) $template->is_active,
            'created_at' => $template->created_at?->toIso8601String(),
        ];
    }

    private function audit(User $user, string $action, LicenseTemplate $template, Request $request, array $extra = []): void
    {
        app(\App\Services\AuditService::class)->adminAction(
            $user,
            $action,
            resourceType: 'license_template',
            resourceId: (string) $template->id,
            context: ['name' => $template->name, ...$extra],
            request: $request,
        );
    }
}
