param(
    [string]$Repository = "yuchen0x1/baidu-tieba-yunqiandao",
    [string]$CurrentVersion = "5.09",
    [string]$ApiUrl = ""
)

$ErrorActionPreference = "Stop"
$apiUrl = if ($ApiUrl -ne "") { $ApiUrl } else { "https://api.github.com/repos/$Repository/releases/latest" }
$headers = @{ "User-Agent" = "Tieba-Cloud-Sign-Updater"; "Accept" = "application/vnd.github+json" }
if ($env:UPDATE_GITHUB_TOKEN -ne "") {
    $headers["Authorization"] = "Bearer " + $env:UPDATE_GITHUB_TOKEN
}
$release = Invoke-RestMethod -Uri $apiUrl -Headers $headers -TimeoutSec 15
$latest = [string]$release.tag_name -replace '^[vV]', ''

if ([version]$latest -gt [version]$CurrentVersion) {
    $asset = @($release.assets | Where-Object { $_.name -match '\.zip$' -and $_.name -match 'windows' } | Select-Object -First 1)
    Write-Output "Update available: V$latest (current V$CurrentVersion)"
    Write-Output "Release: $($release.html_url)"
    if ($asset.Count -gt 0) {
        Write-Output "Windows package: $($asset[0].browser_download_url)"
    }
    exit 10
}

Write-Output "Already up to date: V$CurrentVersion"
exit 0
