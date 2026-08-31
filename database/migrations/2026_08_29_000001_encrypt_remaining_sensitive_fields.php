<?php

declare(strict_types=1);

use App\Services\AesGcmService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * v1.2.5 全链路加密升级（第二批敏感字段）：
 *
 *  1) licenses.customer —— 客户名称 AES-256-GCM 密文；新增 customer_sha256 盲索引列（等值检索）；
 *     原有基于明文的 LIKE 模糊查询降级为 sha256 精确匹配（密文无法模糊检索）。
 *  2) licenses.meta —— 客户自定义元数据 JSON 改为 AES-256-GCM 密文。
 *  3) login_attempts.email / ip / user_agent —— 登录尝试记录隐私加密；
 *     新增 email_sha256 / ip_sha256 盲索引列，防爆破锁定计数等值查询迁移到索引列。
 *  4) audit_logs.ip / user_agent / context —— 审计日志敏感数据加密存储；
 *     哈希链 canonical 始终基于解密后的明文语义重建，历史行不回算 hash，链保持完整。
 *  5) devices.last_ip / last_user_agent —— 设备痕迹加密。
 *  6) heartbeats.client_ip / client_ua —— 心跳痕迹加密。
 *  7) users.last_login_ip —— 管理员登录 IP 加密。
 *
 * 幂等策略：对既有行先尝试 AES-GCM 解密，成功视为已加密（仅补齐盲索引列），
 * 解密失败视为历史明文（执行加密回填）。密文一律经 AesGcmService（env 密钥），无硬编码。
 */
return new class extends Migration
{
    public function up(): void
    {
        $aes = app(AesGcmService::class);

        // ---------- 1) licenses ----------
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropIndex(['customer']);
            $table->text('customer')->nullable()->change();
            $table->char('customer_sha256', 64)->nullable()->after('customer');
            $table->index('customer_sha256');
            $table->text('meta')->nullable()->change();
        });

        foreach (DB::table('licenses')->whereNotNull('customer')->get(['id', 'customer']) as $row) {
            $plain = $this->tryDecrypt($aes, (string) $row->customer);
            if ($plain !== null) {
                // 已加密：仅补齐盲索引列
                DB::table('licenses')->where('id', $row->id)->update([
                    'customer_sha256' => hash('sha256', $plain),
                ]);
            } else {
                DB::table('licenses')->where('id', $row->id)->update([
                    'customer' => $aes->encrypt((string) $row->customer),
                    'customer_sha256' => hash('sha256', (string) $row->customer),
                ]);
            }
        }

        foreach (DB::table('licenses')->whereNotNull('meta')->get(['id', 'meta']) as $row) {
            if ($this->tryDecrypt($aes, (string) $row->meta) !== null) {
                continue; // 已加密
            }
            DB::table('licenses')->where('id', $row->id)->update([
                'meta' => $aes->encrypt((string) $row->meta),
            ]);
        }

        // ---------- 2) login_attempts ----------
        Schema::table('login_attempts', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->dropIndex(['email', 'ip', 'attempted_at']);
            $table->text('email')->change();
            $table->char('email_sha256', 64)->nullable()->after('email');
            $table->index('email_sha256');
            $table->text('ip')->change();
            $table->char('ip_sha256', 64)->nullable()->after('ip');
            $table->index('ip_sha256');
            $table->text('user_agent')->nullable()->change();
        });

        foreach (DB::table('login_attempts')->get(['id', 'email', 'ip']) as $row) {
            $updates = [];

            $emailPlain = $this->tryDecrypt($aes, (string) $row->email);
            if ($emailPlain !== null) {
                $updates['email_sha256'] = hash('sha256', $emailPlain);
            } else {
                $updates['email'] = $aes->encrypt((string) $row->email);
                $updates['email_sha256'] = hash('sha256', (string) $row->email);
            }

            $ipPlain = $this->tryDecrypt($aes, (string) $row->ip);
            if ($ipPlain !== null) {
                $updates['ip_sha256'] = hash('sha256', $ipPlain);
            } else {
                $updates['ip'] = $aes->encrypt((string) $row->ip);
                $updates['ip_sha256'] = hash('sha256', (string) $row->ip);
            }

            if ($updates !== []) {
                DB::table('login_attempts')->where('id', $row->id)->update($updates);
            }
        }

        foreach (DB::table('login_attempts')->whereNotNull('user_agent')->get(['id', 'user_agent']) as $row) {
            if ($this->tryDecrypt($aes, (string) $row->user_agent) !== null) {
                continue;
            }
            DB::table('login_attempts')->where('id', $row->id)->update([
                'user_agent' => $aes->encrypt((string) $row->user_agent),
            ]);
        }

        // ---------- 3) audit_logs（不回算 hash，保持哈希链完整） ----------
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->text('ip')->nullable()->change();
            $table->text('user_agent')->nullable()->change();
            $table->text('context')->nullable()->change();
        });

        foreach (DB::table('audit_logs')->get(['id', 'ip', 'user_agent', 'context']) as $row) {
            $updates = [];

            if ($row->ip !== null && $this->tryDecrypt($aes, (string) $row->ip) === null) {
                $updates['ip'] = $aes->encrypt((string) $row->ip);
            }
            if ($row->user_agent !== null && $this->tryDecrypt($aes, (string) $row->user_agent) === null) {
                $updates['user_agent'] = $aes->encrypt((string) $row->user_agent);
            }
            if ($row->context !== null && $this->tryDecrypt($aes, (string) $row->context) === null) {
                $updates['context'] = $aes->encrypt((string) $row->context);
            }

            if ($updates !== []) {
                DB::table('audit_logs')->where('id', $row->id)->update($updates);
            }
        }

        // ---------- 4) devices ----------
        Schema::table('devices', function (Blueprint $table) {
            $table->text('last_ip')->nullable()->change();
            $table->text('last_user_agent')->nullable()->change();
        });

        foreach (DB::table('devices')->whereNotNull('last_ip')->get(['id', 'last_ip']) as $row) {
            if ($this->tryDecrypt($aes, (string) $row->last_ip) === null) {
                DB::table('devices')->where('id', $row->id)->update([
                    'last_ip' => $aes->encrypt((string) $row->last_ip),
                ]);
            }
        }

        foreach (DB::table('devices')->whereNotNull('last_user_agent')->get(['id', 'last_user_agent']) as $row) {
            if ($this->tryDecrypt($aes, (string) $row->last_user_agent) === null) {
                DB::table('devices')->where('id', $row->id)->update([
                    'last_user_agent' => $aes->encrypt((string) $row->last_user_agent),
                ]);
            }
        }

        // ---------- 5) heartbeats ----------
        Schema::table('heartbeats', function (Blueprint $table) {
            $table->text('client_ip')->nullable()->change();
            $table->text('client_ua')->nullable()->change();
        });

        foreach (DB::table('heartbeats')->whereNotNull('client_ip')->get(['id', 'client_ip']) as $row) {
            if ($this->tryDecrypt($aes, (string) $row->client_ip) === null) {
                DB::table('heartbeats')->where('id', $row->id)->update([
                    'client_ip' => $aes->encrypt((string) $row->client_ip),
                ]);
            }
        }

        foreach (DB::table('heartbeats')->whereNotNull('client_ua')->get(['id', 'client_ua']) as $row) {
            if ($this->tryDecrypt($aes, (string) $row->client_ua) === null) {
                DB::table('heartbeats')->where('id', $row->id)->update([
                    'client_ua' => $aes->encrypt((string) $row->client_ua),
                ]);
            }
        }

        // ---------- 6) users ----------
        Schema::table('users', function (Blueprint $table) {
            $table->text('last_login_ip')->nullable()->change();
        });

        foreach (DB::table('users')->whereNotNull('last_login_ip')->get(['id', 'last_login_ip']) as $row) {
            if ($this->tryDecrypt($aes, (string) $row->last_login_ip) === null) {
                DB::table('users')->where('id', $row->id)->update([
                    'last_login_ip' => $aes->encrypt((string) $row->last_login_ip),
                ]);
            }
        }
    }

    public function down(): void
    {
        // 仅回滚结构变更，不尝试解密还原（避免回滚时密钥不可用造成数据损坏）
        Schema::table('licenses', function (Blueprint $table) {
            $table->dropIndex(['customer_sha256']);
            $table->dropColumn('customer_sha256');
            $table->string('customer', 128)->nullable()->change();
            $table->string('customer', 128)->nullable()->index();
            $table->json('meta')->nullable()->change();
        });

        Schema::table('login_attempts', function (Blueprint $table) {
            $table->dropIndex(['email_sha256']);
            $table->dropIndex(['ip_sha256']);
            $table->dropColumn(['email_sha256', 'ip_sha256']);
            $table->string('email', 191)->change();
            $table->string('ip', 45)->change();
            $table->string('user_agent', 255)->nullable()->change();
            $table->index('email');
            $table->index(['email', 'ip', 'attempted_at']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->string('ip', 45)->nullable()->change();
            $table->string('user_agent', 255)->nullable()->change();
            $table->json('context')->nullable()->change();
        });

        Schema::table('devices', function (Blueprint $table) {
            $table->string('last_ip', 45)->nullable()->change();
            $table->string('last_user_agent', 255)->nullable()->change();
        });

        Schema::table('heartbeats', function (Blueprint $table) {
            $table->string('client_ip', 45)->nullable()->change();
            $table->string('client_ua', 255)->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('last_login_ip', 45)->nullable()->change();
        });
    }

    /**
     * 尝试 AES-GCM 解密；成功返回明文，失败（历史明文）返回 null。
     */
    private function tryDecrypt(AesGcmService $aes, string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        try {
            $plain = $aes->decrypt($value);

            return is_string($plain) && $plain !== '' ? $plain : null;
        } catch (\Throwable) {
            return null;
        }
    }
};
