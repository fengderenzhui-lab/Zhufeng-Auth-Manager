<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 等保 M-02 修复：审计日志哈希链防篡改。
 *
 * 新增 prev_hash / hash 两列：
 *  - 写入时按 id 顺序计算 HMAC-SHA256 链（服务端密钥 LICENSING_AUDIT_HMAC_SECRET）。
 *  - 任一历史行被篡改，其后所有行的链式校验即失败，达到「可检测篡改」的防篡改目标。
 *
 * 迁移会按主键顺序回填存量数据；幂等（已存在列则跳过）。
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('audit_logs', 'hash')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->string('prev_hash', 64)->nullable()->after('context');
                $table->string('hash', 64)->nullable()->after('prev_hash');
                $table->index('hash');
            });
        }

        // 回填存量数据：按 id 升序重建哈希链
        $key = (string) config('license.audit.hmac_secret', '');
        $logs = DB::table('audit_logs')->orderBy('id')->get([
            'id', 'actor_type', 'actor_id', 'action', 'resource_type', 'resource_id',
            'ip', 'user_agent', 'context', 'created_at',
        ]);

        $prev = null;
        foreach ($logs as $log) {
            $contextJson = $log->context === null
                ? null
                : (is_string($log->context) ? $log->context : json_encode($log->context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            $canonical = implode("\n", [
                (string) $prev,
                (string) $log->actor_type,
                (string) $log->actor_id,
                (string) $log->action,
                (string) $log->resource_type,
                (string) $log->resource_id,
                (string) $log->ip,
                (string) $log->user_agent,
                (string) $contextJson,
                (string) $log->created_at,
            ]);

            $hash = $key !== '' ? hash_hmac('sha256', $canonical, $key) : null;

            DB::table('audit_logs')->where('id', $log->id)->update([
                'prev_hash' => $prev,
                'hash' => $hash,
            ]);

            $prev = $hash;
        }
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['hash']);
            $table->dropColumn(['prev_hash', 'hash']);
        });
    }
};
