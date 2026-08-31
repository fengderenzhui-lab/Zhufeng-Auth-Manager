<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 规范化 JSON 序列化：
 * - 键按字典序排序，保证同一数据在不同语言/序列化顺序下签名一致
 * - 用于 Ed25519 签名与客户端验签的确定性载荷
 */
final class CanonicalJson
{
    public static function encode(array $data): string
    {
        ksort($data);

        return json_encode(
            self::sort($data),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    public static function sort($value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $isList = array_is_list($value);

        if ($isList) {
            return array_map([self::class, 'sort'], $value);
        }

        ksort($value, SORT_STRING);

        return array_map([self::class, 'sort'], $value);
    }
}
