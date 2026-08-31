<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\AesGcmService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.2.6 users.email 加密存储。
 *
 *  - email 由明文 varchar(191) + unique 改为 AES-256-GCM 密文 text；
 *  - 新增 email_sha256 盲索引列，唯一约束迁移到该列（保留邮箱唯一性）；
 *  - 登录 / 创建 / 校验邮箱等所有按 email 查询路径迁移到盲索引精确匹配
 *    （服务层负责，见 AuthService / User 模型访问器）；
 *  - 历史数据回填：明文 → 规范化（trim + 小写）→ 加密 + 盲索引；
 *    已加密行（可解密）仅补齐盲索引；幂等可重放。
 *  - 密钥取自 ZF_APP_ENCRYPT_KEY（env），无硬编码。
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) 去除 email 明文唯一索引（密文列无法承载原唯一约束）
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        // 2) 新增盲索引列
        Schema::table('users', function (Blueprint $table) {
            $table->char('email_sha256', 64)->nullable()->after('email');
        });

        // 3) 历史数据回填（幂等）
        $aes = app(AesGcmService::class);

        foreach (DB::table('users')->whereNotNull('email')->get(['id', 'email', 'email_sha256']) as $row) {
            $email = (string) $row->email;
            if ($email === '') {
                continue;
            }

            if ($this->isCipher($email)) {
                // 已是密文：仅补齐盲索引（解密失败抛错，禁止双重加密）
                $plain = $aes->decrypt($email);
                if ($row->email_sha256 === null) {
                    DB::table('users')->where('id', $row->id)->update([
                        'email_sha256' => User::sha256Of($plain),
                    ]);
                }
                continue;
            }

            // 明文 → 规范化 → 加密 + 盲索引
            $normalized = mb_strtolower(trim($email));
            DB::table('users')->where('id', $row->id)->update([
                'email' => $aes->encrypt($normalized),
                'email_sha256' => User::sha256Of($normalized),
            ]);
        }

        // 4) 列类型 text + 盲索引唯一
        Schema::table('users', function (Blueprint $table) {
            $table->text('email')->change();
            $table->unique('email_sha256', 'users_email_sha256_unique');
        });
    }

    public function down(): void
    {
        // 仅回滚结构变更，不尝试解密还原（避免回滚时密钥不可用造成数据损坏）
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_sha256_unique');
            $table->dropColumn('email_sha256');
            $table->string('email', 191)->change();
            $table->unique('email');
        });
    }

    /**
     * 判断值是否已为 AES-GCM 密文（base64(iv12||tag16||ct)，最小长度 28）。
     */
    private function isCipher(string $value): bool
    {
        $raw = base64_decode($value, true);

        return $raw !== false && strlen($raw) >= 28;
    }
};
