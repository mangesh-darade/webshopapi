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
    <style>
    .pr-main{max-width:960px;margin:32px auto;padding:0 16px;font-family:'Inter', sans-serif}
    .pr-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;margin-bottom:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
    .pr-title{margin:0 0 16px;font-size:24px;color:#0F4C81;line-height:1.3}
    .pr-form label{display:block;margin-bottom:8px;font-weight:600;color:#374151}
    .pr-form input[type="text"],.pr-form textarea{width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;margin-bottom:6px;font-size:15px;transition:border-color .15s, box-shadow .15s, background-color .15s}
    .pr-form input[type="text"]:focus,.pr-form textarea:focus{outline:0;border-color:#0F4C81;box-shadow:0 0 0 3px rgba(15,76,129,.15)}
    .pr-form .pr-help{display:flex;justify-content:space-between;align-items:center;font-size:12px;color:#6b7280;margin:0 0 14px}
    .pr-form .pr-help .pr-count{font-variant-numeric:tabular-nums}
    .pr-form .pr-help.is-ok{color:#16a34a}
    .pr-form .pr-help.is-bad{color:#b91c1c}
    .pr-input-error{border-color:#dc2626 !important;background:#fef2f2}
    .pr-input-error:focus{box-shadow:0 0 0 3px rgba(220,38,38,.15) !important}
    .pr-form button[type="submit"]{background:#0F4C81;color:#fff;border:0;border-radius:8px;padding:12px 24px;cursor:pointer;font-weight:700;transition:background .2s, opacity .2s;min-height:44px;font-size:15px}
    .pr-form button[type="submit"]:hover:not(:disabled){background:#0a355a}
    .pr-form button[type="submit"]:disabled{opacity:.6;cursor:not-allowed}

    .star-rating{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:6px;margin:0 0 6px;padding:8px 4px;border-radius:8px}
    .star-rating.pr-input-error{background:#fef2f2}
    .star-rating input{position:absolute;opacity:0;pointer-events:none}
    .star-rating label{font-size:40px;color:#d1d5db;cursor:pointer;transition:color .15s, transform .15s;margin-bottom:0;padding:0 2px;line-height:1}
    .star-rating :checked ~ label{color:#fbbf24}
    .star-rating label:hover,.star-rating label:hover ~ label{color:#fbbf24}
    .star-rating input:focus-visible + label{outline:2px solid #0F4C81;outline-offset:2px;border-radius:4px}

    .pr-item{padding:20px 0;border-bottom:1px solid #f3f4f6}
    .pr-item:last-child{border-bottom:0}
    .pr-stars{color:#fbbf24;font-size:18px;margin-bottom:8px;letter-spacing:1px}
    .pr-rtitle{font-size:17px;font-weight:700;margin-bottom:6px;color:#111827}
    .pr-rtext{color:#4b5563;line-height:1.6;margin-bottom:10px;white-space:pre-wrap;word-break:break-word}
    .pr-meta{font-size:13px;color:#6b7280;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .pr-avatar{width:24px;height:24px;background:#e5e7eb;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#4b5563}
    .pr-alert{padding:12px 14px;border-radius:8px;margin-bottom:20px;font-size:14px;display:flex;gap:10px;align-items:flex-start;line-height:1.4}
    .pr-alert svg{flex:0 0 18px;margin-top:1px}
    .pr-ok{background:#ecfdf3;border:1px solid #bbf7d0;color:#166534}
    .pr-err{background:#fef2f2;border:1px solid #fecaca;color:#991b1b}

    @media (max-width:480px){
        .pr-main{margin:16px auto;padding:0 12px}
        .pr-card{padding:16px;border-radius:10px}
        .pr-title{font-size:20px}
        .star-rating label{font-size:34px}
    }
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
                <input type="hidden" name="product_hash" value="<?= html_escape($product_hash) ?>">

                <label class="form-label" id="pr-rating-label">Overall Rating <span aria-hidden="true" style="color:#dc2626">*</span></label>
                <div class="star-rating<?= $err_class('rating') ?>" role="radiogroup" aria-labelledby="pr-rating-label" id="pr-rating-group">
                    <?php for ($i = 5; $i >= 1; $i--): $checked = ($old_rating === $i) ? ' checked' : ''; ?>
                        <input type="radio" id="<?= $i ?>-stars" name="rating" value="<?= $i ?>"<?= $checked ?>>
                        <label for="<?= $i ?>-stars" aria-label="<?= $i ?> star<?= $i === 1 ? '' : 's' ?>">★</label>
                    <?php endfor; ?>
                </div>
                <p class="pr-help" id="pr-rating-help"><span>Tap a star to rate</span><span class="pr-count" id="pr-rating-count"><?= $old_rating > 0 ? $old_rating . '/5' : '' ?></span></p>

                <label for="review_title">Review Title <span aria-hidden="true" style="color:#dc2626">*</span></label>
                <input type="text" id="review_title" name="review_title"
                       class="<?= trim($err_class('review_title')) ?>"
                       value="<?= html_escape($old_title) ?>"
                       placeholder="Summarize your experience..."
                       maxlength="120" autocomplete="off">
                <p class="pr-help" id="pr-title-help"><span>Min 3 characters</span><span class="pr-count" id="pr-title-count">0/120</span></p>

                <label for="review_details">Review Details <span aria-hidden="true" style="color:#dc2626">*</span></label>
                <textarea id="review_details" name="review_details" rows="5"
                          class="<?= trim($err_class('review_details')) ?>"
                          placeholder="What did you like or dislike?"
                          maxlength="2000"><?= html_escape($old_body) ?></textarea>
                <p class="pr-help" id="pr-body-help"><span>Min 10 characters</span><span class="pr-count" id="pr-body-count">0/2000</span></p>

                <button type="submit" id="pr-submit">Submit Review</button>
            </form>
        </div>

        <div class="pr-card">
            <h2 style="margin-top:0;font-size:20px;">Customer Reviews (<?= is_array($reviews) ? count($reviews) : 0 ?>)</h2>
            <?php if (empty($reviews)): ?>
                <p style="color:#6b7280;margin:20px 0 0;">No reviews yet. Be the first to share your thoughts!</p>
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

<script>
(function () {
    var form = document.getElementById('pr-form');
    if (!form) return;

    var ratingGroup = document.getElementById('pr-rating-group');
    var ratingInputs = ratingGroup ? ratingGroup.querySelectorAll('input[name="rating"]') : [];
    var ratingCount = document.getElementById('pr-rating-count');
    var ratingHelp = document.getElementById('pr-rating-help');

    var titleEl = document.getElementById('review_title');
    var titleCount = document.getElementById('pr-title-count');
    var titleHelp = document.getElementById('pr-title-help');

    var bodyEl = document.getElementById('review_details');
    var bodyCount = document.getElementById('pr-body-count');
    var bodyHelp = document.getElementById('pr-body-help');

    var submitBtn = document.getElementById('pr-submit');
    var flash = document.getElementById('pr-flash');

    function getRating() {
        for (var i = 0; i < ratingInputs.length; i++) {
            if (ratingInputs[i].checked) return parseInt(ratingInputs[i].value, 10) || 0;
        }
        return 0;
    }

    function updateRatingUI() {
        var r = getRating();
        if (ratingCount) ratingCount.textContent = r > 0 ? r + '/5' : '';
        if (ratingGroup) ratingGroup.classList.toggle('pr-input-error', false);
        if (ratingHelp) ratingHelp.classList.toggle('is-ok', r > 0);
    }

    function updateLenUI(el, countEl, helpEl, minLen) {
        if (!el) return;
        var l = el.value.length;
        var max = el.getAttribute('maxlength') || '';
        if (countEl) countEl.textContent = l + (max ? '/' + max : '');
        if (helpEl) {
            helpEl.classList.toggle('is-ok', l >= minLen);
            helpEl.classList.toggle('is-bad', l > 0 && l < minLen);
        }
        if (l >= minLen) el.classList.remove('pr-input-error');
    }

    for (var i = 0; i < ratingInputs.length; i++) {
        ratingInputs[i].addEventListener('change', updateRatingUI);
    }
    if (titleEl) titleEl.addEventListener('input', function () { updateLenUI(titleEl, titleCount, titleHelp, 3); });
    if (bodyEl) bodyEl.addEventListener('input', function () { updateLenUI(bodyEl, bodyCount, bodyHelp, 10); });

    updateRatingUI();
    updateLenUI(titleEl, titleCount, titleHelp, 3);
    updateLenUI(bodyEl, bodyCount, bodyHelp, 10);

    form.addEventListener('submit', function (e) {
        var rating = getRating();
        var title = titleEl ? titleEl.value.trim() : '';
        var body = bodyEl ? bodyEl.value.trim() : '';

        var firstBad = null;
        if (rating < 1) {
            if (ratingGroup) ratingGroup.classList.add('pr-input-error');
            firstBad = firstBad || ratingGroup;
        }
        if (title.length < 3) {
            if (titleEl) titleEl.classList.add('pr-input-error');
            firstBad = firstBad || titleEl;
        }
        if (body.length < 10) {
            if (bodyEl) bodyEl.classList.add('pr-input-error');
            firstBad = firstBad || bodyEl;
        }
        if (firstBad) {
            e.preventDefault();
            try { firstBad.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (_) {}
            if (firstBad.focus) {
                if (firstBad === ratingGroup && ratingInputs.length) {
                    ratingInputs[ratingInputs.length - 1].focus();
                } else {
                    firstBad.focus();
                }
            }
            return;
        }
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting…';
        }
    });

    if (flash) {
        var bad = form.querySelector('.pr-input-error');
        if (bad && bad.scrollIntoView) {
            try { bad.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (_) {}
        }
    }
})();
</script>
</body>
</html>
