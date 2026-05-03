<header id="masthead" class="site-header header-v1" style="background-image: none; border-bottom: thin solid #dfdfdf; ">
    <div class="col-full desktop-only">
        <div class="techmarket-sticky-wrap">
            <div class="row mb-1">
                <div class="site-branding col-4">
                    <?php
                    $logo_file = (!empty($webshop_settings->logo)) ? basename((string) $webshop_settings->logo) : 'logo.png';
                    ?>
                    <a href="<?= base_url('webshop/index')?>" class="custom-logo-link" rel="home">
                        <img src="<?= $uploads ?>logos/<?= htmlspecialchars($logo_file, ENT_QUOTES, 'UTF-8') ?>" class="img" alt="<?= isset($store_display_name) ? htmlspecialchars($store_display_name, ENT_QUOTES, 'UTF-8') : 'Store' ?>" />
                    </a>
                    <!-- /.custom-logo-link -->
                </div>
                <!-- /.site-branding -->
                <!-- ============================================================= End Header Logo ============================================================= -->

                <?php include_once('header_searchbar_cart_menu.php'); ?>

            </div>
            <!-- /.row -->
        </div>
        <!-- .techmarket-sticky-wrap -->
        <div class="row align-items-center">

            <?php include_once('header_department_menu.php'); ?>

            <?php include_once('header_menus.php'); ?>

        </div>
        <!-- /.row -->
    </div>
    <!-- .col-full -->

    <?php include_once('header_mobile.php'); ?>

</header>
<!-- .header-v3 -->
<!-- ============================================================= Header End ============================================================= -->