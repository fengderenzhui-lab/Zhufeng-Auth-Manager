<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 审计动作常量（审计日志 action 字段取值）。
 */
final class AuditAction
{
    // 认证
    public const LOGIN_SUCCESS = 'login_success';
    public const LOGIN_FAILED = 'login_failed';
    public const LOGOUT = 'logout';
    public const PASSWORD_CHANGED = 'password_changed';
    public const TOKEN_REVOKED = 'token_revoked';

    // 授权码
    public const LICENSE_GENERATED = 'license_generated';
    public const LICENSE_ACTIVATED = 'license_activated';
    public const LICENSE_HEARTBEAT = 'license_heartbeat';
    public const LICENSE_VERIFIED = 'license_verified';
    public const LICENSE_DEACTIVATED = 'license_deactivated';
    public const LICENSE_RENEWED = 'license_renewed';
    public const LICENSE_REVOKED = 'license_revoked';
    public const LICENSE_RESTORED = 'license_restored';
    public const LICENSE_BLACKLISTED = 'license_blacklisted';
    public const LICENSE_EXPIRED = 'license_expired';

    // 设备
    public const DEVICE_UNBOUND = 'device_unbound';

    // 账号
    public const USER_CREATED = 'user_created';
    public const USER_UPDATED = 'user_updated';
    public const USER_DELETED = 'user_deleted';

    // 产品
    public const PRODUCT_CREATED = 'product_created';
    public const PRODUCT_UPDATED = 'product_updated';
    public const PRODUCT_DELETED = 'product_deleted';

    // 授权模板
    public const TEMPLATE_CREATED = 'template_created';
    public const TEMPLATE_UPDATED = 'template_updated';
    public const TEMPLATE_DELETED = 'template_deleted';
    public const TEMPLATE_TOGGLED = 'template_toggled';

    // 授权范围
    public const SCOPE_CREATED = 'scope_created';
    public const SCOPE_UPDATED = 'scope_updated';
    public const SCOPE_DELETED = 'scope_deleted';
    public const SCOPE_TOGGLED = 'scope_toggled';

    // 试用
    public const TRIAL_CREATED = 'trial_created';
    public const TRIAL_UPDATED = 'trial_updated';
    public const TRIAL_DELETED = 'trial_deleted';
    public const TRIAL_REVOKED = 'trial_revoked';

    // 转让
    public const LICENSE_TRANSFERRED = 'license_transferred';

    // 系统
    public const SETTINGS_UPDATED = 'settings_updated';
    public const HMAC_SECRET_ROTATED = 'hmac_secret_rotated';
    public const PUBLIC_KEY_IMPORTED = 'public_key_imported';
    public const PUBLIC_KEY_DELETED = 'public_key_deleted';

    private function __construct()
    {
    }
}
