<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Console\ClosureCommand;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

// 心跳/审计/登录尝试记录清理（每日；审计保留天数由设置页 license.audit.retention_days
// 或 .env LICENSING_AUDIT_RETENTION_DAYS 控制，0=自动取 settings/config 值，见 CleanupRecords）
Schedule::command('license:clean --heartbeat-days=30 --login-days=90')
    ->daily()
    ->onFailure(function (ClosureCommand $command) {
        $command->output('license:clean 执行失败');
    });

// 过期授权码状态推进（每小时）
Schedule::command('license:expire')
    ->hourly()
    ->onFailure(function (ClosureCommand $command) {
        $command->output('license:expire 执行失败');
    });
