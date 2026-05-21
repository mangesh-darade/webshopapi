<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$theme = (isset($webshop_settings) && is_object($webshop_settings) && isset($webshop_settings->webshop_theme))
    ? (string) $webshop_settings->webshop_theme : 'gulfpharmacy';
$name = isset($product['name']) ? $product['name'] : 'Product';
$flash_msg = $this->session->flashdata('message');
$flash_err = $this->session->flashdata('error');
$error_field = (string) $this->session->flashdata('error_field');
$old = $this->session->flashdata('pr_old');
$old_rating = (is_array($old) && isset($old['rating'])) ? (int) $old['rating'] : 0;
$old_title  = (is_array($old) && isset($old['review_title'])) ? (string) $old['review_title'] : '';
$old_body   = (is_array($old) && isset($old['review_details'])) ? (string) $old['review_details'] : '';
$err_class = function ($field) use ($error_field) {
    return $error_field === $field ? ' pr-input-error' : '';
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Product Reviews | <?= html_escape($name) ?></title>
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/common.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/header.css">
    <link rel="stylesheet" href="<?= $assets ?>gulfpharmacy_theme/css/product-reviews.css">
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
            <h1 class="pr-title">Write a Review for <?= html_escape($name) ?></h1>

            <?php if ($flash_err): ?>
                <div class="pr-alert pr-err" role="alert" id="pr-flash">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?= html_escape($flash_err) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($flash_msg): ?>
                <div class="pr-alert pr-ok" role="status">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span><?= html_escape($flash_msg) ?></span>
                </div>
            <?php endif; ?>

            <form class="pr-form" id="pr-form" method="post" action="<?= base_url('webshop/submit_product_review') ?>" novalidate>
                <?= function_exists('webshop_csrf_hidden_input') ? webshop_csrf_hidden_input() : '' ?>

                <input type="hidden" name="product_hash" value="<?= html_escape($product_hash) ?>">

                <label class="form-label" id="pr-rating-label">Overall Rating <span aria-hidden="true" class="pr-required">*</span></label>
                <div class="star-rating<?= $err_class('rating') ?>" role="radiogroup" aria-labelledby="pr-rating-label" id="pr-rating-group">
                    <?php for ($i = 5; $i >= 1; $i--): $checked = ($old_rating === $i) ? ' checked' : ''; ?>
                        <input type="radio" id="<?= $i ?>-stars" name="rating" value="<?= $i ?>"<?= $checked ?>>
                        <label for="<?= $i ?>-stars" aria-label="<?= $i ?> star<?= $i === 1 ? '' : 's' ?>">★</label>
                    <?php endfor; ?>
                </div>
                <p class="pr-help" id="pr-rating-help"><span>Tap a star to rate</span><span class="pr-count" id="pr-rating-count"><?= $old_rating > 0 ? $old_rating . '/5' : '' ?></span></p>

                <label for="review_title">Review Title <span aria-hidden="true" class="pr-required">*</span></label>
                <input type="text" id="review_title" name="review_title"
                       class="<?= trim($err_class('review_title')) ?>"
                       value="<?= html_escape($old_title) ?>"
                       placeholder="Summarize your experience..."
                       maxlength="120" autocomplete="off">
                <p class="pr-help" id="pr-title-help"><span>Min 3 characters</span><span class="pr-count" id="pr-title-count">0/120</span></p>

                <label for="review_details">Review Details <span aria-hidden="true" class="pr-required">*</span></label>
                <textarea id="review_details" name="review_details" rows="5"
                          class="<?= trim($err_class('review_details')) ?>"
                          placeholder="What did you like or dislike?"
                          maxlength="2000"><?= html_escape($old_body) ?></textarea>
                <p class="pr-help" id="pr-body-help"><span>Min 10 characters</span><span class="pr-count" id="pr-body-count">0/2000</span></p>

                <button type="submit" id="pr-submit">Submit Review</button>
            </form>
        </div>

        <div class="pr-card">
            <h2 class="pr-reviews-title">Customer Reviews (<?= is_array($reviews) ? count($reviews) : 0 ?>)</h2>
            <?php if (empty($reviews)): ?>
                <p class="pr-no-reviews">No reviews yet. Be the first to share your thoughts!</p>
            <?php else: ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="pr-item">
                        <div class="pr-stars" aria-label="<?= (int) $r['reviews_rattings'] ?> out of 5 stars"><?= str_repeat('★', (int) $r['reviews_rattings']) ?><?= str_repeat('☆', 5 - (int) $r['reviews_rattings']) ?></div>
                        <div class="pr-rtitle"><?= html_escape($r['reviews_title']) ?></div>
                        <div class="pr-rtext"><?= nl2br(html_escape($r['reviews_details'])) ?></div>
                        <div class="pr-meta">
                            <span class="pr-avatar"><?= strtoupper(substr($r['customer_name'] ?: 'G', 0, 1)) ?></span>
                            <span><?= html_escape($r['customer_name'] ?: 'Guest') ?></span>
                            <?php if (!empty($r['reviews_date'])): ?>
                                <span>·</span>
                                <span><?= date('M d, Y', strtotime($r['reviews_date'])) ?></span>
                            <?php endif; ?>
                        </div>
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
<script defer src="<?= $assets ?>gulfpharmacy_theme/js/product-reviews.js"></script>
</body>
</html>
