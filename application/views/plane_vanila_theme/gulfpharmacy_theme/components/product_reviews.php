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
    .pr-main{max-width:960px;margin:32px auto;padding:0 16px;font-family:'Inter', sans-serif}
    .pr-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:24px;margin-bottom:24px;box-shadow:0 1px 3px rgba(0,0,0,0.05)}
    .pr-title{margin:0 0 16px;font-size:24px;color:#0F4C81}
    .pr-form label{display:block;margin-bottom:8px;font-weight:600;color:#374151}
    .pr-form input,.pr-form textarea{width:100%;padding:12px;border:1px solid #d1d5db;border-radius:8px;box-sizing:border-box;margin-bottom:16px;font-size:15px}
    .pr-form button{background:#0F4C81;color:#fff;border:0;border-radius:8px;padding:12px 24px;cursor:pointer;font-weight:700;transition:background 0.2s}
    .pr-form button:hover{background:#0a355a}
    
    /* Star Rating */
    .star-rating {display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:8px;margin-bottom:16px}
    .star-rating input {display:none}
    .star-rating label {font-size:40px;color:#d1d5db;cursor:pointer;transition:all 0.2s cubic-bezier(0.4, 0, 0.2, 1);margin-bottom:0;padding:0 2px}
    .star-rating :checked ~ label {color:#fbbf24;text-shadow: 0 0 10px rgba(251, 191, 36, 0.4);}
    .star-rating label:hover, .star-rating label:hover ~ label {color:#fbbf24;transform: scale(1.1);}

    .pr-item{padding:20px 0;border-bottom:1px solid #f3f4f6}
    .pr-item:last-child{border-bottom:0}
    .pr-stars{color:#fbbf24;font-size:18px;margin-bottom:8px}
    .pr-rtitle{font-size:17px;font-weight:700;margin-bottom:6px;color:#111827}
    .pr-rtext{color:#4b5563;line-height:1.6;margin-bottom:10px}
    .pr-meta{font-size:13px;color:#6b7280;display:flex;align-items:center;gap:8px}
    .pr-avatar{width:24px;height:24px;background:#e5e7eb;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#4b5563}
    .pr-alert{padding:12px 16px;border-radius:8px;margin-bottom:20px;font-size:15px}
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
            <h1 class="pr-title">Write a Review for <?= html_escape($name) ?></h1>
            <?php if ($this->session->flashdata('error')): ?>
                <div class="pr-alert pr-err"><?= html_escape($this->session->flashdata('error')) ?></div>
            <?php endif; ?>
            <?php if ($this->session->flashdata('message')): ?>
                <div class="pr-alert pr-ok"><?= html_escape($this->session->flashdata('message')) ?></div>
            <?php endif; ?>
            
            <form class="pr-form" method="post" action="<?= base_url('webshop/submit_product_review') ?>">
                <input type="hidden" name="product_hash" value="<?= html_escape($product_hash) ?>">
                
                <label class="form-label" style="font-weight: 600; font-size: 1.1rem; color: #1e293b;">Overall Rating</label>
                <div class="star-rating">
                    <input type="radio" id="5-stars" name="rating" value="5" required />
                    <label for="5-stars">★</label>
                    <input type="radio" id="4-stars" name="rating" value="4" />
                    <label for="4-stars">★</label>
                    <input type="radio" id="3-stars" name="rating" value="3" />
                    <label for="3-stars">★</label>
                    <input type="radio" id="2-stars" name="rating" value="2" />
                    <label for="2-stars">★</label>
                    <input type="radio" id="1-star" name="rating" value="1" />
                    <label for="1-star">★</label>
                </div>

                <label for="review_title">Review Title</label>
                <input type="text" id="review_title" name="review_title" placeholder="Summarize your experience..." required>

                <label for="review_details">Review Details</label>
                <textarea id="review_details" name="review_details" rows="5" placeholder="What did you like or dislike?" required></textarea>
                
                <button type="submit">Submit Review</button>
            </form>
        </div>

        <div class="pr-card">
            <h2 style="margin-top:0;font-size:20px;">Customer Reviews (<?= is_array($reviews) ? count($reviews) : 0 ?>)</h2>
            <?php if (empty($reviews)): ?>
                <p style="color:#6b7280;margin:20px 0 0;">No reviews yet. Be the first to share your thoughts!</p>
            <?php else: ?>
                <?php foreach ($reviews as $r): ?>
                    <div class="pr-item">
                        <div class="pr-stars"><?= str_repeat('★', (int) $r['reviews_rattings']) ?><?= str_repeat('☆', 5 - (int) $r['reviews_rattings']) ?></div>
                        <div class="pr-rtitle"><?= html_escape($r['reviews_title']) ?></div>
                        <div class="pr-rtext"><?= nl2br(html_escape($r['reviews_details'])) ?></div>
                        <div class="pr-meta">
                            <span class="pr-avatar"><?= strtoupper(substr($r['customer_name'] ?: 'G', 0, 1)) ?></span>
                            <span><?= html_escape($r['customer_name'] ?: 'Guest') ?></span>
                            <span>·</span>
                            <span><?= date('M d, Y', strtotime($r['reviews_date'])) ?></span>
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
</body>
</html>
