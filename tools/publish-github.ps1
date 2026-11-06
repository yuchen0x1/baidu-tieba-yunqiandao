[CmdletBinding(SupportsShouldProcess = $true, ConfirmImpact = 'High')]
param(
    [ValidatePattern('^[vV]?\d+(\.\d+){1,3}$')]
    [string]$Tag = "",
    [string]$Owner = "yuchen0x1",
    [string]$Repository = "baidu-tieba-yunqiandao",
    [string]$SourceRef = "main",
    [string]$OutputDirectory = "",
    [string]$ReleaseNotesFile = "",
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
    & git archive --format=zip --prefix="$Repository-$Tag/" --output="$package" "$Tag"
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path -LiteralPath $package)) {
        throw "Failed to create GitHub release package."
    }
    $hash = (Get-FileHash -LiteralPath $package -Algorithm SHA256).Hash.ToLowerInvariant()
    "{0}  {1}" -f $hash, [IO.Path]::GetFileName($package) | Set-Content -LiteralPath $checksum -Encoding ASCII

    Write-Host "Package: $package"
    Write-Host "Checksum: $checksum"
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
            Invoke-Gh -Arguments @('release', 'upload', $Tag, $package, $checksum, '--repo', $githubRepository, '--clobber') | Out-Null
        } else {
            $createArgs = @('release', 'create', $Tag, $package, $checksum, '--repo', $githubRepository, '--verify-tag', '--title', "百度贴吧云签到 $Tag Windows 部署包")
            if (-not [string]::IsNullOrWhiteSpace($ReleaseNotesFile)) {
                if (-not (Test-Path -LiteralPath $ReleaseNotesFile)) {
                    throw "ReleaseNotesFile not found: $ReleaseNotesFile"
                }
                $notesPath = (Resolve-Path -LiteralPath $ReleaseNotesFile).Path
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
