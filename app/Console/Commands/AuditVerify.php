<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AesGcmService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 审计日志哈希链全链校验（只读，不修改数据）。
 *
 * 校验方法（与 AuditService::record / 回填迁移完全一致的 canonical 规则）：
 *  - 按 id 升序遍历；对每行基于解密后的明文语义构造 canonical：
 *      prev_hash | actor_type | actor_id | action | resource_type | resource_id |
 *      ip | user_agent | context(JSON) | created_at
 *  - hash = HMAC-SHA256(canonical, LICENSING_AUDIT_HMAC_SECRET)
 *  - 断言：当前行 hash 与重算值一致，且下一条 prev_hash 与当前 hash 相等
 *    （链连续）；任一历史行被篡改，其后所有行校验即失败。
 */
class AuditVerify extends Command
{
    protected $signature = 'zf:audit-verify';

    protected $description = '校验审计日志哈希链完整性（只读，不修改数据）';

    public function handle(): int
    {
        $key = (string) config('license.audit.hmac_secret', '');
        if ($key === '') {
            $this->error('未配置 LICENSING_AUDIT_HMAC_SECRET，无法校验。');

            return self::FAILURE;
        }

        $aes = app(AesGcmService::class);

        $logs = DB::table('audit_logs')->orderBy('id')->get([
            'id', 'actor_type', 'actor_id', 'action', 'resource_type', 'resource_id',
            'ip', 'user_agent', 'context', 'created_at', 'prev_hash', 'hash',
        ]);

        $total = $logs->count();
        if ($total === 0) {
            $this->info('审计日志为空，无需校验。');

            return self::SUCCESS;
        }

        $prev = null;
        $broken = [];

        foreach ($logs as $log) {
            $contextJson = $this->plainText($aes, (string) $log->context);

            $canonical = implode("\n", [
                (string) $prev,
                (string) $log->actor_type,
                mb_substr((string) $log->actor_id, 0, 64),
                mb_substr((string) $log->action, 0, 64),
                mb_substr((string) $log->resource_type, 0, 32),
                mb_substr((string) $log->resource_id, 0, 64),
                mb_substr($this->plainText($aes, (string) $log->ip), 0, 45),
                mb_substr($this->plainText($aes, (string) $log->user_agent), 0, 255),
                (string) $contextJson,
                (string) $log->created_at,
            ]);

            $expected = hash_hmac('sha256', $canonical, $key);

            if ($expected !== (string) $log->hash) {
                $broken[] = [
                    'id' => (int) $log->id,
                    'type' => 'hash_mismatch',
                    'expected' => $expected,
                    'actual' => (string) $log->hash,
                ];
            } elseif ((string) $log->prev_hash !== (string) $prev) {
                $broken[] = [
                    'id' => (int) $log->id,
                    'type' => 'chain_break',
                    'expected_prev' => (string) $prev,
                    'actual_prev' => (string) $log->prev_hash,
                ];
            }

            $prev = (string) $log->hash;
        }

        if ($broken === []) {
            $this->info(sprintf('哈希链校验通过：共 %d 条，链完整连续。', $total));

            return self::SUCCESS;
        }

        $this->error(sprintf('哈希链校验失败：共 %d 条，发现 %d 处异常。', $total, count($broken)));
        $this->table(['id', 'type', 'expected', 'actual'], array_slice($broken, 0, 50));

        return self::FAILURE;
    }

    /**
     * 取敏感列明文语义（ip / user_agent / context 解密后字符串即写入哈希链的原文）。
     * 非密文（历史明文环境）直接返回原值；解密失败按原值兜底。
     */
    private function plainText(AesGcmService $aes, string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $raw = base64_decode($value, true);
        if ($raw === false || strlen($raw) < 28) {
            return $value; // 历史明文（未加密环境）
        }

        try {
            return $aes->decrypt($value);
        } catch (\Throwable) {
            return $value;
        }
    }
}
