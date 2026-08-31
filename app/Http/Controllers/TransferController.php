<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\LicenseStatus;
use App\Http\Requests\RenewSubmitRequest;
use App\Http\Requests\TransferSubmitRequest;
use App\Models\License;
use App\Models\Transfer;
use App\Models\User;
use App\Services\AuditService;
use App\Services\LicenseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 转让与续期记录：基于 licenses 表业务变更（转让改 customer、续期改 expires_at）。
 */
class TransferController extends Controller
{
    public function __construct(
        private readonly LicenseService $licenseService,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Transfer::query()->with(['license:id,key_prefix,product_id,customer', 'license.product:id,name,slug', 'operator:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('license_id')) {
            $query->where('license_id', (int) $request->input('license_id'));
        }

        if ($request->filled('keyword')) {
            $keyword = trim((string) $request->input('keyword'));
            if ($keyword !== '') {
                $generator = app(\App\Services\LicenseKeyGenerator::class);
                $normalized = $generator->normalize($keyword);
                $query->where(function ($q) use ($keyword, $normalized, $generator) {
                    $q->where('reason', 'like', "%{$keyword}%")
                        ->orWhere('customer_after_sha256', hash('sha256', $keyword))
                        ->orWhereHas('operator', fn ($u) => $u->where('name', 'like', "%{$keyword}%"));
                    if ($normalized !== '') {
                        $q->orWhereHas('license', fn ($l) => $l->where('key_hash', $generator->hashKey($normalized)));
                    }
                });
            }
        }

        $page = $query->paginate($request->integer('per_page', 15));

        $items = collect($page->items())->map(fn (Transfer $transfer) => [
            'id' => $transfer->id,
            'type' => $transfer->type,
            'type_label' => $transfer->type === 'transfer' ? '转让' : '续期',
            'license_id' => $transfer->license_id,
            'license' => $transfer->license ? [
                'id' => $transfer->license->id,
                'key_prefix' => $transfer->license->key_prefix,
                'product' => $transfer->license->product ? [
                    'id' => $transfer->license->product->id,
                    'name' => $transfer->license->product->name,
                    'slug' => $transfer->license->product->slug,
                ] : null,
            ] : null,
            'customer_before' => $transfer->customer_before,
            'customer_after' => $transfer->customer_after,
            'original_expires_at' => $transfer->original_expires_at?->toIso8601String(),
            'new_expires_at' => $transfer->new_expires_at?->toIso8601String(),
            'operator' => $transfer->operator ? [
                'id' => $transfer->operator->id,
                'name' => $transfer->operator->name,
            ] : null,
            'reason' => $transfer->reason,
            'created_at' => $transfer->created_at?->toIso8601String(),
        ]);

        return ApiResponse::ok($items, 'ok', [
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    /**
     * 转让：修改授权码客户（customer），记录转让快照。
     */
    public function transfer(TransferSubmitRequest $request): JsonResponse
    {
        $user = $this->authUser($request);

        $license = License::query()->findOrFail((int) $request->input('license_id'));

        $this->assertChangeable($license);

        $customerBefore = $license->customer;

        $license->forceFill([
            'customer' => $request->string('new_customer')->trim()->toString(),
        ])->save();

        Transfer::query()->create([
            'type' => 'transfer',
            'license_id' => $license->id,
            'customer_before' => $customerBefore,
            'customer_after' => $request->string('new_customer')->trim()->toString(),
            'original_expires_at' => $license->expires_at,
            'new_expires_at' => $license->expires_at,
            'operator_id' => $user->id,
            'reason' => $request->string('reason')->trim()->toString() ?: null,
        ]);

        $this->audit->adminAction(
            $user,
            AuditAction::LICENSE_TRANSFERRED,
            resourceType: 'license',
            resourceId: (string) $license->id,
            context: [
                'from' => $customerBefore,
                'to' => $request->string('new_customer')->trim()->toString(),
                'reason' => $request->string('reason')->trim()->toString() ?: null,
            ],
            request: $request,
        );

        return ApiResponse::ok([
            'id' => $license->id,
            'customer' => $license->customer,
        ], '转让成功');
    }

    /**
     * 续期：修改授权码到期时间（复用 LicenseService::renew 状态机与审计）。
     */
    public function renew(RenewSubmitRequest $request): JsonResponse
    {
        $user = $this->authUser($request);

        $license = License::query()->findOrFail((int) $request->input('license_id'));

        $originalExpires = $license->expires_at;

        $this->licenseService->renew(
            $license,
            $request->input('new_expires_at') !== null && $request->input('new_expires_at') !== ''
                ? (string) $request->input('new_expires_at')
                : null,
            $user,
            $request,
        );

        Transfer::query()->create([
            'type' => 'renew',
            'license_id' => $license->id,
            'customer_before' => $license->customer,
            'customer_after' => $license->customer,
            'original_expires_at' => $originalExpires,
            'new_expires_at' => $license->expires_at,
            'operator_id' => $user->id,
            'reason' => $request->string('reason')->trim()->toString() ?: null,
        ]);

        return ApiResponse::ok([
            'id' => $license->id,
            'expires_at' => $license->expires_at?->toIso8601String(),
            'status' => $license->status->value,
        ], '续期成功');
    }

    private function authUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->attributes->get('auth_user');

        return $user;
    }

    private function assertChangeable(License $license): void
    {
        if ($license->status === LicenseStatus::Blacklisted || $license->status === LicenseStatus::Revoked) {
            throw new RuntimeException('拉黑/吊销状态不可转让，请先恢复。', 422);
        }
    }
}
