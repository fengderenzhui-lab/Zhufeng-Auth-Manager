<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * 版权动态签名守护服务（V1.30 新增）
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 设计目标：版权署名防篡改 + 动态签名双校验。
 * 一旦代码包被二次分发/修改（如删除或篡改分散植入的公钥文件、篡改版权署名），
 * 服务层验签失败时抛出 RuntimeException；中间件层（CopyrightSignatureGuard）按等保
 * N-02 修复已 fail-open：仅记录日志/审计并放行，不再返回 503（服务层异常、接入层放行）。
 *
 * 校验流程（verifyGuard）：
 *   a) SHA256 完整性校验：逐一读取每份公钥文件内容，计算 sha256，与配置中的
 *      hashes 基准表逐一比对（hash_equals 防时序攻击），任何一份缺失/内容不符即失败；
 *   b) Ed25519 动态签名验签：从预置签名池（96 组）按轮换索引取当前组，
 *      对该组 message 使用私钥1/私钥2 对应的 sig1/sig2 逐份公钥验签——
 *      公钥内容等于 owner1（私钥1 派生公钥）时必须用 sig1 验证通过；
 *      公钥内容等于 owner2（私钥2 派生公钥）时必须用 sig2 验证通过；
 *      全部通过才算成功；轮换索引每次校验后自增（游标文件持久化，防止固定组重放）；
 *   c) 任一步失败：写 storage/logs 严重告警 + AuditService 记录审计
 *      （type=SIGNATURE_GUARD_FAIL）并抛出 RuntimeException('版权完整性校验失败')；
 *      接入层（中间件）按 N-02 fail-open 降级为告警放行，不再锁死系统。
 *
 * 防自动删除保护：本模块严禁任何 unlink/delete 逻辑；公钥文件分散植入 15 个目录，
 * 任何单点删除都会导致 hashes 比对失败而锁死，无法通过删除“绕开”校验。
 * ─────────────────────────────────────────────────────────────────────────────
 */
class CopyrightSignatureService
{
    /**
     * 版权署名（被篡改则验签失败：签名池 message 由本常量派生，
     * 修改常量后与预置签名无法匹配，从而锁死系统）。
     */
    public const COPYRIGHT_TEXT = '逐风工作室授权码管理平台';

    /**
     * 验签结果静态缓存：避免高频请求下每请求做全量 sha256 + 全量验签。
     * 缓存有效期取 config('license.signature_guard.cache_seconds')，默认 60s。
     * 自测/排障时可传 $force = true 强制绕过缓存实时校验。
     */
    private static ?int $cacheValidUntil = null;

    private static bool $cacheResult = false;

    /**
     * 版权完整性校验入口（供启动与核心接口前置调用）。
     *
     * @param bool $force 为 true 时绕过 60s 静态缓存强制实时校验（自测/排障用）
     * @return bool 校验通过返回 true；未初始化（公钥未生成）在非生产环境返回 true 并提示
     *
     * @throws RuntimeException 校验失败（系统锁定）
     */
    public function verifyGuard(bool $force = false): bool
    {
        // 守护开关：显式关闭时直接放行（极少数排障场景）
        if (! (bool) config('license.signature_guard.enabled', true)) {
            return true;
        }

        // 公钥清单为空 = 尚未执行 zf:signature:init 初始化。
        // 开发/本机环境仅记录提示不锁死（避免首次部署前系统不可用）；
        // 生产环境视为配置缺失，直接锁死（fail-closed）。
        $publicKeys = (array) config('license.signature_guard.public_keys', []);
        if ($publicKeys === []) {
            if ((string) config('app.env') !== 'production') {
                Log::warning('[SIGNATURE_GUARD] 版权签名守护未初始化（未运行 php artisan zf:signature:init），已跳过校验。');

                return true;
            }
            throw new RuntimeException('版权完整性校验失败，系统已锁定（签名守护配置缺失）。');
        }

        // 静态缓存命中（且未强制实时校验）直接返回上次结果
        if (! $force && self::$cacheValidUntil !== null && self::$cacheValidUntil > time()) {
            return self::$cacheResult;
        }

        // 步骤 a)：SHA256 完整性校验（逐份公钥文件比对基准哈希）
        $hashes = (array) config('license.signature_guard.hashes', []);
        foreach ($publicKeys as $relativePath) {
            $absolutePath = base_path($relativePath);
            if (! is_file($absolutePath)) {
                $this->fail('公钥文件缺失: '.$relativePath);
            }
            $actualHash = hash_file('sha256', $absolutePath);
            $expectHash = (string) ($hashes[$relativePath] ?? '');
            // hash_equals 常量时间比较，防止时序攻击
            if ($expectHash === '' || ! hash_equals(strtolower($expectHash), strtolower($actualHash))) {
                $this->fail('公钥文件内容被篡改: '.$relativePath);
            }
        }

        // 步骤 b)：Ed25519 动态签名验签（按轮换索引取当前组）
        $pool = (array) config('license.signature_guard.signature_pool', []);
        if ($pool === []) {
            $this->fail('签名池为空');
        }
        $cursor = $this->nextCursor(count($pool));
        $group = $pool[$cursor] ?? null;
        if (! is_array($group) || ! isset($group['message'], $group['sig1'], $group['sig2'])) {
            $this->fail('签名池当前组数据不完整');
        }

        // 版权署名动态绑定：用【当前】COPYRIGHT_TEXT + 组内 nonce/timestamp 重组期望 message，
        // 与预置签名池 message 比对（hash_equals 常量时间）。
        // 一旦版权署名被篡改 → 重组结果与预置 message 不一致 → 验签失败（系统锁定）。
        $expectedMessage = self::COPYRIGHT_TEXT.'|'.(string) ($group['nonce'] ?? '').'|'.(string) ($group['timestamp'] ?? '');
        if (! hash_equals($expectedMessage, (string) $group['message'])) {
            $this->fail('版权署名校验失败（COPYRIGHT_TEXT 与签名池 message 不一致）');
        }

        // 私钥归属公钥（hex）：owner1 派生自私钥1，owner2 派生自私钥2
        $owner1 = strtolower((string) config('license.signature_guard.owner1', ''));
        $owner2 = strtolower((string) config('license.signature_guard.owner2', ''));

        foreach ($publicKeys as $relativePath) {
            $pubHex = $this->extractPubHex((string) file_get_contents(base_path($relativePath)));
            if ($pubHex === '') {
                $this->fail('公钥文件内容解析失败: '.$relativePath);
            }

            // 归属判定：属于私钥1 派生公钥 → 用 sig1 验证；属于私钥2 → 用 sig2 验证
            if (hash_equals($owner1, $pubHex)) {
                $ok = sodium_crypto_sign_verify_detached(
                    sodium_hex2bin((string) $group['sig1']),
                    $expectedMessage,
                    sodium_hex2bin($pubHex)
                );
            } elseif (hash_equals($owner2, $pubHex)) {
                $ok = sodium_crypto_sign_verify_detached(
                    sodium_hex2bin((string) $group['sig2']),
                    $expectedMessage,
                    sodium_hex2bin($pubHex)
                );
            } else {
                // 公钥不在合法归属表内：视为被注入的伪造公钥，锁死
                $ok = false;
            }

            if (! $ok) {
                $this->fail('动态签名验签失败: '.$relativePath.'（组#'.$cursor.'）');
            }
        }

        // 全部通过：记录缓存并返回
        self::$cacheResult = true;
        self::$cacheValidUntil = time() + (int) config('license.signature_guard.cache_seconds', 60);

        return true;
    }

    /**
     * 从公钥文件中提取 hex 公钥（兼容“注释头 + hex”的混合文件格式）。
     * 提取规则：逐行读取，跳过以 #、//、; 开头的注释行，
     * 返回第一个连续 hex 字符串（64 字符 = 32 字节 Ed25519 公钥）。
     */
    private function extractPubHex(string $content): string
    {
        foreach (preg_split('/\r\n|\r|\n/', $content) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, '//') || str_starts_with($line, ';')) {
                continue;
            }
            // 过滤行内尾随注释（如 "abc123 # comment"）
            if (str_contains($line, '#')) {
                $line = trim(explode('#', $line)[0]);
            }
            if (preg_match('/^[0-9a-fA-F]{64}$/', $line) === 1) {
                return strtolower($line);
            }
        }

        return '';
    }

    /**
     * 动态签名轮换索引：读取游标文件 → 自增 → 写回（持久化，防固定组重放）。
     * 游标文件位于 storage/framework/cache/signature_guard_cursor.txt。
     */
    private function nextCursor(int $poolSize): int
    {
        $cursorFile = storage_path('framework/cache/'.(string) config('license.signature_guard.cursor_file', 'signature_guard_cursor.txt'));
        $current = 0;
        if (is_file($cursorFile)) {
            $raw = (int) trim((string) file_get_contents($cursorFile));
            $current = max(0, $raw);
        }
        $next = ($current + 1) % max(1, $poolSize);
        // 原子写回游标（LOCK_EX 防并发竞争）；失败仅告警不阻断校验
        if (@file_put_contents($cursorFile, (string) $next, LOCK_EX) === false) {
            Log::warning('[SIGNATURE_GUARD] 游标文件写入失败: '.$cursorFile);
        }

        return $current;
    }

    /**
     * 校验失败统一出口：严重日志 + 审计记录 + 抛异常锁死。
     *
     * @throws RuntimeException 版权完整性校验失败
     */
    private function fail(string $reason): never
    {
        // 1) 严重告警日志（storage/logs/laravel-*.log）
        Log::critical('[SIGNATURE_GUARD] 版权完整性校验失败：'.$reason);

        // 2) 审计记录（type=SIGNATURE_GUARD_FAIL，供等保审计链追踪）
        try {
            $audit = app(AuditService::class);
            $audit->systemAction(
                'SIGNATURE_GUARD_FAIL',
                resourceType: 'signature_guard',
                resourceId: null,
                context: ['reason' => $reason, 'time' => date('c')],
            );
        } catch (\Throwable $e) {
            // 审计写入失败不影响锁死主流程（避免递归故障）
            Log::critical('[SIGNATURE_GUARD] 审计记录失败：'.$e->getMessage());
        }

        // 3) 抛出异常 → 启动失败 / 中间件 503
        throw new RuntimeException('版权完整性校验失败，系统已锁定');
    }
}
