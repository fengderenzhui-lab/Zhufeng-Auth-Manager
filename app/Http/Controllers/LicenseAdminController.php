<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\LicenseStatus;
use App\Http\Requests\GenerateLicenseRequest;
use App\Http\Requests\LicenseQueryRequest;
use App\Http\Requests\RenewLicenseRequest;
use App\Models\License;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CustomerNgramService;
use App\Services\LicenseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 授权码管理（普通管理员可用基础管理，批量/拉黑操作需超级管理员）。
 */
class LicenseAdminController extends Controller
{
    public function __construct(
        private readonly LicenseService $licenses,
        private readonly AuditService $audit,
        private readonly CustomerNgramService $ngrams,
    ) {
    }

    public function index(LicenseQueryRequest $request): JsonResponse
    {
        $query = License::query()->with(['product:id,name,slug']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('product_id')) {
            $query->where('product_id', (int) $request->input('product_id'));
        }

        if ($request->filled('customer')) {
            // V1.30：customer 已 AES 加密存储，无法 LIKE 模糊检索；
            // 通过 n-gram 盲索引（2-gram+3-gram，HMAC-SHA256 加盐）实现安全模糊匹配，
            // 检索结果仍经模型访问器解密返回明文客户名。
            $ids = $this->ngrams->matchingLicenseIds($request->string('customer')->toString());
            if ($ids === null) {
                // 关键词过短（<2 字符）无法拆分 n-gram：退化为 sha256 精确匹配
                $query->where('customer_sha256', License::sha256Of($request->string('customer')->toString()));
            } elseif ($ids === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn('id', array_map('intval', $ids));
            }
        }

        if ($request->filled('keyword')) {
            // 关键词按完整授权码 HMAC 精确匹配（不做模糊明文检索，保护密钥熵）
            $hash = app(LicenseService::class)->findByKey($request->string('keyword')->toString());
            if ($hash !== null) {
                $query->whereKey($hash->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $page = $query->orderByDesc('id')->paginate($request->integer('per_page', 20));

        return ApiResponse::ok($page->items(), 'ok', [
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $id = (int) $id;
        $license = License::with(['product:id,name,slug', 'devices'])->find($id);

        if ($license === null) {
            return ApiResponse::fail('授权码不存在。', 404, 1301);
        }

        return ApiResponse::ok($this->present($license));
    }

    public function devices(string $id): JsonResponse
    {
        $id = (int) $id;
        $license = License::find($id);

        if ($license === null) {
            return ApiResponse::fail('授权码不存在。', 404, 1301);
        }

        return ApiResponse::ok($license->devices()->orderByDesc('id')->get()->map(fn ($d) => [
            'id' => $d->id,
            'device_name' => $d->device_name,
            'is_active' => $d->is_active,
            'last_ip' => $d->last_ip,
            'first_seen_at' => $d->first_seen_at?->toIso8601String(),
            'last_seen_at' => $d->last_seen_at?->toIso8601String(),
        ])->values());
    }

    public function generate(GenerateLicenseRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->attributes->get('auth_user');

        $product = Product::find((int) $request->validated('product_id'));
        if ($product === null) {
            return ApiResponse::fail('产品不存在。', 404, 1302);
        }

        try {
            $result = $this->licenses->generate(
                $product,
                (int) $request->validated('count'),
                $request->validated('expires_at'),
                (int) $request->validated('max_devices'),
                $request->validated('customer'),
                (array) $request->validated('meta', []),
                $user,
                $request,
            );
        } catch (RuntimeException $e) {
            return ApiResponse::fail($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : 422, 1303);
        }

        return ApiResponse::ok($result, '生成成功（明文授权码仅本次返回）');
    }

    public function revoke(string $id, Request $request): JsonResponse
    {
        $id = (int) $id;
        $license = License::find($id);
        if ($license === null) {
            return ApiResponse::fail('授权码不存在。', 404, 1301);
        }

        try {
            $this->licenses->revoke($license, $request->attributes->get('auth_user'), $request);
        } catch (RuntimeException $e) {
            return ApiResponse::fail($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : 422, 1304);
        }

        return ApiResponse::ok(null, '已吊销');
    }

    public function restore(string $id, Request $request): JsonResponse
    {
        $id = (int) $id;
        $license = License::find($id);
        if ($license === null) {
            return ApiResponse::fail('授权码不存在。', 404, 1301);
        }

        try {
            $this->licenses->restore($license, $request->attributes->get('auth_user'), $request);
        } catch (RuntimeException $e) {
            return ApiResponse::fail($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : 422, 1305);
        }

        return ApiResponse::ok(null, '已恢复');
    }

    public function blacklist(string $id, Request $request): JsonResponse
    {
        $id = (int) $id;
        $license = License::find($id);
        if ($license === null) {
            return ApiResponse::fail('授权码不存在。', 404, 1301);
        }

        try {
            $this->licenses->blacklist($license, $request->attributes->get('auth_user'), $request);
        } catch (RuntimeException $e) {
            return ApiResponse::fail($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : 422, 1306);
        }

        return ApiResponse::ok(null, '已拉黑');
    }

    public function renew(string $id, RenewLicenseRequest $request): JsonResponse
    {
        $id = (int) $id;
        $license = License::find($id);
        if ($license === null) {
            return ApiResponse::fail('授权码不存在。', 404, 1301);
        }

        try {
            $this->licenses->renew($license, $request->validated('expires_at'), $request->attributes->get('auth_user'), $request);
        } catch (RuntimeException $e) {
            return ApiResponse::fail($e->getMessage(), $e->getCode() > 0 ? $e->getCode() : 422, 1307);
        }

        return ApiResponse::ok(null, '续期成功');
    }

    /**
     * 批量吊销（超级管理员）。
     */
    public function batchRevoke(Request $request): JsonResponse
    {
        $ids = $request->input('ids');
        if (! is_array($ids) || count($ids) < 1 || count($ids) > 200) {
            return ApiResponse::fail('ids 需为 1-200 个授权码 ID 的数组。', 422, 1308);
        }

        $user = $request->attributes->get('auth_user');
        $count = 0;

        foreach (License::whereIn('id', array_map('intval', $ids))->get() as $license) {
            if ($license->status === LicenseStatus::Blacklisted) {
                continue;
            }
            $this->licenses->revoke($license, $user, $request);
            $count++;
        }

        $this->audit->adminAction($user, AuditAction::LICENSE_REVOKED, resourceType: 'license', context: ['batch' => true, 'count' => $count], request: $request);

        return ApiResponse::ok(['revoked' => $count], '批量吊销完成');
    }

    private function present(License $license): array
    {
        return [
            'id' => $license->id,
            'product' => $license->product?->slug,
            'status' => $license->status->value,
            'status_label' => $license->status->label(),
            'customer' => $license->customer,
            'max_devices' => $license->max_devices,
            'meta' => $license->meta,
            'expires_at' => $license->expires_at?->toIso8601String(),
            'activated_at' => $license->activated_at?->toIso8601String(),
            'revoked_at' => $license->revoked_at?->toIso8601String(),
            'last_heartbeat_at' => $license->last_heartbeat_at?->toIso8601String(),
            'devices' => $license->devices->map(fn ($d) => [
                'id' => $d->id,
                'device_name' => $d->device_name,
                'is_active' => $d->is_active,
                'last_ip' => $d->last_ip,
            ])->values(),
        ];
    }
}
