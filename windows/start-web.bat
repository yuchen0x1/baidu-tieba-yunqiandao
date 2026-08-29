@echo off
setlocal
cd /d "%~dp0.."
where php >nul 2>nul
if errorlevel 1 (
    echo 未找到 PHP，请安装 PHP 8.2+ 并将 php.exe 加入 PATH。
    pause
    exit /b 1
)
echo 百度贴吧云签到正在启动：http://127.0.0.1:8080
php -S 127.0.0.1:8080 -t "%CD%"
endlocal
