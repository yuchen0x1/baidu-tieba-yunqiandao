param(
    [string]$BaseUrl = "http://127.0.0.1:8080",
    [ValidateSet("live", "ready")]
    [string]$Mode = "ready",
    [ValidateRange(1, 60)]
    [int]$TimeoutSec = 10
)

$ErrorActionPreference = "Stop"
$uri = $BaseUrl.TrimEnd('/') + "/healthz.php?mode=" + $Mode
try {
    $result = Invoke-RestMethod -Uri $uri -TimeoutSec $TimeoutSec
    $result | ConvertTo-Json -Depth 5
    if ($result.ok -eq $true) { exit 0 }
    exit 1
} catch {
    Write-Error "健康检查失败：$($_.Exception.Message)"
    exit 1
}
