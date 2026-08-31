<?php

declare(strict_types=1);

use App\Services\AesGcmService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * v1.2.6 审计日志哈希链历史行补全。
 *
 * v1.2.5 加密迁移对 audit_logs.ip / user_agent / context 加密时未回算 hash
 * （为避免破坏链完整性）；本迁移按 id 升序从链头重算全部行的 prev_hash/hash，
 * canonical 规则与 AuditService::record 完全一致：
 *
 *   canonical = implode("\n", [
 *       prev_hash, actor_type, actor_id(截64), action(截64),
 *       resource_type(截32), resource_id(截64), ip(截45), user_agent(截255),
 *       context(JSON), created_at,
 *   ])
 *   hash = HMAC-SHA256(canonical, LICENSING_AUDIT_HMAC_SECRET)
 *
 * 敏感列（ip / user_agent / context）取解密后的明文语义参与哈希（与
 * AuditService 写入时一致）；解密失败按原值兜底（未加密环境的历史行）。
 *
 * 幂等：全量重算后与库中现有值比对，全部一致则不做任何写库；
 * 存在不一致时按新值重写（值相同行写库无副作用，可重放）。
 */
return new class extends Migration
{
    public function up(): void
    {
        $key = (string) config('license.audit.hmac_secret', '');
        if ($key === '') {
            throw new \RuntimeException('未配置 LICENSING_AUDIT_HMAC_SECRET，无法回填审计哈希链（fail-closed）。');
        }

        $aes = app(AesGcmService::class);

        $logs = DB::table('audit_logs')->orderBy('id')->get([
            'id', 'actor_type', 'actor_id', 'action', 'resource_type', 'resource_id',
            'ip', 'user_agent', 'context', 'created_at', 'prev_hash', 'hash',
        ]);

        if ($logs->isEmpty()) {
            return;
        }

        $prev = null;
        $expected = [];
        $dirty = false;

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

            $hash = hash_hmac('sha256', $canonical, $key);

            $expected[$log->id] = ['prev_hash' => $prev, 'hash' => $hash];

            if ((string) $log->prev_hash !== (string) $prev || (string) $log->hash !== (string) $hash) {
                $dirty = true;
            }

            $prev = $hash;
        }

        if (! $dirty) {
            return; // 链已完整，跳过写库（幂等）
        }

        foreach ($expected as $id => $pair) {
            DB::table('audit_logs')->where('id', $id)->update([
                'prev_hash' => $pair['prev_hash'],
                'hash' => $pair['hash'],
            ]);
        }
    }

    public function down(): void
    {
        // 回填不可逆，结构未变更，无需操作。
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
};
