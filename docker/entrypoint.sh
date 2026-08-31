#!/bin/sh
# ===========================================================================
# 逐风授权码管理平台 - 应用容器启动引导（v1.2.1）
# 初始化链（幂等，可重复执行）：
#   1. 从 env-data 持久卷恢复 .env；首次启动从 .env.example 生成
#   2. APP_KEY（Laravel 应用密钥，缺失自动生成）
#   3. ZF_APP_ENCRYPT_KEY（AES-256-GCM 敏感字段密钥，32 字节 Base64）
#   4. Ed25519 密钥对 + LICENSING_KEY_HMAC_SECRET + LICENSING_FINGERPRINT_SALT
#      （php artisan license:keys --write，仅补缺不覆盖）
#   5. LICENSING_REPLAY_CLIENT_SECRET（客户端防重放签名密钥）
#   6. ZF_ADMIN_PATH（仍为默认 admin 时自动随机化为 zf-xxxxxx）
#   7. php artisan migrate --force（数据库迁移）
#   8. 版权签名守护初始化（config/license_signature_guard.php 缺失时
#      php artisan zf:signature:init 幂等兜底；镜像内置公钥时跳过）
#   9. php artisan zf:init-admin（首次生成随机管理员账号；
#      凭据输出终端并落盘 storage/app/init-admin-credentials.txt，0600）
#   10. 将最终 .env 回写持久卷，重启不丢密钥
#   11. 启动 PHP-FPM
#
# 说明：
#   - 容器环境变量（env_file / environment）优先级高于 .env 文件；
#     DB_HOST=mysql / REDIS_HOST=redis 由 docker-compose.yml 注入。
#   - compose env_file 会注入 .env.example 中存在的空值密钥（如 APP_KEY=），
#     而 Laravel 环境变量为 immutable（不覆盖已有值）。因此本脚本对所有
#     生成/恢复的密钥统一执行 export，确保运行中的 artisan/php-fpm 读到新值。
#   - 密钥仅生成一次：已配置非空值一律跳过（幂等），重建容器不丢失。
# ===========================================================================
set -e

cd /var/www/html

ENV_DIR=/var/www/html/env-data
ENV_FILE="$ENV_DIR/.env"

# ---------------------------------------------------------------
# 1. 恢复 / 生成 .env
# ---------------------------------------------------------------
if [ -f "$ENV_FILE" ]; then
    echo "[entrypoint] 从持久卷恢复 .env。"
    cp "$ENV_FILE" .env
else
    echo "[entrypoint] 首次启动：从 .env.example 生成 .env。"
    cp .env.example .env
fi

# ---------------------------------------------------------------
# 辅助函数
# ---------------------------------------------------------------
# export_key：将 .env 中的密钥类变量导出到环境（值非空才导出），
# 覆盖 env_file 注入的空值，保证 Laravel 读到 .env 文件中的真实值。
export_key() {
    key=$1
    v=$(grep -E "^${key}=" .env | head -n1 | cut -d= -f2-)
    if [ -n "$v" ]; then
        export "$key=$v"
    fi
}

# ensure_empty_key：键不存在或值为空时生成并写入 .env + export
ensure_empty_key() {
    key=$1
    value=$2
    if grep -Eq "^${key}=.+" .env; then
        echo "[entrypoint] ${key} 已配置，跳过。"
        export_key "$key"
        return 0
    fi
    if grep -Eq "^${key}=" .env; then
        sed -i "s|^${key}=.*|${key}=${value}|" .env
    else
        echo "${key}=${value}" >> .env
    fi
    export "$key=$value"
    echo "[entrypoint] ${key} 已生成。"
}

# ensure_not_placeholder：值为占位符时替换为随机值
ensure_not_placeholder() {
    key=$1
    placeholder=$2
    gen=$3
    if grep -Eq "^${key}=${placeholder}$" .env; then
        sed -i "s|^${key}=.*|${key}=${gen}|" .env
        export "$key=$gen"
        echo "[entrypoint] ${key} 已替换占位符并生成随机值。"
    else
        export_key "$key"
        echo "[entrypoint] ${key} 已配置，跳过。"
    fi
}

# ---------------------------------------------------------------
# 2. APP_KEY
# ---------------------------------------------------------------
ensure_empty_key "APP_KEY" "$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"

# ---------------------------------------------------------------
# 3. ZF_APP_ENCRYPT_KEY（AES-256-GCM，32 字节明文密钥的纯 Base64）
# ---------------------------------------------------------------
ensure_empty_key "ZF_APP_ENCRYPT_KEY" "$(php -r 'echo base64_encode(random_bytes(32));')"

# ---------------------------------------------------------------
# 5. LICENSING_FINGERPRINT_SALT（占位符替换）
# ---------------------------------------------------------------
ensure_not_placeholder "LICENSING_FINGERPRINT_SALT" "change-me-fingerprint-salt" "$(php -r 'echo bin2hex(random_bytes(32));')"
# ---------------------------------------------------------------
# 6. LICENSING_REPLAY_CLIENT_SECRET（客户端防重放签名密钥，占位符替换）
# ---------------------------------------------------------------
ensure_not_placeholder "LICENSING_REPLAY_CLIENT_SECRET" "change-me-client-secret" "$(php -r 'echo bin2hex(random_bytes(32));')"
# ---------------------------------------------------------------
# 10.5 LICENSING_AUDIT_HMAC_SECRET（等保 M-02：审计哈希链密钥，缺失自动生成）
# ---------------------------------------------------------------
ensure_empty_key "LICENSING_AUDIT_HMAC_SECRET" "$(php -r 'echo bin2hex(random_bytes(32));')"
# ---------------------------------------------------------------
# 4. Ed25519 密钥对 + 授权码 HMAC 索引密钥 + 指纹盐
#     （license:keys --write 仅补缺，已有值不覆盖，幂等）
# ---------------------------------------------------------------
echo "[entrypoint] 确保 Ed25519 密钥对 / 授权码 HMAC / 指纹盐（缺失自动生成）。"
php artisan license:keys --write --ansi
export_key "LICENSING_ED25519_PRIVATE_KEY"
export_key "LICENSING_ED25519_PUBLIC_KEY"
export_key "LICENSING_KEY_HMAC_SECRET"
# ---------------------------------------------------------------
# 7. ZF_ADMIN_PATH（仍为默认 admin 时随机化，降低后台暴露面）
# ---------------------------------------------------------------
if grep -Eq '^ZF_ADMIN_PATH=admin$' .env; then
    echo "[entrypoint] 生成随机后台访问路径（ZF_ADMIN_PATH）。"
    php artisan zf:admin-path --write --ansi
fi
export_key "ZF_ADMIN_PATH"
# ---------------------------------------------------------------
# 8. 数据库迁移
# ---------------------------------------------------------------
echo "[entrypoint] 执行数据库迁移（幂等）。"
php artisan migrate --force --ansi
# ---------------------------------------------------------------
# 8.5 版权签名守护初始化（V1.31 部署链补全）
#     config/license_signature_guard.php 存在且 public_keys 非空视为已初始化
#     （镜像内置公钥与配置），跳过；缺失时由 zf:signature:init 幂等兜底生成，
#     保证容器重建后签名守护真实生效。
# ---------------------------------------------------------------
if [ -f "config/license_signature_guard.php" ] && php -r '$cfg = @include "config/license_signature_guard.php"; exit(is_array($cfg) && ! empty($cfg["public_keys"]) ? 0 : 1);'; then
    echo "[entrypoint] 版权签名守护已初始化，跳过 zf:signature:init。"
else
    echo "[entrypoint] 版权签名守护未初始化，执行 zf:signature:init（幂等兜底）。"
    php artisan zf:signature:init --ansi
fi
# ---------------------------------------------------------------
# 9. 随机管理员初始化（已有超管自动跳过）
# ---------------------------------------------------------------
echo "[entrypoint] 执行随机管理员初始化（已有超管跳过）。"
php artisan zf:init-admin --ansi
# ---------------------------------------------------------------
# 10. 持久化 .env 回卷
# ---------------------------------------------------------------
mkdir -p "$ENV_DIR"
cp .env "$ENV_FILE"
echo "[entrypoint] .env 已持久化至 env-data 卷（密钥重启不丢失）。"
# ---------------------------------------------------------------
# 10.5 缓存预热（ZF-2026-011：config/route/view 缓存，提升性能并固化配置）
# ---------------------------------------------------------------
echo "[entrypoint] 预热 config/route/view 缓存。"
php artisan config:cache --ansi
php artisan route:cache --ansi
php artisan view:cache --ansi
# ---------------------------------------------------------------
# 11. 启动 PHP-FPM
# ---------------------------------------------------------------
echo "[entrypoint] 启动 PHP-FPM。"
exec php-fpm
