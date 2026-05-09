<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$theme = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme))
    ? (string) $webshop_settings->webshop_theme : 'gulfpharmacy';
$name = isset($product['name']) ? $product['name'] : 'Product';
$flash_msg = $this->session->flashdata('message');
$flash_err = $this->session->flashdata('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product Reviews | <?= html_escape($name) ?></title>
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <style>
    .pr-main{max-width:960px;margin:32px auto;padding:0 16px}
    .pr-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;margin-bottom:16px}
    .pr-title{margin:0 0 8px;font-size:22px}
    .pr-form input,.pr-form textarea,.pr-form select{width:100%;padding:10px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;margin-bottom:10px}
    .pr-form button{background:#0F4C81;color:#fff;border:0;border-radius:8px;padding:10px 14px;cursor:pointer}
    .pr-item{padding:10px 0;border-bottom:1px solid #f3f4f6}
    .pr-item:last-child{border-bottom:0}
    .pr-meta{font-size:12px;color:#6b7280}
    .pr-alert{padding:9px 10px;border-radius:8px;margin:0 0 10px}
    .pr-ok{background:#ecfdf3;border:1px solid #bbf7d0;color:#166534}
    .pr-err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}
    </style>
</head>
<body>
<div class="gp-site-wrapper">
    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/header.php');
    }
    ?>
    <main class="pr-main">
        <div class="pr-card">
            <h1 class="pr-title">Reviews for <?= html_escape($name) ?></h1>
            <?php if ($flash_msg): ?><div class="pr-alert pr-ok"><?= html_escape($flash_msg) ?></div><?php endif; ?>
            <?php if ($flash_err): ?><div class="pr-alert pr-err"><?= html_escape($flash_err) ?></div><?php endif; ?>
            <form class="pr-form" method="post" action="<?= base_url('webshop/submit_product_review') ?>">
                <input type="hidden" name="product_hash" value="<?= html_escape($product_hash) ?>">
                <label>Rating</label>
                <select name="rating" required>
                    <option value="">Select</option>
                    <option value="5">5 - Excellent</option>
                    <option value="4">4 - Good</option>
                    <option value="3">3 - Average</option>
                    <option value="2">2 - Poor</option>
                    <option value="1">1 - Bad</option>
                </select>
                <label>Review</label>
                <textarea name="review" rows="4" placeholder="Write your experience..." required></textarea>
                <button type="submit">Submit Review</button>
            </form>
        </div>
        <div class="pr-card">
            <h2 style="margin-top:0;">Customer Reviews (<?= is_array($reviews) ? count($reviews) : 0 ?>)</h2>
            <?php if (empty($reviews)): ?>
                <p style="color:#6b7280;margin:0;">No reviews yet. Be the first to review this product.</p>
            <?php else: ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="pr-item">
                        <strong><?= str_repeat('★', (int) $r['rating']) ?></strong>
                        <div><?= html_escape($r['review']) ?></div>
                        <div class="pr-meta"><?= html_escape($r['user']) ?> · <?= html_escape($r['created_at']) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    <?php
    if ($theme === 'nw' || $theme === 'gulfpharmacy') {
        require_once(VIEWPATH . 'plane_vanila_theme/' . $theme . '_theme/footer.php');
    }
    ?>
</div>
</body>
</html>
