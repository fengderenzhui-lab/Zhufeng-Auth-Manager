# 逐风授权码管理平台 V1.30 - deploy.bat 中文界面辅助脚本（UTF-8 BOM）
# 职责：大字标题 / Y-N 确认交互 / 随机三要素汇总展示
# 说明：deploy.bat 为纯 ASCII 批处理，中文显示统一由本脚本承载，
#       文件必须保持 UTF-8 with BOM 编码（Windows PowerShell 5.1 依赖 BOM 正确读取中文）。

param(
    [string]$Mode = 'banner',
    [string]$ProjectDir = '.'
)

$ErrorActionPreference = 'Stop'

function Show-Banner {
    Write-Host ""
    $lines = @(
        '  _____ _   _ _   _ ______ _____ _   _ _____',
        ' |__  /| | | | | | || ____|  ___| \ | |  __ \',
        '   / / | | | | | | ||  _| | |__ |  \| | |  \ \',
        '  / /_ | |_| | |_| || |___|  __|| |\  | |  | |',
        ' /____| \___/ \___/ |_____|_|   |_| \_|_|  |_|'
    )
    foreach ($l in $lines) { Write-Host $l -ForegroundColor Cyan }
    Write-Host ""
    Write-Host "  逐风工作室" -ForegroundColor Yellow
    Write-Host "  逐风授权码管理平台 V1.30 一键部署脚本" -ForegroundColor Cyan
    Write-Host "  自动检测依赖 / 初始化配置 / SQLite 迁移 / 随机凭据" -ForegroundColor DarkGray
    Write-Host ""
}

function Show-Confirm {
    # 部署确认交互：输入 Y 继续，输入 N 退出（与 deploy.sh 逻辑一致）
    $choice = Read-Host "是否开始部署逐风授权码管理平台 V1.30？输入 Y 确认部署，输入 N 退出部署"
    if ($choice -notmatch '^[Yy]') {
        Write-Host "已退出部署。" -ForegroundColor Yellow
        exit 1
    }
    Write-Host "已确认，开始部署..." -ForegroundColor Green
}

function Show-Summary {
    # 汇总展示随机三要素：后台地址 / 管理员用户名 / 管理员密码
    $envFile = Join-Path $ProjectDir '.env'
    $credFile = Join-Path $ProjectDir 'storage\app\init-admin-credentials.txt'

    $zfPath = ''
    if (Test-Path $envFile) {
        $line = Get-Content $envFile -Encoding UTF8 | Where-Object { $_ -match '^ZF_ADMIN_PATH=' } | Select-Object -First 1
        if ($line) { $zfPath = ($line -replace '^ZF_ADMIN_PATH=', '').Trim() }
    }

    $user = ''
    $pass = ''
    if (Test-Path $credFile) {
        foreach ($l in (Get-Content $credFile -Encoding UTF8)) {
            if ($l -match '^username\s*=\s*(\S+)')       { $user = $Matches[1].Trim() }
            if ($l -match '^password\s*=\s*(.+)$')       { $pass = $Matches[1].Trim() }
            if ($l -match '用户名\s*[：:]\s*(\S+)')      { $user = $Matches[1].Trim() }
            if ($l -match '密码\s*[：:]\s*(.+)$')         { $pass = $Matches[1].Trim() }
        }
    }

    Write-Host ""
    Write-Host "============================================================" -ForegroundColor Cyan
    Write-Host "  逐风授权码管理平台 V1.30 部署完成 —— 随机三要素，请立即保存" -ForegroundColor Green
    Write-Host "============================================================" -ForegroundColor Cyan
    Write-Host ("  [1] 后台访问地址 : http://127.0.0.1:8000/" + $zfPath + "/login") -ForegroundColor Green
    Write-Host ("  [2] 管理员用户名 : " + $user) -ForegroundColor Green
    Write-Host ("  [3] 管理员密码   : " + $pass) -ForegroundColor Green
    Write-Host "============================================================" -ForegroundColor Cyan
    Write-Host "  服务已在新窗口启动（php artisan serve）" -ForegroundColor DarkGray
    Write-Host "  首次登录后请在「个人中心」立即修改管理员密码" -ForegroundColor DarkGray
    Write-Host ("  凭据文件：" + $credFile) -ForegroundColor DarkGray
    Write-Host "============================================================" -ForegroundColor Cyan
    Write-Host ""
}

switch ($Mode) {
    'confirm' { Show-Confirm }
    'summary' { Show-Summary }
    default   { Show-Banner }
}
