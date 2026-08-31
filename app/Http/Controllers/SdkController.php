<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\SdkLicenseRequest;
use App\Services\DeviceFingerprintService;
use App\Services\HeartbeatService;
use App\Services\LicenseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * 公开 SDK 端点。
 *
 * 安全约束：
 *  - 错误归一化：授权码不存在 / 已过期 / 已吊销 / 已拉黑 统一折叠为
 *    "授权码无效或不可用"，绝不暴露 key 是否存在（防枚举）。
 *  - 所有端点输出到 data 的载荷均使用 Ed25519 签名，客户端持公钥验签。
 *  - 防重放与基础限流由路由中间件保障。
 */
class SdkController extends Controller
{
    public function __construct(
        private readonly LicenseService $licenses,
        private readonly DeviceFingerprintService $fingerprint,
        private readonly HeartbeatService $heartbeat,
    ) {
    }

    public function activate(SdkLicenseRequest $request): JsonResponse
    {
        $license = $this->licenses->findByKey($request->validated('key'));

        if ($license === null) {
            return ApiResponse::fail('授权码无效或不可用。', 400, 1201);
        }

        try {
            $signals = $this->fingerprint->decodeSignals($request->validated('signals'));
            $result = $this->licenses->activate($license, $signals, $request->validated('device_name'), $request);
        } catch (RuntimeException $e) {
            return ApiResponse::fail($this->normalizeError($e->getMessage()), $e->getCode() > 0 ? $e->getCode() : 400, 1202);
        }

        return ApiResponse::ok($result, '激活成功', null, sign: true);
    }

    public function heartbeat(SdkLicenseRequest $request): JsonResponse
    {
        $license = $this->licenses->findByKey($request->validated('key'));

        if ($license === null) {
            return ApiResponse::fail('授权码无效或不可用。', 400, 1201);
        }

        try {
            $signals = $this->fingerprint->decodeSignals($request->validated('signals'));
            $result = $this->heartbeat->beat($license, $signals, $request);
        } catch (RuntimeException $e) {
            return ApiResponse::fail('心跳处理失败。', 400, 1203);
        }

        // 心跳失败（非激活状态等）同样返回签名载荷
        $payload = array_merge($result, $this->heartbeat->buildPayload($license));

        return ApiResponse::ok($payload, $result['valid'] ? '心跳正常' : '心跳未通过', null, sign: true);
    }

    public function verify(SdkLicenseRequest $request): JsonResponse
    {
        $license = $this->licenses->findByKey($request->validated('key'));

        if ($license === null) {
            return ApiResponse::fail('授权码无效或不可用。', 400, 1201);
        }

        try {
            $signals = $this->fingerprint->decodeSignals($request->validated('signals'));
            $result = $this->licenses->verify($license, $signals, $request);
        } catch (RuntimeException $e) {
            return ApiResponse::fail('验证失败。', 400, 1204);
        }

        return ApiResponse::ok($result, $result['valid'] ? '验证通过' : '验证未通过', null, sign: true);
    }

    public function deactivate(SdkLicenseRequest $request): JsonResponse
    {
        $license = $this->licenses->findByKey($request->validated('key'));

        if ($license === null) {
            return ApiResponse::fail('授权码无效或不可用。', 400, 1201);
        }

        try {
            $signals = $this->fingerprint->decodeSignals($request->validated('signals'));
            $result = $this->licenses->deactivate($license, $signals, $request);
        } catch (RuntimeException $e) {
            return ApiResponse::fail('解绑失败。', 400, 1205);
        }

        return ApiResponse::ok($result, '处理完成', null, sign: true);
    }

    /**
     * 归一化业务错误信息（仅对合法 key 后的业务约束返回原因）。
     */
    private function normalizeError(string $message): string
    {
        $known = [
            '设备已被其他授权码绑定' => '设备已被其他授权码绑定，无法重复激活。',
            '授权码已达设备绑定上限' => '授权码已达设备绑定上限。',
            '授权码已被拉黑' => '授权码无效或不可用。',
            '授权码已被吊销' => '授权码无效或不可用。',
            '授权码已过期' => '授权码无效或不可用。',
            '设备信号不足' => '设备信号不足，无法生成可靠指纹。',
        ];

        foreach ($known as $needle => $replacement) {
            if (str_contains($message, $needle)) {
                return $replacement;
            }
        }

        return '授权码无效或不可用。';
    }
}
