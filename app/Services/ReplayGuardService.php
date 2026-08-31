<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Request;
use RuntimeException;
use Illuminate\Support\Facades\Cache as CacheFacade;

/**
 * 防重放服务：请求时间戳窗口 + nonce 唯一性 + 可选 HMAC 签名。
 *
 * 请求头约定：
 *  - X-Timestamp ：Unix 秒级时间戳，须在窗口内（默认 ±300s）
 *  - X-Nonce     ：客户端每次请求随机生成，must 唯一（缓存去重）
 *  - X-Signature ：HMAC-SHA256(clientSecret, "method\npath\n{timestamp}\n{nonce}\n{rawBody}")
 *                  strict 模式下必须携带且必须校验通过
 *
 * 注意：签名密钥 LICENSING_REPLAY_CLIENT_SECRET 是与集成方共享的防重放密钥，
 * 与授权码密钥体系相互独立。
 */
final class ReplayGuardService
{
    public function __construct()
    {
    }

    /**
     * 校验请求头防重放数据。失败抛出 RuntimeException。
     *
     * @param  string  $mode  校验模式：
     *                        'strict' = SDK 端点，强制 X-Signature（LICENSING_REPLAY_CLIENT_SECRET）
     *                        'admin'  = 管理端 API，强制 X-Signature（当前登录管理员独立 secret）
     *                        'loose'  = 登录等公开入口，仅校验时间戳 + nonce
     */
    public function validateRequest(Request $request, string $mode = 'strict'): void
    {
        $timestamp = $request->header('X-Timestamp');
        $nonce = $request->header('X-Nonce');
        $signature = $request->header('X-Signature');

        if ($timestamp === null || ! ctype_digit($timestamp)) {
            throw new RuntimeException('缺少或非法的时间戳 X-Timestamp。');
        }

        if ($nonce === null || mb_strlen($nonce) < 16 || mb_strlen($nonce) > 128) {
            throw new RuntimeException('缺少或非法的随机串 X-Nonce（16-128 字符）。');
        }

        $ts = (int) $timestamp;
        $window = (int) config('license.security.replay.window_seconds', 300);
        $now = time();

        if (abs($now - $ts) > $window) {
            throw new RuntimeException('请求时间戳超出允许窗口。');
        }

        $secret = $this->resolveSecret($request, $mode);

        // 签名强制策略：
        //  - strict：SDK/客户端对接端点，必须携带 X-Signature（client_secret）
        //  - admin ：管理端端点，由 LICENSING_ADMIN_SIGNATURE_REQUIRED 控制；
        //            关闭时可退化为仅时间戳 + nonce（登录端点等效 loose）
        //  - loose ：仅时间戳 + nonce，携带签名时顺带校验
        $requireSignature = match ($mode) {
            'strict' => true,
            'admin' => (bool) config('license.admin_security.require_signature', true),
            default => $signature !== null,
        };

        if ($requireSignature || $signature !== null) {
            if ($secret === '') {
                throw new RuntimeException('防重放签名密钥不可用。');
            }
            if ($signature === null) {
                throw new RuntimeException('该接口必须携带 X-Signature。');
            }

            $expected = $this->buildSignature($request, $ts, $nonce, $secret);
            if (! hash_equals($expected, $signature)) {
                throw new RuntimeException('请求签名校验失败。');
            }
        }

        // nonce 唯一性（缓存 SETNX，自动过期）
        $ttl = (int) config('license.security.replay.nonce_ttl_seconds', 600);
        $cacheKey = 'replay:'.sha1($request->method().'|'.$request->path().'|'.$nonce);

        /** @var Cache $cache */
        $cache = CacheFacade::store();

        if (! $cache->add($cacheKey, 1, $ttl)) {
            throw new RuntimeException('请求 nonce 已被使用（疑似重放）。');
        }
    }

    /**
     * 解析当前模式下的签名密钥：
     * - strict：LICENSING_REPLAY_CLIENT_SECRET（与 SDK 集成方共享）
     * - admin：当前登录管理员（auth.api 中间件已注入 auth_user）的独立 HMAC secret
     * - loose：空串（不要求签名）
     */
    public function resolveSecret(Request $request, string $mode): string
    {
        if ($mode === 'admin') {
            /** @var \App\Models\User|null $user */
            $user = $request->attributes->get('auth_user');
            if ($user === null || blank($user->hmac_secret_encrypted)) {
                return '';
            }

            return $user->hmacSecret();
        }

        if ($mode === 'loose') {
            return '';
        }

        return (string) config('license.security.replay.client_secret', '');
    }

    /**
     * 构建与校验一致的签名串。
     */
    public function buildSignature(Request $request, int $timestamp, string $nonce, string $secret): string
    {
        $rawBody = (string) $request->getContent();

        $payload = implode("\n", [
            $request->method(),
            $request->path(),
            (string) $timestamp,
            $nonce,
            $rawBody,
        ]);

        return hash_hmac('sha256', $payload, $secret);
    }
}
