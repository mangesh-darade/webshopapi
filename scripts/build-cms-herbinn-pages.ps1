# Build CMS html_block fragments — shared CSS at top (cms-herbinn-global.css), no inline <style> blocks.
$ErrorActionPreference = 'Stop'
$root = 'C:\wamp64\www\webshopapi'
$srcDir = 'C:\wamp64\www\herbinnwellness'
$imgBase = '{{api_base_url}}assets/mdata/{{customer_assets_folder}}/uploads/webshop/uploads/images/'

$cmsHeader = @'
<link rel="icon" type="image/png" href="assets/images/favicon.png">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <!-- AOS Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- Herbinn CMS global styles (no inline style block - edit cms-herbinn-global.css) -->
    <link rel="stylesheet" href="assets/webshop/herbinnwellness/css/cms-herbinn-global.css">
    <!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','GTM-PXCJKJJN');</script>
<!-- End Google Tag Manager -->

<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-PXCJKJJN"
height="0" width="0" class="gtm-noscript-iframe"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->

'@

$scriptFooter = @'

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ once: true, offset: 80 });
'@

function Fix-ImagePaths([string]$html) {
    $html = $html -replace 'assets/webshop/herbinnwellness/images/', $imgBase
    $html = $html -replace 'assets/images/', $imgBase
    return [System.Uri]::UnescapeDataString($html)
}

function Remove-InlineStyleBlocks([string]$html) {
    return [regex]::Replace($html, '(?is)<style\b[^>]*>[\s\S]*?</style>\s*', '')
}

function Merge-DuplicateClasses([string]$html) {
  $pattern = 'class="([^"]*)"\s+class="([^"]*)"'
  while ($html -match $pattern) {
    $html = [regex]::Replace($html, $pattern, {
      param($m)
      $a = $m.Groups[1].Value.Trim()
      $b = $m.Groups[2].Value.Trim()
      "class=`"$a $b`""
    }, 1)
  }
  return $html
}

function Remove-InlineStyle([string]$html, [string]$styleLiteral) {
  if ([string]::IsNullOrEmpty($styleLiteral)) { return $html }
  $escaped = [regex]::Escape($styleLiteral)
  return [regex]::Replace($html, "\s$escaped", '')
}

function Fix-ProductsPaginationJs([string]$html) {
  $html = $html.Replace('paginationContainer.style.display = ''flex'';', 'paginationContainer.classList.add(''is-visible'');')
  $html = $html.Replace('paginationContainer.style.display = ''none'';', 'paginationContainer.classList.remove(''is-visible'');')
  $oldBlock = @'
            html += `<button class="btn btn-outline" ${currentPage === 1 ? 'disabled style="padding: 0.5rem 1rem; opacity:0.5; cursor:not-allowed;"' : `style="padding: 0.5rem 1rem;" onclick="goToPage(${currentPage - 1})"`}>&laquo; Prev</button>`;
            
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    html += `<button class="btn ${i === currentPage ? 'btn-primary' : 'btn-outline'}" style="padding: 0.5rem 1rem;" onclick="goToPage(${i})">${i}</button>`;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    html += `<span style="padding: 0.5rem; color: #6c757d;">...</span>`;
                }
            }
            
            html += `<button class="btn btn-outline" ${currentPage === totalPages ? 'disabled style="padding: 0.5rem 1rem; opacity:0.5; cursor:not-allowed;"' : `style="padding: 0.5rem 1rem;" onclick="goToPage(${currentPage + 1})"`}>Next &raquo;</button>`;
'@
  $newBlock = @'
            html += `<button class="btn btn-outline btn-pagination" ${currentPage === 1 ? 'disabled' : `onclick="goToPage(${currentPage - 1})"`}>&laquo; Prev</button>`;
            
            for (let i = 1; i <= totalPages; i++) {
                if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                    html += `<button class="btn btn-pagination ${i === currentPage ? 'btn-primary' : 'btn-outline'}" onclick="goToPage(${i})">${i}</button>`;
                } else if (i === currentPage - 2 || i === currentPage + 2) {
                    html += `<span class="pagination-ellipsis">...</span>`;
                }
            }
            
            html += `<button class="btn btn-outline btn-pagination" ${currentPage === totalPages ? 'disabled' : `onclick="goToPage(${currentPage + 1})"`}>Next &raquo;</button>`;
'@
  return $html.Replace($oldBlock, $newBlock)
}

function Convert-InlineAttributes([string]$html) {
  $pairs = @(
    @(' style="display:none;visibility:hidden"', ' class="gtm-noscript-iframe"'),
    @('class="bg-white" style="padding: 2.2rem 0; border-top:1px solid #eee; border-bottom:1px solid #eee;"', 'class="bg-white certs-band"'),
    @(' style="margin:0; color:var(--text-dark); opacity:0.8; font-weight:700;"', ' class="certs-band__title"'),
    @(' style="justify-content:center;"', ' class="certs-band__logos"'),
    @(' style="height:60px; filter: grayscale(1); opacity: 0.8; transition: all 0.3s ease;" onmouseover="this.style.filter=''grayscale(0)''; this.style.opacity=''1'';" onmouseout="this.style.filter=''grayscale(1)''; this.style.opacity=''0.8'';"', ' class="certs-logo"'),
    @(' style="height:50px; filter: grayscale(1); opacity: 0.8; transition: all 0.3s ease;" onmouseover="this.style.filter=''grayscale(0)''; this.style.opacity=''1'';" onmouseout="this.style.filter=''grayscale(1)''; this.style.opacity=''0.8'';"', ' class="certs-logo certs-logo--fda"'),
    @(' style="height:55px; filter: grayscale(1); opacity: 0.8; transition: all 0.3s ease;" onmouseover="this.style.filter=''grayscale(0)''; this.style.opacity=''1'';" onmouseout="this.style.filter=''grayscale(1)''; this.style.opacity=''0.8'';"', ' class="certs-logo certs-logo--fssai"'),
    @(' style="margin-bottom:1rem;"', ' class="h2-mb-1"'),
    @(' style="margin-bottom:0.5rem;"', ' class="h5-mb-5"'),
    @(' style="margin-bottom:0.75rem;"', ' class="h5-mb-75"'),
    @(' style="margin-bottom:1.25rem;"', ' class="p-mb-125"'),
    @(' style="margin-bottom:1rem;"', ' class="p-mb-125"'),
    @(' style="padding:2rem;"', ' class="card-pad-2"'),
    @(' style="padding:1.5rem;"', ' class="card-pad-15"'),
    @(' style="padding:1.5rem;overflow:hidden;"', ' class="card-overflow"'),
    @(' style="padding:2.5rem; max-width:680px; margin:auto;"', ' class="card-pad-25-center"'),
    @(' style="padding:2.5rem;"', ' class="card-pad-25"'),
    @(' style="padding:2rem;text-align:center;"', ' class="card-pad-2 leader-card"'),
    @(' style="border-radius:var(--radius-md);box-shadow:var(--shadow-md);width:100%;"', ' class="img-cover-shadow"'),
    @(' style="border-radius:var(--radius-sm);margin-bottom:1.25rem;width:100%;height:160px;object-fit:cover;"', ' class="img-format-card"'),
    @(' style="width:100%;height:160px;object-fit:cover;border-radius:var(--radius-sm);margin-bottom:1.25rem;"', ' class="img-format-card"'),
    @(' style="width:100%;height:100px;object-fit:cover;border-radius:50%;margin:0 auto 1rem;"', ' class="leader-photo"'),
    @(' style="margin-top:1.5rem;"', ' class="mt-15"'),
    @(' style="margin-top:2.5rem;"', ' class="text-center-mt"'),
    @(' style="margin-top:0.5rem;"', ' class="mt-05"'),
    @(' style="margin-bottom:1.5rem;"', ' class="mb-15"'),
    @(' style="margin-bottom:1.25rem;"', ' class="mb-125"'),
    @(' style="background:var(--bg-light);border-radius:var(--radius-sm);padding:1rem;text-align:center;"', ' class="stat-box"'),
    @(' style="font-style:italic;margin-bottom:1rem;"', ' class="blockquote-italic"'),
    @(' style="display:none;"', ' class="is-hidden"'),
    @(' style="border:none; padding-top:0;"', ' class="split-row--flush"'),
    @(' style="border:none;padding-top:0;"', ' class="split-row--flush"'),
    @(' style="border:none; padding-top:0;gap:3rem;align-items:start;"', ' class="split-row split-row--flush"'),
    @(' style="border:none;padding:0;gap:3rem;align-items:start;"', ' class="split-row split-row--flush"'),
    @(' style="border:none;"', ' class="split-row--end"'),
    @(' style="border-bottom:1px solid var(--border-light);"', ' class="nav-simple"'),
    @(' style="font-size:clamp(2rem,4vw,3rem);margin-bottom:1rem;"', ' class="h2-mb-1"'),
    @(' style="font-size:clamp(1.8rem,3vw,2.5rem); margin-bottom:1.5rem;"', ' class="h2-mb-1"'),
    @(' style="margin-bottom:0.6rem;"', ' class="h5-mb-5"'),
    @(' style="margin: 0 auto 1.5rem auto;"', ' class="leader-photo"'),
    @(' style="width:100px;height:100px;object-fit:cover;border-radius:50%;margin:0 auto 1rem;"', ' class="leader-photo"'),
    @(' style="margin-bottom:0.5rem;font-size:clamp(1.1rem, 2vw, 1.25rem);"', ' class="card-format-title"'),
    @(' class="text-primary-custom fw-bold" style="font-size:0.9rem;"', ' class="text-primary-custom fw-bold learn-more-link"'),
    @(' class="text-primary-custom fw-bold" style="font-size:0.9srem;"', ' class="text-primary-custom fw-bold learn-more-link"'),
    @('<div style="background:var(--bg-light);border-bottom:1px solid var(--border-light);padding:0.6rem 0;">', '<div class="breadcrumb-bar">'),
    @('<nav style="font-size:0.82rem;color:var(--text-muted);">', '<nav class="breadcrumb-nav">'),
    @('<span style="color:var(--primary-color);font-weight:600;">', '<span class="breadcrumb-current">'),
    @('<header class="page-header bg-premium-gradient" style="padding-block:5rem;text-align:left;">', '<header class="page-header page-header--landing bg-premium-gradient">'),
    @('<div class="split-row" style="border:none;padding:0;">', '<div class="split-row split-row--zero">'),
    @('<div class="split-row" style="margin-top:4rem; border:none;"', '<div class="split-row split-row-mt-4"'),
    @('<div class="split-row" style="margin-top:4rem;border:none;"', '<div class="split-row split-row-mt-4"'),
    @('<div class="split-row" style="padding-top:0;">', '<div class="split-row split-row-pt-0">'),
    @('<section class="section-sm" style="background:var(--bg-light);">', '<section class="section-sm section-bg-light">'),
    @('<span class="badge badge-primary" style="margin-bottom:1rem;letter-spacing:0.06em;">', '<span class="badge badge-primary badge--spaced">'),
    @('<span class="badge badge-primary" style="margin-bottom:0.75rem;font-size:0.7rem;letter-spacing:0.08em;">', '<span class="badge badge-primary badge--compact">'),
    @('<h1 style="font-size:clamp(2rem,4vw,3.2rem);margin-bottom:1rem;">', '<h1>'),
    @('<h1 style="font-size:clamp(2rem, 4vw, 3.2rem); font-weight:700; margin-bottom:1rem;">', '<h1>'),
    @('<p class="lead text-muted" style="margin-bottom:1.75rem;">', '<p class="lead text-muted lead-mb-lg">'),
    @('<p class="lead text-muted" style="margin-bottom:2.5rem;">', '<p class="lead text-muted contact-lead">'),
    @('<div class="grid grid-2" style="max-width:340px;margin-bottom:2rem;gap:0.6rem;">', '<div class="grid grid-2 feature-grid-compact">'),
    @('<div data-aos="fade-left" style="text-align:center;">', '<div data-aos="fade-left" class="landing-img-col">'),
    @('<div class="flex flex-wrap gap-3" style="margin-bottom:2rem;">', '<div class="flex flex-wrap gap-3 flex-mb-2">'),
    @(' style="border-radius:var(--radius-md);box-shadow:var(--shadow-lg);max-width:460px;width:100%;"', ' class="img-landing-feature"'),
    @(' style="border-radius:var(--radius-lg);box-shadow:var(--shadow-lg);"', ' class="img-shadow-lg"'),
    @(' style="border-radius:var(--radius-md);box-shadow:var(--shadow-lg);width:100%;"', ' class="img-full-shadow"'),
    @('<div style="max-width:800px; margin:0 auto;">', '<div class="content-narrow">'),
    @('<h3 style="color:var(--primary-color);margin-bottom:0.75rem;">', '<h3 class="format-section-title">'),
    @(' alt="Softgels" style="border-radius:var(--radius-sm);"', ' alt="Softgels" class="img-radius-sm"'),
    @(' alt="Tablets" style="border-radius:var(--radius-sm);"', ' alt="Tablets" class="img-radius-sm"'),
    @(' alt="Gummies" style="border-radius:var(--radius-sm);"', ' alt="Gummies" class="img-radius-sm"'),
    @(' style="width:100%;height:200px;object-fit:cover;border-radius:var(--radius-sm);"', ' class="img-format-accordion"'),
    @('<p class="lead text-muted" style="max-width:680px; margin-inline:auto; margin-bottom:3rem;">', '<p class="lead text-muted">'),
    @('<div style="display:flex; justify-content:center; gap:1.5rem; flex-wrap:wrap;">', '<div class="products-stats">'),
    @('<div style="display:flex;align-items:center;gap:1rem;flex-wrap:wrap;">', '<div class="filter-bar-row">'),
    @('<div class="search-box" style="min-width:220px;flex:0 0 220px;">', '<div class="search-box search-box--fixed">'),
    @('<div class="filter-scroll" id="filterPills" style="flex:1;">', '<div class="filter-scroll filter-scroll--grow" id="filterPills">'),
    @('<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">', '<div class="products-results-bar">'),
    @('<div id="paginationControls" style="display:none; justify-content:center; gap:0.5rem; margin-top:3rem; margin-bottom:4rem; flex-wrap:wrap;"></div>', '<div id="paginationControls"></div>'),
    @('<h2 style="font-size:clamp(1.8rem, 3.5vw, 3rem); font-weight:700; margin-bottom:1rem;">', '<h2>'),
    @('<p style="opacity:0.75; font-size:1.1rem; margin-bottom:2.5rem; text-align:center;">', '<p>'),
    @('<div style="text-align:center;">', '<div class="cta-actions">'),
    @('<h3 class="text-primary-custom" style="font-weight:700; margin-bottom:0;">', '<h3 class="text-primary-custom">'),
    @('<div style="background:linear-gradient(135deg, #d4e9ff, #bde4f4);display:flex;align-items:center;justify-content:center;">', '<div class="sig-media sig-media--cognitive">'),
    @('<div style="background:linear-gradient(135deg, #e8f5e9, #c8e6c9);display:flex;align-items:center;justify-content:center;">', '<div class="sig-media sig-media--protect">'),
    @('<div style="background:linear-gradient(135deg, #fff3e0, #ffe0b2);display:flex;align-items:center;justify-content:center;">', '<div class="sig-media sig-media--mobility">'),
    @('<div style="background:linear-gradient(135deg, #fce4ec, #f8bbd0);display:flex;align-items:center;justify-content:center;">', '<div class="sig-media sig-media--beauty">'),
    @('<div style="background:var(--bg-light);border-radius:var(--radius-sm);padding:1rem;">', '<div class="stat-box">'),
    @('<div class="grid grid-2" style="margin-top:1rem;">', '<div class="grid grid-2 mt-15">'),
    @('<div class="card text-center" style="padding:3rem;">', '<div class="card text-center card-pad-3">')
  )
  foreach ($p in $pairs) {
    if ([string]::IsNullOrEmpty($p[0])) { continue }
    if ([string]::IsNullOrEmpty($p[1])) {
      $html = Remove-InlineStyle $html $p[0].Trim()
    } else {
      $html = $html.Replace($p[0], $p[1])
    }
  }
  $html = Remove-InlineStyle $html 'style="font-size:clamp(1.1rem, 2vw, 1.25rem);"'
  $html = Remove-InlineStyle $html 'style="font-size:0.9rem;"'
  $html = Remove-InlineStyle $html 'style="font-size:0.9srem;"'
  $html = Remove-InlineStyle $html 'style="margin-bottom:0.25rem;"'
  $html = Remove-InlineStyle $html 'style="width:100%;height:100%;object-fit:contain;border-radius:12px;padding:12px;aspect-ratio:1/1;"'
  $html = Remove-InlineStyle $html 'style="width:100%;height:100%;object-fit:contain;border-radius:12px;padding:12px;aspect-ratio: 1 / 1;"'
  $html = $html -replace 'class="text-muted small" class="h2-mb-1"', 'class="text-muted small card-format-desc"'
  $html = $html -replace 'class="text-muted small" class="p-mb-125"', 'class="text-muted small card-format-desc"'
  $html = $html -replace 'class="bg-white bg-white certs-band"', 'class="bg-white certs-band"'
  return $html
}

function Get-BodyContent([string]$content) {
    if ($content -match '(?is)</nav>\s*(.*?)\s*(?:<!--\s*Footer\s*-->|<footer\b)') {
        return $matches[1].Trim()
    }
    if ($content -match '(?is)<!-- End Google Tag Manager \(noscript\) -->\s*(.*?)\s*(?:<!--\s*Footer\s*-->|<footer\b)') {
        return $matches[1].Trim()
    }
    return ''
}

function Get-ExtraScript([string]$content, [string]$pageOut = '') {
    if ($content -notmatch '(?is)<script\s+src="https://unpkg.com/aos@2\.3\.1/dist/aos\.js"></script>\s*<script>([\s\S]*?)</script>') {
        return ''
    }
    $inner = $matches[1]
    $inner = $inner -replace '(?is)AOS\.init\([^)]*\);\s*', ''
    $inner = $inner -replace '(?is)//\s*Initialize AOS\s*', ''
    $inner = $inner -replace '(?is)//\s*Navbar Scroll Effect[\s\S]*?(?=//\s*Hero Slider)', ''
    $inner = $inner -replace '(?is)const nav = document\.getElementById\(''mainNav''\);[\s\S]*?links\.classList\.remove\(''open''\);\s*\}\)\);\s*', ''
    $inner = $inner -replace '(?is)const nav = document\.getElementById\(''mainNav''\);[\s\S]*?navLinks\.classList\.remove\(''open''\);\s*\}\)\);\s*', ''
    $inner = $inner -replace '(?is)window\.addEventListener\(''scroll''[\s\S]*?\}\);\s*', ''
    $inner = $inner -replace '(?is)//\s*Hamburger Menu[\s\S]*?links\.classList\.remove\(''open''\);\s*\}\)\);\s*', ''
    $inner = $inner -replace '(?is)toggle\.addEventListener\(''click''[\s\S]*?links\.classList\.toggle\(''open''\);\s*\}\);\s*', ''
    if ($pageOut -eq 'home.html' -and $inner -match '(?is)(//\s*Hero Slider Logic[\s\S]*)') {
        $inner = $matches[1]
    }
    return $inner.Trim()
}

$pages = @(
    @{ out = 'home.html'; src = 'index.html' },
    @{ out = 'about.html'; src = 'about.html' },
    @{ out = 'services.html'; src = 'services.html' },
    @{ out = 'contact.html'; src = 'contact.html' },
    @{ out = 'formats.html'; src = 'formats.html' },
    @{ out = 'faq.html'; src = 'faq.html' },
    @{ out = 'privacy-policy.html'; src = 'privacy-policy.html' },
    @{ out = 'terms-and-conditions.html'; src = 'terms-and-conditions.html' },
    @{ out = 'insights.html'; src = 'insights.html' },
    @{ out = 'blog.html'; src = 'blog.html' },
    @{ out = 'products.html'; src = 'products.html' },
    @{ out = 'private-label.html'; src = 'private-label.html' },
    @{ out = 'signature.html'; src = 'signature.html' },
    @{ out = 'softgel-supplement-manufacturer.html'; src = 'softgel-supplement-manufacturer.html' },
    @{ out = 'private-label-supplements-manufacturer.html'; src = 'private-label-supplements-manufacturer.html' }
)

foreach ($page in $pages) {
    $srcPath = Join-Path $srcDir $page.src
    if (-not (Test-Path $srcPath)) {
        Write-Warning "Skip missing: $($page.src)"
        continue
    }
    $raw = [System.IO.File]::ReadAllText($srcPath)
    $body = Get-BodyContent $raw
    if ($body -eq '') {
        Write-Warning "No body: $($page.out)"
        continue
    }
    $body = Remove-InlineStyleBlocks $body
    $body = Fix-ImagePaths $body
    $body = Convert-InlineAttributes $body
    $body = Merge-DuplicateClasses $body
    $body = $body -replace '(?is)<!--\s*Navigation\s*-->.*?</nav>\s*', ''
    $body = $body -replace '(?is)<!--\s*Navigation\s*-->\s*', ''

    $extra = Get-ExtraScript $raw $page.out
    $out = $cmsHeader + $body + $scriptFooter
    if ($extra -ne '') { $out += "`n$extra`n" }
    $out += "`n    </script>`n"
    if ($page.out -eq 'products.html') {
        $out = Fix-ProductsPaginationJs $out
    }

    $outPath = Join-Path $root $page.out
    [System.IO.File]::WriteAllText($outPath, $out, [System.Text.UTF8Encoding]::new($false))
    Write-Host "Wrote $outPath ($((Get-Item $outPath).Length) bytes)"
}

Write-Host 'Done.'
