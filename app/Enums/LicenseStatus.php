<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 授权码状态机：
 * pending(待定) -> active(有效) -> expired(过期) / revoked(已吊销) / blacklisted(已拉黑)
 * blacklisted 为永久拉黑，仅超级管理员可操作；revoked 可由管理员恢复。
 */
enum LicenseStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
    case Blacklisted = 'blacklisted';

    public function label(): string
    {
        return match ($this) {
            self::Pending => '待定',
            self::Active => '有效',
            self::Expired => '过期',
            self::Revoked => '已吊销',
            self::Blacklisted => '已拉黑',
        };
    }
}
