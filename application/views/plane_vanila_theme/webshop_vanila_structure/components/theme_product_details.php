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
$productVariants = isset($product_variants) && is_array($product_variants) ? $product_variants : array();
$_gpSettings = isset($Settings) ? $Settings : null;
$pdVariantsUi = function_exists('webshop_product_detail_variants_ui')
    ? webshop_product_detail_variants_ui($product, $productVariants, $_gpSettings)
    : array('has_variants' => false, 'items' => array(), 'default' => null);
$pdHasVariants = !empty($pdVariantsUi['has_variants']);
$pdDefaultVariant = $pdHasVariants && !empty($pdVariantsUi['default']) ? $pdVariantsUi['default'] : null;
$overselling = isset($webshop_settings) && is_object($webshop_settings) && !empty($webshop_settings->overselling);

if ($pdDefaultVariant) {
    $stockQty = (float) $pdDefaultVariant['quantity'];
    $pdInStock = !empty($pdDefaultVariant['in_stock']) || $overselling;
    $price = (float) $pdDefaultVariant['unit_price'];
    $promo = isset($pdDefaultVariant['promo_price']) ? (float) $pdDefaultVariant['promo_price'] : 0;
    $formattedPrice = (string) $pdDefaultVariant['formatted_price'];
    $formattedMrp = (string) $pdDefaultVariant['formatted_mrp'];
    $discountPercent = (int) $pdDefaultVariant['discount_percent'];
    $pdSelectedVariantId = (int) $pdDefaultVariant['id'];
    $pdSelectedVariantPrice = (float) $pdDefaultVariant['variant_price'];
    $pdSelectedVariantUq = (float) $pdDefaultVariant['unit_quantity'];
} else {
    $stockQty = webshop_product_display_sellable_qty($product, $productVariants);
    $pdInStock = $stockQty > 0;
    $price = isset($product['price']) ? (float) $product['price'] : 0;
    $mrp = isset($product['mrp']) ? (float) $product['mrp'] : 0;
    $promo = isset($product['promo_price']) ? (float) $product['promo_price'] : 0;
    if ($promo > 0 && $promo < $price) { $price = $promo; }
    $formattedPrice = isset($this->sma) ? $this->sma->formatMoney($price) : webshop_price_display($price, $_gpSettings);
    $formattedMrp = isset($mrp) && $mrp > 0 ? (isset($this->sma) ? $this->sma->formatMoney($mrp) : webshop_price_display($mrp, $_gpSettings)) : '';
    $discountPercent = (isset($mrp) && $mrp > $price && $price > 0) ? round((($mrp - $price) / $mrp) * 100) : 0;
    $pdSelectedVariantId = 0;
    $pdSelectedVariantPrice = 0.0;
    $pdSelectedVariantUq = 1.0;
}
$productTaxRate = isset($product['tax_rate']) ? $product['tax_rate'] : 0;
$productTaxMethod = isset($product['tax_method']) ? $product['tax_method'] : 0;
$isLoggedIn = function_exists('webshop_is_customer_logged_in')
    ? webshop_is_customer_logged_in()
    : (isset($this->session->webshop) && !empty($this->session->webshop->is_login) && !empty($this->session->webshop->user_id));
$pdInWishlist = false;
if ($isLoggedIn && $productId > 0 && function_exists('webshop_product_in_wishlist_lookup')) {
    $wl_map = function_exists('webshop_view_wishlist_lookup')
        ? webshop_view_wishlist_lookup(isset($wishlist_lookup) && is_array($wishlist_lookup) ? $wishlist_lookup : null)
        : (isset($wishlist_lookup) && is_array($wishlist_lookup) ? $wishlist_lookup : array());
    $pdInWishlist = webshop_product_in_wishlist_lookup($wl_map, $productId, $pdSelectedVariantId);
}
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
<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/theme-product-details.css?ver=20260526e') ?>">
<link rel="stylesheet" href="<?= webshop_theme_assets_url('css/wishlist-fav.css?ver=20260526e') ?>">

<div class="pd-wrap<?= $pdInStock ? '' : ' pd-wrap--oos'; ?>">
  <div class="pd-main">
    <div class="pd-card pd-gallery">
      <div class="pd-thumbs" id="pdThumbs">
        <?php foreach ($gallery as $i => $img) { ?>
          <div class="pd-thumb <?= $i === 0 ? 'active' : ''; ?>" data-full="<?= htmlspecialchars($img['full'], ENT_QUOTES, 'UTF-8'); ?>"><img loading="lazy" src="<?= htmlspecialchars($img['thumb'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>" onerror="this.onerror=null;this.src='<?= $noImgSrcAttr ?>';"></div>
        <?php } ?>
      </div>
      <div class="pd-mainimg" id="pdMain"><img id="pdMainImg" src="<?= htmlspecialchars($gallery[0]['full'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>" width="600" height="600" fetchpriority="high" decoding="sync" onerror="this.onerror=null;this.src='<?= $noImgSrcAttr ?>';"></div>
    </div>
    <div class="pd-card pd-summary">
      <div class="pd-head-row">
        <h1 class="pd-title"><?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if ($productId > 0): ?>
        <button type="button"
          class="gp-fav-btn pd-wish<?= $pdInWishlist ? ' is-saved' : '' ?>"
          id="pdWishlistBtn"
          data-in-wishlist="<?= $pdInWishlist ? '1' : '0' ?>"
          aria-pressed="<?= $pdInWishlist ? 'true' : 'false' ?>"
          aria-label="<?= $pdInWishlist ? 'Remove from favourites' : 'Save to favourites' ?>">
          <span class="gp-fav-btn__icon" aria-hidden="true"><?= $pdInWishlist ? '♥' : '♡' ?></span>
          <span class="gp-fav-btn__label"><?= $pdInWishlist ? 'Saved' : 'Save' ?></span>
        </button>
        <?php endif; ?>
      </div>
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
      <div class="pd-price"><span class="pd-price-now" id="price-current"><?= htmlspecialchars((string) $formattedPrice, ENT_QUOTES, 'UTF-8'); ?></span><?php if ($formattedMrp !== '') { ?><span class="pd-mrp" id="price-mrp"><?= htmlspecialchars((string) $formattedMrp, ENT_QUOTES, 'UTF-8'); ?></span><?php } ?><?php if ($discountPercent > 0) { ?><span class="pd-off" id="price-off"><?= (int) $discountPercent; ?>% OFF</span><?php } else { ?><span class="pd-off" id="price-off" style="display:none"></span><?php } ?></div>
      <div class="pd-stock <?= $pdInStock ? 'ok' : 'no'; ?>" id="pdStock"><?= $pdInStock ? 'In Stock' : 'Out of Stock'; ?></div>
      <?php if (!$pdInStock) { ?><p class="pd-unavailable-note" id="pdOosNote">This product cannot be added to the cart or purchased while it is out of stock.</p><?php } else { ?><p class="pd-unavailable-note" id="pdOosNote" style="display:none"></p><?php } ?>
      <?php if ($pdHasVariants && !empty($pdVariantsUi['items'])) { ?>
      <div class="pd-variants" id="pdVariants" role="group" aria-label="Product options">
        <div class="pd-variants-label">Select option</div>
        <div class="pd-variants-list">
          <?php foreach ($pdVariantsUi['items'] as $vi => $vRow) {
              $isFirst = ($vi === 0);
              $vInStock = !empty($vRow['in_stock']) || $overselling;
          ?>
          <button type="button"
            class="pd-variant-btn<?= $isFirst ? ' active' : ''; ?><?= $vInStock ? '' : ' pd-variant-btn--oos'; ?>"
            data-variant-id="<?= (int) $vRow['id']; ?>"
            data-variant-name="<?= htmlspecialchars((string) $vRow['name'], ENT_QUOTES, 'UTF-8'); ?>"
            data-variant-price="<?= htmlspecialchars((string) $vRow['variant_price'], ENT_QUOTES, 'UTF-8'); ?>"
            data-unit-quantity="<?= htmlspecialchars((string) $vRow['unit_quantity'], ENT_QUOTES, 'UTF-8'); ?>"
            data-quantity="<?= htmlspecialchars((string) $vRow['quantity'], ENT_QUOTES, 'UTF-8'); ?>"
            data-unit-price="<?= htmlspecialchars((string) $vRow['unit_price'], ENT_QUOTES, 'UTF-8'); ?>"
            data-promo-price="<?= htmlspecialchars((string) $vRow['promo_price'], ENT_QUOTES, 'UTF-8'); ?>"
            data-formatted-price="<?= htmlspecialchars((string) $vRow['formatted_price'], ENT_QUOTES, 'UTF-8'); ?>"
            data-formatted-mrp="<?= htmlspecialchars((string) $vRow['formatted_mrp'], ENT_QUOTES, 'UTF-8'); ?>"
            data-discount-percent="<?= (int) $vRow['discount_percent']; ?>"
            data-in-stock="<?= $vInStock ? '1' : '0'; ?>"
            aria-pressed="<?= $isFirst ? 'true' : 'false'; ?>">
            <span class="pd-variant-name"><?= htmlspecialchars((string) $vRow['name'], ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="pd-variant-price"><?= htmlspecialchars((string) $vRow['formatted_price'], ENT_QUOTES, 'UTF-8'); ?></span>
            <?php if (!$vInStock && !$overselling) { ?><span class="pd-variant-badge">Out of stock</span><?php } ?>
          </button>
          <?php } ?>
        </div>
      </div>
      <?php } ?>
      <div class="pd-short"><?= $productDescp !== '' ? $productDescp : '<span class="pd-short-empty">No short description available.</span>'; ?></div>
      <div class="pd-actions">
        <div class="pd-qty"><button type="button" id="qDec" <?= $pdInStock ? '' : 'disabled '; ?>>-</button><input id="qVal" class="itemQty" type="number" min="1" <?= $pdInStock ? 'max="' . (int) max(1, floor($stockQty)) . '"' : 'max="1"'; ?> value="1" <?= $pdInStock ? '' : 'disabled '; ?>><button type="button" id="qInc" <?= $pdInStock ? '' : 'disabled '; ?>>+</button></div>
        <button class="pd-btn pd-cart add-to-cart" <?= $pdInStock ? '' : 'disabled '; ?>product_id="<?= (int) $productId; ?>" quantity="1" tax_rate="<?= htmlspecialchars((string) $productTaxRate, ENT_QUOTES, 'UTF-8'); ?>" tax_method="<?= htmlspecialchars((string) $productTaxMethod, ENT_QUOTES, 'UTF-8'); ?>" price="<?= htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8'); ?>" promotion_price="<?= htmlspecialchars((string) $promo, ENT_QUOTES, 'UTF-8'); ?>" product_price="<?= htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8'); ?>" product_desc="<?= htmlspecialchars((string) strip_tags($productDescp), ENT_QUOTES, 'UTF-8'); ?>" imageurl="<?= htmlspecialchars((string) $gallery[0]['full'], ENT_QUOTES, 'UTF-8'); ?>" productname="<?= htmlspecialchars((string) $productName, ENT_QUOTES, 'UTF-8'); ?>" data-variant-id="<?= (int) $pdSelectedVariantId; ?>" data-variant-price="<?= htmlspecialchars((string) $pdSelectedVariantPrice, ENT_QUOTES, 'UTF-8'); ?>" data-variant-unit-quantity="<?= htmlspecialchars((string) $pdSelectedVariantUq, ENT_QUOTES, 'UTF-8'); ?>">Add To Cart</button>
        <button class="pd-btn pd-buy buy-now" type="button" <?= $pdInStock ? '' : 'disabled '; ?>>Buy Now</button>
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
<?php $_pd_csrf = function_exists('webshop_csrf_pair') ? webshop_csrf_pair() : array('name' => '', 'hash' => ''); ?>
<script>window.GP_CSRF=<?= json_encode($_pd_csrf, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="<?= webshop_theme_assets_url('js/webshop-csrf.js?ver=20260526c') ?>"></script>
<script>window.GP_PD_CTX=<?= json_encode(array(
    'no_image_src'        => $noImgSrc,
    'base_url'            => base_url('webshop/'),
    'webshop_request_url' => base_url('webshop/webshop_request'),
    'checkout_url'        => base_url('webshop/checkout'),
    'login_url'           => base_url('webshop/login'),
    'is_logged_in'        => (bool) $isLoggedIn,
    'product_id'          => (int) $productId,
    'in_stock'            => (bool) $pdInStock,
    'overselling'         => (bool) $overselling,
    'has_variants'        => (bool) $pdHasVariants,
    'variants'            => $pdHasVariants ? $pdVariantsUi['items'] : array(),
    'default_variant_id'  => (int) $pdSelectedVariantId,
    'wishlist_lookup'     => function_exists('webshop_view_wishlist_lookup')
        ? webshop_view_wishlist_lookup(isset($wishlist_lookup) && is_array($wishlist_lookup) ? $wishlist_lookup : null)
        : (isset($wishlist_lookup) && is_array($wishlist_lookup) ? $wishlist_lookup : array()),
), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script defer src="<?= webshop_theme_assets_url('js/theme-product-details.js?ver=20260526h') ?>"></script>
