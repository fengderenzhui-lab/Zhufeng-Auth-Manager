#!/usr/bin/env bash
# ===========================================================================
# 逐风授权码管理平台 - GitHub 一键部署脚本（V1.30）
# 适用：
#   - Linux（Ubuntu 22.04 / 24.04 全新裸机，宝塔服务器亦可，见 --bt 说明）
#   - Windows（Git Bash / MSYS / Cygwin 环境自动转交 deploy.bat，逻辑一致）
# 能力：
#   - 启动时打印大号艺术字标题「逐风工作室」+ Y/N 部署确认交互
#   - 自动检测并安装缺失运行环境：PHP 8.2+、Composer、扩展、MySQL、Redis、Nginx
#   - git clone 拉取源码；初始化 .env 并随机生成全部密钥
#   - 随机后台路径 ZF_ADMIN_PATH（zf-xxxx 强随机前缀，抵抗字典扫描）
#   - 适配 V1.30 Ed25519 动态签名模块：仅确认公钥/配置存在，禁止生成或触碰私钥
#   - composer install --no-dev / migrate --force / config+route+view 缓存 / storage 权限
#   - nginx 站点：TLS1.2/1.3、隐藏版本号、敏感文件 deny、HSTS
#   - 自动创建高强度随机管理员账号（密码 ≥16 位含 3 类字符）
#   - 完成醒目输出随机三要素：后台地址 / 管理员账号 / 管理员密码
#
# 用法：
#   sudo bash deploy.sh --domain=lic.example.com --repo=https://github.com/xxx/zhufeng-license.git
#   sudo bash deploy.sh --domain=lic.example.com --repo=... --bt          # 宝塔环境
#   sudo bash deploy.sh --domain=lic.example.com --repo=... --email=admin@example.com
# 可选：--letsencrypt（certbot 自动签正式证书，需域名已解析到本机）
# 注意：部署流程严禁触碰桌面私钥（密钥1.key / 密钥2.key），仅校验仓库内置公钥。
# ===========================================================================
set -euo pipefail

# ----------------------------- 启动 Banner --------------------------------
# 打印大号艺术字标题「逐风工作室」+ 版本信息，Linux 终端 UTF-8 可正常显示中文。
show_banner() {
  echo ""
  echo -e "\033[1;36m"
  cat <<'EOF'
  _____ _   _ _   _ ______ _____ _   _ _____
 |__  /| | | | | | || ____|  ___| \ | |  __ \
   / / | | | | | | ||  _| | |__ |  \| | |  \ \
  / /_ | |_| | |_| || |___|  __|| |\  | |  | |
 /____| \___/ \___/ |_____|_|   |_| \_|_|  |_|
EOF
  echo -e "\033[0m"
  echo -e "\033[1;33m  逐风工作室\033[0m"
  echo -e "\033[1;36m  逐风授权码管理平台 V1.30 一键部署脚本\033[0m"
  echo -e "\033[90m  自动检测依赖 / 初始化配置 / 数据库迁移 / 随机凭据\033[0m"
  echo ""
}

# ----------------------------- 部署确认交互 -------------------------------
# 要求用户输入 Y 才继续，N 或其它输入直接退出部署脚本。
confirm_deploy() {
  echo -e "\033[1;33m是否开始部署逐风授权码管理平台 V1.30？\033[0m"
  read -r -p "输入 Y 确认部署，输入 N 退出部署：" choice
  case "${choice:-}" in
    [Yy]|[Yy][Ee][Ss])
      echo -e "\033[1;36m已确认，开始部署...\033[0m"
      echo ""
      ;;
    *)
      echo -e "\033[1;31m已退出部署。\033[0m"
      exit 0
      ;;
  esac
}

# ----------------------------- 操作系统检测 -------------------------------
# Windows（Git Bash / MSYS / Cygwin）下自动转交 deploy.bat 执行同一部署流程，
# 保证 deploy.sh 与 deploy.bat 逻辑一致、均兼容 Windows 环境。
detect_os() {
  case "$(uname -s)" in
    MINGW*|MSYS*|CYGWIN*)
      echo -e "\033[1;33m检测到 Windows 环境（Git Bash / MSYS / Cygwin）。\033[0m"
      local script_dir bat
      script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
      bat="${script_dir}/deploy.bat"
      if [ -f "$bat" ]; then
        echo -e "正在调用同目录 deploy.bat 执行 Windows 一键部署（与当前脚本逻辑一致）..."
        cmd //c "$(cygpath -w "$bat")"
        exit $?
      fi
      echo -e "\033[1;31m未找到 deploy.bat，请在 Windows 上直接双击 deploy.bat 进行部署。\033[0m"
      exit 1
      ;;
    *)
      return 0
      ;;
  esac
}

# 启动三件套：大字标题 -> Y/N 确认 -> OS 分流
show_banner
confirm_deploy
detect_os

# ----------------------------- 参数解析 ------------------------------------
DOMAIN=""
REPO_URL=""
ADMIN_EMAIL=""
INSTALL_DIR="/var/www/zhufeng-license"
IS_BT=0
USE_LETSENCRYPT=0

for arg in "$@"; do
  case "$arg" in
    --domain=*) DOMAIN="${arg#*=}" ;;
    --repo=*)   REPO_URL="${arg#*=}" ;;
    --email=*)  ADMIN_EMAIL="${arg#*=}" ;;
    --dir=*)    INSTALL_DIR="${arg#*=}" ;;
    --bt)       IS_BT=1 ;;
    --letsencrypt) USE_LETSENCRYPT=1 ;;
    *) echo "未知参数: $arg"; exit 1 ;;
  esac
done

if [ -z "$DOMAIN" ] || [ -z "$REPO_URL" ]; then
  echo "用法: sudo bash $0 --domain=你的域名 --repo=git仓库地址 [--email=管理员邮箱] [--bt] [--letsencrypt]"
  exit 1
fi

if [ "$(id -u)" -ne 0 ]; then
  echo "[错误] 请以 root 运行：sudo bash $0 ..."
  exit 1
fi

# ----------------------------- 工具函数 ------------------------------------
log()  { echo -e "\033[1;36m[deploy]\033[0m $*"; }
warn() { echo -e "\033[1;33m[警告]\033[0m $*"; }
die()  { echo -e "\033[1;31m[失败]\033[0m $*"; exit 1; }
rand_hex()  { openssl rand -hex "${1:-32}"; }
rand_b64()  { openssl rand -base64 "${1:-32}" | tr -d '\n' | tr -d '=' | head -c "${1:-43}"; }

# 生成 16 位以上、含大小写字母+数字+符号的强随机口令
gen_password() {
  local len="${1:-18}"
  local up lo num sym rest
  up="$(tr -dc 'A-Z' < /dev/urandom | head -c 2)"
  lo="$(tr -dc 'a-z' < /dev/urandom | head -c 2)"
  num="$(tr -dc '0-9' < /dev/urandom | head -c 2)"
  sym="$(tr -dc '#$%^&*-_+=' < /dev/urandom | head -c 2)"
  rest="$(tr -dc 'A-Za-z0-9#$%^&*-_+=' < /dev/urandom | head -c "$((len-8))")"
  echo "${up}${lo}${num}${sym}${rest}" | fold -w1 | shuf | tr -d '\n'
}

# ----------------------------- 1. 系统检测与依赖安装 ------------------------
log "1/10 检测系统并安装依赖..."
source /etc/os-release 2>/dev/null || die "无法识别系统（仅支持 Ubuntu 22.04/24.04）"
if [ "${ID:-}" != "ubuntu" ]; then
  die "当前系统为 ${ID:-未知}，本脚本仅支持 Ubuntu 22.04/24.04"
fi

export DEBIAN_FRONTEND=noninteractive
apt-get update -y

# PHP 8.2（Ubuntu 24.04 默认 PHP 8.3 兼容 8.2+；22.04 使用 ondrej/php PPA）
if ! command -v php >/dev/null 2>&1 || [ "$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')" \< "8.2" ]; then
  if [ "${VERSION_ID}" = "22.04" ] && ! grep -q "ondrej/php" /etc/apt/sources.list.d/*.list 2>/dev/null; then
    apt-get install -y software-properties-common
    add-apt-repository -y ppa:ondrej/php
    apt-get update -y
  fi
fi

apt-get install -y --no-install-recommends \
  git curl wget unzip ca-certificates openssl \
  php8.2-fpm php8.2-cli php8.2-mysql php8.2-mbstring php8.2-intl php8.2-bcmath php8.2-xml php8.2-curl php8.2-zip php8.2-opcache php8.2-redis php8.2-gd \
  mysql-server-8.0 mysql-client redis-server nginx \
  || apt-get install -y --no-install-recommends \
  php php-fpm php-cli php-mysql php-mbstring php-intl php-bcmath php-xml php-curl php-zip php-opcache php-redis php-gd \
  mysql-server mysql-client redis-server nginx || die "依赖安装失败，请检查 apt 源"

# Composer
if ! command -v composer >/dev/null 2>&1; then
  log "安装 Composer..."
  EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
  php -r "copy('https://getcomposer.org/installer', '/tmp/composer-setup.php');"
  php -r "if (hash_file('sha384', '/tmp/composer-setup.php') === '$EXPECTED_CHECKSUM') { echo 'installer verified'; } else { unlink('/tmp/composer-setup.php'); exit(1); }"
  php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
  rm -f /tmp/composer-setup.php
fi

# PHP-FPM 配置（安全与性能基线）
PHP_VER="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_FPM_INI="/etc/php/${PHP_VER}/fpm/php.ini"
if [ -f "$PHP_FPM_INI" ]; then
  sed -i 's/^;*opcache.enable=.*/opcache.enable=1/' "$PHP_FPM_INI"
  sed -i 's/^;*opcache.validate_timestamps=.*/opcache.validate_timestamps=0/' "$PHP_FPM_INI"
fi
systemctl enable --now php${PHP_VER}-fpm mysql nginx redis-server 2>/dev/null || true

# ----------------------------- 2. 拉取源码 ----------------------------------
log "2/10 拉取源码到 ${INSTALL_DIR} ..."
mkdir -p "$(dirname "$INSTALL_DIR")"
if [ -d "$INSTALL_DIR/.git" ]; then
  log "目录已存在，执行 git pull"
  cd "$INSTALL_DIR" && git pull --ff-only || warn "git pull 失败，继续使用现有代码"
else
  git clone --depth=1 "$REPO_URL" "$INSTALL_DIR" || die "git clone 失败：$REPO_URL"
fi
cd "$INSTALL_DIR"

# ----------------------------- 3. 数据库与 Redis 口令 -----------------------
log "3/10 初始化 MySQL 数据库与 Redis..."
DB_NAME="zhufeng_license"
DB_USER="zhufeng"
DB_PASSWORD="$(gen_password 20)"
MYSQL_ROOT_PASSWORD="$(gen_password 24)"
REDIS_PASSWORD="$(gen_password 20)"

# MySQL root 密码（本机全新安装默认为空；宝塔已装则以宝塔 root 密码为准）
mysqladmin -uroot ping >/dev/null 2>&1 || warn "MySQL root 无法免密登录：若已设置 root 密码，请用 --bt 模式手动导入 SQL"
mysql -uroot <<SQL || die "MySQL 初始化失败（如宝塔环境请手动执行 /root/zf-deploy-mysql.sql）"
ALTER USER 'root'@'localhost' IDENTIFIED WITH caching_sha2_password BY '${MYSQL_ROOT_PASSWORD}';
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
cp /dev/null /root/zf-deploy-mysql.sql 2>/dev/null || true
# 保存 SQL（含口令，仅 root 可读；宝塔场景供手动导入）
cat > /root/zf-deploy-mysql.sql <<EOF
ALTER USER 'root'@'localhost' IDENTIFIED WITH caching_sha2_password BY '${MYSQL_ROOT_PASSWORD}';
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
EOF
chmod 600 /root/zf-deploy-mysql.sql

# Redis 口令
if grep -q '^requirepass ' /etc/redis/redis.conf; then
  sed -i "s|^requirepass .*|requirepass ${REDIS_PASSWORD}|" /etc/redis/redis.conf
else
  echo "requirepass ${REDIS_PASSWORD}" >> /etc/redis/redis.conf
fi
systemctl restart redis-server || systemctl restart redis || true

# ----------------------------- 4. 生成 .env 与全部密钥 ----------------------
log "4/10 生成 .env 与随机密钥..."
if [ ! -f .env ]; then
  cp .env.example .env
fi

# 随机后台路径：zf- + 8 位强随机（zf-8k2x9q 风格，抵抗字典扫描）
ZF_ADMIN_PATH="zf-$(openssl rand -hex 4)"
APP_KEY="base64:$(openssl rand -base64 32 | tr -d '\n')"
ZF_APP_ENCRYPT_KEY="$(rand_b64 43)"
ZF_APP_ENCRYPT_KEY_PREV="$(rand_b64 43)"
LICENSING_KEY_HMAC_SECRET="$(rand_hex 32)"
LICENSING_FINGERPRINT_SALT="$(rand_hex 32)"
LICENSING_AUDIT_HMAC_SECRET="$(rand_hex 32)"
LICENSING_REPLAY_CLIENT_SECRET="$(rand_hex 32)"

write_env() { # key value
  local k="$1" v="$2"
  if grep -qE "^${k}=" .env; then
    sed -i "s|^${k}=.*|${k}=${v}|" .env
  else
    echo "${k}=${v}" >> .env
  fi
}

write_env "APP_NAME" "逐风授权码管理平台"
write_env "APP_ENV" "production"
write_env "APP_DEBUG" "false"
write_env "APP_KEY" "$APP_KEY"
write_env "APP_URL" "https://${DOMAIN}"
write_env "DB_DATABASE" "$DB_NAME"
write_env "DB_USERNAME" "$DB_USER"
write_env "DB_PASSWORD" "$DB_PASSWORD"
write_env "DB_HOST" "127.0.0.1"
write_env "REDIS_PASSWORD" "$REDIS_PASSWORD"
write_env "CACHE_STORE" "redis"
write_env "SESSION_DRIVER" "redis"
write_env "QUEUE_CONNECTION" "database"
write_env "ZF_APP_ENCRYPT_KEY" "$ZF_APP_ENCRYPT_KEY"
write_env "ZF_APP_ENCRYPT_KEY_PREV" "$ZF_APP_ENCRYPT_KEY_PREV"
write_env "LICENSING_KEY_HMAC_SECRET" "$LICENSING_KEY_HMAC_SECRET"
write_env "LICENSING_FINGERPRINT_SALT" "$LICENSING_FINGERPRINT_SALT"
write_env "LICENSING_AUDIT_HMAC_SECRET" "$LICENSING_AUDIT_HMAC_SECRET"
write_env "LICENSING_REPLAY_CLIENT_SECRET" "$LICENSING_REPLAY_CLIENT_SECRET"
write_env "ZF_ADMIN_PATH" "$ZF_ADMIN_PATH"
write_env "LICENSING_TRUST_PROXIES" "true"
chmod 600 .env

# ----------------------------- 5. 依赖安装与密钥生成 -------------------------
log "5/10 composer install（锁版 --no-dev）..."
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
[ -f composer.lock ] && composer audit --locked --no-interaction || warn "composer audit 未执行或存在风险，请人工核查依赖"

log "生成 Ed25519 / HMAC / 指纹盐（仅补缺不覆盖）..."
php artisan license:keys --write --ansi || die "license:keys 失败"

# ----------------------------- 6. V1.30 签名模块适配校验 ---------------------
# 仅确认公钥/签名池配置存在即可，严禁生成或触碰私钥（桌面密钥1.key/密钥2.key 由人工保管）。
log "6/10 适配 V1.30 签名模块（仅确认公钥存在，禁止触碰私钥）..."
if [ -f "config/license_signature_guard.php" ]; then
  if php -r '
    $root = getcwd();
    $cfg  = require "config/license_signature_guard.php";
    $keys = $cfg["public_keys"] ?? [];
    $ok = 0; $miss = [];
    foreach ($keys as $rel) {
      if (is_string($rel) && is_file($root . DIRECTORY_SEPARATOR . str_replace("/", DIRECTORY_SEPARATOR, $rel))) { $ok++; }
      else { $miss[] = (string) $rel; }
    }
    echo "[签名模块] 配置公钥 " . count($keys) . " 份，已就绪 " . $ok . " 份" . PHP_EOL;
    exit(count($keys) > 0 && $ok === count($keys) ? 0 : 1);
  '; then
    log "签名模块公钥就绪（仅校验，未生成/触碰任何私钥）"
  else
    warn "公钥文件缺失，请确认仓库完整拉取（V1.30 签名守护将拒绝未签名请求）"
  fi
else
  warn "未找到 config/license_signature_guard.php，请确认仓库包含 V1.30 签名模块"
fi

# V1.31 部署链补全：签名守护配置缺失时幂等兜底初始化。
# 已初始化（配置存在且 public_keys 非空）一律跳过，绝不触碰既有公私钥；
# 仅在 config/license_signature_guard.php 缺失时由 zf:signature:init 生成。
if [ -f "config/license_signature_guard.php" ] && php -r '$cfg = @include "config/license_signature_guard.php"; exit(is_array($cfg) && ! empty($cfg["public_keys"]) ? 0 : 1);'; then
  log "版权签名守护配置就绪，跳过 zf:signature:init。"
else
  log "未检测到版权签名守护配置，执行 zf:signature:init 兜底初始化。"
  php artisan zf:signature:init --ansi || warn "zf:signature:init 执行失败，请人工检查签名守护配置"
fi

log "生成 Laravel 应用密钥（幂等）..."
php artisan key:generate --force --ansi || true

# ----------------------------- 7. 迁移与缓存 --------------------------------
log "7/10 数据库迁移与缓存预热..."
php artisan migrate --force --ansi || die "数据库迁移失败，请检查 MySQL 连接"
php artisan config:cache --ansi
php artisan route:cache --ansi
php artisan view:cache --ansi
php artisan storage:link || true
chown -R www-data:www-data storage bootstrap/cache || true
chmod -R ug+rw storage bootstrap/cache || true

# ----------------------------- 8. 创建管理员 ---------------------------------
log "8/10 创建随机管理员账号（密码 ≥16 位，含 3 类字符）..."
if [ -n "$ADMIN_EMAIL" ]; then
  php artisan zf:init-admin --force --ansi 2>/dev/null || php artisan zf:init-admin --ansi
else
  php artisan zf:init-admin --ansi
fi

CRED_FILE="storage/app/init-admin-credentials.txt"
if [ -f "$CRED_FILE" ]; then
  ADMIN_USER="$(grep -E '用户名|username' "$CRED_FILE" | head -n1 | cut -d: -f2- | tr -d ' ' || true)"
  ADMIN_PASS="$(grep -E '密码|password' "$CRED_FILE" | head -n1 | cut -d: -f2- | tr -d ' ' || true)"
  ADMIN_EMAIL_SHOW="$(grep -E '邮箱|email' "$CRED_FILE" | head -n1 | cut -d: -f2- | tr -d ' ' || true)"
fi
: "${ADMIN_USER:=（见 ${CRED_FILE}）}"
: "${ADMIN_PASS:=（见 ${CRED_FILE}）}"

# ----------------------------- 9. Nginx 站点 ---------------------------------
log "9/10 配置 Nginx 站点（TLS1.2/1.3 + 隐藏版本 + 敏感文件 deny + HSTS）..."
mkdir -p /etc/nginx/sites-available /etc/nginx/sites-enabled /etc/nginx/certs

if [ ! -f /etc/nginx/certs/tls.crt ]; then
  openssl req -x509 -nodes -newkey rsa:2048 -days 365 \
    -keyout /etc/nginx/certs/tls.key -out /etc/nginx/certs/tls.crt \
    -subj "/C=CN/ST=License/O=Zhufeng License/CN=${DOMAIN}" >/dev/null 2>&1
  warn "已生成自签证书（浏览器将提示不受信任）。生产建议 --letsencrypt 使用正式证书。"
fi

cat > /etc/nginx/sites-available/zhufeng-license.conf <<NGINX
# 逐风授权码管理平台 - Nginx（TLS 1.2/1.3 only + HSTS + 隐藏版本）
map \$uri \$zf_csp {
    default "default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; connect-src 'self'; object-src 'none'; frame-ancestors 'none'; base-uri 'none'";
    ~^/api "default-src 'none'; base-uri 'none'; frame-ancestors 'none'";
}
server {
    listen 80;
    server_name ${DOMAIN};
    return 301 https://\$host\$request_uri;
}
server {
    listen 443 ssl;
    http2 on;
    server_name ${DOMAIN};

    ssl_certificate     /etc/nginx/certs/tls.crt;
    ssl_certificate_key /etc/nginx/certs/tls.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers 'ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305';
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;
    server_tokens off;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" always;

    root ${INSTALL_DIR}/public;
    index index.php;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "DENY" always;
    add_header Referrer-Policy "no-referrer" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Content-Security-Policy \$zf_csp always;

    charset utf-8;
    client_max_body_size 4M;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }
    location ~ \.php\$ {
        fastcgi_pass unix:/run/php/php${PHP_VER}-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        fastcgi_param X-Forwarded-Proto https;
    }
    location ~ /\.(?!well-known).* { deny all; }
    location ~* \.(env|log|git|pem|key)\$ { deny all; }
    error_page 404 /index.php;
}
NGINX

ln -sf /etc/nginx/sites-available/zhufeng-license.conf /etc/nginx/sites-enabled/zhufeng-license.conf
rm -f /etc/nginx/sites-enabled/default
nginx -t || die "Nginx 配置校验失败"
systemctl reload nginx

if [ "$USE_LETSENCRYPT" = "1" ]; then
  log "申请 Let's Encrypt 正式证书..."
  apt-get install -y certbot python3-certbot-nginx >/dev/null 2>&1 || true
  certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "${ADMIN_EMAIL:-admin@${DOMAIN}}" --redirect || warn "certbot 申请失败，请手动执行 certbot --nginx -d ${DOMAIN}"
fi

if [ "$IS_BT" = "1" ]; then
  warn "宝塔模式：Nginx 站点已写入 /etc/nginx/sites-available/zhufeng-license.conf"
  warn "如宝塔面板接管 Nginx，请将上述配置导入宝塔【网站】或手动在宝塔站点中添加反向代理指向 ${INSTALL_DIR}/public"
fi

# ----------------------------- 9. 输出凭据 -----------------------------------
systemctl enable php${PHP_VER}-fpm nginx mysql redis-server 2>/dev/null || true
systemctl restart php${PHP_VER}-fpm nginx mysql redis-server 2>/dev/null || true

log "部署完成"
echo ""
echo "====================================================================="
echo "  逐风授权码管理平台 V1.30 部署完成 —— 随机三要素，请立即保存"
echo "====================================================================="
echo "  [1] 后台访问地址 : https://${DOMAIN}/${ZF_ADMIN_PATH}/login"
echo "  [2] 管理员用户名 : ${ADMIN_USER}"
echo "  [3] 管理员密码   : ${ADMIN_PASS}"
echo "---------------------------------------------------------------------"
echo "  管理员邮箱   : ${ADMIN_EMAIL_SHOW:-${ADMIN_EMAIL:-（见凭据文件）}}"
echo "---------------------------------------------------------------------"
echo "  数据库名     : ${DB_NAME}"
echo "  数据库用户   : ${DB_USER}"
echo "  数据库口令   : ${DB_PASSWORD}"
echo "  MySQL root   : ${MYSQL_ROOT_PASSWORD}（/root/zf-deploy-mysql.sql）"
echo "  Redis 口令   : ${REDIS_PASSWORD}"
echo "---------------------------------------------------------------------"
echo "  APP_KEY      : ${APP_KEY}"
echo "  ZF_APP_ENCRYPT_KEY     : ${ZF_APP_ENCRYPT_KEY}"
echo "  ZF_APP_ENCRYPT_KEY_PREV: ${ZF_APP_ENCRYPT_KEY_PREV}"
echo "  LICENSING_KEY_HMAC_SECRET: ${LICENSING_KEY_HMAC_SECRET}"
echo "  LICENSING_FINGERPRINT_SALT: ${LICENSING_FINGERPRINT_SALT}"
echo "  LICENSING_AUDIT_HMAC_SECRET: ${LICENSING_AUDIT_HMAC_SECRET}"
echo "  LICENSING_REPLAY_CLIENT_SECRET: ${LICENSING_REPLAY_CLIENT_SECRET}"
echo "====================================================================="
echo "  [重要] 上述凭据仅本次输出，请复制保存到密码管理器；"
echo "         首次登录后请在【个人中心】立即修改管理员密码。"
echo "         源码目录：${INSTALL_DIR}；应用日志：${INSTALL_DIR}/storage/logs"
echo "====================================================================="

exit 0
