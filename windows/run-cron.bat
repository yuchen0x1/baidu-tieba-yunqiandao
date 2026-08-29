@echo off
setlocal
cd /d "%~dp0.."
where php >nul 2>nul
if errorlevel 1 (
    echo 未找到 PHP，请安装 PHP 8.2+ 并将 php.exe 加入 PATH。
    exit /b 1
)
php "%CD%\do.php"
set exitCode=%errorlevel%
endlocal & exit /b %exitCode%
