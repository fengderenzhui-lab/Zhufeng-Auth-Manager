<?php

/**
 * 逐风授权码管理平台 V1.30 - deploy.bat 凭据解析辅助脚本
 *
 * 职责：
 *   读取 storage/app/init-admin-credentials.txt（UTF-8），解析出随机管理员
 *   用户名与密码，并以 ASCII 键值对（ZF_ADMIN_USER= / ZF_ADMIN_PASS=）输出，
 *   供 deploy.bat 的 for /f 直接 set 为环境变量。
 *
 * 说明：
 *   本脚本独立于 Laravel 运行，不加载框架，仅做纯文本解析；
 *   放在 deploy-helpers/ 目录随部署脚本一起交付。
 */

$file = __DIR__ . '/../storage/app/init-admin-credentials.txt';

// 凭据文件不存在（如重复部署且未生成新账号）时返回非 0，调用方忽略即可
if (!is_file($file)) {
    fwrite(STDERR, '[parse-credentials] 凭据文件不存在，跳过解析' . PHP_EOL);
    exit(1);
}

$content = (string) file_get_contents($file);
$username = '';
$password = '';

// 用户名行：兼容 "username=xxx" 与 "  用户名：adm_xxx" / "  用户名: adm_xxx" 两种格式
if (preg_match('/^username\s*=\s*(\S+)/m', $content, $m)) {
    $username = trim($m[1]);
} elseif (preg_match('/用户名\s*[：:]\s*(\S+)/u', $content, $m)) {
    $username = trim($m[1]);
}

// 密码行：兼容 "password=xxx" 与 "  密码  ：Ab3#xYz9@..."（密码可能包含任意非空白字符）
if (preg_match('/^password\s*=\s*(.+)$/m', $content, $m)) {
    $password = trim($m[1]);
} elseif (preg_match('/密码\s*[：:]\s*(.+)/u', $content, $m)) {
    $password = trim($m[1]);
}

// 仅输出 ASCII 键值对，避免 Windows 批处理中文编码问题
echo 'ZF_ADMIN_USER=' . $username . PHP_EOL;
echo 'ZF_ADMIN_PASS=' . $password . PHP_EOL;
exit(0);
