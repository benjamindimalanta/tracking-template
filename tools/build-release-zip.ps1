# Build tracking-template zip for License Hub /releases/
# Usage: .\tools\build-release-zip.ps1 [version]
# Example: .\tools\build-release-zip.ps1 1.6.2

param(
    [string]$Version = (Select-String -Path "tracking-template.php" -Pattern "define\(\s*'ADCT_VERSION',\s*'([^']+)'" | ForEach-Object { $_.Matches.Groups[1].Value })
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$PluginDir = $Root
$OutDir = Join-Path (Split-Path -Parent $Root) "adct-license-hub\public\releases"
$Staging = Join-Path $env:TEMP "adct-zip-staging"
$StagingPlugin = Join-Path $Staging "tracking-template"
$ZipName = "tracking-template-$Version.zip"
$ZipPath = Join-Path $OutDir $ZipName

if (-not (Test-Path $PluginDir)) {
    throw "Plugin folder not found: $PluginDir"
}

Write-Host "Building $ZipName from $PluginDir"

if (Test-Path $Staging) { Remove-Item -Recurse -Force $Staging }
New-Item -ItemType Directory -Path $StagingPlugin -Force | Out-Null

robocopy $PluginDir $StagingPlugin /E /XD .git .next node_modules /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy failed with exit code $LASTEXITCODE" }

New-Item -ItemType Directory -Path $OutDir -Force | Out-Null
if (Test-Path $ZipPath) { Remove-Item -Force $ZipPath }

Compress-Archive -Path $StagingPlugin -DestinationPath $ZipPath -Force
Remove-Item -Recurse -Force $Staging

Write-Host "Created: $ZipPath"
Write-Host "Next: bump lib/update-manifest.ts version, deploy adct-license-hub to Vercel"
