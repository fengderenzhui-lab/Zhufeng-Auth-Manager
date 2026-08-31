<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * 统一 API 响应信封。
 *
 * 基础结构：
 * {
 *   "success": bool,
 *   "code": int,
 *   "message": string,
 *   "data": mixed,
 *   "meta": {...} | null
 * }
 *
 * 客户端 SDK 端点（activate/heartbeat/verify/deactivate）在 data 之上追加
 * "signature"（Ed25519）与 "signature_algorithm"="ed25519"，供客户端验签。
 */
final class ApiResponse
{
    public static function ok(
        mixed $data = null,
        string $message = 'ok',
        ?array $meta = null,
        bool $sign = false,
    ): JsonResponse {
        $payload = [
            'success' => true,
            'code' => 0,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'server_time' => now()->getTimestamp(),
        ];

        if ($sign) {
            $payload['signature'] = app(\App\Services\Ed25519Service::class)->signData(
                app(\App\Services\Ed25519Service::class)->prepareSignable($payload['data'])
            );
            $payload['signature_algorithm'] = 'ed25519';
        }

        return response()->json($payload);
    }

    public static function fail(
        string $message = '请求失败',
        int $httpStatus = 400,
        int $code = 1,
        mixed $data = null,
        ?array $meta = null,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'code' => $code,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
            'server_time' => now()->getTimestamp(),
        ], $httpStatus);
    }

    /**
     * 客户端对接端点（/api/licensing/v1）成功信封：{success:true, data:{...}}
     * 对齐 masterix21/laravel-licensing-client 响应契约。
     */
    public static function okV1(mixed $data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * 客户端对接端点失败信封：{success:false, error:{code, message}}
     * 错误码须与客户端异常映射严格对齐（403 FINGERPRINT_MISMATCH / 409 FINGERPRINT_CONFLICT、
     * OFFLINE_TOKEN_DISABLED / 410 / 423 CANCELLED_LICENSE、licenseSuspended / 429 / 404 / 422）。
     */
    public static function failV1(string $code, string $message, int $httpStatus = 400): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $httpStatus);
    }
}
