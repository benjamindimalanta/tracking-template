# Build tracking-template zip for License Hub /releases/
# Usage: .\tools\build-release-zip.ps1 [version]
# Example: .\tools\build-release-zip.ps1 1.6.3
#
# WordPress expects: tracking-template/tracking-template.php at zip root (one folder only).

param(
    [string]$Version = (Select-String -Path "tracking-template.php" -Pattern "define\(\s*'ADCT_VERSION',\s*'([^']+)'" | ForEach-Object { $_.Matches.Groups[1].Value })
)

$ErrorActionPreference = "Stop"
$Root = Split-Path -Parent $PSScriptRoot
$PluginDir = $Root
$OutDir = Join-Path (Split-Path -Parent $Root) "adct-license-hub\public\releases"
$Staging = Join-Path $env:TEMP "adct-zip-staging"
$StagingPlugin = Join-Path $Staging "tracking-template"
$VersionedZip = Join-Path $OutDir "tracking-template-$Version.zip"
$CanonicalZip = Join-Path $OutDir "tracking-template.zip"

if (-not (Test-Path $PluginDir)) {
    throw "Plugin folder not found: $PluginDir"
}

Write-Host "Building tracking-template-$Version.zip from $PluginDir"

if (Test-Path $Staging) { Remove-Item -Recurse -Force $Staging }
New-Item -ItemType Directory -Path $StagingPlugin -Force | Out-Null

robocopy $PluginDir $StagingPlugin /E /XD .git .next node_modules /NFL /NDL /NJH /NJS /nc /ns /np | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy failed with exit code $LASTEXITCODE" }

New-Item -ItemType Directory -Path $OutDir -Force | Out-Null
foreach ($zip in @($VersionedZip, $CanonicalZip)) {
    if (Test-Path $zip) { Remove-Item -Force $zip }
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
# includeBaseDirectory=true => zip root is "tracking-template/" (required by WordPress)
[System.IO.Compression.ZipFile]::CreateFromDirectory($StagingPlugin, $VersionedZip, [System.IO.Compression.CompressionLevel]::Optimal, $true)
Copy-Item $VersionedZip $CanonicalZip -Force
Remove-Item -Recurse -Force $Staging

# Verify structure
$z = [System.IO.Compression.ZipFile]::OpenRead($CanonicalZip)
$main = $z.Entries | Where-Object { $_.FullName -replace '\\', '/' -eq 'tracking-template/tracking-template.php' }
$z.Dispose()
if (-not $main) {
    throw "Zip verification failed: tracking-template/tracking-template.php not found at root"
}

Write-Host "Created: $VersionedZip"
Write-Host "Created: $CanonicalZip"
Write-Host "Next: bump lib/update-manifest.ts, deploy adct-license-hub to Vercel"
