# Generate a Tracking Template license key + JSON snippet for licenses.json
# Usage: .\new-license-key.ps1
#        .\new-license-key.ps1 -Site "autodealsuae.com" -Expires "2027-12-31" -Plan "agency"

param(
    [string]$Site = "example.com",
    [string]$Expires = "2027-12-31",
    [string]$Plan = "standard",
    [string]$Customer = ""
)

function New-AdctSegment {
    $chars = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789"
    $segment = ""
    for ($i = 0; $i -lt 4; $i++) {
        $segment += $chars[(Get-Random -Maximum $chars.Length)]
    }
    return $segment
}

$key = "ADCT-$(New-AdctSegment)-$(New-AdctSegment)-$(New-AdctSegment)"

$entry = [ordered]@{
    active  = $true
    expires = $Expires
    plan    = $Plan
    sites   = @($Site)
}

if ($Customer) {
    $entry.note = $Customer
}

$jsonObject = [ordered]@{
    version      = 1
    licenses     = [ordered]@{ $key = $entry }
    revoked_keys = @()
    revoked_sites = @()
}

Write-Host ""
Write-Host "License key:" -ForegroundColor Green
Write-Host $key
Write-Host ""
Write-Host "Add this entry under ""licenses"" in licenses.json (merge with existing keys):" -ForegroundColor Cyan
Write-Host ""

$singleEntry = @{ $key = $entry } | ConvertTo-Json -Depth 5
Write-Host $singleEntry
Write-Host ""
Write-Host "Customer site: $Site"
Write-Host "Expires:       $Expires"
Write-Host ""
Write-Host "Next steps:"
Write-Host "  1. Paste the key block into github.com/benjamindimalanta/adct-licenses licenses.json"
Write-Host "  2. Push to GitHub"
Write-Host "  3. Customer enters key under Tracking Template -> License"
Write-Host ""
