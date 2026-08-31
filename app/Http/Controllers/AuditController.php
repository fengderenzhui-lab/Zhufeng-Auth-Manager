<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AuditQueryRequest;
use App\Models\AuditLog;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * 审计日志（仅超级管理员）。
 */
class AuditController extends Controller
{
    public function index(AuditQueryRequest $request): JsonResponse
    {
        $query = AuditLog::query()->orderByDesc('created_at');

        if ($request->filled('action')) {
            $query->where('action', $request->string('action')->toString());
        }

        if ($request->filled('actor_type')) {
            $query->where('actor_type', $request->string('actor_type')->toString());
        }

        if ($request->filled('resource_type')) {
            $query->where('resource_type', $request->string('resource_type')->toString());
        }

        $query->between(
            $request->input('from') !== null ? (string) $request->input('from') : null,
            $request->input('to') !== null ? (string) $request->input('to') : null,
        );

        $page = $query->paginate($request->integer('per_page', 20));

        return ApiResponse::ok($page->items(), 'ok', [
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
        ]);
    }

    public function actions(): JsonResponse
    {
        $actions = AuditLog::query()
            ->selectRaw('action, count(*) as total')
            ->groupBy('action')
            ->orderByDesc('total')
            ->limit(30)
            ->get();

        return ApiResponse::ok($actions);
    }
}
