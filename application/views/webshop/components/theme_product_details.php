<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$product = isset($product) && is_array($product) ? $product : array();
$galleryImages = isset($gallary_images) && is_array($gallary_images) ? $gallary_images : array();
$entityTagGroups = isset($entity_tag_groups) && is_array($entity_tag_groups) ? $entity_tag_groups : array();
$relatedProducts = isset($related_products) && is_array($related_products) ? $related_products : array();
$recentViewed = isset($recent_viewed) && is_array($recent_viewed) ? $recent_viewed : array();
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
$noImage = webshop_media_src($uploadsBase, 'no_image.png');
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
    $main = !empty($product['image']) ? webshop_media_src($uploadsBase, $product['image']) : $noImage;
    $gallery[] = array('full' => $main, 'thumb' => $main);
}
?>
<style>
.pd-wrap{font-family:Inter,sans-serif;max-width:1280px;margin:0 auto;padding:18px}
.pd-main{display:grid;grid-template-columns:1fr 1fr;gap:24px}
.pd-card{background:#fff;border:1px solid #e7edf5;border-radius:16px;box-shadow:0 8px 24px rgba(15,76,129,.08)}
.pd-gallery{padding:14px;display:grid;grid-template-columns:90px 1fr;gap:12px}
.pd-thumbs{display:flex;flex-direction:column;gap:8px;max-height:520px;overflow:auto}
.pd-thumb{border:2px solid transparent;border-radius:10px;padding:2px;cursor:pointer}
.pd-thumb.active,.pd-thumb:hover{border-color:#0F4C81}
.pd-thumb img{width:100%;height:72px;object-fit:contain;background:#F5F7FA;border-radius:8px}
.pd-mainimg{min-height:460px;background:#F5F7FA;border-radius:12px;display:flex;align-items:center;justify-content:center;overflow:hidden}
.pd-mainimg img{max-width:95%;max-height:430px;transition:.25s}
.pd-mainimg:hover img{transform:scale(1.14)}
.pd-summary{padding:22px;position:sticky;top:88px}
.pd-title{font-size:34px;font-weight:700;line-height:1.2;margin:0 0 8px}
.pd-meta{font-size:14px;color:#4b5563;display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px}
.pd-rating{color:#f59e0b;font-weight:700}
.pd-price{display:flex;gap:10px;align-items:center;flex-wrap:wrap}
.pd-price-now{font-size:32px;font-weight:600;color:#0F4C81}
.pd-mrp{text-decoration:line-through;color:#6b7280}
.pd-off{background:#00A884;color:#fff;border-radius:999px;padding:4px 9px;font-size:12px;font-weight:600}
.pd-stock{margin:10px 0;font-size:14px}
.pd-stock.ok{color:#0a7c3f}.pd-stock.no{color:#b91c1c}
.pd-short{font-size:16px;line-height:1.6;color:#374151;background:#F5F7FA;border-radius:10px;padding:12px}
.pd-actions{display:grid;grid-template-columns:130px 1fr 1fr 46px;gap:8px;margin-top:14px}
.pd-qty{display:flex;border:1px solid #d5dde8;border-radius:10px;overflow:hidden}
.pd-qty button{border:0;background:#f8fafc;width:34px}
.pd-qty input{border:0;text-align:center;width:60px}
.pd-btn{border:0;border-radius:10px;padding:11px 12px;color:#fff;font-size:15px;font-weight:600}
.pd-cart{background:#0F4C81}.pd-buy{background:#00A884}
.pd-wish{display:flex;align-items:center;justify-content:center;border:1px solid #d5dde8;border-radius:10px}
.pd-tabs{margin-top:26px}
.pd-tab-nav{display:flex;gap:8px;flex-wrap:wrap}
.pd-tab-link{padding:9px 14px;border-radius:999px;background:#f1f5f9;font-size:13px;font-weight:600;cursor:pointer}
.pd-tab-link.active{background:#0F4C81;color:#fff}
.pd-tab{display:none;margin-top:12px;border:1px solid #e6ecf4;border-radius:10px;padding:14px;line-height:1.7}
.pd-tab.active{display:block}
.pd-section{margin-top:28px}
.pd-list{display:flex;gap:12px;overflow:auto;padding-bottom:6px}
.pd-item{min-width:210px;border:1px solid #e8edf3;border-radius:12px;padding:10px;text-decoration:none;color:inherit}
.pd-item img{width:100%;height:140px;object-fit:contain;background:#f8fafc;border-radius:8px}
.pd-item h4{font-size:14px;height:38px;overflow:hidden}
.pd-item .p{font-weight:700;color:#0F4C81}
@media(max-width:992px){.pd-main{grid-template-columns:1fr}.pd-summary{position:static}.pd-gallery{grid-template-columns:1fr}.pd-thumbs{flex-direction:row;max-height:none}.pd-thumb img{width:70px;height:70px}}
</style>

<div class="pd-wrap">
  <div class="pd-main">
    <div class="pd-card pd-gallery">
      <div class="pd-thumbs" id="pdThumbs">
        <?php foreach ($gallery as $i => $img) { ?>
          <div class="pd-thumb <?= $i === 0 ? 'active' : ''; ?>" data-full="<?= htmlspecialchars($img['full'], ENT_QUOTES, 'UTF-8'); ?>"><img loading="lazy" src="<?= htmlspecialchars($img['thumb'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>"></div>
        <?php } ?>
      </div>
      <div class="pd-mainimg" id="pdMain"><img id="pdMainImg" src="<?= htmlspecialchars($gallery[0]['full'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?>"></div>
    </div>
    <div class="pd-card pd-summary">
      <h1 class="pd-title"><?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8'); ?></h1>
      <div class="pd-meta"><span class="pd-rating"><?= str_repeat('★', (int) round($rating)); ?> <?= number_format($rating, 1); ?></span><span>(<?= (int) $reviews; ?> reviews)</span><?php if ($brandName !== '') { ?><span>Brand: <strong><?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></strong></span><?php } ?></div>
      <div class="pd-price"><span class="pd-price-now" id="price-current"><?= htmlspecialchars((string) $formattedPrice, ENT_QUOTES, 'UTF-8'); ?></span><?php if ($formattedMrp !== '') { ?><span class="pd-mrp"><?= htmlspecialchars((string) $formattedMrp, ENT_QUOTES, 'UTF-8'); ?></span><?php } ?><?php if ($discountPercent > 0) { ?><span class="pd-off"><?= (int) $discountPercent; ?>% OFF</span><?php } ?></div>
      <div class="pd-stock <?= $stockQty > 0 ? 'ok' : 'no'; ?>"><?= $stockQty > 0 ? 'In Stock' : 'Out of Stock'; ?></div>
      <div class="pd-short"><?= $productDescp !== '' ? $productDescp : '#N/A'; ?></div>
      <div class="pd-actions">
        <div class="pd-qty"><button type="button" id="qDec">-</button><input id="qVal" class="itemQty" type="number" min="1" value="1"><button type="button" id="qInc">+</button></div>
        <button class="pd-btn pd-cart add-to-cart" product_id="<?= (int) $productId; ?>" quantity="1" tax_rate="<?= htmlspecialchars((string) $productTaxRate, ENT_QUOTES, 'UTF-8'); ?>" tax_method="<?= htmlspecialchars((string) $productTaxMethod, ENT_QUOTES, 'UTF-8'); ?>" price="<?= htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8'); ?>" promotion_price="<?= htmlspecialchars((string) $promo, ENT_QUOTES, 'UTF-8'); ?>" product_price="<?= htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8'); ?>" product_desc="<?= htmlspecialchars((string) strip_tags($productDescp), ENT_QUOTES, 'UTF-8'); ?>" imageurl="<?= htmlspecialchars((string) $gallery[0]['full'], ENT_QUOTES, 'UTF-8'); ?>" productname="<?= htmlspecialchars((string) $productName, ENT_QUOTES, 'UTF-8'); ?>">Add To Cart</button>
        <button class="pd-btn pd-buy" type="button">Buy Now</button>
        <div class="pd-wish">♡</div>
      </div>
    </div>
  </div>

  <div class="pd-tabs">
    <div class="pd-tab-nav" id="pdTabNav">
      <div class="pd-tab-link active" data-tab="t1">Description</div><div class="pd-tab-link" data-tab="t2">Supplement Facts</div><div class="pd-tab-link" data-tab="t3">Ingredients</div><div class="pd-tab-link" data-tab="t4">Usage</div><div class="pd-tab-link" data-tab="t5">Reviews</div><div class="pd-tab-link" data-tab="t6">FAQs</div>
    </div>
    <div class="pd-tab active" id="t1"><?= !empty($product['cf1']) ? $product['cf1'] : ($productDescp !== '' ? $productDescp : '#N/A'); ?></div>
    <div class="pd-tab" id="t2"><?= !empty($product['cf3']) ? $product['cf3'] : '#N/A'; ?></div>
    <div class="pd-tab" id="t3"><?= !empty($product['cf2']) ? $product['cf2'] : '#N/A'; ?></div>
    <div class="pd-tab" id="t4">Consult physician/pharmacist for personalized usage.</div>
    <div class="pd-tab" id="t5"><?= $reviews > 0 ? ('Rated ' . number_format($rating, 1) . '/5 by ' . $reviews . ' customers') : 'No reviews yet.'; ?></div>
    <div class="pd-tab" id="t6">Need help? Contact support for FAQ and product guidance.</div>
  </div>

  
</div>
<script>
(function(){var t=document.querySelectorAll('#pdThumbs .pd-thumb'),m=document.getElementById('pdMainImg');for(var i=0;i<t.length;i++){(function(ix){t[ix].addEventListener('click',function(){for(var j=0;j<t.length;j++)t[j].classList.remove('active');this.classList.add('active');m.style.opacity='0.25';var im=new Image();im.onload=function(){m.src=t[ix].getAttribute('data-full');m.style.opacity='1';};im.src=t[ix].getAttribute('data-full');});})(i);}var q=document.getElementById('qVal');document.getElementById('qInc').onclick=function(){q.value=parseInt(q.value||'1',10)+1;};document.getElementById('qDec').onclick=function(){var v=parseInt(q.value||'1',10);q.value=v>1?v-1:1;};function tabs(id){var n=document.getElementById(id);if(!n)return;var l=n.querySelectorAll('.pd-tab-link');for(var i=0;i<l.length;i++){l[i].onclick=function(){for(var j=0;j<l.length;j++)l[j].classList.remove('active');this.classList.add('active');var all=document.querySelectorAll('.pd-tab');for(var k=0;k<all.length;k++)all[k].classList.remove('active');var p=document.getElementById(this.getAttribute('data-tab'));if(p)p.classList.add('active');};}}tabs('pdTabNav');})();
</script>
