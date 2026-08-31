---
AIGC:
    Label: "1"
    ContentProducer: 001191440300708461136T1XGW3
    ProduceID: ab98165fed8b22aad5c08dac48ce7e1a_1ed6a1c8a48111f193c6525400f8a581
    ReservedCode1: 3Jvh1EN7Bf7TXcBgVT2aQ5icHMaiYRn0vcYP4P2XnaaGmYzy7pXKKXExP+i76a4l0DfCEbUAVr0H4+rMpD7PBH7+61vpyYPGfLEip2KjO3fS7iEEfJoRgfveDDAWtBCwUOzLig9Eo9AXzYeHEuMtr1KoKQrWFwaeOgb7JGfw50vbq5H6PHXCQmJSzbQ=
    ContentPropagator: 001191440300708461136T1XGW3
    PropagateID: ab98165fed8b22aad5c08dac48ce7e1a_1ed6a1c8a48111f193c6525400f8a581
    ReservedCode2: 3Jvh1EN7Bf7TXcBgVT2aQ5icHMaiYRn0vcYP4P2XnaaGmYzy7pXKKXExP+i76a4l0DfCEbUAVr0H4+rMpD7PBH7+61vpyYPGfLEip2KjO3fS7iEEfJoRgfveDDAWtBCwUOzLig9Eo9AXzYeHEuMtr1KoKQrWFwaeOgb7JGfw50vbq5H6PHXCQmJSzbQ=
---







# 更新日志（CHANGELOG）

## V1.32（2026-08-30）——未开发功能补全（雷池人机验证 + 设置页保存接口生效）

### 一、功能一：雷池（SafeLine）人机验证真实生效（原仅配置无实现）
- **新增 `app/Http/Middleware/SafelineMiddleware.php`**：开关 `LICENSING_SAFELINE_ENABLED`（默认关闭，关闭时完全放行，不影响现有行为）；开启时校验雷池 WAF 注入的可信请求头（默认 `X-SafeLine-Checked`，可用 `LICENSING_SAFELINE_TRUSTED_HEADER` 配置头名），头缺失或值不匹配返回 `403 {success:false,error:{code:"SAFELINE_BLOCKED",message:"..."}}`（统一信封，与 licensing/v1 错误契约一致）。
- **中间件注册**：`bootstrap/app.php` 注册别名 `safeline`；`routes/api.php` 挂载至公网防刷端点——`/api/licensing/v1` 六业务端点（activate/deactivate/refresh/heartbeat/validate/show，health 探活除外）+ `public-key`；admin / SDK / health 端点不挂载（默认关闭时天然不拦截）。
- **开关可设置页控制**：设置页新增「雷池人机验证开关」；`SettingController` 白名单放开 `license.safeline.enabled`（仅开关；endpoint/api_key/可信头名仍走 .env，纵深防御前缀同步收紧）。
- 本项目无独立公网「试用申请」端点（试用码由后台管理员创建，属 admin 端点，按要求不拦截），故实际挂载上述公网防刷端点。

### 二、功能二：设置页保存接口真实生效（settings 表接入业务）
- **新增 `app/Services/SettingsService.php`**：统一 get/set/forget/find/all 能力；读值 DB（settings 表）优先、config 兜底，请求生命周期内按 key 缓存，写入即时失效——保存后立即生效、无需清 config/重启。
- **业务接入 ① 心跳/离线判定超时**：`LicenseService::verify` 与 `LicenseService::expire` 的 `enforce_online` / `timeout_seconds`，`HeartbeatService::buildPayload` 的 `enforce_online` / `interval_seconds` 全部改为 SettingsService 读取（DB 优先、config 兜底；未配置时行为与现状完全一致）。
- **业务接入 ② 审计日志保留天数**：`CleanupRecords` 命令 `--audit-days` 默认改为 `0=自动`，自动时取 settings → config（`LICENSING_AUDIT_RETENTION_DAYS`，默认 365）；`routes/console.php` 每日调度去掉硬编码 `--audit-days=180`（仍保留 heartbeat-days/login-days 显式值）；手动执行 `--audit-days=N` 仍可显式覆盖。
- **设置页 UI**：`resources/views/settings/index.blade.php` 新增「运行参数」卡片（心跳上报周期 / 心跳离线超时 / 心跳强制在线开关 / 审计日志保留天数 / 雷池人机验证开关，含 config 兜底默认值与说明）；`public/js/pages/settings.js` 支持加载 DB 值回显与逐项保存（保留原 HMAC 查看/轮换能力）。
- `SettingController` 读写统一走 SettingsService（白名单校验、敏感前缀拦截不变）。

### 三、配套
- `config/license.php`：`platform.version` 1.31 → 1.32。
- `AppServiceProvider`：注册 `SettingsService` 单例。
- README 同步：5.3 公开端点雷池说明、七（风控）、九（心跳配置）、十（设置页运行参数）、十一/十二（雷池边界与 WAF 接入）。
- 约束遵守：不破坏 `/api/licensing/v1` 七端点现有契约与错误码映射（safeline 默认关闭零影响；`SAFELINE_BLOCKED` 为新增中间件专属码）；不改动 `.env` 实际密钥；保留等保整改标记与版权署名。

## V1.31（2026-08-30）——等保2.0一级安全自查整改（v1.30 报告项）

### 一、高危修复（P0）
- **N-01 移除发行包硬编码超管凭据**：`temp/create_admin.php`、`temp/bootstrap_create_admin.php` 改为读取环境变量 `ADMIN_EMAIL` / `ADMIN_PASSWORD`（为空则报错退出），`must_change_password` 固定为 true（新管理员首登强制改密）；发行包整体排除 `temp/` 目录。
- **N-02 版权签名守护降级为「告警不阻断」**：`AppServiceProvider::boot()` 中 `verifyGuard()` 失败仅写日志不再抛异常拒绝启动；`CopyrightSignatureGuard` 中间件校验失败仅记录日志/审计并放行，不再返回 503（code=5900 路径移除）；删除 `public/css`、`public/js`、`public/js/pages` 下 5 个公钥指纹文件，并同步 `config/license_signature_guard.php` 的 `public_keys`（24→19）与 `hashes`（24→19）数组。
- **H-01 降险**：`config/license.php` `auth.token_ttl_minutes` 默认 120→60（Token 有效期缩短一倍，降低 XSS 窃取会话的暴露窗口；前端仍 sessionStorage 存储，保持不变）。

### 二、中危修复（P1）
- **M-05 可信代理白名单**：新增 `license.tls.trusted_proxies`（env `LICENSING_TRUSTED_PROXIES`，逗号分隔 IP，默认空）；`ForceHttps` 仅当来源 IP 命中白名单时才信任 `X-Forwarded-Proto`，否则仅以 `isSecure()` 判定，杜绝伪造代理头绕过 HTTPS 强制；旧 `trust_proxies` 键保留兼容、不再读取。
- **M-06 指纹约束（中间方案）**：`LicensingV1Controller::validatePayload` 对最终使用的 fingerprint 增加长度/熵约束（32~128 字符、仅允许 hex/base64 白名单字符集、唯一字符数 ≥8），非法即拒绝；校验规则 `min/max` 同步收紧为 32/128；不改动服务端计算逻辑。
- **M-08 MySQL 认证插件**：`deploy.sh` 两处 `mysql_native_password` → `caching_sha2_password`（PHP 8.2 驱动已支持）。
- **N-03 审计 actorId 掩码**：`AuthService` 登录失败「凭据错误」分支审计 actorId 改用 `maskEmail()`（补全 `maskEmail` 实现，与 inactive 分支一致），审计日志不落明文邮箱。
- **N-04 打包清理**：删除项目根 0 字节空文件「0」；发行包排除 `temp/`、`bootstrap/cache/`、`vendor/`、`.env`、`密钥*.key`、`storage/` 等。

### 三、低危/流程（P2）
- **N-07 依赖漏洞审计**：`composer audit --locked` 结果见交付说明（不随包分发）。

### 四、版本
- `config/license.php` `platform.version` → `1.31`；发行包：`逐风授权码管理平台-v1.31.zip`。

### 五、部署链补全（V1.31，2026-08-30 当日追加）
- **D-01 版权签名守护接入部署链**：`.dockerignore` 放行签名公钥（8 个 `.k_*.key` 伪装公钥 + `sig_key_*.pub` + `guard_*.ed25519`），镜像内置公钥与 `config/license_signature_guard.php`；`docker/entrypoint.sh` 与 `deploy.sh` 在 `license:keys` 之后幂等调用 `zf:signature:init`（配置已初始化即跳过，绝不触碰既有公私钥）；`InitSignatureGuard` 增加幂等保护（`public_keys` 非空直接返回，不再生成新密钥对），防止重复初始化覆盖 `owner` 导致公钥归属判定失效；容器重建后由镜像内置配置 + entrypoint 兜底双保险，签名守护部署后真实生效。
- **D-02 每日数据库备份接入定时**：新增 `docker/install-backup-cron.sh` 一键安装宿主机 cron（默认每天 02:30，支持 `BACKUP_CRON_TIME` / `BACKUP_DIR` / `BACKUP_RETENTION_DAYS` / `DB_*` 参数），按脚本路径去重幂等安装，日志写入 `/var/log/zf-backup.log`；`docker/backup.sh` 保留 N 天清理逻辑照常生效；README 部署章节同步说明。
- 版本号保持 **V1.31** 不变；未改动任何公钥/私钥文件、`.env` 密钥与 `windblade.top` 线上配置。

## V1.30（2026-08-30）——Ed25519 动态签名安全模块 + 部署脚本重构 + 版本标识统一

### 一、新增 Ed25519 动态签名安全模块
- `CopyrightSignatureService`：SHA256+Ed25519 双校验、版权署名绑定、校验失败锁死（fail-closed）。
- `CopyrightSignatureGuard` 中间件：核心接口前置守护，校验失败返回 503 / code=5900。
- `InitSignatureGuard` 命令（`zf:signature:init`）：生成双 Ed25519 密钥对、24 份公钥分散植入 15 目录、构建 96 组签名池；桌面密钥 1.key/2.key 已存在则跳过不删。
- 配置 `config/license_signature_guard.php`（public_keys / hashes / signature_pool / owner1 / owner2）+ 游标文件。
- 挂载点：`AppServiceProvider::boot`（启动完整性校验）、`routes/api.php`（licensing/v1 核心路由 + admin 组）。

### 二、GitHub 一键部署脚本重构
- `deploy.sh`（Linux/macOS/GitHub Actions）与 `deploy.bat`（Windows 双击运行）逻辑一致。
- 控制台大字标题「逐风工作室」+ Y/N 部署确认交互（Y 部署 / N 退出）。
- 自动检测缺失依赖（PHP>=8.2 / Composer / 扩展）并自动安装；composer install → .env 初始化 → SQLite 迁移 → 启动服务。
- 适配 V1.30 签名模块：仅确认仓库内公钥存在，禁止生成/触碰私钥。
- 部署完成后自动生成并醒目输出随机三要素：随机管理员账号 / 随机高强度密码 / 随机后台访问地址。

### 三、版本标识统一
- 全项目版本标识统一为 V1.30（config/license.php、README、deploy 脚本等对外文案）。
- 发行包：`逐风授权码管理平台-v1.30.zip`。

## v1.3.0（2026-08-29）——等保2.0一级整改 + UI 黑白企业主题重构 + 6 新功能模块 + GitHub 一键部署

### 一、等保2.0一级 16 项修复（ZF-2026-001 ~ 016）

| 编号 | 修复内容 | 关键落地 |
|------|----------|----------|
| ZF-2026-001 | `docker/backup.sh` 去明文口令 | 改读 `/root/.my.cnf`（权限 600）或 `MYSQL_PWD`，严禁 `-p` 明文传参；README cron 示例同步清理 |
| ZF-2026-002 | 客户端指纹服务端化 | `LicensingV1Controller` 支持 `signals` 上报，服务端 `DeviceFingerprintService` 计算指纹覆盖客户端值；不支持上报的已知风险写入部署文档 |
| ZF-2026-003 | 登录审计掩码 | `AuthService` 审计 actorId 使用 `maskEmail()`（preg_replace 中间打星） |
| ZF-2026-004 | Docker 锁版安装 | `.dockerignore` 移除 composer.lock 排除；Dockerfile `COPY composer.json composer.lock` + `composer install --no-dev` + `composer audit --locked` 漏洞扫描 |
| ZF-2026-005 | 登录限流 key 归一化 | 邮箱统一 `mb_strtolower(trim())`，防大小写绕过 |
| ZF-2026-006 | AES 旧密钥回退 | `AesGcmService::decrypt` 失败尝试 `ZF_APP_ENCRYPT_KEY_PREV` 并回传重加密密文（`reencryptWithCurrentKey`）；`.env.example`/README 补充轮换流程 |
| ZF-2026-007 | 登录页版本号移除 | 确认无残留 |
| ZF-2026-008 | 品牌化 404 页 | 新增 `resources/views/errors/404.blade.php`（黑白主题）；nginx `error_page 404 /index.php` |
| ZF-2026-009 | CSP 可配置生效 | `SecurityHeaders` 改读 `config('license.security.headers.csp')`，`LICENSING_CSP` 环境变量真正生效 |
| ZF-2026-010 | API 跳过应用层 CSP | `/api/*` 或 `X-Requested-With` 请求跳过，保留 nginx strict CSP（`default-src 'none'`） |
| ZF-2026-011 | 容器缓存预热 | `docker/entrypoint.sh` 追加 `config:cache && route:cache && view:cache` |
| ZF-2026-012 | 全局限流 key 去 UA | 全局 `api` 限流仅用 IP；端点专属限流保留 IP+UA |
| ZF-2026-013 | SDK 响应去 customer 明文 | `LicenseService::verify` 返回布尔 `unverified_customer`；README 对接文档同步更新 |
| ZF-2026-014 | 占位符 fail-closed 扩展 | `LICENSING_KEY_HMAC_SECRET` 缺失/`change-me` 拒绝启动（AppServiceProvider + LicenseKeyGenerator 双保险，不再回退 APP_KEY） |
| ZF-2026-015 | 主机层加固建议 | README 新增章节（雷池 SafeLine、云安全 Agent、SSH 改端口+密钥登录等） |
| ZF-2026-016 | 监控告警建议 | README 新增章节（资源监控、备份失败 webhook、`zf:audit-verify` 每日调度、备份同步对象存储） |

### 二、UI 黑白企业主题重构

- 全站黑白体系（浅色柔和暖调浅灰白 / 深色黑色系），删除球体星球装饰与玻璃拟态；侧边栏 240px 可折叠
- 右上角管理员下拉：修改密码 / 退出登录 / 新增管理员（超管）
- 侧边栏底部角色与加密算法说明清理；删除全部「开发中」标记，6 个新功能模块完整落地（授权模板 / 授权范围 / 试用 / 转让续期 / 心跳监控 / 个人中心）

### 三、GitHub 一键部署脚本

- 新增 `deploy.sh`：Ubuntu 22.04/24.04 裸机或宝塔自动部署
- 检测/安装 PHP 8.2+（pdo_mysql/mbstring/intl/bcmath/opcache/redis）、Composer、MySQL 8.0+、Nginx
- 随机生成全部密钥与随机后台路径 `ZF_ADMIN_PATH`（zf-xxxx 强随机前缀）、高强度随机管理员账号（密码 ≥16 位含 3 类字符）
- nginx 站点：TLS1.2/1.3、隐藏版本号、敏感文件 deny、HSTS；部署完成醒目输出全部凭据

### 四、版本信息与打包

- `config/license.php` version → 1.3.0（仅内部配置，不对外输出）；登录页/接口/响应头版本号全量清理
- 发行包：`逐风授权码管理平台-v1.3.0.zip`

---

## v1.2.6（2026-08-29）——v1.2.5 遗留项闭环：安全模糊检索 / 审计链补全 / email 加密 / HTTPS 默认

### 一、客户名称安全模糊检索（n-gram 盲索引）

| 文件 | 要点 |
|------|------|
| `app/Services/CustomerNgramService.php`（新增） | 客户名 2-gram + 3-gram 拆分；子串经 HMAC-SHA256（密钥派生自 `ZF_APP_ENCRYPT_KEY`，域前缀 `zf-license:customer-ngram:v1:` 隔离）加盐哈希落库；检索多 gram AND 语义；2-gram 超阈值退化 3-gram、3-gram 仍超阈值取前缀优先前 N 个（可配置） |
| `database/migrations/2026_08_29_000002_add_customer_ngram_index_to_licenses.php`（新增） | 新建 `license_customer_ngrams`（license_id + gram_sha256 联合索引）；历史数据清空重建（幂等可重放）；兼容密文/明文存量 |
| `app/Http/Controllers/LicenseAdminController.php` | `index` 客户筛选由 `customer_sha256` 精确匹配恢复为 n-gram 模糊匹配（关键词 <2 字符退化为 sha256 精确匹配）；检索结果经模型访问器解密返回明文客户名 |
| `app/Services/LicenseService.php` | 批量生成授权码时同步写入客户名 n-gram 盲索引 |
| `resources/views/licenses/index.blade.php` | 客户筛选框提示恢复为"支持模糊搜索" |
| `config/license.php` | 新增 `license.customer_ngram` 阈值配置 |

### 二、审计日志哈希链历史行补全

| 文件 | 要点 |
|------|------|
| `database/migrations/2026_08_29_000003_backfill_audit_hash_chain.php`（新增） | 按 `id` 升序从链头重算全部行 `prev_hash/hash`；canonical 规则与 `AuditService::record` 完全一致（prev_hash\nactor_type\naction\nresource_type\nresource_id\nip\nuser_agent\ncontext(JSON)\ncreated_at，HMAC-SHA256，密钥 `LICENSING_AUDIT_HMAC_SECRET`）；敏感列取解密后明文语义；幂等（全链一致则跳过写库） |
| `app/Console/Commands/AuditVerify.php`（新增） | `php artisan zf:audit-verify` 只读全链校验：逐行 hash 重算比对 + 相邻行 prev_hash 连续性断言，任一历史行被篡改其后全部校验失败 |

### 三、users.email 加密存储

| 文件 | 要点 |
|------|------|
| `database/migrations/2026_08_29_000004_encrypt_users_email.php`（新增） | email 明文 → AES-256-GCM 密文（text）；新增 `email_sha256` 唯一盲索引列，唯一约束迁移到盲索引；历史数据幂等回填（明文加密+盲索引、已加密仅补盲索引）；dropUnique `users_email_unique` |
| `app/Models/User.php` | 新增 `email` 加密访问器（写入规范化 trim+小写 后加密并自动维护 `email_sha256`；读取自动解密，列表/详情仍展示明文邮箱）+ 静态 `sha256Of()` |
| `app/Services/AuthService.php` | 登录查询 `where('email')` → `where('email_sha256', User::sha256Of(...))` |
| `app/Console/Commands/InitAdmin.php` | `--reset-password` 按邮箱定位迁移到盲索引；`uniqueEmail()` 唯一性校验迁移到盲索引；创建/输出走模型访问器自动加密 |
| `app/Http/Requests/UserStoreRequest.php` / `UserUpdateRequest.php` | 唯一性校验 `unique:users,email` → 盲索引精确匹配（Update 排除自身） |
| `app/Http/Controllers/UserController.php` | 无改动（创建/更新/列表走 Eloquent 访问器，自动加密/解密） |

### 四、默认 HTTPS 配置

| 文件 | 要点 |
|------|------|
| `.env.example` | `APP_URL` 由 `http://localhost` 改为 `https://www.example.com`，补充注释：生产必须 HTTPS，`APP_FORCE_HTTPS=true` 时应用层强制跳转（API 403 HTTPS_REQUIRED / 页面 301） |
| `README.md` | 3.1 / 3.2 部署说明同步 HTTPS 要求；密钥清单与敏感字段说明更新；目录结构同步 |

### 五、版本与清理

- `config/license.php` `version` 1.2.5 → 1.2.6；`public/js|css` 15 个压缩产物版本注释同步 v1.2.6。
- 密钥仍从 `ZF_APP_ENCRYPT_KEY` / `LICENSING_AUDIT_HMAC_SECRET` 环境变量读取，无硬编码；所有解密/鉴权/授权判定均在 PHP 后端执行。
- 等保既有闭环（防重放、RBAC、防暴破、TLS、安全头、审计 fail-closed）未改动；未重构 UI、未改动正常业务流程。
- 发行包：`逐风授权码管理平台-v1.2.6.zip`（桌面）。

## v1.2.5（2026-08-29）——全链路加密薄弱点排查与全量化加密升级

### 一、薄弱点排查清单（本次排查发现的真实薄弱点）

| # | 位置 | 风险 | 处置 |
|---|------|------|------|
| 1 | `licenses.customer`（客户名称）明文存储，128 字符 varchar + 索引 | 客户隐私泄露；明文入库违反"敏感字段加密存储" | 已整改：AES-256-GCM 密文，新增 `customer_sha256` 盲索引列 |
| 2 | `licenses.meta`（客户自定义元数据 JSON）明文存储 | 客户业务数据明文落库 | 已整改：整段 JSON 加密存储，读取时后端解密还原数组，API 结构不变 |
| 3 | `login_attempts.email / ip / user_agent` 明文存储 | 登录失败日志泄露邮箱、IP、UA 等隐私；配合审计哈希链可被拖库溯源 | 已整改：全部加密存储；新增 `email_sha256` / `ip_sha256` 盲索引列，防爆破锁定等值查询迁移到索引列 |
| 4 | `audit_logs.ip / user_agent / context` 明文存储 | 审计日志中敏感上下文（IP、UA、操作参数）明文落库 | 已整改：全部加密存储；哈希链 canonical 基于解密后明文语义重建，历史行不回算 hash，哈希链完整 |
| 5 | `devices.last_ip / last_user_agent` 明文存储 | 设备痕迹泄露终端 IP/UA | 已整改：AES-256-GCM 加密 |
| 6 | `heartbeats.client_ip / client_ua` 明文存储 | 心跳痕迹泄露终端 IP/UA | 已整改：AES-256-GCM 加密 |
| 7 | `users.last_login_ip` 明文存储 | 管理员登录 IP 明文落库 | 已整改：AES-256-GCM 加密 |
| 8 | 客户筛选接口按明文 `customer LIKE %kw%` 模糊检索 | 加密后无法模糊检索；明文检索与加密存储冲突 | 已整改：改为 `customer_sha256` 精确匹配，前端提示文案同步更新 |

### 二、实际整改清单（文件级）

| 文件 | 要点 |
|------|------|
| `database/migrations/2026_08_29_000001_encrypt_remaining_sensitive_fields.php`（新增） | 7 张表敏感列结构升级（string/json → text）+ 历史数据幂等加密回填 + 盲索引列；密钥取自 `ZF_APP_ENCRYPT_KEY`（env），无硬编码 |
| `app/Models/License.php` | 新增 `customer` / `meta` 加密访问器 + `customer_sha256` 自动维护 + `sha256Of()`；meta 读取解密还原数组保持 API 结构 |
| `app/Models/LoginAttempt.php` | `email` / `ip` / `user_agent` 加密访问器 + `email_sha256` / `ip_sha256` 自动维护 |
| `app/Models/AuditLog.php` | `ip` / `user_agent` / `context` 加密访问器（context 解密还原数组） |
| `app/Models/Device.php` | `last_ip` / `last_user_agent` 加密访问器 |
| `app/Models/Heartbeat.php` | `client_ip` / `client_ua` 加密访问器 |
| `app/Models/User.php` | `last_login_ip` 加密访问器 |
| `app/Services/LicenseService.php` | 批量生成授权码时对 customer/meta 显式加密 + 写 customer_sha256（批量路径不触发模型 mutator） |
| `app/Services/AuthService.php` | 登录锁定/全局锁定计数查询迁移到 `email_sha256` / `ip_sha256` 盲索引列 |
| `app/Services/AuditService.php` | 无需改动（哈希链 canonical 基于明文语义构建，写入经模型访问器加密，校验链自洽）；保留哈希链完整 |
| `app/Http/Controllers/LicenseAdminController.php` | 客户筛选改为 `customer_sha256` 精确匹配 |
| `resources/views/licenses/index.blade.php` | 客户筛选输入框提示改为"精确匹配"，说明加密存储限制 |
| `config/license.php` | `version` 1.2.4 → 1.2.5 |
| `README.md` | 版本号同步 v1.2.5 |
| `public/js/*.js`、`public/css/app.css` | 压缩产物版本注释同步 v1.2.5 |

### 三、未处理项及原因

| 项 | 原因 |
|----|------|
| 客户名称模糊搜索（LIKE） | AES-256-GCM 密文不可模糊检索；为满足"敏感字段加密存储"硬性要求，降级为 sha256 精确匹配（隐私优先） |
| 审计日志历史行哈希链回算 | 历史行 context 加密后不回算 hash，避免破坏既有哈希链完整性；校验基于解密后明文语义，链仍自洽 |
| `users.email`（登录邮箱）明文 | 登录标识需唯一约束 + 精确查询，属必要明文（非隐私字段）；凭据本身为 Argon2id 哈希 |
| 前端 `localStorage` 中的 `zf_hmac_secret` | 管理端 API 请求签名密钥（HMAC-SHA256 + 时间戳防重放），配合全站 TLS 传输；核心鉴权/授权判定仍全部在 PHP 后端执行 |
| `APP_URL` 默认 `http://localhost` | 仅本地兜底值；生产环境由 `.env` 配置 HTTPS 域名，ForceHttps 中间件强制跳转 |

### 四、既有能力核验结论（v1.2.4 基线，本次复核未发现缺口）

- AES-256-GCM 敏感字段加密（密钥 env 注入，无硬编码）✓
- Argon2id 密码哈希 + bcrypt 自动升级 ✓（无 MD5/SHA1 残留）
- API HMAC-SHA256 签名 + 时间戳防重放（`replay` 中间件全量挂载）✓
- RBAC 超管/普管隔离（`role:super_admin` 组）、全接口强制鉴权 ✓
- 登录防暴破（IP+全局双维度锁定、随机延时、归一化响应）✓
- TLS1.2/1.3（Nginx）+ ForceHttps + 全套安全响应头 + 隐藏版本号 ✓
- 审计日志哈希链（HMAC-SHA256，fail-closed）✓
- 核心逻辑全部 PHP 后端执行，前端无绕过路径 ✓

---

## v1.2.4（2026-08-29）——前端压缩与精简
- 14 个 JS 文件 Terser 压缩（mangle + 基础 compress）；`app.css` cssnano 压缩（27.8KB）
- 前端中文注释清零；版本号同步；冗余资源检测
- 发行包：逐风授权码管理平台-v1.2.4.zip

## v1.2.3（2026-08-29）——前端美化
- UI 视觉升级（双主题、backdrop-filter 动效），不改变业务流程

## v1.2.1（2026-08-29）——一级自查整改
- 自查整改项落地，版本同步

## v1.2.0（2026-08-28）——等保二级安全加固
- 审计日志哈希链、防重放、RBAC、TLS/安全头等安全能力建立
*（内容由AI生成，仅供参考）*
*（内容由AI生成，仅供参考）*
*（内容由AI生成，仅供参考）*
*（内容由AI生成，仅供参考）*
