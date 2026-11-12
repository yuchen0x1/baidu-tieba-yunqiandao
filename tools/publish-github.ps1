[CmdletBinding(SupportsShouldProcess = $true, ConfirmImpact = 'High')]
param(
    [ValidatePattern('^[vV]?\d+(\.\d+){1,3}$')]
    [string]$Tag = "",
    [string]$Owner = "yuchen0x1",
    [string]$Repository = "baidu-tieba-yunqiandao",
    [string]$SourceRef = "main",
    [string]$OutputDirectory = "",
    [string]$ReleaseNotesFile = "",
    [string]$SigningPrivateKeyFile = "",
    [ValidateRange(0, 1000)]
    [int]$CleanOldReleases = 0,
    [switch]$DeleteOldTags,
    [switch]$Publish
)

$ErrorActionPreference = "Stop"
$projectRoot = Split-Path -Parent $PSScriptRoot
$githubRepository = "$Owner/$Repository"

function Invoke-Gh {
    param([Parameter(Mandatory = $true)][string[]]$Arguments)

    $output = & gh @Arguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "GitHub CLI failed ($LASTEXITCODE): $($Arguments -join ' ')`n$($output -join "`n")"
    }
    return $output
}

Push-Location $projectRoot
try {
    if ([string]::IsNullOrWhiteSpace($Tag)) {
        $Tag = (& git describe --tags --exact-match $SourceRef 2>$null)
    }
    if ([string]::IsNullOrWhiteSpace($Tag)) {
        throw "SourceRef must point to an existing version tag; specify -Tag vX.Y.Z."
    }
    $Tag = $Tag.Trim()
    if ($Tag -notmatch '^[vV]?\d+(\.\d+){1,3}$') {
        throw "Tag must be a numeric version tag such as vX.Y.Z: $Tag"
    }
    & git rev-parse --verify --quiet "$Tag^{commit}" | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "Version tag not found: $Tag"
    }

    # Package only the committed tree pointed to by the tag.
    $dirty = & git status --porcelain --untracked-files=no
    if (-not [string]::IsNullOrWhiteSpace(($dirty -join "`n"))) {
        throw "Tracked files have uncommitted changes. Commit or revert them before packaging."
    }

    $outputRoot = if ([string]::IsNullOrWhiteSpace($OutputDirectory)) {
        Join-Path ([IO.Path]::GetTempPath()) "tieba-github-release"
    } else {
        $OutputDirectory
    }
    New-Item -ItemType Directory -Path $outputRoot -Force | Out-Null
    $package = Join-Path $outputRoot ("tieba-cloud-sign-windows-{0}.zip" -f $Tag)
    $checksum = "$package.sha256"
    $manifest = Join-Path $outputRoot ("tieba-cloud-sign-update-{0}.json" -f $Tag)
    $signature = Join-Path $outputRoot ("tieba-cloud-sign-windows-{0}.zip.sig" -f $Tag)
    & git archive --format=zip --prefix="$Repository-$Tag/" --output="$package" "$Tag"
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $package)) {
        throw "Failed to create GitHub release package."
    }
    $hash = (Get-FileHash -LiteralPath $package -Algorithm SHA256).Hash.ToLowerInvariant()
    "{0}  {1}" -f $hash, [IO.Path]::GetFileName($package) | Set-Content -LiteralPath $checksum -Encoding ASCII
    $normalizedVersion = $Tag.TrimStart('v', 'V')
    $signatureMetadata = $null
    if (-not [string]::IsNullOrWhiteSpace($SigningPrivateKeyFile)) {
        if (-not (Test-Path -LiteralPath $SigningPrivateKeyFile)) {
            throw "SigningPrivateKeyFile not found: $SigningPrivateKeyFile"
        }
        if (-not (Get-Command openssl -ErrorAction SilentlyContinue)) {
            throw "openssl is required when SigningPrivateKeyFile is supplied."
        }
        $signatureRaw = "$signature.raw"
        & openssl dgst -sha256 -sign $SigningPrivateKeyFile -out $signatureRaw $package
        if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $signatureRaw)) {
            throw "Failed to sign release package."
        }
        $signatureText = [Convert]::ToBase64String([IO.File]::ReadAllBytes($signatureRaw))
        [IO.File]::WriteAllText($signature, $signatureText + [Environment]::NewLine, [Text.UTF8Encoding]::new($false))
        Remove-Item -LiteralPath $signatureRaw -Force
        $signatureMetadata = [ordered]@{ name = [IO.Path]::GetFileName($signature); algorithm = 'RSA-SHA256'; encoding = 'base64' }
    }
    $manifestPayload = [ordered]@{
        version = $normalizedVersion
        tag = $Tag
        channel = 'stable'
        releaseDate = [DateTime]::UtcNow.ToString('o')
        mandatory = $false
        minSupportedVersion = $normalizedVersion
        releaseNotes = "GitHub Release $Tag"
        assets = @([ordered]@{
            name = [IO.Path]::GetFileName($package)
            sha256 = $hash
            size = (Get-Item -LiteralPath $package).Length
        })
        signature = $signatureMetadata
    }
    [IO.File]::WriteAllText($manifest, ($manifestPayload | ConvertTo-Json -Depth 6), [Text.UTF8Encoding]::new($false))

    Write-Host "Package: $package"
    Write-Host "Checksum: $checksum"
    Write-Host "Manifest: $manifest"
    if ($signatureMetadata) { Write-Host "Signature: $signature" }
    if (-not $Publish) {
        if ($CleanOldReleases -gt 0) {
            Write-Warning "Old-release cleanup only runs after publishing; local packaging made no remote changes."
        }
        Write-Host "Local package only. Pass -Publish to create or update a GitHub Release."
        return
    }

    if (-not (Get-Command gh -ErrorAction SilentlyContinue)) {
        throw "GitHub CLI (gh) is required to publish. Install it and authenticate with access to $githubRepository."
    }
    Invoke-Gh -Arguments @('auth', 'status', '--hostname', 'github.com') | Out-Null

    $releaseListJson = Invoke-Gh -Arguments @('release', 'list', '--repo', $githubRepository, '--limit', '1000', '--json', 'tagName,publishedAt')
    $releases = @($releaseListJson -join "`n" | ConvertFrom-Json)
    $existingRelease = @($releases | Where-Object { [string]$_.tagName -eq $Tag }).Count -gt 0

    $description = "Publish GitHub Release $Tag for $githubRepository"
    if ($PSCmdlet.ShouldProcess($githubRepository, $description)) {
        if ($existingRelease) {
            $uploadArgs = @('release', 'upload', $Tag, $package, $checksum, $manifest, '--repo', $githubRepository, '--clobber')
            if ($signatureMetadata) { $uploadArgs = @('release', 'upload', $Tag, $package, $checksum, $manifest, $signature, '--repo', $githubRepository, '--clobber') }
            Invoke-Gh -Arguments $uploadArgs | Out-Null
        } else {
            # 标题取发布说明首个一级标题并从正文剔除，避免 Release 页标题与目录重复；
            # 说明文件未带一级标题时回退为通用标题。
            $notesContent = $null
            if (-not [string]::IsNullOrWhiteSpace($ReleaseNotesFile)) {
                if (-not (Test-Path -LiteralPath $ReleaseNotesFile)) {
                    throw "ReleaseNotesFile not found: $ReleaseNotesFile"
                }
                $notesPath = (Resolve-Path -LiteralPath $ReleaseNotesFile).Path
                $notesContent = [IO.File]::ReadAllText($notesPath, [Text.UTF8Encoding]::new($false))
            }
            $releaseTitle = "V$($Tag.TrimStart('v', 'V'))：百度贴吧云签到更新发布"
            if ($null -ne $notesContent -and $notesContent -match '(?m)^#\s+(.+?)\s*$') {
                $releaseTitle = $Matches[1]
                $notesContent = ($notesContent -replace '(?m)^#\s+.+?\s*\r?\n', '') -replace '^(\r?\n)+', ''
                $trimmedNotes = Join-Path ([IO.Path]::GetTempPath()) ("release-notes-{0}.md" -f $Tag)
                [IO.File]::WriteAllText($trimmedNotes, $notesContent, [Text.UTF8Encoding]::new($false))
                $notesPath = $trimmedNotes
            }
            $createArgs = @('release', 'create', $Tag, $package, $checksum, $manifest, '--repo', $githubRepository, '--verify-tag', '--title', $releaseTitle)
            if ($signatureMetadata) { $createArgs = @('release', 'create', $Tag, $package, $checksum, $manifest, $signature, '--repo', $githubRepository, '--verify-tag', '--title', $releaseTitle) }
            if ($null -ne $notesPath) {
                $createArgs += @('--notes-file', $notesPath)
            } else {
                $createArgs += '--generate-notes'
            }
            Invoke-Gh -Arguments $createArgs | Out-Null
        }
        Write-Host "GitHub Release: https://github.com/$githubRepository/releases/tag/$Tag"

        if ($CleanOldReleases -gt 0) {
            $releaseListJson = Invoke-Gh -Arguments @('release', 'list', '--repo', $githubRepository, '--limit', '1000', '--json', 'tagName,publishedAt')
            $releases = @($releaseListJson -join "`n" | ConvertFrom-Json | Sort-Object -Property @{ Expression = { [DateTime]$_.publishedAt }; Descending = $true })
            $toDelete = @($releases | Select-Object -Skip $CleanOldReleases | Where-Object { [string]$_.tagName -ne $Tag })
            foreach ($old in $toDelete) {
                $oldTag = [string]$old.tagName
                try {
                    Invoke-Gh -Arguments @('release', 'delete', $oldTag, '--repo', $githubRepository, '--yes') | Out-Null
                    Write-Host "Deleted old GitHub Release: $oldTag"
                } catch {
                    Write-Warning "Could not delete Release $oldTag; preserving its tag: $($_.Exception.Message)"
                    continue
                }
                if ($DeleteOldTags) {
                    try {
                        Invoke-Gh -Arguments @('api', '--method', 'DELETE', "repos/$githubRepository/git/refs/tags/$oldTag") | Out-Null
                        Write-Host "Deleted remote tag: $oldTag"
                    } catch {
                        Write-Warning "Release $oldTag was deleted, but its tag could not be deleted: $($_.Exception.Message)"
                    }
                }
            }
        }
    }
} finally {
    Pop-Location
}
