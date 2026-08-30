[CmdletBinding(SupportsShouldProcess = $true, ConfirmImpact = 'High')]
param(
    [ValidatePattern('^[vV]?\d+(\.\d+){1,3}$')]
    [string]$Tag = "",
    [string]$Owner = "yuchen0x1",
    [string]$Repository = "baidu-tieba-yunqiandao",
    [string]$SourceRef = "main",
    [string]$OutputDirectory = "",
    [switch]$Publish
)

$ErrorActionPreference = "Stop"
$projectRoot = Split-Path -Parent $PSScriptRoot
$token = [string]$env:GITEE_ACCESS_TOKEN
if ([string]::IsNullOrWhiteSpace($token)) {
    # Backward-compatible fallback for older deployment shells.
    $token = [string]$env:GITEE_TOKEN
}
if ([string]::IsNullOrWhiteSpace($token)) {
    throw "未找到 GITEE_ACCESS_TOKEN。请使用 Gitee 个人令牌后重试。"
}

Push-Location $projectRoot
try {
    if ([string]::IsNullOrWhiteSpace($Tag)) {
        $Tag = (& git describe --tags --exact-match $SourceRef 2>$null)
    }
    if ([string]::IsNullOrWhiteSpace($Tag)) {
        throw "SourceRef 必须指向一个已存在的版本 Tag；请使用 -Tag v5.10。"
    }
    $Tag = $Tag.Trim()
    & git rev-parse --verify --quiet "$Tag^{commit}" | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "找不到版本 Tag：$Tag"
    }
    $dirty = & git status --porcelain --untracked-files=all
    if (-not [string]::IsNullOrWhiteSpace(($dirty -join "`n"))) {
        throw "工作区存在未提交改动。请先提交版本，发布脚本不会打包未提交内容。"
    }

    $outputRoot = if ([string]::IsNullOrWhiteSpace($OutputDirectory)) { Join-Path $env:TEMP "tieba-gitee-release" } else { $OutputDirectory }
    New-Item -ItemType Directory -Path $outputRoot -Force | Out-Null
    $package = Join-Path $outputRoot ("tieba-cloud-sign-windows-{0}.zip" -f $Tag)
    $checksum = "$package.sha256"
    & git archive --format=zip --prefix="$Repository-$Tag/" --output="$package" "$Tag"
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $package)) {
        throw "生成 Gitee 发布包失败。"
    }
    $hash = (Get-FileHash -LiteralPath $package -Algorithm SHA256).Hash.ToLowerInvariant()
    "{0}  {1}" -f $hash, [IO.Path]::GetFileName($package) | Set-Content -LiteralPath $checksum -Encoding ASCII

    $apiRoot = "https://gitee.com/api/v5/repos/$Owner/$Repository"
    $headers = @{ Authorization = "Bearer $token"; Accept = "application/json"; 'User-Agent' = 'Tieba-Cloud-Sign-Gitee-Publisher' }
    $releaseBody = @{ tag_name = $Tag; name = "百度贴吧云签到 $Tag Windows 部署包"; body = "国内 Gitee 正式发布 $Tag"; target_commitish = $SourceRef } | ConvertTo-Json

    Write-Host "已生成：$package"
    Write-Host "校验文件：$checksum"
    if (-not $Publish) {
        Write-Host "仅生成本地发布包。传入 -Publish 后才会创建 Gitee Release 并上传附件。"
        return
    }
    if (-not $PSCmdlet.ShouldProcess("Gitee $Owner/$Repository Release $Tag", "创建 Release 并上传 Windows ZIP 和 SHA-256")) {
        return
    }

    $release = Invoke-RestMethod -Uri "$apiRoot/releases" -Method Post -Headers $headers -Body $releaseBody -ContentType "application/json"
    $releaseId = [string]$release.id
    if ([string]::IsNullOrWhiteSpace($releaseId)) {
        throw "Gitee Release 响应缺少 id。"
    }
    $client = [System.Net.Http.HttpClient]::new()
    try {
        $client.DefaultRequestHeaders.Authorization = [System.Net.Http.Headers.AuthenticationHeaderValue]::new("Bearer", $token)
        foreach ($file in @($package, $checksum)) {
            $form = [System.Net.Http.MultipartFormDataContent]::new()
            $stream = [System.IO.File]::OpenRead($file)
            $content = [System.Net.Http.StreamContent]::new($stream)
            $form.Add($content, "file", [IO.Path]::GetFileName($file))
            try {
                $response = $client.PostAsync("$apiRoot/releases/$releaseId/attach_files", $form).GetAwaiter().GetResult()
                if (-not $response.IsSuccessStatusCode) {
                    throw "上传 $file 失败：HTTP $([int]$response.StatusCode)"
                }
            } finally {
                $form.Dispose()
                $stream.Dispose()
            }
        }
    } finally {
        $client.Dispose()
    }
    Write-Host "Gitee Release 发布完成：https://gitee.com/$Owner/$Repository/releases/tag/$Tag"
} finally {
    Pop-Location
}
