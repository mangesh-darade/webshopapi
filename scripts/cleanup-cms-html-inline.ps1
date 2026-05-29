# Apply inline-style -> class conversions to existing webshopapi/*.html (no empty-string Replace).
# Prefer: powershell -File scripts\build-cms-herbinn-pages.ps1  (rebuild from herbinnwellness sources)
$ErrorActionPreference = 'Stop'
. "$PSScriptRoot\build-cms-herbinn-pages.ps1"

$root = 'C:\wamp64\www\webshopapi'
$cmsPages = @(
    'home.html', 'about.html', 'services.html', 'contact.html', 'formats.html', 'faq.html',
    'privacy-policy.html', 'terms-and-conditions.html', 'insights.html', 'products.html',
    'private-label.html', 'signature.html', 'softgel-supplement-manufacturer.html',
    'private-label-supplements-manufacturer.html'
)

foreach ($name in $cmsPages) {
    $path = Join-Path $root $name
    if (-not (Test-Path $path)) {
        Write-Warning "Skip missing: $name"
        continue
    }
    $h = [IO.File]::ReadAllText($path)
    $orig = $h
    $h = Convert-InlineAttributes $h
    $h = Merge-DuplicateClasses $h
    if ($name -eq 'products.html') { $h = Fix-ProductsPaginationJs $h }
    if ($h -ne $orig) {
        [IO.File]::WriteAllText($path, $h, [Text.UTF8Encoding]::new($false))
        Write-Host "Updated $name"
    } else {
        Write-Host "No change: $name"
    }
}
Write-Host 'Cleanup done.'
