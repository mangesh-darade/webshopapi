<?php
$cart_items = isset($cart_items) && is_array($cart_items) ? $cart_items : [];
$cartCount = "";
if (count($cart_items)) {
    $cartCount = count($cart_items);
}
$categoriesArray = isset($this->data["categories"]) && is_array($this->data["categories"]) ? $this->data["categories"] : ['main' => []];
$activeCategoryId = isset($this->data['get_category_id']) ? $this->data['get_category_id'] : null;
$webshopSession = (isset($this->session->webshop) && is_object($this->session->webshop)) ? $this->session->webshop : (object) ['is_login' => "false", 'name' => 'Guest'];
$isLoggedIn = (isset($webshopSession->is_login) && $webshopSession->is_login == "true");
?>
<header>
    <style>
        @media (max-width: 768px) {

        .highlight-nav-option {
            border-left: 4px solid #428B00;
            background: #f4fbf1;
            font-weight: bold;
            color: #428B00 !important;
        }
    }
        ul.dropdown-menu.nav-categories.show {
        margin-top: -25px !important;
    }
        /* ===== Desktop Default ===== */
    .Mobile-logo {
        display: none;
    }

/* ===== Mobile View ===== */
@media (max-width: 820px) {

    /* Show Mobile Logo */
    .Mobile-logo {
        display: flex;
        justify-content: left;
    }

    .Mobile-logo img {
        height: 60px !important;
        width: auto !important;
        margin-top: 0 !important;
    }

    /* Hide Desktop Logo inside nav */
    nav > a:first-child {
        display: none !important;
    }

}

/* ===== Desktop View ===== */
@media (min-width: 821px) {

    /* Show Desktop Logo */
    nav > a:first-child {
        display: block !important;
    }

}

    </style>
    <div class="header-inner">
        <?php $logo_image = 'webshop/default_banner.png';
        $ws = isset($this->data['website_setting']) ? $this->data['website_setting'] : array();
        if (is_array($ws) || (is_object($ws) && $ws instanceof Traversable)) {
            foreach ($ws as $item) {
                if (is_object($item) && isset($item->fields) && $item->fields === 'logo_image' && !empty($item->value)) {
                    $logo_image = $item->value;
                    break;
                }
            }
        }
        ?>
        <div class="Mobile-logo">
            <a href="<?= base_url('webshop') ?>">
            <img src="<?= webshop_media_src($uploads, $logo_image) ?>" alt="Logo" style="height: 120px; width: 125px; margin-top: -44px;"></a>
        </div>
        <nav>
            <a href="<?= base_url('webshop') ?>">
                <img src="<?= webshop_media_src($uploads, $logo_image) ?>" alt="Logo" style="height: 120px; width: 125px; margin-top: -44px;">
            </a>


            <a href="#" class="close-nav nav-opt"><i class="fa fa-close"></i> Close</a>
            <a id="home-nav" href="<?= base_url('webshop') ?>" class="nav-opt">Home</a>
            <?php if (is_array($categoriesArray) && isset($categoriesArray["main"]) && is_array($categoriesArray["main"])) { ?>
                <div class="dropdown nav-pd-ddm">
                    <button class="btn btn-secondary dropdown-toggle nav-btn-prods product_details_nav category_products_nav" type="button" data-bs-toggle="dropdown" aria-expanded="false" class="nav-opt">
                        Products
                        <i class="fa fa-caret-down"></i>
                    </button>
                    
                    <ul class="dropdown-menu nav-categories">
                        <?php $categories = $categoriesArray["main"];
                        foreach ($categories as $category) {
                            if (!is_object($category)) {
                                $category = is_array($category) ? (object) $category : (object) array();
                            }
                            $cid = isset($category->id) ? $category->id : '';
                            $cname = isset($category->name) ? $category->name : '';
                            ?>
                            <li><a class="dropdown-item <?= ($cid == $activeCategoryId) ? 'highlight-nav-option' : '' ?>"
                            href="<?= base_url('webshop/category_products/' . $cid) ?>"><?= htmlspecialchars((string) $cname) ?></a></li>
                        <?php } ?>
                    </ul>
                </div>
            <?php }
            ?>
            <a id="about-us-nav" href="<?= base_url('webshop/about_us') ?>" class="nav-opt">About Us</a>
            <?php if (!empty($has_active_blogs)): ?>
                <a id="blogs-nav" href="<?= base_url('webshop/blogs') ?>" class="nav-opt">Blogs</a>
            <?php endif; ?>
            <a id="contact-us-nav" href="<?= base_url('webshop/contact_us') ?>" class="nav-opt">Contact Us</a>
            <?php if (!empty($header_theme_pages) && is_array($header_theme_pages)): ?>
                <?php foreach ($header_theme_pages as $themePage): ?>
                    <?php
                    if (is_scalar($themePage) || $themePage === null) {
                        continue;
                    }
                    $tpSlug = is_array($themePage) ? (isset($themePage['slug']) ? $themePage['slug'] : '') : (is_object($themePage) && isset($themePage->slug) ? $themePage->slug : '');
                    $tpTitle = is_array($themePage) ? (isset($themePage['title']) ? $themePage['title'] : '') : (is_object($themePage) && isset($themePage->title) ? $themePage->title : '');
                    if ($tpSlug !== '') :
                    ?>
                    <a href="<?= base_url('webshop/' . $tpSlug) ?>" class="nav-opt"><?= htmlspecialchars((string) $tpTitle) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php /* Removed executable PHP inside HTML comment — was running and accessing session when webshop was not an object */ ?>
            <!-- <a href="#">Specials</a>
            <a href="#">Product Finder</a>
            <a href="#">Health News</a> -->
        </nav>
        <a class="show show-nav"><img src="<?= $assets ?>gulfpharmacy_theme/images/nav-icon-menu.svg" alt="Menu"></a>
        <!-- <a class="show show-search"><img src="<?= $assets ?>gulfpharmacy_theme/images/nav-icon-search.svg" alt="Search"></a> -->
        <?php
        if ($isLoggedIn) {
            $navUserName = (is_object($webshopSession) && isset($webshopSession->name) && (string) $webshopSession->name !== '')
                ? preg_split('/\s+/', trim((string) $webshopSession->name), 2)[0]
                : 'User';
            ?>
            <a href="<?= base_url('webshop/your_account') ?>" class="show-user"><img src="<?= $assets . 'restaurant/img/my_profile_icon.svg' ?>" /><?= htmlspecialchars($navUserName) ?></a>
        <?php }
        ?>
        <a class="show show-my-account" style="top: 18px;" href="<?= $isLoggedIn ? base_url('webshop/logout') : base_url('webshop/login') ?>"><?= $isLoggedIn ? "Logout" : "Login" ?></a>
        <a class="show show-cart" href="<?= base_url('webshop/cart') ?>">
            <div><img src="<?= $assets ?>gulfpharmacy_theme/images/nav-icon-cart.svg" alt="View Cart"><?= $cartCount ? "<span>$cartCount</span>" : "" ?></div>
        </a>
        <div class="cart">
            <h5>Cart</h5>
            <div class="cart-inner">
                <p>There are no items in your cart.</p>
            </div>
        </div><!--/cart-->
        <!-- <div class="search-contain">
            <form action="#">
                <div class="input-group">
                    <div class="input-group-addon"><i class="fa fa-search"></i></div>
                    <input type="text" class="form-control input-medium search-query ui-autocomplete-input" name="q" placeholder="Enter search term" id="search-term" autocomplete="off">
                    <span class="input-group-btn">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </span>
                </div>
                <ul id="ui-id-1" tabindex="0" class="ui-menu ui-widget ui-widget-content ui-autocomplete ui-front" style="display: none;"></ul>
            </form>
        </div> -->
        <!--/search-contain-->
    </div><!--/header-inner-->
</header>