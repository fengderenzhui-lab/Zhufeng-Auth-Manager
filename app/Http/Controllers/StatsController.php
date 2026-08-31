<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LicenseStatus;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Heartbeat;
use App\Models\License;
use App\Models\Product;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * 仪表盘统计（普通管理员可用）。
 */
class StatsController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $licenseByStatus = License::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $today = now()->startOfDay();

        return ApiResponse::ok([
            'products' => Product::query()->count(),
            'licenses' => array_sum($licenseByStatus),
            'licenses_by_status' => [
                LicenseStatus::Pending->value => (int) ($licenseByStatus[LicenseStatus::Pending->value] ?? 0),
                LicenseStatus::Active->value => (int) ($licenseByStatus[LicenseStatus::Active->value] ?? 0),
                LicenseStatus::Expired->value => (int) ($licenseByStatus[LicenseStatus::Expired->value] ?? 0),
                LicenseStatus::Revoked->value => (int) ($licenseByStatus[LicenseStatus::Revoked->value] ?? 0),
                LicenseStatus::Blacklisted->value => (int) ($licenseByStatus[LicenseStatus::Blacklisted->value] ?? 0),
            ],
            'devices' => Device::query()->count(),
            'heartbeats_today' => Heartbeat::query()->where('checked_at', '>=', $today)->count(),
            'audit_today' => AuditLog::query()->where('created_at', '>=', $today)->count(),
        ]);
    }

    public function recentAudits(): JsonResponse
    {
        return ApiResponse::ok(AuditLog::query()->orderByDesc('created_at')->limit(20)->get());
    }
}
