<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * 版权签名守护初始化命令（V1.30 新增）—— `php artisan zf:signature:init`
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 功能：
 *   1) 用 PHP sodium 生成两套 Ed25519 密钥对 pair1/pair2（sodium_crypto_sign_keypair）；
 *   2) 构建 96 组签名池（message = COPYRIGHT_TEXT.'|'.$nonce.'|'.$timestamp，
 *      sig1 = 私钥1 签名、sig2 = 私钥2 签名），写入 config/license_signature_guard.php；
 *   3) 私钥输出：Windows 桌面 密钥1.key / 密钥2.key（含中文注释头）；
 *      - 目标文件已存在 → 跳过不覆盖、不删除（打印“已存在，跳过”）；
 *      - 非 Windows 环境 → 输出到当前目录 signature-keys/ 并提示；
 *      - ⚠️ 本命令严禁任何 unlink/delete 逻辑（防自动删除保护）；
 *   4) 公钥植入：将 pub1/pub2 各自复制多份，共 24~28 份公钥文件，
 *      分散写入 15 个业务目录（每目录 1~3 份，命名混合 .k_xx.key / sig_key_xx.pub / guard_xx.ed25519）；
 *      - 同名已存在则跳过（幂等，可重复执行）；
 *   5) 计算每份公钥文件 sha256 写入 hashes 基准表；
 *   6) 输出执行总结。
 * ─────────────────────────────────────────────────────────────────────────────
 */
class InitSignatureGuard extends Command
{
    /**
     * 命令签名与描述。
     */
    protected $signature = 'zf:signature:init';

    protected $description = '初始化版权动态签名守护（V1.30）：生成双 Ed25519 密钥对、分散植入公钥、构建 96 组签名池';

    /**
     * 版权署名常量：必须与 CopyrightSignatureService::COPYRIGHT_TEXT 保持一致。
     * 签名池 message = COPYRIGHT_TEXT.'|'.$nonce.'|'.$timestamp，篡改常量即验签失败。
     */
    private const COPYRIGHT_TEXT = '逐风工作室授权码管理平台';

    /**
     * 公钥分散植入计划：目录（相对项目根） => 份数。
     * 严禁集中在同一目录；每目录 1~3 份；合计 24 份（符合 24~28 要求）。
     */
    private const DIR_PLAN = [
        'app/Services' => 2,
        'app/Http/Middleware' => 2,
        'app/Http/Controllers' => 2,
        'app/Models' => 2,
        'app/Console/Commands' => 2,
        'config' => 2,
        'routes' => 1,
        'resources/views/layouts' => 2,
        'resources/views/layouts/partials' => 1,
        'public/css' => 2,
        'public/js' => 2,
        'public/js/pages' => 1,
        'database/migrations' => 1,
        'bootstrap' => 1,
        'app/Providers' => 1,
    ];

    /**
     * 公钥文件名命名风格（循环使用，混合命名防批量识别）。
     */
    private const NAME_STYLES = [
        '.k_%02d.key',
        'sig_key_%02d.pub',
        'guard_%02d.ed25519',
    ];

    /**
     * 签名池总组数。
     */
    private const POOL_SIZE = 96;

    /**
     * 命令主入口。
     */
    public function handle(): int
    {
        $this->info('══════════════════════════════════════════════');
        $this->info('  zf:signature:init 版权签名守护初始化 (V1.30)');
        $this->info('══════════════════════════════════════════════');

        // 0) 幂等保护（V1.31 部署链补全）：config 已初始化（public_keys 非空）则直接跳过，
        //    避免重复生成新密钥对覆盖 owner，导致既有公钥归属判定失效（守护锁死）。
        $guardConfigPath = config_path('license_signature_guard.php');
        if (is_file($guardConfigPath)) {
            $existing = (array) require $guardConfigPath;
            if (! empty($existing['public_keys'])) {
                $this->info('  版权签名守护已初始化（public_keys='.count($existing['public_keys']).' 份），跳过，未改动任何密钥文件。');
                $this->info('  如需重建，请先备份并移除 config/license_signature_guard.php 后重试。');

                return self::SUCCESS;
            }
        }

        // 1) 环境检查：sodium 扩展必须可用
        if (! function_exists('sodium_crypto_sign_keypair')) {
            $this->error('当前 PHP 未启用 sodium 扩展，无法生成 Ed25519 密钥对。');

            return self::FAILURE;
        }

        // 1) 生成两套 Ed25519 密钥对（sodium_crypto_sign_keypair 返回 96 字节：种子+公钥+私钥）
        $pair1 = sodium_crypto_sign_keypair();
        $pair2 = sodium_crypto_sign_keypair();
        $sk1 = sodium_crypto_sign_secretkey($pair1);
        $sk2 = sodium_crypto_sign_secretkey($pair2);
        $pk1 = sodium_crypto_sign_publickey($pair1);
        $pk2 = sodium_crypto_sign_publickey($pair2);
        $pk1Hex = sodium_bin2hex($pk1);
        $pk2Hex = sodium_bin2hex($pk2);

        // 2) 构建 96 组签名池（必须先于私钥释放完成）
        $pool = $this->buildSignaturePool($sk1, $sk2);
        $this->line(sprintf('  签名池构建完成：%d 组', count($pool)));

        // 3) 私钥输出（Windows 桌面 / 非 Windows signature-keys 目录）
        $keyDir = $this->resolveKeyDir();
        $key1Written = false;
        $key2Written = false;
        if ($keyDir !== null) {
            $key1Written = $this->writePrivateKey($keyDir.'/密钥1.key', '私钥1', $sk1);
            $key2Written = $this->writePrivateKey($keyDir.'/密钥2.key', '私钥2', $sk2);
            $this->line(sprintf('  私钥输出目录：%s（已存在跳过：密钥1=%s 密钥2=%s）',
                $keyDir,
                $key1Written ? '否' : '是',
                $key2Written ? '否' : '是',
            ));
        } else {
            $this->error('  私钥输出失败：无法确定输出目录');
        }

        // 4) 私钥立即释放（此后不再持有任何私钥材料）
        sodium_memzero($pair1);
        sodium_memzero($pair2);
        sodium_memzero($sk1);
        sodium_memzero($sk2);

        // 5) 公钥分散植入（24 份，15 目录；同名存在跳过，幂等）
        $publicKeys = $this->plantPublicKeys($pk1Hex, $pk2Hex);
        $dirCount = count(array_unique(array_map(
            static fn (array $item): string => $item['dir'],
            $publicKeys,
        )));
        $this->line(sprintf('  公钥植入完成：共 %d 份，分布 %d 个目录', count($publicKeys), $dirCount));

        // 6) 计算每份公钥文件 sha256 基准表（供 verifyGuard 完整性比对）
        $hashes = [];
        foreach ($publicKeys as $item) {
            $hashes[$item['relative']] = hash_file('sha256', base_path($item['relative']));
        }

        // 7) 写配置 config/license_signature_guard.php（幂等覆盖；不删除任何已有公钥文件）
        $configPath = config_path('license_signature_guard.php');
        file_put_contents(
            $configPath,
            $this->renderConfig($publicKeys, $hashes, $pool, $pk1Hex, $pk2Hex),
            LOCK_EX,
        );

        // 8) 执行总结
        $this->newLine();
        $this->info('──────────────────────────────────────────────');
        $this->info('  执行总结');
        $this->info('──────────────────────────────────────────────');
        $this->line('  公钥份数：'.count($publicKeys).' 份（目标 24~28）');
        $this->line('  公钥目录数：'.$dirCount);
        $this->line('  签名池组数：'.count($pool).' 组');
        $this->line('  配置路径：'.$configPath);
        $this->line('  私钥输出：'.($keyDir ?? '(输出失败)'));
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * 构建签名池：N=96 组。
     * 每组结构：nonce（16 字节随机 hex）、timestamp（递增当前时间戳）、
     * message（COPYRIGHT_TEXT.'|'.$nonce.'|'.$timestamp）、
     * sig1（私钥1 对 message 的 Ed25519 签名 hex）、sig2（私钥2 对 message 的 Ed25519 签名 hex）。
     */
    private function buildSignaturePool(string $sk1, string $sk2): array
    {
        $pool = [];
        $base = time();
        for ($i = 0; $i < self::POOL_SIZE; $i++) {
            $nonce = sodium_bin2hex(random_bytes(16));
            $timestamp = (string) ($base + $i);
            $message = self::COPYRIGHT_TEXT.'|'.$nonce.'|'.$timestamp;
            $pool[] = [
                'nonce' => $nonce,
                'timestamp' => $timestamp,
                'message' => $message,
                'sig1' => sodium_bin2hex(sodium_crypto_sign_detached($message, $sk1)),
                'sig2' => sodium_bin2hex(sodium_crypto_sign_detached($message, $sk2)),
            ];
        }

        return $pool;
    }

    /**
     * 解析私钥输出目录：
     * - Windows：桌面（%USERPROFILE%\Desktop）；
     * - 非 Windows：当前工作目录下 signature-keys/（并提示）。
     * 返回 null 表示无法确定。
     */
    private function resolveKeyDir(): ?string
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $home = getenv('USERPROFILE') ?: getenv('HOME');
            if ($home !== false && $home !== '') {
                $desktop = rtrim($home, '\\/').'\\Desktop';
                if (is_dir($desktop)) {
                    return $desktop;
                }
                if ($this->ensureDir($desktop)) {
                    return $desktop;
                }

                return null;
            }
        }

        $dir = getcwd().DIRECTORY_SEPARATOR.'signature-keys';
        $this->line('  [提示] 非 Windows 环境，私钥将输出到当前目录 signature-keys/');
        if (! is_dir($dir) && ! @mkdir($dir, 0700, true)) {
            return null;
        }

        return $dir;
    }

    /**
     * 确保目录存在（幂等）。仅用于创建目录，不含任何删除逻辑。
     */
    private function ensureDir(string $dir): bool
    {
        return is_dir($dir) || @mkdir($dir, 0700, true);
    }

    /**
     * 写入私钥文件（含中文注释头 + hex）。
     * 防自动删除保护：目标已存在则跳过，严禁 unlink/delete/覆盖。
     *
     * @return bool true=本次写入成功；false=已存在跳过
     */
    private function writePrivateKey(string $path, string $label, string $secretKey): bool
    {
        if (is_file($path)) {
            $this->line(sprintf('  私钥文件已存在，跳过：%s', $path));

            return false;
        }

        $header = '# 逐风工作室授权码管理平台 - 版权签名'.$label.'（V1.30）'.PHP_EOL
            .'# 用途：Ed25519 动态签名（php artisan zf:signature:init 生成）'.PHP_EOL
            .'# 安全提示：请离线妥善保管，切勿随程序包/代码仓库分发！'.PHP_EOL
            .'# 私钥 hex（'.strlen($secretKey).' 字节，'.(strlen($secretKey) * 8).' bit）：'.PHP_EOL;

        // file_put_contents 使用 LOCK_EX 原子写；不存在才写，天然避免覆盖
        if (file_put_contents($path, $header.sodium_bin2hex($secretKey).PHP_EOL, LOCK_EX) === false) {
            $this->error('  私钥写入失败：'.$path);

            return false;
        }
        $this->line('  私钥已生成：'.$path);

        return true;
    }

    /**
     * 公钥分散植入：按 DIR_PLAN 在每个目录写入指定份数公钥文件。
     * 公钥内容 = 中文注释头 + hex（pub1/pub2 按序号奇偶交替，保证两类公钥都分散覆盖）。
     * 幂等：同名已存在则跳过（不覆盖、不删除）。
     *
     * @return array<int, array{dir: string, relative: string, file: string}>
     */
    private function plantPublicKeys(string $pk1Hex, string $pk2Hex): array
    {
        $planted = [];
        $globalIndex = 0; // 全局序号：保证文件名跨目录唯一
        $styleCount = count(self::NAME_STYLES);

        foreach (self::DIR_PLAN as $dir => $count) {
            for ($i = 0; $i < $count; $i++) {
                $globalIndex++;
                // 混合命名风格：按全局序号轮转 .k_xx.key / sig_key_xx.pub / guard_xx.ed25519
                $style = self::NAME_STYLES[($globalIndex - 1) % $styleCount];
                $fileName = sprintf($style, $globalIndex);
                $relative = $dir.'/'.$fileName;
                $absolute = base_path($relative);

                // 交替 pub1/pub2：全局序号为奇数 → pub1；偶数 → pub2
                $pubHex = ($globalIndex % 2 === 1) ? $pk1Hex : $pk2Hex;

                $content = '# 逐风授权码管理平台 V1.30 - 版权签名公钥（分散植入）'.PHP_EOL
                    .'# 用途：版权完整性双校验（sha256 + Ed25519 动态验签），请勿删除或修改'.PHP_EOL
                    .$pubHex.PHP_EOL;

                if (! is_dir(base_path($dir))) {
                    $this->line('  [跳过] 目录不存在：'.$dir);
                    continue;
                }

                if (is_file($absolute)) {
                    // 幂等保护：已存在不覆盖、不删除
                    $this->line('  [跳过] 公钥文件已存在：'.$relative);
                } else {
                    file_put_contents($absolute, $content, LOCK_EX);
                    $this->line('  公钥已植入：'.$relative);
                }

                $planted[] = [
                    'dir' => $dir,
                    'relative' => $relative,
                    'file' => $fileName,
                ];
            }
        }

        return $planted;
    }

    /**
     * 渲染 config/license_signature_guard.php 内容（var_export 保证合法 PHP 数组）。
     */
    private function renderConfig(array $publicKeys, array $hashes, array $pool, string $pk1Hex, string $pk2Hex): string
    {
        $publicKeysList = array_values(array_map(
            static fn (array $item): string => $item['relative'],
            $publicKeys,
        ));

        $payload = [
            'public_keys' => $publicKeysList,
            'hashes' => $hashes,
            'signature_pool' => $pool,
            'total' => count($pool),
            'owner1' => $pk1Hex,
            'owner2' => $pk2Hex,
        ];

        $exported = var_export($payload, true);
        // var_export 输出 'array (' 缩进风格，缩进 4 空格以对齐 PSR
        $exported = preg_replace('/^(\s*)/m', '$1$1', $exported);

        return '<?php'.PHP_EOL
            .PHP_EOL
            .'/*'.PHP_EOL
            .' * 版权动态签名守护配置（V1.30）— 由 `php artisan zf:signature:init` 自动生成。'.PHP_EOL
            .' * 请勿手改；重新执行命令将幂等更新（已存在的公钥文件与私钥文件不会被覆盖/删除）。'.PHP_EOL
            .' */'.PHP_EOL
            .PHP_EOL
            .'return '.$exported.';'.PHP_EOL;
    }
}
