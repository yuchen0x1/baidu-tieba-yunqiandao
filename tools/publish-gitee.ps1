[CmdletBinding(SupportsShouldProcess = $true, ConfirmImpact = 'High')]
param(
    [ValidatePattern('^[vV]?\d+(\.\d+){1,3}$')]
    [string]$Tag = "",
    [string]$Owner = "yuchen0x1",
    [string]$Repository = "baidu-tieba-yunqiandao",
    [string]$SourceRef = "main",
    [string]$OutputDirectory = "",
    [string]$ReleaseNotesFile = "",
    [int]$CleanOldReleases = 0,
    [switch]$DeleteOldTags,
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
    # 打包内容来自 tag 指向的已提交树，未跟踪的本地文件不影响发布，只检查已跟踪文件的改动
    $dirty = & git status --porcelain --untracked-files=no
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
    $releaseBodyText = "国内 Gitee 正式发布 $Tag"
    if (-not [string]::IsNullOrWhiteSpace($ReleaseNotesFile)) {
        if (-not (Test-Path -LiteralPath $ReleaseNotesFile)) {
            throw "ReleaseNotesFile 不存在：$ReleaseNotesFile"
        }
        $releaseBodyText = [IO.File]::ReadAllText($ReleaseNotesFile, [Text.Encoding]::UTF8)
    }
    $releaseBody = @{ tag_name = $Tag; name = "百度贴吧云签到 $Tag Windows 部署包"; body = $releaseBodyText; target_commitish = $SourceRef } | ConvertTo-Json

    Write-Host "已生成：$package"
    Write-Host "校验文件：$checksum"
    if (-not $Publish) {
        if ($CleanOldReleases -gt 0) {
            Write-Warning "清理旧版本仅在 -Publish（真正发布）成功后执行；本地打包模式未做任何删除。"
        }
        Write-Host "仅生成本地发布包。传入 -Publish 后才会创建 Gitee Release 并上传附件。"
        return
    }
    # -File 运行时 $PSCmdlet 可能为 null，改用通用参数 $Confirm 手动确认（-Confirm:$false 跳过询问）
    if ($Confirm) {
        $answer = Read-Host "确定创建 Gitee Release $Tag 并上传 Windows ZIP 和 SHA-256 吗？[y/N]"
        if ($answer -notmatch '^[Yy]') {
            return
        }
    }

    # PS 5.1 字符串请求体按代码页编码，必须显式以 UTF-8 字节发送，否则中文说明乱码
    $releaseBodyBytes = [Text.Encoding]::UTF8.GetBytes($releaseBody)
    $release = Invoke-RestMethod -Uri "$apiRoot/releases" -Method Post -Headers $headers -Body $releaseBodyBytes -ContentType "application/json; charset=utf-8"
    $releaseId = [string]$release.id
    if ([string]::IsNullOrWhiteSpace($releaseId)) {
        throw "Gitee Release 响应缺少 id。"
    }
    # Windows PowerShell 5.1 默认未加载 System.Net.Http 程序集
    if (-not ('System.Net.Http.HttpClient' -as [type])) {
        Add-Type -AssemblyName System.Net.Http
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

    # 可选：清理旧版本（删除旧 Release；配合 -DeleteOldTags 连同远端标签一并删除）。
    # 仅在发布成功后执行；刚发布的 $Tag 永远保留，保留数量按 Release 创建时间倒序由 -CleanOldReleases 指定。
    if ($CleanOldReleases -gt 0) {
        # 令牌只走 Authorization 头，不进 URL（查询串会落入反代/访问日志）
        $authHeaders = @{ Authorization = "Bearer $token"; 'User-Agent' = 'Tieba-Cloud-Sign-Gitee-Publisher' }
        $releases = Invoke-RestMethod -Uri "$apiRoot/releases?per_page=100" -Headers $authHeaders
        $sorted = @($releases | Sort-Object -Property created_at -Descending)
        $toDelete = @($sorted | Select-Object -Skip $CleanOldReleases | Where-Object { [string]$_.tag_name -ne $Tag })
        if ($toDelete.Count -eq 0) {
            Write-Host "旧版本清理：没有需要删除的历史 Release（共 $($sorted.Count) 个，保留最近 $CleanOldReleases 个）。"
        }
        foreach ($old in $toDelete) {
            $oldId = [string]$old.id
            $oldTag = [string]$old.tag_name
            try {
                Invoke-RestMethod -Method Delete -Uri "$apiRoot/releases/$oldId" -Headers $authHeaders | Out-Null
                Write-Host "已删除旧版本：$oldTag（Release $oldId）"
            } catch {
                Write-Warning "删除 Release $oldTag 失败，跳过其标签清理：$($_.Exception.Message)"
                continue
            }
            if ($DeleteOldTags -and $oldTag -ne '') {
                try {
                    Invoke-RestMethod -Method Delete -Uri "$apiRoot/tags/$([Uri]::EscapeDataString($oldTag))" -Headers $authHeaders | Out-Null
                    Write-Host "已删除远端标签：$oldTag"
                } catch {
                    Write-Warning "删除标签 $oldTag 失败（Release 已删除）：$($_.Exception.Message)"
                }
            }
        }
    }
} finally {
    Pop-Location
}
