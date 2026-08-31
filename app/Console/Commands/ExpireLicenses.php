<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\LicenseStatus;
use App\Models\License;
use App\Services\LicenseService;
use Illuminate\Console\Command;

class ExpireLicenses extends Command
{
    protected $signature = 'license:expire
                            {--chunk=500 : 每批处理数量}
                            {--dry-run : 仅统计不落库}';

    protected $description = '将到期 / 强制在线心跳超时的有效授权码批量置为过期';

    public function handle(LicenseService $licenses): int
    {
        $chunk = (int) $this->option('chunk');
        $dryRun = (bool) $this->option('dry-run');

        $total = 0;
        $expiredByTime = 0;
        $expiredByHeartbeat = 0;

        License::query()
            ->where('status', LicenseStatus::Active->value)
            ->orderBy('id')
            ->chunkById($chunk, function ($rows) use ($licenses, $dryRun, &$total, &$expiredByTime, &$expiredByHeartbeat) {
                foreach ($rows as $license) {
                    if ($dryRun) {
                        if ($license->hasExpired()) {
                            $expiredByTime++;
                        }
                        continue;
                    }

                    $result = $licenses->expire($license);
                    if ($result === null) {
                        continue;
                    }

                    $total++;
                    if ($result['by_time']) {
                        $expiredByTime++;
                    }
                    if ($result['by_heartbeat']) {
                        $expiredByHeartbeat++;
                    }
                    $this->line("  [expired] license #{$result['license_id']}");
                }
            });

        $this->info($dryRun
            ? "dry-run 完成：到期将过期 {$expiredByTime} 条"
            : "完成：共过期 {$total} 条（到期 {$expiredByTime} / 心跳超时 {$expiredByHeartbeat}）");

        return self::SUCCESS;
    }
}
