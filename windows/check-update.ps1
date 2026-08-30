param(
    [string]$Repository = "yuchen0x1/baidu-tieba-yunqiandao",
    [string]$CurrentVersion = "5.09",
    [string]$ApiUrl = ""
)

# 在线更新双通道：默认使用 Gitee 国内主通道。
# 显式传入 ApiUrl 或 UPDATE_PRIMARY_CHANNEL=github 时才使用其他通道。
$ErrorActionPreference = "Stop"

# 启用 TLS 1.2，确保 Windows PowerShell 5.1 可与 Gitee / ip.sb / GitHub 完成握手
try { [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12 } catch { }

# a) 百度智能云 IP 属地接口：响应含"中国/China" → 国内
function Test-ChinaByBaiduGeo {
    try {
        $geo = Invoke-RestMethod -Uri "https://qifu-api.baidubce.com/ip/local/geo/v1/district" -TimeoutSec 3
        $text = if ($geo -is [string]) { [string]$geo } else { ($geo | ConvertTo-Json -Compress -Depth 8) }
        $china = [string][char]0x4E2D + [string][char]0x56FD   # 中国
        if ($text.Contains($china) -or $text -match "China" -or $text -match '\\u4e2d\\u56fd') { return $true }
    } catch { }
    return $false
}

# b) ip.sb GeoIP：响应含"CN" → 国内
function Test-CNByIpSb {
    try {
        $geo = Invoke-RestMethod -Uri "https://api.ip.sb/geoip" -TimeoutSec 3
        $text = if ($geo -is [string]) { [string]$geo } else { ($geo | ConvertTo-Json -Compress -Depth 8) }
        if ($text -match "CN") { return $true }
    } catch { }
    return $false
}

# 旧版通道竞速辅助函数保留用于兼容；默认检查流程不会调用它们。
function Find-RacingChannel {
    param([string]$Repository)
    try { Add-Type -AssemblyName System.Net.Http } catch { return $null }
    $names = @("gitee", "github")
    $urls = @(
        "https://gitee.com/api/v5/repos/$Repository/releases/latest",
        "https://api.github.com/repos/$Repository/releases/latest"
    )
    $clients = @()
    $tasks = @()
    try {
        foreach ($url in $urls) {
            $client = New-Object System.Net.Http.HttpClient
            $client.Timeout = [TimeSpan]::FromSeconds(8)
            [void]$client.DefaultRequestHeaders.UserAgent.ParseAdd("Tieba-Cloud-Sign-Updater")
            [void]$client.DefaultRequestHeaders.Accept.ParseAdd("application/json")
            $clients += $client
            $tasks += $client.GetStringAsync($url)
        }
        $pending = @(0, 1)
        while ($pending.Count -gt 0) {
            $subset = @($pending | ForEach-Object { $tasks[$_] })
            $index = [System.Threading.Tasks.Task]::WaitAny([System.Threading.Tasks.Task[]]$subset, 500)
            if ($index -lt 0) { continue }
            $task = $subset[$index]
            $which = $pending[$index]
            $pending = @($pending | Where-Object { $_ -ne $which })
            if ($task.Status -eq [System.Threading.Tasks.TaskStatus]::RanToCompletion) {
                $content = $task.Result
                if (-not [string]::IsNullOrWhiteSpace($content) -and $content.Contains('"tag_name"')) {
                    return $names[$which]
                }
            }
        }
        return $null
    } catch {
        return $null
    } finally {
        foreach ($client in $clients) { try { $client.Dispose() } catch { } }
    }
}

# 通道判定：默认 Gitee；GitHub 必须显式配置。
function Get-UpdateChannel {
    param([string]$Repository)
    $configured = ([string]$env:UPDATE_PRIMARY_CHANNEL).Trim().ToLowerInvariant()
    if ($configured -eq "github") { return "github" }
    return "gitee"
}

if ($ApiUrl -ne "") {
    # 显式指定 ApiUrl：最高优先，不做通道判定
    $apiUrl = $ApiUrl
} else {
    $channel = Get-UpdateChannel -Repository $Repository
    if ($channel -eq "gitee") {
        Write-Verbose "Update channel: Gitee"
        $apiUrl = "https://gitee.com/api/v5/repos/$Repository/releases/latest"
    } else {
        Write-Verbose "Update channel: GitHub"
        $apiUrl = "https://api.github.com/repos/$Repository/releases/latest"
    }
}

function Get-Release {
    param([string]$Uri)
    $headers = @{ "User-Agent" = "Tieba-Cloud-Sign-Updater"; "Accept" = "application/json" }
    $token = ""
    if ($Uri -match '^https://api\.github\.com/') {
        $token = [string]$env:UPDATE_GITHUB_TOKEN
        $headers["Accept"] = "application/vnd.github+json"
    } elseif ($Uri -match '^https://gitee\.com/api/') {
        $token = [string]$env:UPDATE_GITEE_TOKEN
    }
    if (-not [string]::IsNullOrWhiteSpace($token)) {
        $headers["Authorization"] = "Bearer " + $token
    }
    try {
        return Invoke-RestMethod -Uri $Uri -Headers $headers -TimeoutSec 15
    } catch {
        $statusCode = 0
        if ($_.Exception.Response -and $_.Exception.Response.StatusCode) {
            $statusCode = [int]$_.Exception.Response.StatusCode
        }
        if ($statusCode -in @(401, 403, 404) -and $headers.ContainsKey("Authorization")) {
            $headers.Remove("Authorization")
            return Invoke-RestMethod -Uri $Uri -Headers $headers -TimeoutSec 15
        }
        throw
    }
}

try {
    $release = Get-Release -Uri $apiUrl
} catch {
    if ($ApiUrl -eq "" -and $apiUrl -match '^https://gitee\.com/api/' -and [System.Convert]::ToBoolean(($env:UPDATE_ALLOW_FOREIGN_FALLBACK -as [string]) -eq 'true')) {
        Write-Verbose "Gitee update request failed, falling back to GitHub"
        $apiUrl = "https://api.github.com/repos/$Repository/releases/latest"
        $release = Get-Release -Uri $apiUrl
    } else {
        throw
    }
}
$latest = [string]$release.tag_name -replace '^[vV]', ''
if ([string]::IsNullOrWhiteSpace($latest)) {
    throw "更新服务器响应缺少 tag_name"
}

if ([version]$latest -gt [version]$CurrentVersion) {
    $asset = @($release.assets | Where-Object { $_.name -match '\.zip$' -and $_.name -match 'windows' } | Select-Object -First 1)
    $checksum = @($release.assets | Where-Object { $_.name -match '\.zip\.sha256$' } | Select-Object -First 1)
    $releaseUrl = [string]$release.html_url
    if ([string]::IsNullOrWhiteSpace($releaseUrl) -and $apiUrl -match '^https://gitee\.com/api/') {
        $releaseUrl = "https://gitee.com/$Repository/releases/tag/$($release.tag_name)"
    }
    Write-Output "Update available: V$latest (current V$CurrentVersion)"
    Write-Output "Release: $releaseUrl"
    if ($asset.Count -gt 0) {
        Write-Output "Windows package: $($asset[0].browser_download_url)"
    }
    if ($checksum.Count -gt 0) {
        Write-Output "SHA-256: $($checksum[0].browser_download_url)"
    }
    exit 10
}

Write-Output "Already up to date: V$CurrentVersion"
exit 0
