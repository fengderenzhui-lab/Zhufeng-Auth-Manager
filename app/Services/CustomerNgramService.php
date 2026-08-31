<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * 客户名称安全模糊检索（n-gram 盲索引）。
 *
 * V1.30：licenses.customer 为 AES-256-GCM 密文，无法 LIKE 模糊检索。
 * 本服务对明文客户名做 2-gram + 3-gram 拆分，子串经 HMAC-SHA256 加盐哈希
 * （盐=ZF_APP_ENCRYPT_KEY 派生，无硬编码）存入 license_customer_ngrams，
 * 密文/哈希均不可还原客户名。
 *
 * 检索策略（保证长关键词可用性，阈值见 config/license.php customer_ngram）：
 *  - 关键词 2-gram 数量 ≤ MAX_2GRAM         → 全部 2-gram AND 匹配；
 *  - 2-gram 超阈值改用 3-gram（AND）；
 *  - 3-gram 仍超阈值                          → 取关键词前 MAX_3GRAM 个 3-gram
 *    （前缀优先 AND，退化为"包含前缀片段"的高召回策略）。
 *  - 关键词 < 2 字符无法拆分 → 返回 null，调用方退化为 sha256 精确匹配。
 */
final class CustomerNgramService
{
    /** 域分隔前缀：与授权码/指纹等其它 HMAC 用途隔离，防串扰 */
    private const DOMAIN = 'zf-license:customer-ngram:v1:';

    public function __construct(private readonly AesGcmService $aes)
    {
    }

    /**
     * n-gram HMAC 密钥：复用 ZF_APP_ENCRYPT_KEY（32 字节 Base64，无硬编码）。
     */
    public function hmacKey(): string
    {
        return $this->aes->key();
    }

    /**
     * 子串 -> HMAC-SHA256 盲索引值。
     */
    public function gramHash(string $gram): string
    {
        return hash_hmac('sha256', self::DOMAIN.$gram, $this->hmacKey());
    }

    /**
     * 对明文客户名生成全部 2-gram + 3-gram（按出现顺序、去重）。
     *
     * @return list<string>
     */
    public function gramsOf(string $customer): array
    {
        $normalized = trim($customer);
        if ($normalized === '') {
            return [];
        }

        $chars = preg_split('//u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false || count($chars) < 2) {
            return [];
        }

        $grams = [];
        for ($i = 0, $n = count($chars); $i < $n - 1; $i++) {
            $grams[] = $chars[$i].$chars[$i + 1];
        }
        for ($i = 0, $n = count($chars); $i < $n - 2; $i++) {
            $grams[] = $chars[$i].$chars[$i + 1].$chars[$i + 2];
        }

        return array_values(array_unique($grams));
    }

    /**
     * 关键词检索：返回命中的 license_id 列表（多 gram AND 语义）。
     * 关键词过短（<2 字符）返回 null，调用方应退化为 sha256 精确匹配。
     *
     * @return list<int>|null
     */
    public function matchingLicenseIds(string $keyword): ?array
    {
        $chars = preg_split('//u', trim($keyword), -1, PREG_SPLIT_NO_EMPTY);
        if ($chars === false || count($chars) < 2) {
            return null;
        }

        $len = count($chars);
        $two = [];
        for ($i = 0; $i < $len - 1; $i++) {
            $two[] = $chars[$i].$chars[$i + 1];
        }
        $three = [];
        for ($i = 0; $i < $len - 2; $i++) {
            $three[] = $chars[$i].$chars[$i + 1].$chars[$i + 2];
        }

        $max2 = max(1, (int) config('license.customer_ngram.max_2gram', 6));
        $max3 = max(1, (int) config('license.customer_ngram.max_3gram', 8));

        if (count($two) <= $max2) {
            $grams = $two;
        } elseif (count($three) <= $max3) {
            $grams = $three;
        } else {
            $grams = array_slice($three, 0, $max3);
        }

        $hashes = array_map(fn (string $g): string => $this->gramHash($g), $grams);
        $hashCount = count($hashes);

        return DB::table('license_customer_ngrams')
            ->whereIn('gram_sha256', $hashes)
            ->groupBy('license_id')
            ->havingRaw('COUNT(DISTINCT gram_sha256) = ?', [$hashCount])
            ->orderBy('license_id')
            ->pluck('license_id')
            ->all();
    }
}
