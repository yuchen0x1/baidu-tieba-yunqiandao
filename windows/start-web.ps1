param(
    [ValidateRange(1, 65535)]
    [int]$Port = 8080,
    [switch]$NoBrowser
)

$ErrorActionPreference = "Stop"
$projectRoot = Split-Path -Parent $PSScriptRoot
$php = Get-Command php -ErrorAction SilentlyContinue
if (-not $php) {
    throw "未找到 PHP。请安装 PHP 8.2 或更高版本，并将 php.exe 加入 PATH。"
}

$versionId = & $php.Source -r "echo PHP_VERSION_ID;"
if ([int]$versionId -lt 80200) {
    throw "当前 PHP 版本过低，需要 PHP 8.2 或更高版本。"
}

$requiredExtensions = @("curl", "json", "mbstring", "mysqli", "openssl", "session", "zip")
$loadedExtensions = @(& $php.Source -r "echo implode(PHP_EOL, get_loaded_extensions());")
$missingExtensions = @($requiredExtensions | Where-Object { $_ -notin $loadedExtensions })
if ($missingExtensions.Count -gt 0) {
    throw "缺少 PHP 扩展：$($missingExtensions -join ', ')"
}

$existing = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
if ($existing) {
    throw "端口 $Port 已被进程 $($existing.OwningProcess) 占用。请使用 -Port 指定其他端口。"
}

$baseUrl = "http://127.0.0.1:$Port"
$targetUrl = if (Test-Path -LiteralPath (Join-Path $projectRoot "setup\install.lock")) {
    "$baseUrl/"
} else {
    "$baseUrl/setup/install.php"
}

Write-Host "百度贴吧云签到正在启动：$targetUrl"
Write-Host "按 Ctrl+C 可停止本地 Web 服务。"
if (-not $NoBrowser) {
    Start-Process $targetUrl
}

Push-Location $projectRoot
try {
    & $php.Source -S "127.0.0.1:$Port" -t $projectRoot
    exit $LASTEXITCODE
} finally {
    Pop-Location
}
