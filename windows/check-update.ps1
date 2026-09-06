param(
    [string]$Repository = "yuchen0x1/baidu-tieba-yunqiandao",
    [string]$CurrentVersion = "7.2.1"
)

# 在线更新仅使用 Gitee 国内 Release 通道。
$ErrorActionPreference = "Stop"

try { [Net.ServicePointManager]::SecurityProtocol = [Net.ServicePointManager]::SecurityProtocol -bor [Net.SecurityProtocolType]::Tls12 } catch { }

function Get-GiteeRelease {
    param([string]$Uri)

    $headers = @{ "User-Agent" = "Tieba-Cloud-Sign-Updater"; "Accept" = "application/json" }
    $token = [string]$env:UPDATE_GITEE_TOKEN
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

$apiUrl = "https://gitee.com/api/v5/repos/$Repository/releases/latest"
$release = Get-GiteeRelease -Uri $apiUrl
$latest = [string]$release.tag_name -replace '^[vV]', ''
if ([string]::IsNullOrWhiteSpace($latest)) {
    throw "更新服务器响应缺少 tag_name"
}

if ([version]$latest -gt [version]$CurrentVersion) {
    $asset = @($release.assets | Where-Object { $_.name -match '\.zip$' -and $_.name -match 'windows' } | Select-Object -First 1)
    $checksum = @($release.assets | Where-Object { $_.name -match '\.zip\.sha256$' } | Select-Object -First 1)
    $releaseUrl = [string]$release.html_url
    if ([string]::IsNullOrWhiteSpace($releaseUrl)) {
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
