<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Hero slider — matches herbinnwellness.com home (#homeHero).
 * CMS section_contain JSON: { "slides": [ { "badge", "title", "lead", "cta_label", "cta_href", "image" } ] }
 */
$cfg = isset($config) && is_array($config) ? $config : array();
$uploadsB = isset($uploads) ? (string) $uploads : '';
$webshop_url = base_url('webshop');

$slides = array();
if (isset($cfg['slides']) && is_array($cfg['slides'])) {
    $slides = $cfg['slides'];
} elseif (isset($cfg['content']) && is_string($cfg['content'])) {
    $decoded = json_decode($cfg['content'], true);
    if (is_array($decoded) && isset($decoded['slides']) && is_array($decoded['slides'])) {
        $slides = $decoded['slides'];
    }
}

if (empty($slides)) {
    $slides = array(
        array(
            'badge' => '30+ Years of Excellence',
            'title' => 'Your Trusted Partner in Nutraceuticals',
            'lead' => 'Over 3 decades of experience delivering premium supplements to the global market.',
            'cta_label' => 'Start Your Project',
            'cta_href' => '/contact',
            'image' => 'images/herbinn_hero_bg_1773742461337.png',
        ),
        array(
            'badge' => 'Global Scale',
            'title' => 'State-of-the-Art Global Manufacturing',
            'lead' => 'FDA registered & GMP certified facilities ready to scale your wellness brand worldwide.',
            'cta_label' => 'Explore Services',
            'cta_href' => '/services',
            'image' => 'images/herbinn_facility_modern_1773742515470.png',
        ),
        array(
            'badge' => 'Comprehensive Formats',
            'title' => 'Diverse Product Range & Formulations',
            'lead' => 'From serums, gummies and soft gel capsules we cater every formulation requirement.',
            'cta_label' => 'View Products',
            'cta_href' => '/products',
            'image' => 'images/softgel_capsules_premium_1773742482977.png',
        ),
    );
}

$resolve_img = function ($img) use ($uploadsB) {
    $img = trim((string) $img);
    if ($img === '') {
        return webshop_theme_assets_url('images/herbinn_hero_bg_1773742461337.png');
    }
    if (strpos($img, 'http') === 0) {
        return $img;
    }
    if ($uploadsB !== '' && function_exists('webshop_media_src')) {
        return webshop_media_src($uploadsB, $img);
    }
    if (strpos($img, 'assets/') === 0) {
        return base_url($img);
    }
    return webshop_theme_assets_url(ltrim($img, '/'));
};
?>
<section class="hero-slider" id="homeHero" aria-label="Hero">
    <?php foreach ($slides as $i => $slide) :
        if (!is_array($slide)) {
            continue;
        }
        $badge = isset($slide['badge']) ? htmlspecialchars((string) $slide['badge'], ENT_QUOTES, 'UTF-8') : '';
        $title = isset($slide['title']) ? htmlspecialchars((string) $slide['title'], ENT_QUOTES, 'UTF-8') : '';
        $lead = isset($slide['lead']) ? htmlspecialchars((string) $slide['lead'], ENT_QUOTES, 'UTF-8') : '';
        $cta_label = isset($slide['cta_label']) ? htmlspecialchars((string) $slide['cta_label'], ENT_QUOTES, 'UTF-8') : 'Learn More';
        $cta_href = isset($slide['cta_href']) ? (string) $slide['cta_href'] : $webshop_url;
        if ($cta_href !== '' && $cta_href[0] === '/') {
            $cta_href = rtrim($webshop_url, '/') . $cta_href;
        }
        $cta_href = htmlspecialchars($cta_href, ENT_QUOTES, 'UTF-8');
        $bg = $resolve_img(isset($slide['image']) ? $slide['image'] : '');
        $active = $i === 0 ? ' active' : '';
    ?>
    <div class="hero-slide<?= $active ?>" style="background-image: url('<?= htmlspecialchars($bg, ENT_QUOTES, 'UTF-8') ?>')">
        <div class="hero-overlay"></div>
        <div class="container hero-content">
            <?php if ($badge !== '') : ?><span class="hero-badge"><?= $badge ?></span><?php endif; ?>
            <?php if ($title !== '') : ?><h1><?= $title ?></h1><?php endif; ?>
            <?php if ($lead !== '') : ?><p class="lead"><?= $lead ?></p><?php endif; ?>
            <div class="hero-btns">
                <a href="<?= $cta_href ?>" class="btn btn-primary btn-lg"><?= $cta_label ?></a>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="slider-dots" id="sliderDots" aria-hidden="true"></div>
</section>
