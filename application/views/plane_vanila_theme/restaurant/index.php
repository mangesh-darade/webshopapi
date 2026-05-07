<?php defined('BASEPATH') OR exit('No direct script access allowed'); ?>
<?php
/**
 * Premium Dynamic Restaurant Theme (CMS Driven)
 * PHP 5.6 Compatible
 */
$isDynamicCmsPage = !empty($is_dynamic_cms_page);
$cmsHeaderSectionsHtml = isset($cms_header_sections_html) ? (string) $cms_header_sections_html : '';
$cmsFooterSectionsHtml = isset($cms_footer_sections_html) ? (string) $cms_footer_sections_html : '';
$uploads = isset($uploads) ? $uploads : '';
$bannerSrc = isset($page_banner_image_url) && trim((string) $page_banner_image_url) !== '' ? webshop_media_src($uploads, (string) $page_banner_image_url) : '';
$logoSrc = isset($page_logo_image_url) && trim((string) $page_logo_image_url) !== '' ? webshop_media_src($uploads, (string) $page_logo_image_url) : '';

// Resolve body content
$bodyContent = '';
if (!empty($home_section_html_block)) {
    $bodyContent = webshop_normalize_html_media_urls((string) $home_section_html_block, $uploads);
} elseif (isset($home_page_cms) && is_object($home_page_cms) && !empty($home_page_cms->page_text)) {
    $bodyContent = webshop_normalize_html_media_urls((string) $home_page_cms->page_text, $uploads);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo !empty($page_title) ? html_escape($page_title) : 'Premium Dining'; ?></title>
    <?php if (!empty($meta_tags)) : ?>
        <?php echo $meta_tags; ?>
    <?php endif; ?>
    
    <!-- Modern Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&family=Playfair+Display:ital,wght@0,400;0,700;1,400&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary: #fa8507;
            --primary-dark: #e67700;
            --secondary: #1a1a1a;
            --accent: #fdf2f2;
            --text-main: #2d3748;
            --text-muted: #718096;
            --bg-body: #fff9f5;
            --shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
        }

        * { box-sizing: border-box; }
        body { 
            margin: 0; 
            font-family: 'Outfit', sans-serif; 
            color: var(--text-main); 
            background: var(--bg-body); 
            line-height: 1.6;
        }

        h1, h2, h3, h4 { font-family: 'Playfair+Display', serif; font-weight: 700; color: var(--secondary); margin-top: 0; }

        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 0 24px; 
        }

        /* Hero / Header Overlay Area */
        .hero-section {
            position: relative;
            background: var(--secondary);
            color: #fff;
            padding: 80px 0;
            overflow: hidden;
            border-radius: 0 0 40px 40px;
            margin-bottom: 40px;
        }

        .hero-bg {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            object-fit: cover;
            opacity: 0.4;
            filter: blur(2px);
        }

        .hero-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .hero-logo {
            max-height: 100px;
            width: auto;
            margin-bottom: 24px;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));
        }

        .hero-title {
            font-size: 3.5rem;
            margin-bottom: 16px;
            color: #fff;
            letter-spacing: -1px;
        }

        /* Dynamic CMS Slots */
        .cms-slot { margin-bottom: 32px; }
        
        .main-content {
            padding: 40px 0;
            background: #fff;
            border-radius: 32px;
            box-shadow: var(--shadow);
            margin-top: -80px;
            position: relative;
            z-index: 10;
        }

        /* Premium Section Headers */
        .section-header {
            text-align: center;
            margin-bottom: 48px;
        }
        
        .section-header h2 {
            font-size: 2.5rem;
            position: relative;
            display: inline-block;
            padding-bottom: 12px;
        }

        .section-header h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: var(--primary);
            border-radius: 2px;
        }

        /* Footer Stylings */
        footer {
            background: var(--secondary);
            color: #cbd5e0;
            padding: 60px 0 40px;
            margin-top: 80px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero-title { font-size: 2.5rem; }
            .hero-section { padding: 60px 0 100px; }
            .main-content { margin-top: -60px; }
        }

        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .animate-fade {
            animation: fadeIn 0.8s ease forwards;
        }

        /* Adjust Component Styles for Restaurant */
        .gp-component { margin-bottom: 60px; }
        .cms-pg-title { text-align: center; font-family: 'Playfair+Display', serif; font-size: 2.5rem !important; margin-bottom: 40px !important; }
        .gp-product-card { border: none !important; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .gp-add-to-cart-btn { background: var(--primary) !important; }
        .gp-add-to-cart-btn:hover { background: var(--primary-dark) !important; }
    </style>
</head>
<body>

    <!-- Dynamic Header Slot -->
    <?php if ($isDynamicCmsPage && !empty($home_has_header_section) && trim($cmsHeaderSectionsHtml) !== '') : ?>
        <div class="cms-slot"><?php echo $cmsHeaderSectionsHtml; ?></div>
    <?php endif; ?>

    <section class="hero-section">
        <?php if ($bannerSrc !== '') : ?>
            <img src="<?php echo html_escape($bannerSrc); ?>" class="hero-bg" alt="Dining Background">
        <?php else: ?>
            <div class="hero-bg" style="background: linear-gradient(135deg, #1a1a1a 0%, #fa8507 100%);"></div>
        <?php endif; ?>
        
        <div class="container hero-content animate-fade">
            <?php if ($logoSrc !== '') : ?>
                <img class="hero-logo" src="<?php echo html_escape($logoSrc); ?>" alt="Restaurant Logo">
            <?php endif; ?>
            
            <h1 class="hero-title"><?php echo !empty($page_title) ? html_escape($page_title) : 'Exquisite Dining Experience'; ?></h1>
            
            <?php if ($isDynamicCmsPage && !empty($home_page_cms->page_summary)): ?>
                <p style="font-size: 1.2rem; max-width: 700px; margin: 0 auto; color: #e2e8f0;">
                    <?php echo html_escape($home_page_cms->page_summary); ?>
                </p>
            <?php endif; ?>
        </div>
    </section>

    <main class="main-content container animate-fade">
        <?php if ($bodyContent !== ''): ?>
            <div class="cms-body-render">
                <?php echo $bodyContent; ?>
            </div>
        <?php else: ?>
            <div class="section-header">
                <h2>Our Menu</h2>
                <p>Select from our handcrafted specialties</p>
            </div>
            
            <!-- Default placeholder if no CMS content -->
            <div style="text-align:center; padding: 40px; color: var(--text-muted);">
                <p>Experience the finest flavors delivered to your doorstep.</p>
                <a href="<?php echo base_url('webshop/products'); ?>" style="display:inline-block; background: var(--primary); color:#fff; padding: 12px 32px; border-radius: 50px; text-decoration:none; font-weight:700; margin-top:20px;">Explore Menu</a>
            </div>
        <?php endif; ?>
    </main>

    <!-- Dynamic Footer Slot -->
    <?php if ($isDynamicCmsPage && !empty($home_has_footer_section) && trim($cmsFooterSectionsHtml) !== '') : ?>
        <div class="cms-slot"><?php echo $cmsFooterSectionsHtml; ?></div>
    <?php endif; ?>

    <footer>
        <div class="container">
            <div style="display:flex; justify-content: space-between; flex-wrap: wrap; gap: 40px;">
                <div style="flex: 1; min-width: 250px;">
                    <h3 style="color:#fff; margin-bottom: 20px;">About Us</h3>
                    <p>Creating memorable culinary moments since 1996. Our commitment to quality and taste defines every dish we serve.</p>
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <h3 style="color:#fff; margin-bottom: 20px;">Quick Links</h3>
                    <ul style="list-style:none; padding:0;">
                        <li><a href="<?php echo base_url('webshop'); ?>" style="color:inherit; text-decoration:none;">Home</a></li>
                        <li><a href="<?php echo base_url('webshop/products'); ?>" style="color:inherit; text-decoration:none;">Menu</a></li>
                        <li><a href="<?php echo base_url('webshop/cart'); ?>" style="color:inherit; text-decoration:none;">My Cart</a></li>
                    </ul>
                </div>
                <div style="flex: 1; min-width: 250px;">
                    <h3 style="color:#fff; margin-bottom: 20px;">Newsletter</h3>
                    <p>Subscribe for exclusive offers and events.</p>
                    <div style="display:flex; margin-top: 15px;">
                        <input type="email" placeholder="Email address" style="padding: 10px 16px; border-radius: 50px 0 0 50px; border:none; flex:1;">
                        <button style="background: var(--primary); color:#fff; border:none; padding: 10px 24px; border-radius: 0 50px 50px 0; cursor:pointer;">Join</button>
                    </div>
                </div>
            </div>
            <div style="margin-top: 60px; padding-top: 20px; border-top: 1px solid #4a5568; text-align:center; font-size: 0.9rem;">
                &copy; <?php echo date('Y'); ?> <?php echo isset($Settings->site_name) ? $Settings->site_name : 'Restaurant'; ?>. All rights reserved.
            </div>
        </div>
    </footer>

</body>
</html>
