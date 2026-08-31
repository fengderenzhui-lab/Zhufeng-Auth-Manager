<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AesGcmService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 加密体系升级：
 *  1) users.hmac_secret_encrypted —— 每管理员独立 HMAC 签名密钥（AES-256-GCM 加密存储），
 *     已有管理员自动回填随机密钥。
 *  2) devices.fingerprint_hash —— 由 char(64) 明文哈希改为 text AES-256-GCM 密文；
 *     新增 fingerprint_hash_sha256 明文指纹 sha256 独立索引列用于精确查询；
 *     历史明文指纹自动加密回填并重建唯一约束。
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->ensureAesKey();

        $aes = app(AesGcmService::class);

        // ---------- 1) users ----------
        if (! Schema::hasColumn('users', 'hmac_secret_encrypted')) {
            Schema::table('users', function (Blueprint $table) {
                $table->text('hmac_secret_encrypted')->nullable()->after('password');
            });
        }

        foreach (DB::table('users')->whereNull('deleted_at')->get(['id', 'hmac_secret_encrypted']) as $user) {
            if (blank($user->hmac_secret_encrypted)) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['hmac_secret_encrypted' => $aes->encrypt(User::generateHmacSecret())]);
            }
        }

        // ---------- 2) devices ----------
        if (! Schema::hasColumn('devices', 'fingerprint_hash_sha256')) {
            Schema::table('devices', function (Blueprint $table) {
                // 先移除基于密文列的旧唯一/索引，再放宽列类型
                $table->dropUnique(['license_id', 'fingerprint_hash']);
                $table->dropIndex(['fingerprint_hash']);
                $table->text('fingerprint_hash')->nullable()->change();
                $table->char('fingerprint_hash_sha256', 64)->nullable()->after('fingerprint_hash');
                $table->unique(['license_id', 'fingerprint_hash_sha256']);
                $table->index('fingerprint_hash_sha256');
            });
        }

        // 历史明文指纹自动加密回填（含软删除行，保证查询一致性）
        foreach (DB::table('devices')->whereNotNull('fingerprint_hash')->get(['id', 'fingerprint_hash']) as $device) {
            $plain = (string) $device->fingerprint_hash;
            // 幂等：已加密过的行跳过（AES 密文不是 64 位 hex）
            if (preg_match('/^[0-9a-f]{64}$/i', $plain) !== 1) {
                continue;
            }

            DB::table('devices')
                ->where('id', $device->id)
                ->update([
                    'fingerprint_hash' => $aes->encrypt($plain),
                    'fingerprint_hash_sha256' => hash('sha256', $plain),
                ]);
        }
    }

    public function down(): void
    {
        // 仅回滚结构变更，不尝试解密还原（避免迁移回滚时密钥不可用造成数据损坏）
        if (Schema::hasColumn('users', 'hmac_secret_encrypted')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('hmac_secret_encrypted');
            });
        }

        if (Schema::hasColumn('devices', 'fingerprint_hash_sha256')) {
            Schema::table('devices', function (Blueprint $table) {
                $table->dropUnique(['license_id', 'fingerprint_hash_sha256']);
                $table->dropIndex(['fingerprint_hash_sha256']);
                $table->dropColumn('fingerprint_hash_sha256');
                $table->string('fingerprint_hash', 64)->nullable()->change();
                $table->unique(['license_id', 'fingerprint_hash']);
                $table->index('fingerprint_hash');
            });
        }
    }

    /**
     * 确保 ZF_APP_ENCRYPT_KEY 可用：缺失时生成 32 字节随机密钥并写入 .env（与
     * AesGcmService 的 key 派生规则一致），使迁移可独立于初始化命令运行。
     *
     * 注意：
     *  - 生成值为「32 字节明文密钥的纯 Base64」（无 Laravel 'base64:' 前缀），
     *    与 AesGcmService::key() 的 base64_decode(strict)+32 字节校验完全一致；
     *  - 只写入项目 .env，严禁写入 .env.example（示例文件不允许出现真实密钥）。
     */
    private function ensureAesKey(): void
    {
        $key = (string) Config::get('license.aes.encrypt_key', '');

        if ($key !== '') {
            return;
        }

        $generated = base64_encode(random_bytes(32));
        $envPath = base_path('.env');

        if (is_file($envPath)) {
            $content = (string) file_get_contents($envPath);
            if (str_contains($content, 'ZF_APP_ENCRYPT_KEY=')) {
                $content = preg_replace(
                    '/^ZF_APP_ENCRYPT_KEY=.*$/m',
                    'ZF_APP_ENCRYPT_KEY='.$generated,
                    $content,
                ) ?? $content;
            } else {
                $content .= PHP_EOL.'ZF_APP_ENCRYPT_KEY='.$generated.PHP_EOL;
            }
            file_put_contents($envPath, $content, LOCK_EX);
        }

        Config::set('license.aes.encrypt_key', $generated);
        putenv('ZF_APP_ENCRYPT_KEY='.$generated);
    }
};
