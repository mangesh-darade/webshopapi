<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/* Herbinn CMS footer strip — main chrome is rendered by footer.php */
$cfg = isset($config) && is_array($config) ? $config : array();
$navPages = array();
foreach (array(
    isset($cfg['nav_pages']) ? $cfg['nav_pages'] : null,
    isset($cms_footer_nav_pages) ? $cms_footer_nav_pages : null,
    isset($cms_nav_pages) ? $cms_nav_pages : null,
) as $candidatePages) {
    if (is_array($candidatePages) && !empty($candidatePages)) {
        $navPages = $candidatePages;
        break;
    }
}
$bodyText = isset($cfg['content']) ? trim((string) $cfg['content']) : '';
$ftTitle = '';
foreach (array('title', 'heading') as $k) {
    if (isset($cfg[$k]) && trim((string) $cfg[$k]) !== '') {
        $ftTitle = (string) $cfg[$k];
        break;
    }
}
if ($ftTitle === '' && $bodyText === '' && empty($navPages)) {
    return;
}
$copyright = isset($cfg['copyright']) ? trim((string) $cfg['copyright']) : '';
?>
<section class="hb-cms-footer-strip" aria-label="Additional footer content">
    <div class="container">
        <?php if ($ftTitle !== '') : ?>
        <h2 class="hb-cms-footer-strip__title"><?= htmlspecialchars($ftTitle, ENT_QUOTES, 'UTF-8') ?></h2>
        <?php endif; ?>
        <?php if ($bodyText !== '') : ?>
        <div class="hb-cms-footer-strip__body"><?= $bodyText ?></div>
        <?php endif; ?>
        <?php if (!empty($navPages)) : ?>
        <nav aria-label="CMS footer navigation">
            <ul class="hb-cms-footer-strip__nav">
                <?php foreach ($navPages as $np) :
                    $href = isset($np['href']) ? (string) $np['href'] : (isset($np['url']) ? (string) $np['url'] : '#');
                    $label = isset($np['title']) ? (string) $np['title'] : (isset($np['page_name']) ? (string) $np['page_name'] : '');
                    if ($label === '' || $href === '') {
                        continue;
                    }
                ?>
                <li><a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></a></li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <?php endif; ?>
        <?php if ($copyright !== '') : ?>
        <p class="hb-cms-footer-strip__copy"><?= htmlspecialchars($copyright, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
    </div>
</section>
