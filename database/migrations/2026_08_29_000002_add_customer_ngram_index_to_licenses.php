<?php

declare(strict_types=1);

use App\Services\AesGcmService;
use App\Services\CustomerNgramService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.2.6 客户名称安全模糊检索：n-gram 盲索引。
 *
 *  - 新增 license_customer_ngrams（license_id + gram_sha256），
 *    对明文客户名做 2-gram + 3-gram 拆分，子串经 HMAC-SHA256（密钥派生自
 *    ZF_APP_ENCRYPT_KEY）加盐哈希后落库，密文/哈希均不可还原客户名。
 *  - 历史数据回填：先清空再按当前 licenses 数据重建（幂等，可重放）。
 *  - 检索：关键词同规则拆分后哈希匹配（多 gram AND），见 CustomerNgramService。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_customer_ngrams', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('license_id');
            $table->char('gram_sha256', 64);
            $table->index(['gram_sha256', 'license_id']);
            $table->index('license_id');
        });

        $this->rebuild();
    }

    public function down(): void
    {
        Schema::dropIfExists('license_customer_ngrams');
    }

    /**
     * 回填（幂等：清空重建，与当前 licenses 数据保持一致）。
     */
    private function rebuild(): void
    {
        DB::table('license_customer_ngrams')->truncate();

        $aes = app(AesGcmService::class);
        $ngram = app(CustomerNgramService::class);

        $rows = DB::table('licenses')
            ->whereNotNull('customer')
            ->whereNull('deleted_at')
            ->get(['id', 'customer']);

        $inserts = [];
        foreach ($rows as $row) {
            $plain = $this->decryptOrPlain($aes, (string) $row->customer);
            if ($plain === null) {
                continue;
            }

            foreach ($ngram->gramsOf($plain) as $gram) {
                $inserts[] = [
                    'license_id' => (int) $row->id,
                    'gram_sha256' => $ngram->gramHash($gram),
                ];
            }

            if (count($inserts) >= 2000) {
                DB::table('license_customer_ngrams')->insert($inserts);
                $inserts = [];
            }
        }

        if ($inserts !== []) {
            DB::table('license_customer_ngrams')->insert($inserts);
        }
    }

    /**
     * 兼容密文/明文两种存量：密文解密返回明文；明文直接返回。
     * 密文解密失败时抛错（密钥不匹配属部署事故，禁止把密文当明文再次加密）。
     */
    private function decryptOrPlain(AesGcmService $aes, string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $raw = base64_decode($value, true);
        if ($raw === false || strlen($raw) < 28) {
            return $value; // 历史明文
        }

        return $aes->decrypt($value); // 密文：解密失败直接抛错
    }
};
