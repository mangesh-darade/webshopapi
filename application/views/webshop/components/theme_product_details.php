<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
$themeVariant = isset($theme_variant) ? (string) $theme_variant : '';
$product = isset($product) && is_array($product) ? $product : array();
$galleryImages = isset($gallary_images) && is_array($gallary_images) ? $gallary_images : array();
$cartItems = isset($cart_items) && is_array($cart_items) ? $cart_items : array();
$categoriesList = isset($categories) ? $categories : array();
$uploadsBase = isset($uploads) ? (string) $uploads : '';
$thumbsBase = isset($thumbs) ? (string) $thumbs : '';
$isGulf = $themeVariant === 'gulfpharmacy';

$productId = isset($product['id']) ? (int) $product['id'] : 0;
$productCategoryId = isset($product['category_id']) ? (int) $product['category_id'] : 0;
$productName = isset($product['name']) ? (string) $product['name'] : '';
$productDescp = isset($product['product_details']) ? (string) $product['product_details'] : '';
$productPrice = isset($product['price']) ? (float) $product['price'] : 0;
$noImage = webshop_media_src($uploadsBase, 'no_image.png');
$productImage = !empty($product['image']) ? webshop_media_src($uploadsBase, $product['image']) : $noImage;
$formattedPrice = isset($this->sma) ? $this->sma->formatMoney($productPrice) : webshop_price_display($productPrice, isset($Settings) ? $Settings : null);
$productTaxRate = isset($product['tax_rate']) ? $product['tax_rate'] : 0;
$productTaxMethod = isset($product['tax_method']) ? $product['tax_method'] : 0;
$productPromoPrice = isset($product['promo_price']) ? $product['promo_price'] : 0;
$found = false;
$isIncartQuantity = false;
foreach ($cartItems as $item) {
    if (isset($item['product_id']) && (int) $item['product_id'] === $productId) {
        $isIncartQuantity = $item;
        $found = true;
        break;
    }
}

$productCF1 = !empty($product['cf1']) ? $product['cf1'] : '';
$productCF2 = !empty($product['cf2']) ? $product['cf2'] : '';
$productCF3 = !empty($product['cf3']) ? $product['cf3'] : '';

$productCategory = '';
if (!empty($categoriesList)) {
    foreach ($categoriesList as $category) {
        if (is_object($category) && isset($category->id) && (int) $category->id === $productCategoryId) {
            $productCategory = isset($category->name) ? (string) $category->name : '';
            break;
        }
    }
}
$hidePriceClass = ($productCategory === 'Softgel Capsules' || $productCategory === 'Veterinary Nutraceuticals') ? 'hide_price' : '';
?>
<div class="main product-pf">
    <div class="container">
        <meta itemprop="description" content="<?= htmlspecialchars($productDescp, ENT_QUOTES, 'UTF-8') ?>" />
        <div class="product-inner-pf">
            <div class="prod-img-head-div">
                <div class="heading">
                    <h1 itemprop="name"><?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?></h1>
                    <div class="productDescp"><?= $productDescp ?></div>
                </div>
                <div class="images">
                    <div class="slider-product-contain">
                        <div class="slider slider-product">
                            <?php if (!empty($galleryImages)): ?>
                                <?php foreach ($galleryImages as $image): ?>
                                    <?php $photo = isset($image['photo']) ? (string) $image['photo'] : ''; ?>
                                    <div><a href="<?= htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8') ?>"><img src="<?= htmlspecialchars(webshop_media_src($uploadsBase, $photo), ENT_QUOTES, 'UTF-8') ?>" class="img-responsive" title="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>" itemprop="image" /></a></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div><a href="<?= htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8') ?>"><img src="<?= htmlspecialchars($productImage, ENT_QUOTES, 'UTF-8') ?>" class="img-responsive" title="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>" itemprop="image" /></a></div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty($galleryImages)): ?>
                            <div class="thumbs">
                                <?php foreach ($galleryImages as $image): ?>
                                    <?php $photo = isset($image['photo']) ? (string) $image['photo'] : ''; ?>
                                    <a><img src="<?= htmlspecialchars($thumbsBase . $photo, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') ?>" class="img-responsive" /></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="prod-detail-div">
                <div class="purchase-options" id="purchase-options">
                    <form name="prodinfo" id="prodinfo">
                        <input type="hidden" name="addtocart">
                        <input type="hidden" name="additem" value="<?= htmlspecialchars((string) $productId, ENT_QUOTES, 'UTF-8') ?>">
                        <div itemprop="offers" itemscope itemtype="http://schema.org/Offer">
                            <meta itemprop="availability" content="InStock" />
                            <meta itemprop="priceCurrency" content="USD" />
                            <meta itemprop="price" content="<?= htmlspecialchars((string) $formattedPrice, ENT_QUOTES, 'UTF-8') ?>" />
                        </div>
                        <p class="price">
                            <span id="price-old"></span>
                            <span class="<?= $isGulf ? $hidePriceClass : '' ?>" id="price-current"><?= htmlspecialchars((string) $formattedPrice, ENT_QUOTES, 'UTF-8') ?></span>
                            <span id="price-savings"></span>
                        </p>
                        <div class="buy-qty" style="display:flex; margin-bottom:15px;">
                            <div style="display:flex; flex-grow:1; max-width:120px; gap:5px;">
                                <button type="button" class="btn btn-outline-secondary btn-decrease" style="width:35px; border:1px solid #cbcaca;">-</button>
                                <input name="txtquanto" oninput="this.value = (this.value < 0) ? '' : this.value;" type="number" min="1" value="<?= $found && isset($isIncartQuantity['quantity']) ? (int) $isIncartQuantity['quantity'] : 1 ?>" isincart="<?= $found ? '1' : '0' ?>" class="form-control itemQty" id="txtquanto" style="height:100%" onkeydown="event.preventDefault(); alert('Please use spinner arrows');" />
                                <button type="button" class="btn btn-outline-secondary btn-increase" style="width:35px; border:1px solid #cbcaca;">+</button>
                            </div>
                            <div style="padding-left:15px; flex-grow:3;">
                                <button class="btn btn-block add-to-cart" name="submit-form" type="submit" value="Add to Cart" product_id="<?= (int) $productId ?>" quantity="1" tax_rate="<?= htmlspecialchars((string) $productTaxRate, ENT_QUOTES, 'UTF-8') ?>" tax_method="<?= htmlspecialchars((string) $productTaxMethod, ENT_QUOTES, 'UTF-8') ?>" price="<?= htmlspecialchars((string) $productPrice, ENT_QUOTES, 'UTF-8') ?>" promotion_price="<?= htmlspecialchars((string) $productPromoPrice, ENT_QUOTES, 'UTF-8') ?>" product_price="<?= htmlspecialchars((string) $productPrice, ENT_QUOTES, 'UTF-8') ?>" product_desc="<?= htmlspecialchars((string) $productDescp, ENT_QUOTES, 'UTF-8') ?>" imageurl="<?= htmlspecialchars((string) $productImage, ENT_QUOTES, 'UTF-8') ?>" productname="<?= htmlspecialchars((string) $productName, ENT_QUOTES, 'UTF-8') ?>">Add to Cart</button>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="info-long">
                    <a id="product-details"></a> <a id="tabArea"></a>
                    <div id="product-tabs" class="product-tabs">
                        <ul class="product-tabs-nav">
                            <li><a href="#ProductDetail">Product Detail</a></li>
                            <?php if (!$isGulf): ?><li><a href="#UsageWarnings">Usage/Warnings</a></li><?php endif; ?>
                            <li><a href="#SupplementFacts">Supplement Facts</a></li>
                        </ul>
                        <?php if ($productCF1): ?><div id="ProductDetail"><div class="desc-pd"><?= $productCF1 ?></div></div><?php endif; ?>
                        <?php if (!$isGulf && $productCF2): ?><div id="UsageWarnings"><div class="desc-pd"><?= $productCF2 ?></div></div><?php endif; ?>
                        <?php if ($productCF3): ?><div id="SupplementFacts"><div class="desc-pd"><?= $productCF3 ?></div></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
