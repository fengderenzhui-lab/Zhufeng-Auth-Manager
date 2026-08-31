# 逐风授权码管理平台

基于 PHP 8.2+ / Laravel 11 / MySQL 8.0+ 的自托管授权码管理平台，提供**授权码全生命周期**、**一机一码**、**强制在线心跳**、**管理员分级**、**登录安全**、**审计日志**与**接口风控**，遵循等保 2.0 一级安全要求。项目包含**后端 REST API** 与**雷池风格双主题管理后台**（深色/浅色，Laravel Blade + 原生 JS + 本地化 Chart.js，无构建链、无 CDN），支持 **Docker Compose 一键部署**（entrypoint 全自动密钥初始化）。

> 

## 一、技术栈

| 层 | 选型 |
|---|---|
| 语言 / 框架 | PHP 8.2+ / Laravel 11 |
| 数据库 | MySQL 8.0+（utf8mb4） |
| 缓存 / 限流 | Redis（phpredis / predis 均可，CACHE_STORE=redis） |
| 加密体系 | Argon2id（密码哈希）/ AES-256-GCM（敏感字段）/ Ed25519（签名）/ HMAC-SHA256（防重放与索引）/ PASETO v4.public（客户端令牌） |
| 前端 | Blade + 原生 JS + Chart.js 4.4.3（本地化），深色/浅色双主题（CSS 变量） |
| 部署 | Docker Compose（php8.2-fpm + nginx + mysql8 + redis，TLS1.2/1.3 + HSTS） |

---

## 二、目录结构

```
逐风授权码管理平台/
├── app/
│   ├── Console/Commands/          # license:keys / license:expire / license:clean / zf:admin-path / zf:init-admin
│   ├── Enums/                     # LicenseStatus / Role / AuditAction
│   ├── Http/
│   │   ├── Controllers/           # Sdk / Auth / LicenseAdmin / Product / User / Audit / Setting / Stats / PublicKey / LicensingV1
│   │   ├── Middleware/            # AuthenticateAdmin / AuthorizeRole / ReplayProtect / ForceJson / SecurityHeaders
│   │   └── Requests/              # 全部 FormRequest 参数校验（DTO）
│   ├── Models/                    # User / ApiToken / LoginAttempt / Product / License / Device / Heartbeat / AuditLog / Setting
│   ├── Providers/                 # AppServiceProvider（服务单例 + RateLimiter 独立桶）
│   ├── Services/                  # License / Heartbeat / DeviceFingerprint / Ed25519 / Audit / Auth /
│   │                              #   ReplayGuardService（防重放）/ PasetoService（令牌）/ AesGcmService（字段加密）/ CustomerNgramService（n-gram 模糊检索）
│   └── Support/                   # ApiResponse / CanonicalJson
├── bootstrap/                     # app.php（中间件别名注册）
├── config/                        # license.php（全量业务配置，admin.path 白名单校验）
├── database/
│   ├── migrations/                # 10+ 张表迁移（含 encryption_upgrade 加密回填；V1.30 新增 customer n-gram 盲索引 / 审计哈希链回填 / users.email 加密，均幂等）
│   └── seeders/                   # 超管初始化（密码走 .env）
├── routes/                        # api.php（v1 全量路由 + licensing/v1 客户端对接）
├── docker/
│   ├── entrypoint.sh              # 密钥链初始化 -> migrate -> zf:init-admin -> php-fpm
│   └── nginx/                     # TLS/HSTS/CSP（按 URI 区分 API 与页面）
├── resources/views/               # layouts(app/sidebar) / dashboard / keys / settings / auth/login / 业务页面
├── public/                        # js(app/theme/pages/*) / css(app.css 双主题) / vendor/chartjs
├── Dockerfile / docker-compose.yml
├── .env.example
└── README.md
```

---

## 三、快速部署

### 3.1 Docker Compose 一键部署（V1.30 全自动初始化链）

```bash
# 1. 准备环境（首次）
cp .env.example .env
#    编辑 .env 至少填写以下两项（其余可使用默认值，全部密钥由容器自动生成）：
#    APP_URL=https://www.example.com（生产必须 HTTPS；APP_FORCE_HTTPS=true 时应用层强制跳转）
#    DB_PASSWORD=...                # MySQL 业务账号密码（≥12 位）
#    MYSQL_ROOT_PASSWORD=...        # MySQL root 密码（容器健康检查依赖）

# 2. 准备 TLS 证书（nginx 仅启用 TLSv1.2/1.3）
#    将证书放入 ./certs/tls.crt 与 ./certs/tls.key（无证书可用自签，生产建议权威证书）

# 3. 构建并一键启动
docker compose up -d --build

# 4. 等待初始化完成（entrypoint 自动执行完整密钥链，见下）
docker compose logs -f app
#    看到 "[entrypoint] 启动 PHP-FPM" 即就绪

# 5. 获取随机管理员凭据（首次部署自动生成，0600 权限，取用后删除）
docker compose exec app cat storage/app/init-admin-credentials.txt

# 6. 获取随机后台访问路径（首次部署自动随机化，如 /zf-8k2x9q）
docker compose exec app grep '^ZF_ADMIN_PATH' .env
#    浏览器打开 https://localhost:8443/{随机路径}/login（HTTPS_PORT 默认 8443）

# 7. 定时任务（过期/心跳超时批量失效 + 日志清理）
docker compose exec app php artisan schedule:work
#   或 crontab: * * * * * php /var/www/html/artisan schedule:run
```

**entrypoint 自动初始化链（幂等，可重复执行，已配置的密钥一律跳过）**：

| 步骤 | 内容 | 说明 |
|---|---|---|
| 1 | 恢复/生成 `.env` | 从持久卷 `env-data` 恢复；首次从 `.env.example` 生成 |
| 2 | `APP_KEY` | 缺失自动 `key:generate` |
| 3 | `ZF_APP_ENCRYPT_KEY` | AES-256-GCM 敏感字段密钥（32 字节 Base64；加密 customer/email/审计等敏感字段，缺失自动生成） |
| 4 | Ed25519 密钥对 + 授权码 HMAC + 指纹盐 | `php artisan license:keys --write`，仅补缺不覆盖 |
| 5 | `LICENSING_REPLAY_CLIENT_SECRET` | 客户端防重放签名密钥，缺失自动生成 |
| 6 | `ZF_ADMIN_PATH` | 仍为默认 `admin` 时自动随机化（如 `zf-8k2x9q`） |
| 7 | 数据库迁移 | `php artisan migrate --force`（幂等） |
| 8 | 随机管理员初始化 | `php artisan zf:init-admin`，已有超管自动跳过 |
| 8.5 | 版权签名守护初始化 | 镜像内置签名公钥与 `config/license_signature_guard.php` 时自动跳过；配置缺失时 `zf:signature:init` 幂等兜底生成（不触碰任何公私钥） |
| 9 | 持久化 `.env` | 回写 `env-data` 卷，重启容器密钥不丢失 |

> 说明：
> - 容器首次启动自动完成上述全部步骤；`.env` 通过目录卷 `env-data:/var/www/html/env-data` 持久化（entrypoint 恢复/回写），重建容器不丢失密钥。
> - 环境变量（`env_file` / `environment`）优先级高于 `.env` 文件：`DB_HOST=mysql`、`REDIS_HOST=redis` 由 compose 注入，无需手工修改。
> - 停止/清理：`docker compose down`（保留数据卷）；如需彻底重置：`docker compose down -v`（**会删除数据库、Redis 与全部密钥，慎用**）。

### 3.2 手动部署

- 要求：PHP 8.2+（ext-sodium / ext-pdo_mysql / ext-bcmath / ext-redis 等）、Composer 2、MySQL 8.0+、Redis

```bash
composer install
cp .env.example .env
# 编辑 .env：DB_* / MYSQL_ROOT_PASSWORD / APP_URL=https://你的域名（生产必须 HTTPS；APP_FORCE_HTTPS=true 时应用层强制跳转）
php artisan key:generate
php artisan license:keys --write          # 生成 Ed25519 密钥对 / 授权码 HMAC / 指纹盐并写入 .env
# 【等保 H-03 启动前校验】手动部署时必须确认以下密钥均已替换占位符/非空，
#   生产环境（APP_ENV=production）存在 change-me-* 占位符时应用将拒绝启动（fail-closed）：
#   LICENSING_REPLAY_CLIENT_SECRET、LICENSING_FINGERPRINT_SALT、LICENSING_AUDIT_HMAC_SECRET
#   生成方式：php -r "echo bin2hex(random_bytes(32));"
php artisan zf:admin-path --write         # （可选）生成随机后台路径
# ZF_APP_ENCRYPT_KEY 未配置时由迁移自动生成写入 .env
php artisan migrate --force
php artisan zf:signature:init             # （可选）首次生成版权签名守护公钥/签名池，已初始化自动跳过
php artisan zf:init-admin                 # 首次生成随机管理员账号
php artisan serve                         # 或使用 nginx + php-fpm 部署
```

---

## 四、初始化密钥（重要）

> v1.1.0 起：Docker 部署时 entrypoint 自动完成全部密钥生成（见 3.1 初始化链），无需手工操作；手动部署时按下方命令执行。

### 4.1 手工生成全部密钥

```bash
# 1. Ed25519 密钥对 + 授权码 HMAC 索引密钥 + 指纹盐（--write 自动写入 .env，仅补缺不覆盖）
php artisan license:keys --write

# 2. AES-256-GCM 敏感字段密钥（32 字节明文密钥的纯 Base64）
php -r "echo 'ZF_APP_ENCRYPT_KEY='.base64_encode(random_bytes(32));"

# 3. 客户端防重放签名密钥（与集成方共享）
php -r "echo 'LICENSING_REPLAY_CLIENT_SECRET='.bin2hex(random_bytes(32));"

# 4. （可选）随机后台访问路径
php artisan zf:admin-path --write
```

### 4.2 密钥清单

| 配置项 | 用途 | 安全性要求 |
|---|---|---|
| `LICENSING_ED25519_PRIVATE_KEY` | Ed25519 私钥（Base64），签名激活/心跳响应与客户端令牌 | 仅存 .env，权限 0600，严禁入库/入仓库 |
| `LICENSING_ED25519_PUBLIC_KEY` | 公钥，随激活响应与 `/public-key` 端点下发 | 可安全公开 |
| `LICENSING_KEY_HMAC_SECRET` | 授权码索引 HMAC 密钥（派生 key_hash 与校验字符） | 泄露将导致授权码可离线爆破 |
| `LICENSING_FINGERPRINT_SALT` | 设备指纹加盐 | 泄露将导致指纹可预测 |
| `ZF_APP_ENCRYPT_KEY` | AES-256-GCM 敏感字段密钥（32 字节 Base64），加密设备指纹/管理员 HMAC 密钥/customer/email/审计敏感字段 | 仅存 .env，严禁入库/入仓库 |
| `LICENSING_REPLAY_CLIENT_SECRET` | 客户端 SDK 防重放签名密钥（HMAC-SHA256） | 与集成方共享，泄露仅影响防重放 |
| `LICENSING_AUDIT_HMAC_SECRET` | 审计日志哈希链密钥（等保 M-02，HMAC-SHA256 防篡改） | 泄露将导致审计链可被伪造，仅存 .env |
| `ZF_ADMIN_PATH` | 随机后台访问前缀（如 `zf-8k2x9q`），默认 `admin` | 生产建议随机化，降低后台暴露面 |

> 授权码**明文不落库**：数据库仅存 `key_hash = HMAC-SHA256(LICENSING_KEY_HMAC_SECRET, key)`；明文 key 仅在生成接口与激活回执中一次性返回。
> 存量明文敏感字段（设备指纹、管理员 HMAC 密钥）由迁移 `encryption_upgrade` 自动加密回填（幂等，可重复执行，跳过已加密行）。
> V1.30 追加：`users.email` 加密 + `email_sha256` 唯一盲索引回填；`audit_logs` 哈希链按 id 顺序全链重算补全（`zf:audit-verify` 可校验）；`licenses.customer` 新增 n-gram 盲索引回填，恢复安全模糊检索。

### 4.3 密钥轮换流程（等保 ZF-2026-006）

AES 敏感字段密钥（`ZF_APP_ENCRYPT_KEY`）轮换采用"旧密钥回退 + 渐进回写"机制，可零停机完成：

1. 生成新密钥并追加配置旧密钥：
   ```bash
   # 生成新 32 字节 Base64 密钥
   php -r "echo base64_encode(random_bytes(32));"
   # .env：ZF_APP_ENCRYPT_KEY=新密钥 / ZF_APP_ENCRYPT_KEY_PREV=旧密钥
   ```
2. 重启应用（php-fpm/容器）。`AesGcmService::decrypt` 对旧密钥密文自动回退解密；需要升级历史密文时，可在读改写路径手动解密后以当前密钥重新加密。
3. 数据回写完成后，移除 `ZF_APP_ENCRYPT_KEY_PREV` 并再次重启，轮换结束。

> 其它密钥（`APP_KEY` / `LICENSING_KEY_HMAC_SECRET` / Ed25519 私钥）轮换会导致历史授权码验证、令牌失效，须在维护窗口内整体处理并重新下发授权，**严禁在运行中单独轮换**。`LICENSING_KEY_HMAC_SECRET` 缺失或为 `change-me` 时应用拒绝启动（fail-closed，ZF-2026-014）。

---

## 五、API 文档

### 5.1 通用约定

- Base URL：`/api/v1`
- 统一响应信封：

```json
{
  "success": true,
  "code": 0,
  "message": "ok",
  "data": { },
  "meta": null,
  "server_time": 1785200000
}
```

- SDK 端点（activate / heartbeat / verify / deactivate）的 `data` 载荷带 Ed25519 签名：

```json
{
  "success": true,
  "code": 0,
  "message": "ok",
  "data": { "...": "..." },
  "signature": "Base64...",
  "signature_algorithm": "ed25519",
  "server_time": 1785200000
}
```

- 管理端点鉴权：`Authorization: Bearer <token>`（登录返回的状态化 Token，仅存 SHA-256 哈希于库）

### 5.2 防重放请求头（SDK 与管理端点均要求）

| 请求头 | 说明 |
|---|---|
| `X-Timestamp` | Unix 秒级时间戳，须在 ±`LICENSING_REPLAY_WINDOW`(默认300s) 内 |
| `X-Nonce` | 每次请求随机串（16-128 字符），缓存去重防重放 |
| `X-Signature` | `HMAC-SHA256(clientSecret, "METHOD\n路径\n时间戳\nnonce\n原始请求体")`；strict 模式必须携带 |

### 5.3 公开端点

| 方法 | 路径 | 说明 | 限流 |
|---|---|---|---|
| POST | `/api/v1/auth/login` | 管理员登录（错误归一化防枚举 + 失败锁定） | `login` |
| GET | `/api/v1/public-key` | 下发 Ed25519 公钥（客户端验签用） | `client` |
| POST | `/api/v1/licenses/activate` | 激活授权码并绑定设备（一机一码） | `client` |
| POST | `/api/v1/licenses/heartbeat` | 心跳上报（强制在线刷新） | `client` |
| POST | `/api/v1/licenses/verify` | 在线验证（含心跳超时判定） | `client` |
| POST | `/api/v1/licenses/deactivate` | 解绑当前设备 | `client` |

**SDK 激活/验证请求体**：

```json
{
  "key": "ZF-XXXXX-XXXXX-XXXXX-XXXXX-XXXXX-C",
  "signals": "<base64(JSON{ cpu_id, machine_id, mac_address, disk_serial, system_uuid })>",
  "device_name": "客户电脑01"
}
```

- `signals`：客户端仅采集**原始硬件信息**并 base64 上报，**指纹由服务端加盐 HMAC 计算**，禁止客户端自定义 fingerprint。
- 错误归一化：授权码不存在 / 已过期 / 已吊销 / 已拉黑统一返回 `授权码无效或不可用`，**不暴露 key 是否存在**（防枚举）。

**激活响应 data 示例**：

```json
{
  "license_id": 1,
  "device_id": 10,
  "device_name": "客户电脑01",
  "activated_at": "2026-08-27T10:00:00+08:00"
}
```

**验证/心跳响应 data 示例**：

```json
{
  "valid": true,
  "reason": "ok",
  "license_id": 1,
  "status": "active",
  "product": "my-app",
  "unverified_customer": false,
  "expires_at": "2027-08-27T00:00:00+08:00",
  "max_devices": 1,
  "bound_devices": 1,
  "features": { "pro": true },
  "enforce_online": true,
  "heartbeat_interval": 60
}
```

> 等保 ZF-2026-013：verify/validate 响应**不再下发客户名明文**，仅返回布尔 `unverified_customer`（true=客户名未登记，false=已登记）。客户端不得依赖明文 customer 做展示；需要客户名请在管理后台查看。

### 5.4 客户端对接端点（`/api/licensing/v1`，对齐 masterix21/laravel-licensing-client）

> 统一信封 `{success,data}` / `{success:false,error:{code,message}}`；路由层叠加 HMAC strict 防重放 + PASETO/Ed25519 令牌（详见 `LicensingV1Controller`）。

| 方法 | 路径 | 说明 | 限流 |
|---|---|---|---|
| GET | `/api/licensing/v1/health` | 健康检查 | `licensing` |
| POST | `/api/licensing/v1/activate` | 激活授权码并绑定设备（事务 + 行锁，防并发超绑） | `licensing-register` |
| POST | `/api/licensing/v1/deactivate` | 解绑当前设备 | `licensing-register` |
| POST | `/api/licensing/v1/refresh` | 验指纹后重签令牌 | `licensing-token` |
| POST | `/api/licensing/v1/heartbeat` | 心跳上报（更新设备/授权最后在线时间） | `licensing-validate` |
| POST | `/api/licensing/v1/validate` | 在线校验（状态 + 指纹 + 心跳在线，返回新签令牌） | `licensing-validate` |
| POST | `/api/licensing/v1/licenses/show` | 授权详情 | `licensing-validate` |

**请求体**：`{ "license_key": "...", "fingerprint": "<sha256 指纹字符串>", "metadata": { "hostname": "..." } }`

> 等保 ZF-2026-002：可选字段 `signals`（base64(JSON) 的原始硬件信号，如 CPU/MAC/UUID/磁盘序列号）由**服务端** `DeviceFingerprintService` 计算指纹并覆盖 `fingerprint`，客户端无法伪造/预测。**已知风险声明**：若客户端 SDK 不支持上报 `signals`，则指纹仍由客户端自算上报，存在被绕过/克隆风险（等同"软指纹"），高安全场景请务必升级 SDK 支持 `signals` 上报，或叠加 TPM/硬件绑定。

**错误码**（客户端 `mapRequestException` 对齐，归一化防枚举）：`INVALID_LICENSE_KEY`(404)、`FINGERPRINT_MISMATCH`(403)、`FINGERPRINT_CONFLICT`(409)、`USAGE_LIMIT_EXCEEDED`(409)、`LICENSE_EXPIRED`(410)、`CANCELLED_LICENSE`(423)、`LICENSE_SUSPENDED`(423)、限流(429)。

**激活/刷新/校验响应 data**：`token`（PASETO v4.public，含 usage_fingerprint 绑定）、`license`、`public_key_bundle`、`refresh_after`、`device_id`、`max_usages`。

### 5.5 管理端点（`Authorization: Bearer`）

| 方法 | 路径 | 权限 | 说明 |
|---|---|---|---|
| GET | `/api/v1/admin/me` | 管理员 | 当前登录人 |
| POST | `/api/v1/admin/logout` | 管理员 | 登出（销毁当前 Token） |
| POST | `/api/v1/admin/licenses/generate` | 管理员 | 批量生成授权码（明文仅本次返回） |
| GET | `/api/v1/admin/licenses` | 管理员 | 授权码列表（状态/产品/客户/完整 key 关键词过滤） |
| GET | `/api/v1/admin/licenses/{id}` | 管理员 | 授权码详情（含绑定设备） |
| GET | `/api/v1/admin/licenses/{id}/devices` | 管理员 | 设备列表 |
| POST | `/api/v1/admin/licenses/{id}/renew` | 管理员 | 续期 |
| POST | `/api/v1/admin/licenses/{id}/revoke` | 管理员 | 远程吊销（可恢复） |
| POST | `/api/v1/admin/licenses/{id}/restore` | 管理员 | 恢复已吊销 |
| POST | `/api/v1/admin/licenses/{id}/blacklist` | 超级管理员 | 永久拉黑 |
| POST | `/api/v1/admin/licenses/batch/revoke` | 超级管理员 | 批量吊销（≤200 个） |
| GET | `/api/v1/admin/products` | 管理员 | 产品列表 |
| POST | `/api/v1/admin/products` | 超级管理员 | 新建产品 |
| PATCH | `/api/v1/admin/products/{id}` | 超级管理员 | 编辑产品 |
| DELETE | `/api/v1/admin/products/{id}` | 超级管理员 | 删除产品（存在授权码时拒绝） |
| GET | `/api/v1/admin/stats` | 管理员 | 仪表盘统计 |
| GET | `/api/v1/admin/stats/recent-audits` | 管理员 | 最近审计 |
| GET | `/api/v1/admin/users` | 超级管理员 | 账号列表 |
| POST | `/api/v1/admin/users` | 超级管理员 | 新建管理员 |
| PATCH | `/api/v1/admin/users/{id}` | 超级管理员 | 编辑（改密后强制重新登录） |
| DELETE | `/api/v1/admin/users/{id}` | 超级管理员 | 删除（保护最后一个超管） |
| GET | `/api/v1/admin/audit-logs` | 超级管理员 | 审计日志（action/类型/时间范围过滤） |
| GET | `/api/v1/admin/audit-logs/actions` | 超级管理员 | 动作分布统计 |
| GET | `/api/v1/admin/settings` | 超级管理员 | 系统配置（非敏感项） |
| POST | `/api/v1/admin/settings` | 超级管理员 | 保存配置（敏感前缀禁止入库） |
| DELETE | `/api/v1/admin/settings/{key}` | 超级管理员 | 删除配置项 |
| GET | `/api/v1/admin/security/hmac-secret` | 管理员 | 查看本人 HMAC 签名密钥（掩码） |
| POST | `/api/v1/admin/security/hmac-secret/rotate` | 管理员 | 轮换本人 HMAC 签名密钥 |
| GET | `/api/v1/admin/public-keys` | 超级管理员 | Ed25519 公钥列表（指纹前缀） |
| POST | `/api/v1/admin/public-keys` | 超级管理员 | 录入公钥（Base64 解码 + 32 字节 Ed25519 校验） |
| GET | `/api/v1/admin/public-keys/{id}` | 超级管理员 | 公钥详情 |
| DELETE | `/api/v1/admin/public-keys/{id}` | 超级管理员 | 删除公钥 |

---

## 六、客户端验签示例（PHP）

```php
<?php
/**
 * 客户端验签示例：验证激活/心跳响应（data 载荷）的 Ed25519 签名。
 * 公钥从 /api/v1/public-key 获取（与平台签名私钥配套）。
 */
function verifyResponse(array $response, string $publicKeyBase64): bool
{
    if (empty($response['signature']) || empty($response['data'])) {
        return false;
    }
    $dataJson = json_encode($response['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $signature = base64_decode($response['signature']);
    $publicKey = base64_decode($publicKeyBase64);

    return $signature !== false
        && sodium_crypto_sign_verify_detached($signature, $dataJson, $publicKey);
}

$resp = json_decode($body, true);
if (! verifyResponse($resp, $publicKey)) {
    exit('签名校验失败，响应可能被篡改');
}
// 校验通过后再读取 $resp['data']，如 valid / expires_at / features
```

> 注意：签名对象为 `data` 数组的**规范化 JSON**（`JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES`），与 `CanonicalJson` 保持一致。

---

## 七、安全设计说明（对应安全基线）

| # | 基线要求 | 实现 |
|---|---|---|
| 1 | key 存储/索引用 HMAC-SHA256 | `licenses.key_hash = HMAC-SHA256(LICENSING_KEY_HMAC_SECRET, key)`，唯一索引，明文不落库 |
| 2 | 公开端点错误归一化 | SDK 端点统一折叠为"授权码无效或不可用"，不暴露存在性 |
| 3 | 设备指纹服务端计算 | 客户端仅上报原始信号 base64，服务端加盐 HMAC 生成指纹，白名单字段 + 排序 + 最少信号数 |
| 4 | 强制在线心跳 | `enforce_online=true` 时心跳超时即失效；激活/绑定数在 DB 事务 + 行锁内原子计数 |
| 5 | 管理/SDK 凭证分离 + 角色 | 管理端 Bearer 状态化 Token（SHA-256 哈希存库）；SDK 用授权码本身 + 防重放签名；`super_admin` / `admin` 两级角色 |
| 6 | 密钥全走环境变量 | 私钥/DB 密码/APP_KEY/HMAC/指纹盐均来自 .env；私钥 0600、不入库 |
| 7 | 登录熔断 | 失败次数锁定（DB 持久化）+ 限流 + Token 有效期 + 登出销毁 + 单用户活跃 Token 上限 |
| 8 | 参数化 + 转义 + 全路由鉴权 | Eloquent 参数化查询；Blade/API 输出统一 JSON（XSS 面收窄）；SecurityHeaders 中间件（CSP/HSTS/X-Frame） |
| 9 | 规避糟糕做法 | 无无鉴权端点（除公开 SDK 与登录）、无明文密钥展示、无假鉴权、客户端无对称密钥、无明文 cookie、无 innerHTML 拼接、无 sum 取模校验（校验字符为 HMAC 派生） |

补充风控：
- 限流：登录 `login`、SDK `client`、管理 `admin`、全局限流（`throttle` RateLimiter，见 `AppServiceProvider`）
- 防重放：时间戳窗口 + nonce 去重 + 可选/强制 HMAC 签名（`ReplayProtect`）
- 雷池（SafeLine）人机验证（V1.32 已实现）：`SafelineMiddleware` 默认关闭；开启后校验雷池注入的可信头（默认 `X-SafeLine-Checked`），缺失/不匹配返回 403 `SAFELINE_BLOCKED`，仅挂载公网防刷端点（licensing/v1 六业务端点 + public-key），health/admin/SDK 不受影响；开关可在设置页或 `.env`（`LICENSING_SAFELINE_ENABLED`）控制
- 审计：管理员操作 / 客户端激活 / 心跳（可配）全记录 `audit_logs`，支持保留期清理

---

## 八、数据库表清单

| 表 | 说明 | 关键字段 |
|---|---|---|
| users | 管理员（分级） | role(super_admin/admin), is_active, must_change_password, last_login_at/ip |
| api_tokens | 状态化登录 Token | token_hash(SHA-256), expires_at, revoked_at, last_used_at |
| login_attempts | 登录失败记录（熔断） | email, ip, success, attempted_at |
| products | 产品 | slug, name, is_active |
| licenses | 授权码（不落明文） | key_hash(HMAC), status, max_devices, meta, expires_at, activated_at, revoked_at, last_heartbeat_at |
| devices | 设备绑定（一机一码） | fingerprint_hash, is_active, last_ip, first/last_seen_at |
| heartbeats | 心跳上报 | license_id, device_id, status, checked_at |
| audit_logs | 审计日志 | actor_type/id, action, resource, ip, ua, context |
| settings | 系统配置（非敏感） | key, value, type, updated_by |

状态机：`pending(待定) → active(有效) → expired(过期) / revoked(已吊销) / blacklisted(已拉黑)`；blacklisted 不可恢复，仅超管操作。

---

## 九、心跳配置

| 配置项 | 默认 | 说明 |
|---|---|---|
| `LICENSING_HEARTBEAT_INTERVAL` | 60 | 心跳上报周期（秒），客户端略小于该值上报 |
| `LICENSING_HEARTBEAT_TIMEOUT` | 300 | 超时阈值（秒），超时即失效（强制在线无宽限） |
| `LICENSING_ENFORCE_ONLINE` | true | 是否启用强制在线心跳判定 |
| `LICENSING_HEARTBEAT_RETENTION_DAYS` | 90 | 心跳记录保留天数（`license:clean` 清理） |

> V1.32 起，心跳周期/超时/强制在线与审计保留天数可在管理后台「设置页 → 运行参数」维护，
> 写入 `settings` 表后立即生效（DB 优先、config 兜底）；未配置时回落上表 / `.env` 值，行为与现状一致。
> `license:clean` 的审计保留天数默认自动取 settings → config（`LICENSING_AUDIT_RETENTION_DAYS`，默认 365），
> 亦可手动执行 `php artisan license:clean --audit-days=180` 显式覆盖。

调度命令：`php artisan license:expire`（到期/心跳超时批量置过期）、`php artisan license:clean`（清理心跳/审计/登录失败记录）。

---

## 十、管理后台

- 入口：`/{ZF_ADMIN_PATH}/login`（默认 `admin`，生产建议随机化，如 `zf-8k2x9q`；旧路径访问返回 404）
- 主题：**深色/浅色双主题**（CSS 变量体系，顶栏/登录页切换按钮，`localStorage.zf-theme` 持久化，防 FOUC 首帧内联脚本，Chart.js 双主题自动重绘）
- 布局：顶栏 + 侧边栏（PHP 数组驱动递归渲染，分组导航、当前页高亮、超管限定项按角色隐藏、移动端折叠）+ 内容区
- 看板：数据看板（4 统计卡 + 授权状态分布柱状图 + 产品授权分布条形图 + 最近审计动态，数据源 `/admin/stats`）
- 页面：登录、仪表盘、授权码列表/详情、产品、设备、用户（超管）、审计（超管）、设置（超管）、**公钥录入（超管，实时 Base64/32 字节校验）**
- 设置页：显示当前后台前缀、HMAC 密钥查看/轮换（轮换后前端自动换新签名）、加密状态只读网格（Argon2id/AES-256-GCM/Ed25519/HMAC/HTTPS/HSTS）
- 前端调用全部管理 API 时自动携带 `Authorization: Bearer` 与防重放头（X-Timestamp/X-Nonce/X-Signature），HMAC secret 与 Token 仅存 `sessionStorage`（等保 H-01：不落 localStorage/cookie），401 自动跳回登录页，页面刷新后由 `/admin/security/hmac-secret` 重新拉取
- 页面路由均挂在 `auth.web` 中间件组；API 侧审计/设置/用户/公钥等敏感接口由 `role:super_admin` 保护


