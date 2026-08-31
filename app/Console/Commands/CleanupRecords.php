<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Heartbeat;
use App\Models\LoginAttempt;
use App\Services\SettingsService;
use Illuminate\Console\Command;

class CleanupRecords extends Command
{
    protected $signature = 'license:clean
                            {--heartbeat-days=30 : 心跳日志保留天数}
                            {--audit-days=0 : 审计日志保留天数（0=自动，取设置页 license.audit.retention_days，无则回落 config/license.php）}
                            {--login-days=90 : 登录尝试保留天数}
                            {--dry-run : 仅统计不删除}';

    protected $description = '清理超期的心跳 / 审计 / 登录尝试记录';

    public function handle(SettingsService $settings): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $heartbeatDays = (int) $this->option('heartbeat-days');
        $loginDays = (int) $this->option('login-days');

        // V1.32：审计保留天数 DB 优先、config 兜底；显式传参（>0）优先于自动取值，手动执行可覆盖。
        $auditDays = (int) $this->option('audit-days');
        if ($auditDays <= 0) {
            $auditDays = (int) $settings->get('license.audit.retention_days', (int) config('license.audit.retention_days', 365));
            $this->line(sprintf('[audit-days] 自动取 settings/config：%d 天', $auditDays));
        }

        $jobs = [
            ['label' => '心跳日志', 'model' => Heartbeat::class, 'column' => 'checked_at', 'days' => $heartbeatDays],
            ['label' => '审计日志', 'model' => AuditLog::class, 'column' => 'created_at', 'days' => $auditDays],
            ['label' => '登录尝试', 'model' => LoginAttempt::class, 'column' => 'attempted_at', 'days' => $loginDays],
        ];

        foreach ($jobs as $job) {
            $cutoff = now()->subDays($job['days']);
            $count = $job['model']::query()->where($job['column'], '<', $cutoff)->count();

            $this->line(sprintf('[%s] 超期 %d 条（保留 %d 天）', $job['label'], $count, $job['days']));

            if (! $dryRun && $count > 0) {
                $job['model']::query()->where($job['column'], '<', $cutoff)->delete();
            }
        }

        $this->info($dryRun ? 'dry-run 完成，未删除任何数据。' : '清理完成。');

        return self::SUCCESS;
    }
}
