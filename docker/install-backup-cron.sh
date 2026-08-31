#!/bin/sh
# =============================================================
# 逐风授权码管理平台 - 一键安装宿主机每日备份定时任务（V1.31 部署链补全）
# 在宿主机（非容器内）执行，将 docker/backup.sh 注册为每日 cron 定时任务，
# 按 BACKUP_RETENTION_DAYS 保留天数自动清理过期备份。
#
# 用法：
#   ./docker/install-backup-cron.sh
#
# 可选环境变量：
#   BACKUP_CRON_TIME=30 2 * * *      # 执行时刻（默认每天 02:30）
#   BACKUP_DIR=/opt/zf-backups        # 备份输出目录（默认 /opt/zf-backups）
#   BACKUP_RETENTION_DAYS=7           # 保留天数（默认 7）
#   BACKUP_LOG_FILE=/var/log/zf-backup.log   # cron 日志（默认 /var/log/zf-backup.log）
#   DB_HOST=127.0.0.1  DB_PORT=3306  DB_USERNAME=zhufeng  DB_DATABASE=zhufeng_license
#
# 安全说明（等保 ZF-2026-001）：
#   - 数据库口令严禁明文传入；请提前配置 /root/.my.cnf（chmod 600）或 MYSQL_PWD。
#   - 幂等：重复执行按脚本路径去重，不会产生重复 cron 条目。
# =============================================================
set -e

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
BACKUP_SH="$SCRIPT_DIR/backup.sh"

CRON_TIME="${BACKUP_CRON_TIME:-30 2 * * *}"
BACKUP_DIR="${BACKUP_DIR:-/opt/zf-backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"
LOG_FILE="${BACKUP_LOG_FILE:-/var/log/zf-backup.log}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="${DB_USERNAME:-zhufeng}"
DB_DATABASE="${DB_DATABASE:-zhufeng_license}"

if [ ! -f "$BACKUP_SH" ]; then
    echo "[install-backup-cron] 错误：未找到 $BACKUP_SH" >&2
    exit 1
fi
chmod +x "$BACKUP_SH"

if ! command -v crontab >/dev/null 2>&1; then
    echo "[install-backup-cron] 错误：未检测到 crontab，请先安装 cron（如 apt install cron / yum install cronie）并确保服务运行。" >&2
    exit 1
fi

# 幂等安装：先剔除指向同一 backup.sh 的旧条目（按脚本路径去重），再写入新条目
CRON_LINE="$CRON_TIME BACKUP_DIR='$BACKUP_DIR' BACKUP_RETENTION_DAYS=$RETENTION_DAYS DB_HOST='$DB_HOST' DB_PORT=$DB_PORT DB_USERNAME='$DB_USERNAME' DB_DATABASE='$DB_DATABASE' $BACKUP_SH >> $LOG_FILE 2>&1"

( crontab -l 2>/dev/null | grep -vF "$BACKUP_SH" | grep -v '^#' | grep -v '^$'; echo "$CRON_LINE" ) | crontab -

echo "[install-backup-cron] 已安装每日备份定时任务（宿主机 cron）："
echo "    $CRON_LINE"
echo ""
echo "[install-backup-cron] 当前 crontab："
crontab -l
echo ""
echo "[install-backup-cron] 提示："
echo "    - 数据库口令请配置 /root/.my.cnf（chmod 600）或 MYSQL_PWD，禁止明文 -p 传参。"
echo "    - 手动验证：BACKUP_DIR='$BACKUP_DIR' BACKUP_RETENTION_DAYS=$RETENTION_DAYS $BACKUP_SH"
echo "    - 查看日志：tail -f $LOG_FILE"
