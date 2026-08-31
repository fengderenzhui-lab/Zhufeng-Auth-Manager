<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Services\AuditService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 产品管理（普通管理员可查看，写入需超级管理员）。
 */
class ProductController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Product::query()->withCount('licenses');

        if ($request->boolean('only_active')) {
            $query->where('is_active', true);
        }

        $products = $query->orderByDesc('id')->paginate($request->integer('per_page', 20));

        return ApiResponse::ok($products->items(), 'ok', [
            'total' => $products->total(),
            'per_page' => $products->perPage(),
            'current_page' => $products->currentPage(),
            'last_page' => $products->lastPage(),
        ]);
    }

    public function store(ProductRequest $request): JsonResponse
    {
        $product = Product::query()->create($request->validated());

        $this->audit->adminAction(
            $request->attributes->get('auth_user'),
            AuditAction::PRODUCT_CREATED,
            resourceType: 'product',
            resourceId: (string) $product->id,
            request: $request,
        );

        return ApiResponse::ok($product, '创建成功');
    }

    public function update(int $id, ProductRequest $request): JsonResponse
    {
        $product = Product::find($id);
        if ($product === null) {
            return ApiResponse::fail('产品不存在。', 404, 1401);
        }

        $product->update($request->validated());

        $this->audit->adminAction(
            $request->attributes->get('auth_user'),
            AuditAction::PRODUCT_UPDATED,
            resourceType: 'product',
            resourceId: (string) $product->id,
            request: $request,
        );

        return ApiResponse::ok($product, '更新成功');
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $product = Product::find($id);
        if ($product === null) {
            return ApiResponse::fail('产品不存在。', 404, 1401);
        }

        if ($product->licenses()->count() > 0) {
            return ApiResponse::fail('该产品下存在授权码，不可删除。', 422, 1402);
        }

        $product->delete();

        $this->audit->adminAction(
            $request->attributes->get('auth_user'),
            AuditAction::PRODUCT_DELETED,
            resourceType: 'product',
            resourceId: (string) $id,
            request: $request,
        );

        return ApiResponse::ok(null, '已删除');
    }
}
