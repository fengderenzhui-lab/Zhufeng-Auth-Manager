<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | 平台基础信息
    |--------------------------------------------------------------------------
    */

    'platform' => [
        'name' => env('LICENSING_PLATFORM_NAME', '逐风授权码管理平台'),
        // V1.30：仅内部配置使用，严禁输出到前端/接口/响应头（等保-信息泄露）
        'version' => '1.33',
        'api_version' => 'v1',
    ],

    /*
    |--------------------------------------------------------------------------
    | 授权码生成
    |--------------------------------------------------------------------------
    */

    'key' => [
        // 授权码字符集（不含易混淆字符 0O1lI）
        'alphabet' => env('LICENSING_KEY_ALPHABET', 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'),
        // 分段长度（5-5-5-5-5）
        'segments' => 5,
        'segment_length' => 5,
        // 授权码前缀（用于后台展示与客户识别，不包含敏感信息）
        'prefix' => env('LICENSING_KEY_PREFIX', 'ZF-'),
        'default_prefix' => env('LICENSING_KEY_PREFIX', 'ZF-'),
        // 授权码 HMAC 索引密钥（服务端密钥，派生 key_hash 与校验字符；必填，缺失拒绝启动——ZF-2026-014）
        'hmac_secret' => env('LICENSING_KEY_HMAC_SECRET', ''),
        // 批量生成默认数量上限（单次）
        'max_batch_size' => (int) env('LICENSING_MAX_BATCH_SIZE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | 批量生成
    |--------------------------------------------------------------------------
    */

    'generate' => [
        'max_batch' => (int) env('LICENSING_MAX_BATCH_SIZE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | 一机一码 / 设备指纹
    |--------------------------------------------------------------------------
    */

    'fingerprint' => [
        // 指纹计算 salt（必须配置，泄露会导致指纹可预测）
        'salt' => env('LICENSING_FINGERPRINT_SALT', ''),
        // 单码默认可绑定设备数
        'default_max_devices' => (int) env('LICENSING_DEFAULT_MAX_DEVICES', 1),
        // 指纹 HMAC 算法
        'algo' => 'sha256',
        // 允许的原始信号字段
        'signal_fields' => ['cpu_id', 'machine_id', 'mac_address', 'disk_serial', 'system_uuid'],
        // 至少需要多少个有效信号才能计算指纹
        'min_signals' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | 心跳在线验证（强制在线，无离线宽限）
    |--------------------------------------------------------------------------
    */

    'heartbeat' => [
        // 心跳上报周期（秒），客户端应略小于该值
        'interval_seconds' => (int) env('LICENSING_HEARTBEAT_INTERVAL', 60),
        // 心跳超时阈值（秒）：超过该时间未上报视为失效
        'timeout_seconds' => (int) env('LICENSING_HEARTBEAT_TIMEOUT', 300),
        // 是否启用心跳自动失效判定（关闭则仅按 expires_at 判定）
        'enforce_online' => (bool) env('LICENSING_ENFORCE_ONLINE', true),
        // 心跳记录保留天数（之后由调度命令清理）
        'retention_days' => (int) env('LICENSING_HEARTBEAT_RETENTION_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ed25519 签名
    |--------------------------------------------------------------------------
    */

    'ed25519' => [
        // 服务端私钥（Base64，sodium 格式，仅存 .env，绝不入库/入仓库）
        'private_key' => env('LICENSING_ED25519_PRIVATE_KEY', ''),
        // 服务端公钥（Base64，可安全对外发布，客户端用于验签）
        'public_key' => env('LICENSING_ED25519_PUBLIC_KEY', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | 登录安全
    |--------------------------------------------------------------------------
    */

    'auth' => [
        // API Token 有效期（分钟）；等保二级建议 ≤120 分钟（生产可调小）
        'token_ttl_minutes' => (int) env('LICENSING_TOKEN_TTL_MINUTES', 60),
        // 单用户最大活跃 Token 数（超过后最旧失效）
        'max_active_tokens' => (int) env('LICENSING_MAX_ACTIVE_TOKENS', 5),
        // 登录失败锁定阈值（按 email+IP）
        'lockout_threshold' => (int) env('LICENSING_LOCKOUT_THRESHOLD', 5),
        // 全局锁定阈值（按 email，不区分 IP；等保 M-01 修复：防轮换 IP 绕过）
        'lockout_global_threshold' => (int) env('LICENSING_LOCKOUT_GLOBAL_THRESHOLD', 15),
        // 锁定时长（分钟）
        'lockout_minutes' => (int) env('LICENSING_LOCKOUT_MINUTES', 15),
        // 密码策略（等保 H-02 修复：长度 + 复杂度类数统一）
        'password_min_length' => (int) env('LICENSING_PASSWORD_MIN_LENGTH', 12),
        'password_max_length' => (int) env('LICENSING_PASSWORD_MAX_LENGTH', 72),
        'password_min_classes' => (int) env('LICENSING_PASSWORD_MIN_CLASSES', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | 接口风控
    |--------------------------------------------------------------------------
    */

    'security' => [
        'rate_limit' => [
            'global_per_minute' => (int) env('LICENSING_GLOBAL_RATE_LIMIT', 120),
            'login_per_minute' => (int) env('LICENSING_LOGIN_RATE_LIMIT_MIN', 10),
            'login_per_day' => (int) env('LICENSING_LOGIN_RATE_LIMIT_DAY', 50),
            'client_per_minute' => (int) env('LICENSING_CLIENT_RATE_LIMIT', 60),
            'admin_per_minute' => (int) env('LICENSING_ADMIN_RATE_LIMIT', 300),
        ],
        'replay' => [
            // 是否强制请求签名校验
            'strict_signature' => (bool) env('LICENSING_REPLAY_STRICT', true),
            // 时间窗口（秒）
            'window_seconds' => (int) env('LICENSING_REPLAY_WINDOW', 300),
            // nonce 去重缓存时长（秒）
            'nonce_ttl_seconds' => (int) env('LICENSING_REPLAY_NONCE_TTL', 600),
            // 客户端签名密钥（与集成方共享，仅用于防重放签名，非授权码密钥）
            'client_secret' => env('LICENSING_REPLAY_CLIENT_SECRET', ''),
        ],
        'headers' => [
            'hsts' => (bool) env('LICENSING_HSTS', true),
            'csp' => env('LICENSING_CSP', "default-src 'none'; base-uri 'none'; frame-ancestors 'none'"),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | 审计
    |--------------------------------------------------------------------------
    */

    'audit' => [
        // 审计日志保留天数
        'retention_days' => (int) env('LICENSING_AUDIT_RETENTION_DAYS', 365),
        // 是否记录心跳类高频审计（量大，建议关闭）
        'record_heartbeat_audit' => (bool) env('LICENSING_AUDIT_HEARTBEAT', false),
        // 审计哈希链 HMAC 密钥（等保 M-02 修复）：服务端密钥，用于计算审计日志 prev_hash/hash 链；
        // 未配置时审计写入会抛错（fail-closed），避免产生无防篡改保护的审计记录。
        'hmac_secret' => env('LICENSING_AUDIT_HMAC_SECRET', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | AES-256-GCM 敏感字段加密
    |--------------------------------------------------------------------------
    */

    'aes' => [
        // 32 字节 Base64 密钥，仅存 .env（ZF_APP_ENCRYPT_KEY），严禁入库/入仓库
        'encrypt_key' => env('ZF_APP_ENCRYPT_KEY', ''),
        // ZF-2026-006：密钥轮换期旧密钥（ZF_APP_ENCRYPT_KEY_PREV，32 字节 Base64，可缺省）。
        // 配置后 decrypt 对旧密钥密文自动回退解密；升级历史密文可手动解密后以当前密钥重加密。
        'encrypt_key_prev' => env('ZF_APP_ENCRYPT_KEY_PREV', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | 客户名称安全模糊检索（n-gram 盲索引，V1.30）
    |--------------------------------------------------------------------------
    */

    'customer_ngram' => [
        // 2-gram 数量阈值：关键词 2-gram 数量超过则退化为 3-gram 策略
        'max_2gram' => (int) env('LICENSING_CUSTOMER_NGRAM_MAX_2GRAM', 6),
        // 3-gram 数量阈值：超过则取关键词前缀优先的前 N 个 3-gram（高召回）
        'max_3gram' => (int) env('LICENSING_CUSTOMER_NGRAM_MAX_3GRAM', 8),
    ],

    /*
    |--------------------------------------------------------------------------
    | 管理端 HMAC 签名（每管理员独立 secret）
    |--------------------------------------------------------------------------
    */

    'admin_security' => [
        // 管理端 API 是否强制要求 X-Signature（登录端点除外）
        'require_signature' => (bool) env('LICENSING_ADMIN_SIGNATURE_REQUIRED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 随机后台路径
    |--------------------------------------------------------------------------
    */

    'admin' => [
        // 后台访问前缀（部署时随机生成，如 /zf-8k2x9q），存 ZF_ADMIN_PATH
        'path' => env('ZF_ADMIN_PATH', 'admin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 随机管理员初始化
    |--------------------------------------------------------------------------
    */

    'init_admin' => [
        // 随机用户名前缀
        'username_prefix' => env('LICENSING_ADMIN_USERNAME_PREFIX', 'adm_'),
        // 随机用户名后缀长度
        'suffix_length' => 4,
        // 初始密码长度（≥16）
        'password_length' => (int) env('LICENSING_ADMIN_PASSWORD_LENGTH', 18),
        // 首次运行凭据写入文件（相对 storage 目录）
        'credential_file' => 'app/init-admin-credentials.txt',
    ],

    /*
    |--------------------------------------------------------------------------
    | 客户端对接（/api/licensing/v1，对齐 masterix21/laravel-licensing-client）
    |--------------------------------------------------------------------------
    */

    'licensing_v1' => [
        // 令牌签发者（客户端 iss 校验默认 laravel-licensing）
        'issuer' => env('LICENSING_V1_ISSUER', 'laravel-licensing'),
        // 令牌有效期（天，客户端本地缓存默认 7 天）
        'token_ttl_days' => (int) env('LICENSING_V1_TOKEN_TTL_DAYS', 7),
        // 强制在线刷新阈值（天：令牌可用期 + 宽限期后必须联网 refresh）
        'force_online_after_days' => (int) env('LICENSING_V1_FORCE_ONLINE_DAYS', 14),
        // 时钟容差（秒）
        'clock_skew_seconds' => (int) env('LICENSING_V1_CLOCK_SKEW', 60),
        // 客户端指纹字符串允许的最大长度
        'fingerprint_max_length' => 512,
    ],

    /*
    |--------------------------------------------------------------------------
    | TLS/HTTPS
    |--------------------------------------------------------------------------
    */

    'tls' => [
        // 是否强制 HTTPS（.env 配置；nginx 侧同时校验）
        // true：应用层兜底强制——API/JSON 返回 403 HTTPS_REQUIRED，页面 301 跳 https；
        // false：本地开发 / 纯内网场景跳过
        'force_https' => (bool) env('APP_FORCE_HTTPS', true),
        // 等保 M-05 修复：可信代理 IP 白名单（env LICENSING_TRUSTED_PROXIES，逗号分隔 IP，默认空）。
        // 仅当请求来源 IP 命中白名单时才信任 X-Forwarded-Proto，否则仅以 isSecure() 判定，
        // 杜绝手动部署直连场景伪造代理头绕过 HTTPS 强制。
        'trusted_proxies' => env('LICENSING_TRUSTED_PROXIES', ''),
        // 兼容旧配置项（v1.30 及以前）：信任代理开关已被 trusted_proxies 白名单取代，
        // 保留仅为避免破坏历史引用，ForceHttps 已不再读取该键。
        'trust_proxies' => (bool) env('LICENSING_TRUST_PROXIES', false),
        'hsts' => (bool) env('LICENSING_HSTS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | 初始超级管理员（Seeder 使用，密码强制 ≥12 位）
    |--------------------------------------------------------------------------
    */

    'seed' => [
        'admin_email' => env('LICENSING_INITIAL_ADMIN_EMAIL', 'admin@example.com'),
        'admin_password' => env('LICENSING_INITIAL_ADMIN_PASSWORD', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | 雷池（SafeLine）人机验证接入（V1.32 已实现，默认关闭）
    |--------------------------------------------------------------------------
    | 开关可经设置页（license.safeline.enabled，DB 优先）或本处 .env 控制；
    | 开启后 SafelineMiddleware 校验雷池注入的可信头（trusted_header），
    | 缺失/不匹配返回 403 {success:false,error:{code:"SAFELINE_BLOCKED"}}。
    | 仅挂载公网防刷端点（/api/licensing/v1 业务六端点 + public-key），
    | health / admin / SDK 端点不拦截。endpoint/api_key 为雷池管理接口凭据（仅 .env）。
    */

    'safeline' => [
        'enabled' => (bool) env('LICENSING_SAFELINE_ENABLED', false),
        'endpoint' => env('LICENSING_SAFELINE_ENDPOINT', ''),
        'api_key' => env('LICENSING_SAFELINE_API_KEY', ''),
        // 由雷池校验通过后设置的可信头
        'trusted_header' => env('LICENSING_SAFELINE_TRUSTED_HEADER', 'X-SafeLine-Checked'),
    ],

    /*
    |--------------------------------------------------------------------------
    | 版权动态签名守护（V1.30 新增）
    |--------------------------------------------------------------------------
    | 说明：本段配置由 `php artisan zf:signature:init` 生成并写入独立配置文件
    | config/license_signature_guard.php（包含 public_keys 公钥清单 / hashes 哈希基准表 /
    | signature_pool 签名池 / total 组数 / owner1|owner2 私钥归属公钥）。
    | 此处通过 require 合并，保证 CopyrightSignatureService 统一从
    | config('license.signature_guard.*') 读取，无需关心来源文件。
    |--------------------------------------------------------------------------
    */
    'signature_guard' => array_merge(
        [
            // 是否启用版权签名守护（false 时 verifyGuard 直接放行，用于极少数排障场景）
            'enabled' => (bool) env('LICENSING_SIGNATURE_GUARD_ENABLED', true),
            // 公钥文件相对项目根路径清单（由 zf:signature:init 生成）
            'public_keys' => [],
            // 公钥文件 sha256 基准表（相对路径 => hex，由 zf:signature:init 生成）
            'hashes' => [],
            // 预置签名池（由生成器用私钥签名，每组含 nonce/timestamp/message/sig1/sig2）
            'signature_pool' => [],
            // 签名池总组数
            'total' => 0,
            // 私钥1/私钥2 派生公钥 hex（用于签名归属判定：pub1 对应 sig1，pub2 对应 sig2）
            'owner1' => '',
            'owner2' => '',
            // 动态签名轮换游标文件（相对 storage/framework/cache）
            'cursor_file' => 'signature_guard_cursor.txt',
            // 验签结果缓存秒数（启动/中间件高频调用时避免每请求全量验签）
            'cache_seconds' => 60,
        ],
        is_file(__DIR__.'/license_signature_guard.php') ? require __DIR__.'/license_signature_guard.php' : [],
    ),

];
