<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* ── Variable resolution ────────────────────────────────────────── */
$isDynamic        = !empty($is_dynamic_cms_page);
$isCmsHome        = isset($home_page_cms) && is_object($home_page_cms);
$dynamicBanner    = isset($page_banner_image_url) ? trim((string)$page_banner_image_url) : '';
$dynamicLogo      = isset($page_logo_image_url)   ? trim((string)$page_logo_image_url)   : '';
$cmsHeaderHtml    = isset($cms_header_sections_html) ? (string)$cms_header_sections_html : '';
$cmsFooterHtml    = isset($cms_footer_sections_html) ? (string)$cms_footer_sections_html : '';
$cmsBodyHtml      = isset($cms_body_html)            ? (string)$cms_body_html            : '';

$ws = isset($webshop_settings) && is_object($webshop_settings) ? $webshop_settings : new stdClass();
$shopName = isset($Settings->site_name) && $Settings->site_name !== '' ? $Settings->site_name : 'My Shop';

/* Legacy website_setting fields (non-CMS mode) */
$banner_image = $message_section = $bullet_points = '';
$company_updates = $phone_number = $logo_image = '';
if (!$isDynamic && !$isCmsHome && !empty($this->data['website_setting'])) {
    foreach ($this->data['website_setting'] as $item) {
        switch ($item->fields) {
            case 'banner_image':         if (!empty($item->value)) $banner_image = $item->value;  break;
            case 'message_section':      $message_section = $item->value; break;
            case 'bullet_points':        $bullet_points   = $item->value; break;
            case 'company_updates':
            case 'company_certification':$company_updates = $item->value; break;
            case 'phone_number':         $phone_number    = $item->value; break;
            case 'logo_image':           $logo_image      = $item->value; break;
        }
    }
}
if ($isDynamic || $isCmsHome) { $banner_image = $dynamicBanner; $logo_image = $dynamicLogo; }

/* Custom page sections (legacy) */
$legacySections = isset($this->data['custom_pages']['header_strip']) && is_array($this->data['custom_pages']['header_strip'])
    ? $this->data['custom_pages']['header_strip'] : array();
$legacyWelcome = $legacyCert = $legacyBullets = $legacyUpdates = null;
foreach ($legacySections as $s) {
    if ($s['page_key'] === 'homepagewelcomemessagesection')   $legacyWelcome = $s;
    if ($s['page_key'] === 'homepagecompanycertificationsection') $legacyCert = $s;
    if ($s['page_key'] === 'homepagebulletpoints')            $legacyBullets = $s;
    if ($s['page_key'] === 'homepagecompanyupdatessection')   $legacyUpdates = $s;
}

$catItems = !empty($main_categories) && is_array($main_categories)
    ? $main_categories
    : (isset($this->data['categories']['main']) && is_array($this->data['categories']['main'])
        ? $this->data['categories']['main'] : array());

$bodyHtml = '';
if (!empty($home_section_html_block))                          $bodyHtml = (string)$home_section_html_block;
elseif (!empty($cmsBodyHtml))                                  $bodyHtml = $cmsBodyHtml;
elseif ($isCmsHome && !empty($home_page_cms->page_text))       $bodyHtml = (string)$home_page_cms->page_text;
elseif (!empty($legacyWelcome['page_text']))                   $bodyHtml = (string)$legacyWelcome['page_text'];


$showCatGrid = (!isset($home_has_category_grid) || $home_has_category_grid) && !empty($catItems);
$pageTitle   = !empty($page_title) ? $page_title : $shopName;
$flashMsg    = $this->session->flashdata('message');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <?= isset($meta_tags) ? $meta_tags : '' ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <style>
    /* GLOBAL LAYOUT RESET - FIX FOR SHRINKING ISSUE */
    html, body { 
        width: 100% !important; 
        max-width: 100% !important; 
        margin: 0 !important; 
        padding: 0 !important; 
        overflow-x: hidden !important; 
        -webkit-font-smoothing: antialiased;
        background: #f1f5f9; /* Soft background */
    }
    body { 
        display: block !important; 
        transform: none !important; 
        zoom: 1 !important; 
        min-height: 100vh;
    }
    .gp-site-wrapper {
        width: 100%;
        max-width: 100%;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        background: #fff;
        margin: 0 auto;
        box-shadow: 0 0 100px rgba(0,0,0,0.05);
    }

    :root{--gp-primary:#214548;--gp-accent:#4caf89;--gp-text:#1a2e30;--gp-border:#e2e8f0;--gp-header-h:70px;}
    *,*::before,*::after{box-sizing:border-box;}
    body{margin:0;font-family:'Inter',system-ui,sans-serif;color:var(--gp-text);background:#f8fafc;}
    .container{max-width:1280px;margin:0 auto;padding:0 20px;}
    /* Flash */
    .gp-flash{padding:12px 20px;border-radius:8px;margin:16px 0;font-size:14px;font-weight:500;}
    .gp-flash.success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
    .gp-flash.error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}
    /* Hero / Banner */
    .gp-hero{position:relative;overflow:hidden;background:linear-gradient(135deg,#214548 0%,#2f6366 100%);margin-bottom:40px;width:100%;}
    .gp-hero-img{width:100%;height:auto;min-height:360px;max-height:500px;object-fit:cover;display:block;opacity:.95;}
    .gp-hero-overlay{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:12px;text-align:center;padding:20px;z-index:2;}
    .gp-hero-title{font-size:clamp(28px,5vw,54px);font-weight:900;color:#fff;text-shadow:0 2px 20px rgba(0,0,0,.4);margin:0;line-height:1.1;}
    .gp-hero-sub{font-size:18px;font-weight:500;color:rgba(255,255,255,.9);margin:0;}
    .gp-hero-cta{background:#4caf89;color:#fff;font-size:16px;font-weight:700;padding:14px 36px;border-radius:12px;text-decoration:none;transition:all .3s ease;display:inline-block;box-shadow:0 8px 24px rgba(76,175,137,.3);}
    .gp-hero-cta:hover{background:#3a9974;transform:translateY(-2px);box-shadow:0 12px 28px rgba(76,175,137,.4);}
    .gp-hero-cta-alt{background:rgba(255,255,255,.15);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);border:1px solid rgba(255,255,255,.3);color:#fff;box-shadow:none;}
    .gp-hero-cta-alt:hover{background:rgba(255,255,255,.25);transform:translateY(-2px);}
    .gp-hero-glass{background:rgba(0,0,0,.35);backdrop-filter:blur(12px);-webkit-backdrop-filter:blur(12px);padding:40px 60px;border-radius:28px;border:1px solid rgba(255,255,255,.15);max-width:800px;width:90%;box-shadow:0 24px 64px rgba(0,0,0,.2);}
    .gp-hero-actions{display:flex;gap:16px;justify-content:center;margin-top:28px;}
    @media(max-width:768px){.gp-hero-img{height:380px;}.gp-hero-glass{padding:30px 24px;}.gp-hero-actions{flex-direction:column;gap:12px;}}
    .gp-hero.no-img{min-height:320px;}
    /* Section titles */
    .gp-section-title{font-size:clamp(20px,2.5vw,28px);font-weight:800;color:var(--gp-primary);margin:0 0 6px;text-align:center;}
    .gp-section-sub{font-size:14px;color:#6b7280;text-align:center;margin:0 0 28px;}
    .gp-section-divider{width:48px;height:4px;background:var(--gp-accent);border-radius:2px;margin:10px auto 24px;}
    .gp-section{padding:48px 0;}
    /* Category grid */
    .gp-cat-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:20px;}
    @media(max-width:900px){.gp-cat-grid{grid-template-columns:repeat(3,1fr);}}
    @media(max-width:540px){.gp-cat-grid{grid-template-columns:repeat(2,1fr);}}
    .gp-cat-card{background:#fff;border:1px solid var(--gp-border);border-radius:16px;overflow:hidden;transition:transform .3s,box-shadow .3s;text-decoration:none;color:inherit;display:flex;flex-direction:column;align-items:center;}
    .gp-cat-card:hover{transform:translateY(-8px);box-shadow:0 20px 40px rgba(33,69,72,.12);border-color:var(--gp-primary);}
    .gp-cat-img{width:100%;height:140px;object-fit:cover;transition:transform .4s;}
    .gp-cat-card:hover .gp-cat-img{transform:scale(1.06);}
    .gp-cat-name{padding:14px 12px;font-size:14px;font-weight:700;color:var(--gp-primary);text-align:center;background:#fff;width:100%;}
    /* CMS dynamic sections passthrough */
    .gp-cms-section{margin-bottom:32px;}
    /* Body content area */
    .gp-body-content{background:#fff;border-radius:16px;padding:32px;box-shadow:0 2px 12px rgba(0,0,0,.04);}
    .gp-body-content img{max-width:100%;height:auto;border-radius:8px;}
    /* CMS header/footer slots */
    #cms-header-sections{border-bottom:1px solid var(--gp-border);}
    #cms-footer-sections{border-top:1px solid var(--gp-border);margin-top:40px;}
    @media(max-width:600px){.gp-hero-img{height:220px;}.gp-section{padding:32px 0;}}
    </style>
</head>
<body>
<div class="gp-site-wrapper">

<?php require_once(VIEWPATH . 'plane_vanila_theme/gulfpharmacy_theme/header.php'); ?>



<div class="container">
    <?php if ($flashMsg): ?>
    <div class="gp-flash <?= strpos(strtolower($flashMsg), 'error') !== false ? 'error' : 'success' ?>"><?= htmlspecialchars($flashMsg, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
</div>

<!-- Hero / Banner (Edge-to-Edge) -->
<?php if (trim((string)$banner_image) !== ''): ?>
<div class="gp-hero">
    <?php $bSrc = (strpos($banner_image,'http') === 0) ? $banner_image : webshop_media_src($uploads, $banner_image); ?>
    <img src="<?= htmlspecialchars($bSrc, ENT_QUOTES,'UTF-8') ?>" alt="<?= htmlspecialchars($pageTitle, ENT_QUOTES,'UTF-8') ?>" class="gp-hero-img">
  
</div>
<?php elseif (!$isDynamic): ?>
<div class="gp-hero no-img">
    <div class="gp-hero-overlay">
        <div class="gp-hero-glass">
            <h1 class="gp-hero-title"><?= htmlspecialchars($pageTitle, ENT_QUOTES,'UTF-8') ?></h1>
            <p class="gp-hero-sub">Quality health & wellness products</p>
            <a href="<?= base_url('webshop/search_products') ?>" class="gp-hero-cta">Shop Now</a>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="container">

<!-- CMS Dynamic Sections (product grid, category grid, html blocks etc.) -->
<?php if ($isDynamic && !empty($home_section_html_block)): ?>
<div class="container gp-section">
    <div class="gp-body-content gp-cms-section"><?= webshop_normalize_html_media_urls($bodyHtml, $uploads) ?></div>
</div>
<?php elseif ($bodyHtml !== ''): 
    ?>
<div class="container gp-section">
    <div class="gp-body-content"><?= webshop_normalize_html_media_urls($bodyHtml, $uploads) ?></div>
</div>
<?php endif; ?>

<!-- Dynamic CMS Section: Product Carousel + Grid -->
<?php if (!empty($home_has_product_grid) && !empty($home_product_grid_items) && is_array($home_product_grid_items)): ?>
<?= $this->load->view('webshop/components/product_showcase', array(
    'items' => $home_product_grid_items,
    'uploads' => $uploads,
    'thumbs' => $thumbs,
    'title' => (isset($home_product_grid_title) && $home_product_grid_title ? $home_product_grid_title : 'Featured Products'),
    'show_carousel' => true,
    'show_grid' => true,
), true) ?>
<?php endif; ?>

<!-- Category Grid (Legacy or CMS driven) -->
<?php if ($showCatGrid): ?>
<div class="container gp-section" style="padding-top:0;">
    <?= $this->load->view('webshop/components/category_grid', array(
        'items' => $catItems,
        'uploads' => $uploads,
        'thumbs' => $thumbs,
        'config' => array(
            'title' => (isset($home_category_grid_title) && $home_category_grid_title !== '' ? $home_category_grid_title : 'Shop by Category'),
            'columns_desktop' => 5
        )
    ), true) ?>
</div>
<?php endif; ?>

<!-- Legacy: Company Certifications -->
<?php if (!$isDynamic && !empty($legacyCert['page_text'])): ?>
<section class="gp-section">
    <div class="container">
        <h2 class="gp-section-title">Our Certifications</h2>
        <div class="gp-section-divider"></div>
        <?= $this->load->view('webshop/components/html_block', array('config' => array('content' => $legacyCert['page_text']), 'uploads' => $uploads), true) ?>
    </div>
</section>
<?php endif; ?>

<!-- Legacy: Company Updates -->
<?php if (!$isDynamic && !empty($legacyUpdates['page_text'])): ?>
<section class="gp-section" style="background:#f0faf6;">
    <div class="container">
        <?= $this->load->view('webshop/components/html_block', array('config' => array('content' => $legacyUpdates['page_text']), 'uploads' => $uploads), true) ?>
    </div>
</section>
<?php endif; ?>

<!-- CMS Footer Sections -->


<?php require_once(VIEWPATH . 'plane_vanila_theme/gulfpharmacy_theme/footer.php'); ?>

<script src="<?= $assets ?>gulfpharmacy_theme/js/main.js?ver=200406"></script>
<script src="<?= $assets ?>gulfpharmacy_theme/js/index.js" defer></script>
<script>
const baseUrl = "<?= base_url('webshop') ?>";
const assets  = "<?= $assets ?>";
</script>
</div> <!-- end gp-site-wrapper -->
</body>
</html>
