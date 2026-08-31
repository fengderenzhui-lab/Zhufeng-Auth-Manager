<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LicenseStatus;
use App\Http\Requests\HeartbeatQueryRequest;
use App\Models\License;
use App\Services\SettingsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * 心跳监控：以授权码为行聚合设备/授权码/状态/最后心跳/IP/UA，超时设备标红。
 */
class HeartbeatController extends Controller
{
    public function __construct(private readonly SettingsService $settings)
    {
    }

    public function index(HeartbeatQueryRequest $request): JsonResponse
    {
        // V1.32：超时阈值支持设置页 DB 覆盖（license.heartbeat.timeout_seconds），未配置回落 config。
        $timeoutSeconds = (int) $this->settings->get('license.heartbeat.timeout_seconds', 300);
        $now = Carbon::now();

        $query = License::query()
            ->with(['product:id,name,slug', 'latestHeartbeat:id,license_id,client_ip,client_ua,status,checked_at'])
            ->orderByRaw('last_heartbeat_at is null')
            ->orderByDesc('last_heartbeat_at');

        // 状态筛选（授权码状态）
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        $keyword = trim((string) $request->input('keyword', ''));
        if ($keyword !== '') {
            $query->where(function ($q) use ($keyword) {
                $q->where('key_prefix', 'like', "%{$keyword}%")
                    ->orWhereHas('product', fn ($p) => $p
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('slug', 'like', "%{$keyword}%"));
            });
        }

        // 超时筛选：仅对 active 授权码计算（未激活/已失效不视为异常心跳）
        if ($request->has('timeout') && $request->boolean('timeout')) {
            $query->where('status', LicenseStatus::Active)
                ->where(function ($q) use ($now, $timeoutSeconds) {
                    $q->whereNull('last_heartbeat_at')
                        ->orWhere('last_heartbeat_at', '<', $now->copy()->subSeconds($timeoutSeconds));
                });
        }

        $page = $query->paginate($request->integer('per_page', 15));

        $items = collect($page->items())->map(function (License $license) use ($now, $timeoutSeconds) {
            $heartbeat = $license->latestHeartbeat;
            $isActive = $license->status === LicenseStatus::Active;
            $timeout = $isActive && (
                $license->last_heartbeat_at === null
                || $license->last_heartbeat_at->getTimestamp() < $now->getTimestamp() - $timeoutSeconds
            );

            return [
                'license_id' => $license->id,
                'key' => $license->key_prefix !== null && $license->key_prefix !== ''
                    ? mb_strtoupper((string) $license->key_prefix).' #'.$license->id
                    : 'ZF #'.$license->id,
                'product' => $license->product ? [
                    'id' => $license->product->id,
                    'name' => $license->product->name,
                    'slug' => $license->product->slug,
                ] : null,
                'customer' => $license->customer,
                'status' => $license->status->value,
                'status_label' => $license->status->label(),
                'device_count' => $license->devices()->count(),
                'last_heartbeat_at' => $license->last_heartbeat_at?->toIso8601String(),
                'client_ip' => $heartbeat?->client_ip,
                'client_ua' => $heartbeat?->client_ua,
                'checked_at' => $heartbeat?->checked_at?->toIso8601String(),
                'timeout' => $timeout,
                'max_devices' => $license->max_devices,
                'expires_at' => $license->expires_at?->toIso8601String(),
            ];
        });

        return ApiResponse::ok($items, 'ok', [
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'timeout_seconds' => $timeoutSeconds,
        ]);
    }
}
