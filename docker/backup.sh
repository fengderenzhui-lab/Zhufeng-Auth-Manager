#!/bin/sh
# =============================================================
# 逐风授权码管理平台 - 每日数据库备份脚本（等保 M-03 修复）
# 用法（宿主机 cron 示例，每天 02:30）：
#   30 2 * * * BACKUP_DIR=/opt/zf-backups DB_HOST=127.0.0.1 DB_PORT=3306 \
#     DB_USERNAME=zhufeng DB_DATABASE=zhufeng_license \
#     /path/to/逐风授权码管理平台/docker/backup.sh >> /var/log/zf-backup.log 2>&1
#
# 安全说明（等保 ZF-2026-001 修复：禁止明文口令传参）：
#   - 严禁在 cron / 命令行中以 -p"$DB_PASSWORD" 方式传密码，会通过 ps 泄露。
#   - 数据库口令来源（二选一，推荐 my.cnf）：
#     1) /root/.my.cnf（或 $HOME/.my.cnf），权限必须 600，格式：
#        [client]
#        user=zhufeng
#        password=你的DB密码
#        创建：printf '[client]\nuser=zhufeng\npassword=你的DB密码\n' > /root/.my.cnf && chmod 600 /root/.my.cnf
#     2) 环境变量 MYSQL_PWD（仅限 cron 行内单次注入，勿写入 shell 历史/日志）
#   - Docker 部署时也可在宿主机执行（不经容器内口令）：
#       docker compose exec -T mysql sh -c 'exec mysqldump --single-transaction zhufeng_license'
# =============================================================
set -e

BACKUP_DIR="${BACKUP_DIR:-./backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="${DB_USERNAME:-zhufeng}"
DB_DATABASE="${DB_DATABASE:-zhufeng_license}"

# 凭据解析：优先 ~/.my.cnf（权限 600），其次 MYSQL_PWD 环境变量；绝不允许明文 -p 参数
MY_CNF=""
if [ -n "$HOME" ] && [ -f "$HOME/.my.cnf" ] && [ -r "$HOME/.my.cnf" ]; then
    MY_CNF="$HOME/.my.cnf"
elif [ -f /root/.my.cnf ] && [ -r /root/.my.cnf ]; then
    MY_CNF=/root/.my.cnf
fi

if [ -n "$MY_CNF" ]; then
    # 权限必须为 600（或更严格 400/500），否则拒绝执行
    PERM=$(stat -c %a "$MY_CNF" 2>/dev/null || stat -f %Lp "$MY_CNF" 2>/dev/null || echo "")
    case "$PERM" in
        400|500|600) ;;
        *)
            echo "[backup] 错误: $MY_CNF 权限必须为 600（当前 $PERM）。请执行 chmod 600 $MY_CNF 后重试。" >&2
            exit 1
            ;;
    esac
elif [ -z "$MYSQL_PWD" ]; then
    echo "[backup] 错误: 未找到数据库口令。请配置 /root/.my.cnf（chmod 600）或 MYSQL_PWD 环境变量；禁止在命令行明文传密码。" >&2
    exit 1
fi

STAMP=$(date +%Y%m%d_%H%M%S)
mkdir -p "$BACKUP_DIR"

OUT_FILE="$BACKUP_DIR/${DB_DATABASE}_${STAMP}.sql.gz"

# 口令仅从 my.cnf / MYSQL_PWD 读取，命令参数不出现密码明文（ps 不可见）
if [ -n "$MY_CNF" ]; then
    mysqldump --defaults-extra-file="$MY_CNF" -h "$DB_HOST" -P "$DB_PORT" \
        --single-transaction --routines --triggers "$DB_DATABASE" | gzip > "$OUT_FILE"
else
    mysqldump -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" \
        --single-transaction --routines --triggers "$DB_DATABASE" | gzip > "$OUT_FILE"
fi

echo "[backup] ok: $OUT_FILE ($(du -h "$OUT_FILE" | cut -f1))"

# 保留 N 天
find "$BACKUP_DIR" -name "${DB_DATABASE}_*.sql.gz" -mtime +"$RETENTION_DAYS" -delete
echo "[backup] retention: ${RETENTION_DAYS} 天"
