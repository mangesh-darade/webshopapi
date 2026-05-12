<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$product = isset($product) && is_array($product) ? $product : array();
$galleryImages = isset($gallary_images) && is_array($gallary_images) ? $gallary_images : array();
$entityTagGroups = isset($entity_tag_groups) && is_array($entity_tag_groups) ? $entity_tag_groups : array();
$relatedProducts = isset($related_products) && is_array($related_products) ? $related_products : array();
$recentViewed = isset($recent_viewed) && is_array($recent_viewed) ? $recent_viewed : array();
$productReviews = isset($product_reviews) && is_array($product_reviews) ? $product_reviews : array();
$uploadsBase = isset($uploads) ? (string) $uploads : '';
$thumbsBase = isset($thumbs) ? (string) $thumbs : '';
$productId = isset($product['id']) ? (int) $product['id'] : 0;
$productName = isset($product['name']) ? (string) $product['name'] : 'Product';
$productDescp = isset($product['product_details']) ? (string) $product['product_details'] : '';
$brandName = isset($product['brand_name']) ? (string) $product['brand_name'] : '';
$rating = isset($product['ratings_avarage']) ? (float) $product['ratings_avarage'] : 0;
$reviews = isset($product['ratings_count']) ? (int) $product['ratings_count'] : 0;
$stockQty = isset($product['quantity']) ? (float) $product['quantity'] : 0;
$price = isset($product['price']) ? (float) $product['price'] : 0;
$mrp = isset($product['mrp']) ? (float) $product['mrp'] : 0;
$promo = isset($product['promo_price']) ? (float) $product['promo_price'] : 0;
if ($promo > 0 && $promo < $price) { $price = $promo; }
$formattedPrice = isset($this->sma) ? $this->sma->formatMoney($price) : webshop_price_display($price, isset($Settings) ? $Settings : null);
$formattedMrp = $mrp > 0 ? (isset($this->sma) ? $this->sma->formatMoney($mrp) : webshop_price_display($mrp, isset($Settings) ? $Settings : null)) : '';
$discountPercent = ($mrp > $price && $price > 0) ? round((($mrp - $price) / $mrp) * 100) : 0;
$productTaxRate = isset($product['tax_rate']) ? $product['tax_rate'] : 0;
$productTaxMethod = isset($product['tax_method']) ? $product['tax_method'] : 0;
$isLoggedIn = isset($this->session->webshop) && !empty($this->session->webshop->is_login) && !empty($this->session->webshop->user_id);
$noImgSrc = webshop_no_image_src($uploadsBase, $thumbsBase);
$noImgSrcAttr = htmlspecialchars($noImgSrc, ENT_QUOTES, 'UTF-8');
$gallery = array();
foreach ($galleryImages as $img) {
    $row = is_array($img) ? $img : (array) $img;
    $file = isset($row['photo']) ? trim((string) $row['photo']) : '';
    if ($file === '' && isset($row['image'])) { $file = trim((string) $row['image']); }
    if ($file === '') { continue; }
    $gallery[] = array(
        'full' => webshop_media_src($uploadsBase, $file),
        'thumb' => webshop_product_image_src($uploadsBase, $thumbsBase, array('image' => $file)),
    );
}
if (empty($gallery)) {
    $main = webshop_product_image_src($uploadsBase, $thumbsBase, $product);
    $gallery[] = array('full' => $main, 'thumb' => $main);
}
$pd_assets = isset($assets) ? $assets : base_url('assets/webshop/');
?>
<link rel="stylesheet" href="<?= $pd_assets ?>gulfpharmacy_theme/css/theme-product-details.css">

<div class="pd-wrap">
  <div class="pd-main">
    <div class="pd-card pd-gallery">
      <div class="pd-thumbs" id="pdThumbs">
        <?php foreach ($gallery as $i => $img) { ?>
          <div class="pd-thumb <?= $i === 0 ? 'active' : ''; ?>" data-full="<?= htmlspecialchars($img['full'], ENT_QUOTES, 'UTF-8'); ?>"><img loading="lazy" src="<?= htmlspecialchars($img['thumb'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>" onerror="this.onerror=null;this.src='<?= $noImgSrcAttr ?>';"></div>
        <?php } ?>
      </div>
      <div class="pd-mainimg" id="pdMain"><img id="pdMainImg" src="<?= htmlspecialchars($gallery[0]['full'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>" onerror="this.onerror=null;this.src='<?= $noImgSrcAttr ?>';"></div>
    </div>
    <div class="pd-card pd-summary">
      <h1 class="pd-title"><?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?></h1>
      <div class="pd-meta">
        <?php 
        $catName = '';
        $catId = isset($product['category_id']) ? (int)$product['category_id'] : 0;
        $subId = isset($product['subcategory_id']) ? (int)$product['subcategory_id'] : 0;
        $cats = isset($categories) ? $categories : array();
        
        if ($subId > 0 && isset($cats[$catId][$subId])) {
            $catName = is_object($cats[$catId][$subId]) ? $cats[$catId][$subId]->name : (isset($cats[$catId][$subId]['name']) ? $cats[$catId][$subId]['name'] : '');
        } elseif ($catId > 0 && isset($cats['main'][$catId])) {
            $catName = is_object($cats['main'][$catId]) ? $cats['main'][$catId]->name : (isset($cats['main'][$catId]['name']) ? $cats['main'][$catId]['name'] : '');
        }
        ?>
        <span class="pd-rating">
            <?= str_repeat('★', (int) round($rating)); ?><?= str_repeat('☆', 5 - (int) round($rating)); ?> 
            <?= number_format($rating, 1); ?>
        </span>
        <span>(<?= (int) $reviews; ?> reviews)</span>
        <?php if (!empty($productId)): ?>
          <span><a href="<?= base_url('webshop/product_reviews/' . md5((int) $productId)) ?>" class="pd-meta-write">Write a review</a></span>
        <?php endif; ?>
        <?php if ($catName !== '') { ?><span>Category: <strong><?= htmlspecialchars($catName, ENT_QUOTES, 'UTF-8'); ?></strong></span><?php } ?>
        <?php if ($brandName !== '') { ?><span>Brand: <strong><?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></strong></span><?php } ?>
      </div>
      <div class="pd-price"><span class="pd-price-now" id="price-current"><?= htmlspecialchars((string) $formattedPrice, ENT_QUOTES, 'UTF-8'); ?></span><?php if ($formattedMrp !== '') { ?><span class="pd-mrp"><?= htmlspecialchars((string) $formattedMrp, ENT_QUOTES, 'UTF-8'); ?></span><?php } ?><?php if ($discountPercent > 0) { ?><span class="pd-off"><?= (int) $discountPercent; ?>% OFF</span><?php } ?></div>
      <div class="pd-stock <?= $stockQty > 0 ? 'ok' : 'no'; ?>"><?= $stockQty > 0 ? 'In Stock' : 'Out of Stock'; ?></div>
      <div class="pd-short"><?= $productDescp !== '' ? $productDescp : '<span class="pd-short-empty">No short description available.</span>'; ?></div>
      <div class="pd-actions">
        <div class="pd-qty"><button type="button" id="qDec">-</button><input id="qVal" class="itemQty" type="number" min="1" value="1"><button type="button" id="qInc">+</button></div>
        <button class="pd-btn pd-cart add-to-cart" product_id="<?= (int) $productId; ?>" quantity="1" tax_rate="<?= htmlspecialchars((string) $productTaxRate, ENT_QUOTES, 'UTF-8'); ?>" tax_method="<?= htmlspecialchars((string) $productTaxMethod, ENT_QUOTES, 'UTF-8'); ?>" price="<?= htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8'); ?>" promotion_price="<?= htmlspecialchars((string) $promo, ENT_QUOTES, 'UTF-8'); ?>" product_price="<?= htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8'); ?>" product_desc="<?= htmlspecialchars((string) strip_tags($productDescp), ENT_QUOTES, 'UTF-8'); ?>" imageurl="<?= htmlspecialchars((string) $gallery[0]['full'], ENT_QUOTES, 'UTF-8'); ?>" productname="<?= htmlspecialchars((string) $productName, ENT_QUOTES, 'UTF-8'); ?>">Add To Cart</button>
        <button class="pd-btn pd-buy buy-now" type="button">Buy Now</button>
        <button type="button" class="pd-wish" id="pdWishlistBtn" data-in-wishlist="0" aria-label="Add to favourites">♡</button>
      </div>
    </div>
  </div>

  <div class="pd-tabs">
    <div class="pd-tab-nav" id="pdTabNav">
      <div class="pd-tab-link active" data-tab="t1">Description</div><div class="pd-tab-link" data-tab="t2">Supplement Facts</div><div class="pd-tab-link" data-tab="t3">Ingredients</div><div class="pd-tab-link" data-tab="t4">Usage</div><div class="pd-tab-link" data-tab="t5">Reviews</div><div class="pd-tab-link" data-tab="t6">FAQs</div>
    </div>
    <div class="pd-tab active" id="t1"><?php
      $t1 = !empty($product['cf1']) ? $product['cf1'] : ($productDescp !== '' ? $productDescp : '');
      echo $t1 !== '' ? $t1 : '<p class="pd-rev-empty pd-rev-empty-tab">No description has been added for this product.</p>';
    ?></div>
    <div class="pd-tab" id="t2"><?= !empty($product['cf3']) ? $product['cf3'] : '<p class="pd-rev-empty pd-rev-empty-tab">No supplement facts available.</p>'; ?></div>
    <div class="pd-tab" id="t3"><?= !empty($product['cf2']) ? $product['cf2'] : '<p class="pd-rev-empty pd-rev-empty-tab">No ingredients list available.</p>'; ?></div>
    <div class="pd-tab" id="t4"><p class="pd-rev-empty pd-rev-empty-tab">Consult physician or pharmacist for personalized usage.</p></div>
    <div class="pd-tab" id="t5">
      <?php if (!empty($productReviews)): ?>
        <div class="pd-rev-summary">Rated <?= number_format($rating, 1); ?>/5 average · <?= (int) $reviews; ?> review<?= (int) $reviews !== 1 ? 's' : ''; ?></div>
        <div class="pd-rev-list">
          <?php foreach ($productReviews as $r):
            $r = is_array($r) ? $r : (array) $r;
            $rt = isset($r['reviews_rattings']) ? max(0, min(5, (int) $r['reviews_rattings'])) : 0;
            $ttl = isset($r['reviews_title']) ? trim((string) $r['reviews_title']) : '';
            $det = isset($r['reviews_details']) ? trim((string) $r['reviews_details']) : '';
            $dt = isset($r['reviews_date']) ? trim((string) $r['reviews_date']) : '';
            $who = isset($r['customer_name']) ? trim((string) $r['customer_name']) : 'Customer';
            $dtShow = ($dt !== '' && strtotime($dt) > 0) ? date('M j, Y', strtotime($dt)) : '';
          ?>
          <article class="pd-rev-card">
            <div class="pd-rev-head">
              <span class="pd-rev-stars"><?= str_repeat('★', $rt) . str_repeat('☆', 5 - $rt); ?></span>
              <?php if ($ttl !== ''): ?><h3 class="pd-rev-title"><?= htmlspecialchars($ttl, ENT_QUOTES, 'UTF-8'); ?></h3><?php endif; ?>
            </div>
            <div class="pd-rev-meta"><?= htmlspecialchars($who, ENT_QUOTES, 'UTF-8'); ?><?= $dtShow !== '' ? ' · ' . htmlspecialchars($dtShow, ENT_QUOTES, 'UTF-8') : ''; ?></div>
            <?php if ($det !== ''): ?><p class="pd-rev-body"><?= nl2br(htmlspecialchars($det, ENT_QUOTES, 'UTF-8')); ?></p><?php endif; ?>
          </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="pd-rev-empty"><?= (int) $reviews > 0
          ? 'Rating summary is available above, but no review text was returned. You can still write a review.'
          : 'No customer reviews yet.'; ?></p>
      <?php endif; ?>
      <?php if (!empty($productId)): ?>
        <p class="pd-rev-cta"><a href="<?= base_url('webshop/product_reviews/' . md5((int) $productId)); ?>" class="pd-rev-write">Write a review</a></p>
      <?php endif; ?>
    </div>
    <div class="pd-tab" id="t6"><p class="pd-rev-empty pd-rev-empty-tab">Need help? Contact support for FAQ and product guidance.</p></div>
  </div>

<?php // Technical Specifications data is available in $technical_specs array for API use but hidden from UI per request ?>

</div>
<script>window.GP_PD_CTX=<?= json_encode(array(
    'no_image_src'  => $noImgSrc,
    'base_url'      => base_url('webshop/'),
    'checkout_url'  => base_url('webshop/checkout'),
    'login_url'     => base_url('webshop/login'),
    'is_logged_in'  => (bool) $isLoggedIn,
    'product_id'    => (int) $productId,
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script defer src="<?= $pd_assets ?>gulfpharmacy_theme/js/theme-product-details.js"></script>
