@echo off
chcp 65001 >nul
setlocal EnableDelayedExpansion

REM ===========================================================================
REM  Zhufeng License Platform - Windows One-Click Deploy Script (V1.30)
REM  deploy.bat (ASCII only) + deploy-helpers\ui.ps1 (UTF-8 BOM, Chinese UI)
REM  Same logic as deploy.sh: banner / Y-N confirm / dependency auto-install /
REM  .env init / SQLite migrate / V1.30 signature check / random admin / serve
REM  NOTE: NEVER touch desktop private keys (key1.key / key2.key), only verify
REM        public keys shipped inside the repository.
REM ===========================================================================

set "PROJECT_DIR=%~dp0"
cd /d "%PROJECT_DIR%"
set "PS_UI=powershell -NoProfile -ExecutionPolicy Bypass -File "%PROJECT_DIR%deploy-helpers\ui.ps1""

REM ----------------------------- 1. Banner + Confirm ------------------------
%PS_UI% -Mode banner -ProjectDir "%PROJECT_DIR%"
%PS_UI% -Mode confirm -ProjectDir "%PROJECT_DIR%"
if errorlevel 1 (
  exit /b 0
)
echo.
echo  [deploy] Confirmed. Starting deployment...
echo.

REM ----------------------------- 2. Detect PHP (auto download if missing) ---
set "PHP_CMD=php"
where php >nul 2>&1
if errorlevel 1 (
  echo  [deploy] PHP not found, downloading PHP 8.2 to %USERPROFILE%\.zf-tools\php ...
  call :download_php
  if errorlevel 1 goto :fail
  set "PHP_CMD=%USERPROFILE%\.zf-tools\php\php.exe"
) else (
  php -r "exit(version_compare(PHP_VERSION,'8.2.0','>=')?0:1);" >nul 2>&1
  if errorlevel 1 (
    echo  [deploy] System PHP below 8.2, downloading PHP 8.2 to %USERPROFILE%\.zf-tools\php ...
    call :download_php
    if errorlevel 1 goto :fail
    set "PHP_CMD=%USERPROFILE%\.zf-tools\php\php.exe"
  )
)
echo  [deploy] Using PHP: !PHP_CMD!

REM ----------------------------- 3. Verify required extensions --------------
if "!PHP_CMD!"=="php" (
  php -r "$req=array('sodium','mbstring','openssl','pdo_sqlite','sqlite3','fileinfo','bcmath'); $m=get_loaded_extensions(); $miss=array_diff($req,$m); if(!empty($miss)){ fwrite(STDERR,'missing: '.implode(',',$miss).PHP_EOL); exit(1); }" >nul 2>nul
  if errorlevel 1 (
    echo  [deploy] System PHP missing required extensions ^(sodium/mbstring/openssl/pdo_sqlite/sqlite3/fileinfo/bcmath^).
    echo  [deploy] Enable them and retry, or remove system php and let this script download a full PHP 8.2.
    goto :fail
  )
  echo  [deploy] PHP extensions OK
)

REM ----------------------------- 4. Detect Composer (auto install) ---------
set "COMPOSER_CMD=composer"
where composer >nul 2>&1
if errorlevel 1 (
  echo  [deploy] Composer not found, installing to %USERPROFILE%\.zf-tools\composer ...
  call :download_composer
  if errorlevel 1 goto :fail
  set "COMPOSER_CMD=%USERPROFILE%\.zf-tools\composer\composer.bat"
)
echo  [deploy] Using Composer: !COMPOSER_CMD!

REM ----------------------------- 5. composer install ------------------------
echo  [deploy] Running composer install ^(--no-dev^) ...
call !COMPOSER_CMD! install --no-dev --no-interaction --prefer-dist --optimize-autoloader
if errorlevel 1 goto :fail

REM ----------------------------- 6. .env init -------------------------------
if not exist .env (
  copy /Y .env.example .env >nul
)
echo  [deploy] Generating Laravel APP_KEY...
!PHP_CMD! artisan key:generate --force
if errorlevel 1 goto :fail

echo  [deploy] Generating Ed25519/HMAC keys ^(fill missing only^)...
!PHP_CMD! artisan license:keys --write
if errorlevel 1 goto :fail

echo  [deploy] Generating random admin path ^(ZF_ADMIN_PATH^)...
!PHP_CMD! artisan zf:admin-path --write --length=6
if errorlevel 1 goto :fail

REM Local SQLite deployment params (idempotent write into .env)
!PHP_CMD! -r "$f='.env'; $c=file_get_contents($f); $repl=array('APP_ENV=local','APP_DEBUG=false','APP_URL=http://127.0.0.1:8000','APP_FORCE_HTTPS=false','DB_CONNECTION=sqlite','CACHE_STORE=file','SESSION_DRIVER=file','SESSION_SECURE_COOKIE=false'); foreach($repl as $line){ $k=explode('=',$line,2)[0]; if(preg_match('/^'.$k.'=.*$/m',$c)){ $c=preg_replace('/^'.$k.'=.*$/m',$line,$c); } else { $c.=$line.PHP_EOL; } } file_put_contents($f,$c); echo '[deploy] .env local params written',PHP_EOL;"
if errorlevel 1 goto :fail

if not exist database\database.sqlite (
  type nul > database\database.sqlite
)

REM ----------------------------- 7. V1.30 signature check -------------------
REM Verify public keys only; NEVER generate or touch private keys.
echo  [deploy] V1.30 signature module check ^(public keys only^)...
if exist config\license_signature_guard.php (
  !PHP_CMD! -r "$root=getcwd(); $cfg=require 'config/license_signature_guard.php'; $keys=isset($cfg['public_keys'])?$cfg['public_keys']:array(); $ok=0; $miss=array(); foreach($keys as $rel){ if(is_string($rel) && is_file($root.'/'.str_replace('\\','/',$rel))){ $ok++; } else { $miss[]=(string)$rel; } } echo '[signature] config keys='.count($keys).' ready='.$ok.PHP_EOL; exit((count($keys)>0 && $ok===count($keys))?0:1);"
  if errorlevel 1 (
    echo  [deploy] WARNING: some public key files missing, ensure repo is complete.
  ) else (
    echo  [deploy] V1.30 signature public keys ready ^(no private key touched^)
  )
) else (
  echo  [deploy] WARNING: config\license_signature_guard.php not found.
)

REM ----------------------------- 8. Database migrate ------------------------
echo  [deploy] Running database migration ^(SQLite^)...
!PHP_CMD! artisan migrate --force
if errorlevel 1 goto :fail

REM ----------------------------- 9. Random admin account --------------------
echo  [deploy] Creating random admin account ^(skip if exists^)...
!PHP_CMD! artisan zf:init-admin

REM Parse credentials (via UTF-8 helper script to avoid codepage issues)
set "ZF_ADMIN_USER="
set "ZF_ADMIN_PASS="
if exist "%PROJECT_DIR%deploy-helpers\parse-credentials.php" (
  for /f "delims=" %%L in ('!PHP_CMD! "%PROJECT_DIR%deploy-helpers\parse-credentials.php"') do set "%%L"
)

REM Read random admin path
set "ZF_PATH="
for /f "tokens=1,* delims==" %%A in ('findstr /B "ZF_ADMIN_PATH=" .env') do set "ZF_PATH=%%B"

REM ----------------------------- 10. Start service (new window) -------------
echo  [deploy] Starting local service ^(http://127.0.0.1:8000^)...
start "Zhufeng License Platform V1.30" cmd /k ""!PHP_CMD!" artisan serve --host=127.0.0.1 --port=8000"

REM ----------------------------- 11. Summary output -------------------------
%PS_UI% -Mode summary -ProjectDir "%PROJECT_DIR%"
echo.
pause
exit /b 0

REM ----------------------------- Failure exit -------------------------------
:fail
echo.
echo  [FAIL] Deployment failed, please check messages above.
pause
exit /b 1

REM ----------------------------- Download PHP 8.2 ---------------------------
:download_php
powershell -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; $dir=Join-Path $env:USERPROFILE '.zf-tools\php'; $zip=Join-Path $env:TEMP 'php82.zip'; [Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12; if(!(Test-Path (Join-Path $dir 'php.exe'))){ New-Item -ItemType Directory -Force -Path $dir | Out-Null; Invoke-WebRequest -Uri 'https://windows.php.net/downloads/releases/archives/php-8.2.33-nts-Win32-vs16-x64.zip' -OutFile $zip -UseBasicParsing; Expand-Archive -Path $zip -DestinationPath $dir -Force; Remove-Item $zip -Force }; $ini=Join-Path $dir 'php.ini'; if(!(Test-Path $ini)){ Copy-Item (Join-Path $dir 'php.ini-development') $ini }; $lines=Get-Content $ini; $lines=$lines -replace '^;extension_dir','extension_dir'; foreach($e in @('sodium','mbstring','openssl','pdo_sqlite','sqlite3','fileinfo','bcmath','curl','zip','gd','xml','ctype','tokenizer','dom','simplexml')){ $lines=$lines -replace ('^;extension='+$e),('extension='+$e) }; Set-Content -Path $ini -Value $lines -Encoding ASCII; Write-Output 'PHP 8.2 download OK'"
if errorlevel 1 (
  echo  [FAIL] PHP auto-download failed, install PHP 8.2 manually ^(sodium/mbstring/pdo_sqlite^) and retry.
  exit /b 1
)
exit /b 0

REM ----------------------------- Install Composer ---------------------------
:download_composer
mkdir "%USERPROFILE%\.zf-tools\composer" >nul 2>&1
powershell -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; $dir=Join-Path $env:USERPROFILE '.zf-tools\composer'; [Net.ServicePointManager]::SecurityProtocol=[Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://getcomposer.org/download/latest-stable/composer.phar' -OutFile (Join-Path $dir 'composer.phar') -UseBasicParsing; Write-Output 'Composer phar download OK'"
if errorlevel 1 (
  echo  [FAIL] Composer auto-download failed, install Composer manually and retry.
  exit /b 1
)
(
echo @echo off
echo if exist "%%~dp0..\php\php.exe" ^(
echo   "%%~dp0..\php\php.exe" "%%~dp0composer.phar" %%*
echo ^) else ^(
echo   @php "%%~dp0composer.phar" %%*
echo ^)
) > "%USERPROFILE%\.zf-tools\composer\composer.bat"
exit /b 0
