<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 管理员角色分级：
 * super_admin 超级管理员：全权限（账号管理 / 系统配置 / 批量操作 / 审计日志）
 * admin 普通管理员：仅授权码基础管理
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';

    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }
}
