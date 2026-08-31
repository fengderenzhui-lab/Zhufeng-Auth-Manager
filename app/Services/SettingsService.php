<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;

/**
 * 系统配置服务 —— settings 表 DB 值优先、config 兜底（V1.32）。
 *
 * 语义：
 *  - get($key)：先查 settings 表（DB 运行期配置），无记录时回落 config($key, $default)（.env 静态配置）；
 *  - 未配置（DB 无记录）时行为与接入前完全一致（config 原值原样返回）；
 *  - 缓存：请求生命周期内按 key 缓存，set()/forget() 后即时失效，设置页保存立即生效、无需清 config 缓存。
 *
 * 适用：非敏感业务运行参数（心跳周期/超时、审计保留天数、雷池开关等）。
 * 敏感项（密钥/TLS/DB/缓存等）继续走 .env，严禁写入 settings 表（等保 M-07 白名单在 SettingController 侧拦截）。
 */
final class SettingsService
{
    /** @var array<string, mixed> 请求生命周期内按 key 缓存 */
    private array $cache = [];

    /**
     * 读取配置：settings 表 DB 值优先，无记录回落 config 兜底。
     *
     * @param  mixed  $default  config 无该键时的最终兜底值（默认 null）
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $setting = Setting::query()->where('key', $key)->first();
        if ($setting === null) {
            return $this->cache[$key] = config($key, $default);
        }

        return $this->cache[$key] = $setting->castValue();
    }

    /**
     * 写入配置（不存在则创建，存在则更新），并失效缓存。
     *
     * @param  mixed  $value
     */
    public function set(
        string $key,
        mixed $value,
        string $type = 'string',
        ?string $description = null,
        ?int $updatedBy = null,
    ): Setting {
        $setting = Setting::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => (string) $value,
                'type' => $type,
                'description' => $description,
                'updated_by' => $updatedBy,
            ]
        );

        $this->forget($key);

        return $setting;
    }

    /**
     * 失效指定 key 的缓存（删除配置项时同样调用）。
     */
    public function forget(string $key): void
    {
        unset($this->cache[$key]);
    }

    /**
     * 读取指定 key 的 DB 记录（无则返回 null；供删除等场景使用）。
     */
    public function find(string $key): ?Setting
    {
        return Setting::query()->where('key', $key)->first();
    }

    /**
     * 全部 DB 配置记录（按 key 排序，供设置页回显）。
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Setting>
     */
    public function all(): \Illuminate\Database\Eloquent\Collection
    {
        return Setting::query()->orderBy('key')->get();
    }
}
