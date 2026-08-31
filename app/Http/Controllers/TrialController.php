<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\TrialRequest;
use App\Models\Product;
use App\Models\Trial;
use App\Models\User;
use App\Services\LicenseKeyGenerator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 试用授权管理（仅超级管理员可写入）。
 */
class TrialController extends Controller
{
    public function __construct(private readonly LicenseKeyGenerator $keyGenerator)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Trial::query()->with(['product:id,name,slug', 'creator:id,name'])->orderByDesc('id');

        $keyword = trim((string) $request->input('keyword', ''));
        if ($keyword !== '') {
            // 客户名称已加密：走 sha256 盲索引精确匹配 + 产品/掩码模糊
            $query->where(function ($q) use ($keyword) {
                $q->where('customer_sha256', Trial::sha256Of($keyword))
                    ->orWhere('trial_code_preview', 'like', "%{$keyword}%")
                    ->orWhereHas('product', fn ($p) => $p
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('slug', 'like', "%{$keyword}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->input('product_id'));
        }

        $page = $query->paginate($request->integer('per_page', 15));

        $items = collect($page->items())->map(fn (Trial $trial) => $this->present($trial));

        return ApiResponse::ok($items, 'ok', [
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $trial = Trial::query()->with(['product:id,name,slug', 'creator:id,name'])->findOrFail($id);

        return ApiResponse::ok($this->present($trial));
    }

    public function store(TrialRequest $request): JsonResponse
    {
        $user = $this->authUser($request);

        $product = Product::query()->findOrFail((int) $request->input('product_id'));

        $key = $this->keyGenerator->generate('ZT-');
        $keyHash = $this->keyGenerator->hashKey($key);

        if (Trial::query()->where('trial_code', $keyHash)->exists()) {
            return ApiResponse::fail('授权码冲突，请重试。', 422);
        }

        $startsAt = $request->input('starts_at') !== null && $request->input('starts_at') !== ''
            ? \Illuminate\Support\Carbon::parse($request->input('starts_at'))
            : now();

        $trial = Trial::query()->create([
            'product_id' => $product->id,
            'customer' => $request->string('customer')->trim()->toString() ?: null,
            'trial_code' => $keyHash,
            'trial_code_preview' => $this->previewOf($key),
            'trial_days' => (int) $request->integer('trial_days', 7),
            'starts_at' => $startsAt,
            'status' => $request->string('status')->toString() ?: 'pending',
            'remark' => $request->string('remark')->trim()->toString() ?: null,
            'created_by' => $user->id,
        ]);

        $this->audit($user, AuditAction::TRIAL_CREATED, $trial, $request, [
            'product' => $product->slug,
            'trial_days' => $trial->trial_days,
        ]);

        // 明文授权码仅本次返回一次（不落库）
        return ApiResponse::ok([
            ...$this->present($trial),
            'trial_code_plain' => $key,
        ], '试用授权已创建');
    }

    public function update(TrialRequest $request, int $id): JsonResponse
    {
        $trial = Trial::query()->findOrFail($id);
        $user = $this->authUser($request);

        $startsAt = $request->input('starts_at') !== null && $request->input('starts_at') !== ''
            ? \Illuminate\Support\Carbon::parse($request->input('starts_at'))
            : $trial->starts_at;

        $trial->update([
            'product_id' => (int) $request->input('product_id'),
            'customer' => $request->string('customer')->trim()->toString() ?: null,
            'trial_days' => (int) $request->integer('trial_days', 7),
            'starts_at' => $startsAt,
            'status' => $request->string('status')->toString() ?: $trial->status,
            'remark' => $request->string('remark')->trim()->toString() ?: null,
        ]);

        $this->audit($user, AuditAction::TRIAL_UPDATED, $trial, $request, [
            'status' => $trial->status,
        ]);

        return ApiResponse::ok($this->present($trial), '已保存');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $trial = Trial::query()->findOrFail($id);
        $user = $this->authUser($request);

        $trial->delete();

        $this->audit($user, AuditAction::TRIAL_DELETED, $trial, $request);

        return ApiResponse::ok(null, '已删除');
    }

    public function revoke(Request $request, int $id): JsonResponse
    {
        $trial = Trial::query()->findOrFail($id);
        $user = $this->authUser($request);

        if ($trial->status === 'revoked') {
            return ApiResponse::fail('该试用授权已处于吊销状态。', 422);
        }

        $trial->update(['status' => 'revoked']);

        $this->audit($user, AuditAction::TRIAL_REVOKED, $trial, $request);

        return ApiResponse::ok(['id' => $trial->id, 'status' => $trial->status], '已吊销');
    }

    private function authUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->attributes->get('auth_user');

        return $user;
    }

    private function previewOf(string $key): string
    {
        if (mb_strlen($key) <= 10) {
            return $key;
        }

        return mb_substr($key, 0, 4).'…'.mb_substr($key, -4);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Trial $trial): array
    {
        return [
            'id' => $trial->id,
            'product_id' => $trial->product_id,
            'product' => $trial->product ? [
                'id' => $trial->product->id,
                'name' => $trial->product->name,
                'slug' => $trial->product->slug,
            ] : null,
            'customer' => $trial->customer,
            'trial_code' => $trial->trial_code_preview,
            'trial_days' => $trial->trial_days,
            'starts_at' => $trial->starts_at?->toIso8601String(),
            'ends_at' => $trial->starts_at?->copy()->addDays($trial->trial_days)->toIso8601String(),
            'status' => $trial->status,
            'remark' => $trial->remark,
            'creator' => $trial->creator ? [
                'id' => $trial->creator->id,
                'name' => $trial->creator->name,
            ] : null,
            'created_at' => $trial->created_at?->toIso8601String(),
        ];
    }

    private function audit(User $user, string $action, Trial $trial, Request $request, array $extra = []): void
    {
        app(\App\Services\AuditService::class)->adminAction(
            $user,
            $action,
            resourceType: 'trial',
            resourceId: (string) $trial->id,
            context: ['code' => $trial->trial_code_preview, 'customer' => $trial->customer, ...$extra],
            request: $request,
        );
    }
}
