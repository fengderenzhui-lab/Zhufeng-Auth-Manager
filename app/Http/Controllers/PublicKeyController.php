<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\PublicKey;
use App\Services\AuditService;
use App\Services\Ed25519Service;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Ed25519 公钥管理。
 *
 * - show（公开端点）：下发当前签名公钥，客户端验签用（仅公钥，绝不下发私钥）。
 * - index / store / detail / destroy（管理端，super_admin）：公钥可视化录入、
 *   列表、详情、删除；导入时校验 base64 格式与 Ed25519 长度（32 字节）。
 * 录入的公钥用于客户端 SDK 凭证（PASETO v4.public / Ed25519 载荷）验签。
 */
class PublicKeyController extends Controller
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    /**
     * 公开端点：下发当前 Ed25519 公钥。
     */
    public function show(Ed25519Service $ed25519): JsonResponse
    {
        try {
            $publicKey = $ed25519->publicKey();
        } catch (RuntimeException $e) {
            return ApiResponse::fail('公钥尚未配置，请先运行 php artisan license:keys。', 503, 1901);
        }

        return ApiResponse::ok([
            'algorithm' => 'ed25519',
            'public_key' => base64_encode($publicKey),
            'signature_algorithm' => 'ed25519',
        ]);
    }

    /**
     * 管理端：公钥列表。
     */
    public function index(Request $request): JsonResponse
    {
        $keys = PublicKey::query()
            ->select(['id', 'name', 'fingerprint', 'is_active', 'created_by', 'created_at'])
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 20));

        return ApiResponse::ok($keys->items(), 'ok', [
            'total' => $keys->total(),
            'per_page' => $keys->perPage(),
            'current_page' => $keys->currentPage(),
            'last_page' => $keys->lastPage(),
        ]);
    }

    /**
     * 管理端：粘贴导入 base64 Ed25519 公钥。
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'public_key' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:128'],
        ]);

        $raw = trim($data['public_key']);
        $raw = preg_replace('/\s+/', '', $raw) ?? $raw;

        // 格式校验：base64 解码 + Ed25519 公钥长度（32 字节）
        $bin = base64_decode($raw, true);
        if ($bin === false || strlen($bin) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return ApiResponse::fail('公钥格式非法：应为 base64 编码的 Ed25519 公钥（32 字节）。', 422, 1902);
        }

        $fingerprint = PublicKey::fingerprintOf($raw);
        if (PublicKey::query()->where('fingerprint', $fingerprint)->exists()) {
            return ApiResponse::fail('该公钥已存在，请勿重复导入。', 422, 1903);
        }

        /** @var \App\Models\User $operator */
        $operator = $request->attributes->get('auth_user');

        $key = PublicKey::query()->create([
            'name' => isset($data['name']) ? mb_substr($data['name'], 0, 128) : null,
            'public_key' => $raw,
            'fingerprint' => $fingerprint,
            'is_active' => true,
            'created_by' => $operator->id,
        ]);

        $this->audit->adminAction(
            $operator,
            AuditAction::PUBLIC_KEY_IMPORTED,
            resourceType: 'public_key',
            resourceId: (string) $key->id,
            context: ['fingerprint' => substr($fingerprint, 0, 16)],
            request: $request,
        );

        return ApiResponse::ok([
            'id' => $key->id,
            'name' => $key->name,
            'fingerprint' => $fingerprint,
            'is_active' => true,
        ], '导入成功');
    }

    /**
     * 管理端：公钥详情。
     */
    public function detail(int $id): JsonResponse
    {
        $key = PublicKey::find($id);
        if ($key === null) {
            return ApiResponse::fail('公钥不存在。', 404, 1904);
        }

        return ApiResponse::ok([
            'id' => $key->id,
            'name' => $key->name,
            'public_key' => $key->public_key,
            'fingerprint' => $key->fingerprint,
            'is_active' => $key->is_active,
            'created_by' => $key->created_by,
            'created_at' => $key->created_at?->toIso8601String(),
        ]);
    }

    /**
     * 管理端：删除公钥（软删除）。
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $key = PublicKey::find($id);
        if ($key === null) {
            return ApiResponse::fail('公钥不存在。', 404, 1904);
        }

        $key->delete();

        /** @var \App\Models\User $operator */
        $operator = $request->attributes->get('auth_user');

        $this->audit->adminAction(
            $operator,
            AuditAction::PUBLIC_KEY_DELETED,
            resourceType: 'public_key',
            resourceId: (string) $key->id,
            context: ['fingerprint' => substr((string) $key->fingerprint, 0, 16)],
            request: $request,
        );

        return ApiResponse::ok(null, '已删除');
    }
}
