<?php

/**
 * Build image URL: POS paths are relative to uploads base; some payloads return absolute URLs.
 * ElintOm/CMS may still emit legacy …/assets/uploads/… — those are mapped onto the mdata uploads
 * root ($uploads_base), i.e. …/assets/mdata/{host}/uploads/…
 *
 * @param string $uploads_base From Webshop_api_model::get_media_uploads_base() / view $uploads
 * @param string $path         Relative path or http(s) URL
 */
function webshop_media_src($uploads_base, $path) {
    if ($path === null || $path === '') {
        return '';
    }
    $s = trim((string) $path);
    if ($s === '') {
        return '';
    }

    // Must come from Webshop_api_model::get_media_uploads_base() (dynamic mdata host root).
    $base = rtrim(str_replace('\\', '/', (string) $uploads_base), '/') . '/';
    $base_path = (string) parse_url($base, PHP_URL_PATH);
    $base_mdata_folder = '';
    if ($base_path !== '' && preg_match('#/assets/mdata/([^/]+)/uploads/?$#i', str_replace('\\', '/', $base_path), $bm) && isset($bm[1])) {
        $base_mdata_folder = (string) $bm[1];
    }

    $query = '';
    $path_part = $s;
    if (preg_match('#^https?://#i', $s)) {
        $parsed = @parse_url($s);
        $path_part = isset($parsed['path']) ? (string) $parsed['path'] : '';
        $query = !empty($parsed['query']) ? '?' . (string) $parsed['query'] : '';
    }

    $path_part = str_replace('\\', '/', (string) $path_part);
    if ($path_part === '') {
        return '';
    }

    // Absolute path variants.
    if (preg_match('#/assets/mdata/[^/]+/uploads/(.+)$#i', $path_part, $m) && isset($m[1]) && trim($m[1]) !== '') {
        $path_part = $m[1];
    } elseif (preg_match('#/assets/uploads/(.+)$#i', $path_part, $m) && isset($m[1]) && trim($m[1]) !== '') {
        $path_part = $m[1];
    } else {
        // Relative path variants.
        $path_part = preg_replace('#^/?assets/mdata/[^/]+/uploads/#i', '', $path_part);
        $path_part = preg_replace('#^/?assets/uploads/#i', '', $path_part);
        $path_part = preg_replace('#^/?uploads/#i', '', $path_part);
        // Some records store "{hostOrTenant}/uploads/file.jpg".
        if ($base_mdata_folder !== '') {
            $quoted = preg_quote($base_mdata_folder, '#');
            $path_part = preg_replace('#^/?' . $quoted . '/uploads/#i', '', $path_part);
        }
        // Fallback for any "<segment>/uploads/file.jpg".
        $path_part = preg_replace('#^/?[^/]+/uploads/#i', '', $path_part);
    }
    return $base . ltrim($path_part, '/') . $query;
}

/**
 * Normalize CMS HTML media links to the current uploads base.
 * Useful when API/CMS body contains hardcoded /assets/uploads/... URLs.
 *
 * @param string $html
 * @param string $uploads_base
 * @return string
 */
function webshop_normalize_html_media_urls($html, $uploads_base) {
        
    if (!is_string($html) || trim($html) === '') {
        return (string) $html;
    }
    $base = rtrim(str_replace('\\', '/', (string) $uploads_base), '/') . '/';
    $map_tail = function ($tail) use ($base) {
        return $base . ltrim(str_replace('\\', '/', (string) $tail), '/');
    };

    $out = $html;
    $out = preg_replace_callback(
        '#https?://[^"\'\s)]+/assets/uploads/([^"\'\s)]+)#i',
        function ($m) use ($map_tail) { return $map_tail($m[1]); },
        $out
    );
    $out = preg_replace_callback(
        '#https?://[^"\'\s)]+/assets/mdata/[^/]+/uploads/([^"\'\s)]+)#i',
        function ($m) use ($map_tail) { return $map_tail($m[1]); },
        $out
    );
    $out = preg_replace_callback(
        '#(?<=["\'\(=])/?assets/uploads/([^"\'\s)]+)#i',
        function ($m) use ($map_tail) { return $map_tail($m[1]); },
        $out
    );
    $out = preg_replace_callback(
        '#(?<=["\'\(=])/?assets/mdata/[^/]+/uploads/([^"\'\s)]+)#i',
        function ($m) use ($map_tail) { return $map_tail($m[1]); },
        $out
    );

    // Add a safe fallback for broken CMS image URLs (missing files on disk).
    $fallback_src = htmlspecialchars(
        webshop_no_image_src($uploads_base, rtrim((string) $uploads_base, '/') . '/thumbs/'),
        ENT_QUOTES,
        'UTF-8'
    );
    $out = preg_replace_callback(
        '#<img\b[^>]*>#i',
        function ($m) use ($fallback_src) {
            $tag = $m[0];
            if (preg_match('/\bonerror\s*=/i', $tag)) {
                return $tag;
            }
            return preg_replace(
                '#\s*/?>$#',
                ' onerror="this.onerror=null;this.src=\'' . $fallback_src . '\';"$0',
                $tag
            );
        },
        $out
    );
    return $out;
}

/**
 * Currency symbol from POS/API settings ($this->data['Settings']).
 *
 * @param object|null $Settings
 * @return string
 */
function webshop_currency_symbol($Settings) {
    if (is_object($Settings) && isset($Settings->symbol) && trim((string) $Settings->symbol) !== '') {
        return (string) $Settings->symbol;
    }
    return '$';
}

/**
 * Format a positive storefront price using the same rules as Sma::formatMoney (symbol position,
 * Indian/SAC grouping when enabled, separators). Returns empty string when amount is not positive.
 *
 * @param float|int|string|null $amount
 * @param object|null           $Settings Passed through for fallback only; live requests use CI + sma.
 * @return string
 */
function webshop_price_display($amount, $Settings) {
    $n = isset($amount) ? (float) $amount : 0;
    if ($n <= 0) {
        return '';
    }
    $CI = function_exists('get_instance') ? get_instance() : null;
    if ($CI && isset($CI->sma) && is_object($CI->sma) && method_exists($CI->sma, 'formatMoney')) {
        return $CI->sma->formatMoney($n);
    }
    return webshop_price_display_fallback($n, $Settings);
}

/**
 * Mirrors Sma::formatMoney when the Sma library is unavailable (symbol prefix/suffix via display_symbol).
 *
 * @param float              $n
 * @param object|null        $Settings
 * @return string
 */
function webshop_price_display_fallback($n, $Settings) {
    $S = is_object($Settings) ? $Settings : new stdClass();
    $decimals = isset($S->decimals) ? max(0, min(8, (int) $S->decimals)) : 2;
    $ds = isset($S->decimals_sep) ? (string) $S->decimals_sep : '.';
    $ts_raw = isset($S->thousands_sep) ? (string) $S->thousands_sep : ',';
    $ts = ($ts_raw === '0') ? ' ' : $ts_raw;
    $sym = isset($S->symbol) && trim((string) $S->symbol) !== '' ? (string) $S->symbol : '$';
    $disp = isset($S->display_symbol) ? (int) $S->display_symbol : 1;
    $formatted = number_format((float) $n, $decimals, $ds, $ts);
    if ($disp === 0) {
        return $formatted;
    }
    if ($disp === 2) {
        return $formatted . $sym;
    }
    return $sym . $formatted;
}

/**
 * Fallback when thumbs/uploads have no product or category photo (tries standard paths, then SVG data URI).
 *
 * @param string $uploads_base
 * @param string $thumbs_base  Usually …/uploads/thumbs/
 * @return string
 */
function webshop_no_image_src($uploads_base, $thumbs_base = '') {
    $try = array();
    if ($thumbs_base !== null && $thumbs_base !== '') {
        $try[] = webshop_media_src($thumbs_base, 'no_image.png');
    }
    if ($uploads_base !== null && $uploads_base !== '') {
        $try[] = webshop_media_src($uploads_base, 'thumbs/no_image.png');
        $try[] = webshop_media_src($uploads_base, 'no_image.png');
    }
    foreach ($try as $u) {
        if ($u !== '') {
            return $u;
        }
    }
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="180" viewBox="0 0 180 180"><rect fill="#f1f5f9" width="180" height="180" rx="12"/><path fill="#e2e8f0" d="M52 58h76v48H52z"/><circle cx="64" cy="54" r="7" fill="#cbd5e1"/><path fill="#cbd5e1" d="M44 122h92v10H44z"/><text x="90" y="108" text-anchor="middle" fill="#64748b" font-family="system-ui,sans-serif" font-size="12">No image</text></svg>';
    return 'data:image/svg+xml;charset=UTF-8,' . rawurlencode($svg);
}

/**
 * @param string               $uploads_base
 * @param string               $thumbs_base
 * @param array|object         $row
 * @return string
 */
function webshop_product_image_src($uploads_base, $thumbs_base, $row) {
    $row = is_array($row) ? $row : (array) $row;
    $img = '';
    if (!empty($row['image']) && trim((string) $row['image']) !== '') {
        $img = trim((string) $row['image']);
    } elseif (!empty($row['photo']) && trim((string) $row['photo']) !== '') {
        $img = trim((string) $row['photo']);
    }
    if ($img !== '') {
        return webshop_media_src($uploads_base, $img);
    }
    return webshop_no_image_src($uploads_base, $thumbs_base);
}

/**
 * First non-empty category image path from API/DB row (same field order as product: image, photo, then common aliases).
 *
 * @param array|object $row
 * @return string Relative path or absolute URL; empty if none.
 */
function webshop_category_primary_image_path($row) {
    $keys = array('image', 'photo', 'category_image', 'categoryImage', 'thumb', 'thumbnail', 'picture', 'icon', 'logo');
    if (is_object($row)) {
        foreach ($keys as $k) {
            if (isset($row->$k)) {
                $s = trim((string) $row->$k);
                if ($s !== '') {
                    return $s;
                }
            }
        }
        return '';
    }
    if (!is_array($row)) {
        return '';
    }
    foreach ($keys as $k) {
        if (!empty($row[$k]) && trim((string) $row[$k]) !== '') {
            return trim((string) $row[$k]);
        }
    }
    return '';
}

/**
 * @param string               $uploads_base
 * @param string               $thumbs_base
 * @param array|object         $row
 * @return string
 */
function webshop_category_image_src($uploads_base, $thumbs_base, $row) {
    $img = webshop_category_primary_image_path($row);
    if ($img !== '') {
        return webshop_media_src($uploads_base, $img);
    }
    return webshop_no_image_src($uploads_base, $thumbs_base);
}

function product_sale_price( $productData=[], $variant_price = NULL, $discount = NULL) {

    $data['promo_price'] = 0;

    if ($productData['promotion']) {

        $now = strtotime(date('Y-m-d H:i:s'));

        $start_date = !empty($productData['start_date']) ? strtotime($productData['start_date']) : '';
        $end_date   = !empty($productData['end_date']) ? strtotime($productData['end_date']) : '';

        if (!empty($start_date) && $start_date <= $now && $end_date >= $now) {
            $data['promo_price'] = $productData['promo_price'];
        }
    }

    if (is_array($variant_price)) {
        $price = (float) $variant_price[1] + (float) $productData['price'];
    } else {
        $price = (isset($productData['variant_price'])) ? ((float) $productData['variant_price'] + (float) $productData['price']) : $productData['price'];
    }

    $data['real_unit_price'] = $price;
    $data['discount_rate'] = 0;
    $data['unit_discount'] = 0;
    $pr_discount = 0;
    if ($discount != NULL) {
        $dpos = strpos($discount, '%');
        if ($dpos !== false) {
            $pds = explode("%", $discount);
            //Note : unitprice is product and variant price. Real unit price is actual product price. if we taken realunitprice then grandtotal and discount calculate wrong becuase real unit price not included variant price. so now taken unit_price.(28-03-2020)
            $pr_discount = ( ( (Float) $price * (Float) $pds[0] ) / 100);
        } else {
            $pr_discount = $discount;
        }

        $price = $price - $pr_discount;
        $data['discount_rate'] = $discount;
        $data['unit_discount'] = $pr_discount;
    } else if ($data['promo_price'] > 0 && (int) $data['promo_price'] < $price) {

        $discount_amount = $price - $data['promo_price'];
        $data['discount_rate'] = $discount_amount;
        $data['unit_discount'] = $discount_amount;
        $price = $data['promo_price'];
    }

    $data['tax_rate'] = $productData['tax_rate'] . '%';
    $data['tax_method'] = $productData['tax_method'];

    if ($productData['tax_rate']) {
        if ($productData['tax_method'] == 1) {

            $unit_tax = ($price * (float) $productData['tax_rate'] / 100 );

            $data['unit_tax'] = $unit_tax;
            $data['net_unit_price'] = $price;

            $data['unit_price'] = ((float) $price + $unit_tax);
        } else {

            $unit_tax = (($price * (float) $productData['tax_rate']) / (100 + (float) $productData['tax_rate']));

            $data['unit_tax'] = $unit_tax;
            $data['net_unit_price'] = $price - $unit_tax;
            $data['unit_price'] = $price;
        }
    } else {

        $unit_tax = 0;
        $data['unit_tax'] = $unit_tax;
        $data['net_unit_price'] = $price - $unit_tax;
        $data['unit_price'] = $price;
    }

    return $data;
}
function product_sale_price_webshop( $productData=[], $variant_price = NULL, $discount = NULL, $unit_quantity = NULL) {

    $data['promo_price'] = 0;

    if ($productData['promotion']) {

        $now = strtotime(date('Y-m-d H:i:s'));

        $start_date = !empty($productData['start_date']) ? strtotime($productData['start_date']) : '';
        $end_date   = !empty($productData['end_date']) ? strtotime($productData['end_date']) : '';

        if (!empty($start_date) && $start_date <= $now && $end_date >= $now) {
            $data['promo_price'] = $productData['promo_price'];
        }
    }

    if (is_array($variant_price)) {
        $price = (float) $variant_price[1] + (float) $productData['price'];
    } else {
        $price = (isset($productData['variant_price'])) ? ((float) $productData['variant_price'] + (float) $productData['price']) : $productData['price'];
    }

    $data['real_unit_price'] = $price;
    $data['discount_rate'] = 0;
    $data['unit_discount'] = 0;
    $pr_discount = 0;
    if ($discount != NULL) {
        $dpos = strpos($discount, '%');
        if ($dpos !== false) {
            $pds = explode("%", $discount);
            //Note : unitprice is product and variant price. Real unit price is actual product price. if we taken realunitprice then grandtotal and discount calculate wrong becuase real unit price not included variant price. so now taken unit_price.(28-03-2020)
            $pr_discount = ( ( (Float) $price * (Float) $pds[0] ) / 100)/$unit_quantity;
        } else {
            $pr_discount = $discount/$unit_quantity;
        }

        $price = $price - $pr_discount;
        // $price = $price/$pr_discount;
        $data['discount_rate'] = $discount;
        $data['unit_discount'] = $pr_discount * $unit_quantity;
    } else if ($data['promo_price'] > 0 && (int) $data['promo_price'] < $price) {

        $discount_amount = $price - $data['promo_price'];
        $data['discount_rate'] = $discount_amount;
        $data['unit_discount'] = $discount_amount;
        $price = $data['promo_price'];
    }

    $data['tax_rate'] = $productData['tax_rate'] . '%';
    $data['tax_method'] = $productData['tax_method'];

    if ($productData['tax_rate']) {
        if ($productData['tax_method'] == 1) {

            $unit_tax = ($price * (float) $productData['tax_rate'] / 100 );

            $data['unit_tax'] = $unit_tax;
            $data['net_unit_price'] = $price;
            // $data['unit_price'] = ((float) $price + $unit_tax);
            $data['unit_price'] = $price;
        } else {

            $unit_tax = (($price * (float) $productData['tax_rate']) / (100 + (float) $productData['tax_rate']));

            $data['unit_tax'] = $unit_tax;
            // $data['net_unit_price'] = $price - $unit_tax;
            $data['net_unit_price'] = $price;
            $data['unit_price'] = $price;
        }
    } else {

        $unit_tax = 0;
        $data['unit_tax'] = $unit_tax;
        // $data['net_unit_price'] = $price - $unit_tax;
        $data['net_unit_price'] = $price;
        $data['unit_price'] = $price;
    }

    return $data;
}
///////////////////////////////////////////////////////////////////////////////////
// #Format: Y-m-d H:i:s 		=> 	#output: 2012-03-24 17:45:12
// #Format: Y-m-d h:i A			=> 	#output: 2012-03-24 05:45 PM
// #Format: d/m/Y H:i:s 		=> 	#output: 24/03/2012 17:45:12
// #Format: d/m/Y                       => 	#output: 24/03/2012
// #Format: g:i A 			=> 	#output: 5:45 PM
// #Format: h:ia 			=> 	#output: 05:45pm
// #Format: g:ia \o\n l jS F Y          => 	#output: 5:45pm on Saturday 24th March 2012
// #Format: l jS F Y 			=> 	#output: Saturday 24th March 2012
// #Format: D jS M Y 			=> 	#output: Sat 24th Mar 2012
// #Format: jS F Y g:ia			=> 	#output: 24th March 2012 5:45pm
// #Format: j F Y			=> 	#output: 24 March 2012
// #Format: j M y			=> 	#output: 24 Mar 12
// #Format: F j				=> 	#output: March 24 
// #Format: F Y				=> 	#output: March 2012
/////////////////////////////////////////////////////////////////////////////////////
function Date_Time_Format($dateTime, $dateFormat = 'jS M Y') {
    $date = date_create($dateTime);

    $newDateFormat = date_format($date, $dateFormat);

    $newDateFormat = str_replace('th ', '<sup>th </sup>', $newDateFormat);
    $newDateFormat = str_replace('1st ', '1<sup>st </sup>', $newDateFormat);
    $newDateFormat = str_replace('nd ', '<sup>nd </sup>', $newDateFormat);
    $newDateFormat = str_replace('rd ', '<sup>rd </sup>', $newDateFormat);

    return $newDateFormat;
}

function get_brands($ids = NULL) {

    $CI = CI();
    if (!isset($CI->db)) {
        return false;
    }
    $CI->db->select('id, code, name, image');
    if ($ids) {
        $CI->db->where_in('id', $ids);
    }
    $q = $CI->db->get('brands');

    if ($q && is_object($q) && $q->num_rows() > 0) {

        foreach ($q->result() as $row) {
            $data[] = $row;
        }

        return $data;
    }

    return false;
}

function baseurl($uri){
    $uri = ltrim((string) $uri, '/');
    $CI = get_instance();
    $configured = '';
    if ($CI && isset($CI->config)) {
        $configured = (string) $CI->config->base_url();
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
    $script_name = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';

    $script_dir = trim(str_replace('\\', '/', dirname($script_name)), '/');
    $candidate_dir = $script_dir;

    // Fallback: if runtime script points to another app, infer app root from current URI.
    if ($request_uri !== '') {
        $path = (string) parse_url($request_uri, PHP_URL_PATH);
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        if (!empty($segments) && (empty($candidate_dir) || strcasecmp($candidate_dir, $segments[0]) !== 0)) {
            $candidate_dir = $segments[0];
        }
    }

    if ($host !== '') {
        $root = $protocol . $host . '/';
        if ($candidate_dir !== '') {
            $root .= trim($candidate_dir, '/') . '/';
        }
        return $root . $uri;
    }

    return rtrim($configured, '/') . '/' . $uri;
}

function create_products_structure($productsArray, $imagePath='', $sectionKey = null, $display=null ){
    
    if(is_array($productsArray)) {
        $p=0;
        $productStructure = '';
        
        foreach ($productsArray as $product) {
                        
            $p++;
          //  if($p > 24) { break; } //Maximum 24 Products Display In Tab Container
            $product_hash = md5($product['id'].$sectionKey);           

            $variant_options = '';
            if(isset($product['variants'])){
                $v=0;
                foreach ($product['variants'] as $variant) {
                    $v++;
                    $variant_options           .= '<option value="'.$variant['id'].'" price="'.($variant['price']).'" unit_quantity="'.$variant['unit_quantity'].'" quantity="'.$variant['quantity'].'" title="'.$variant['name']. '" class="attached enabled text-capitalize">'.$variant['name']. '</option>';              
                    $variant_quantity[$v]       = $variant['quantity'];
                    $variant_name[$v]           = $variant['name'];
                    $variant_price[$v]          = $variant['price'];
                    $variant_unit_quantity[$v]  = $variant['unit_quantity'];                        
                    $variant_id[$v]             = $variant['id'];     

                    if($v==1){
                        $product_variants = $variant;
                        $product_name = $product['name'] .' (<span class="variant_name_'.$product_hash.'">'.$variant['name'].'</span>)';
                    }
                }
                $item_quantity = $variant_quantity[1];

            } else {
                $variant_name[1]        = '';
                $variant_price[1]       = 0;
                $variant_quantity[1]    = 0;
                $item_quantity          = $product['quantity'];
                $product_name           = $product['name'];
                $product_variants       = false;
            }

            
            //Set Overselling Condition.
            $item_quantity = $webshop_settings->overselling ? 999 : $item_quantity;
            
            //Webshop helper function
            $product_price = product_sale_price($product, $variant_price);

            $promo_price = $product_price['promo_price'] ? $product_price['promo_price'] : FALSE;

            $sale_price = $promo_price ? $promo_price : $product_price['unit_price'];
            
            $product['promo_price'] = $promo_price;
            $product['unit_price']  = $sale_price;
            
        $productStructure .= '<div class="product product_'.$product_hash.' '.$active.'_tab_product"  style="'. ($display == true?'':'display:none;') .'" >
                    <div class="yith-wcwl-add-to-wishlist">
                        <a style="cursor:pointer; font-size:20px; float:right; margin-right:10px;" class="addtowishlist" product_hash="'.$product_hash.'"><i class="tm tm-favorites"></i></a>
                    </div>
                    <a href="'.baseurl("webshop/product_details/").md5($product['id']).'" class="woocommerce-LoopProduct-link">
                        <div style="height:200px;">
                            <img src="'.$imagePath.$product['image'].'" style="max-height:190px;" class="wp-post-image" alt="'.$product['code'].'">
                        </div>
                        <span class="price">';
       
                         if($promo_price && (int)$promo_price < $product_price['unit_price']) {
        $productStructure.= '<del class="text-danger" style="margin-right:15px;">
                                <span class="amount">Rs. '.number_format($product_price['unit_price'],2).'</span>
                            </del>';
                         }
        $productStructure.= ' <ins>
                                <span class="amount"> </span>
                            </ins>
                            <span class="amount">Rs. <span id="display_unit_price_'.$product_hash.'">'.number_format($sale_price,2).'</span></span> <br/>
                            <span class="mrp"> MRP : '.$Settings->symbol.' '. number_format($product['mrp'],2).'</span>
                        </span>
                        <!-- /.price -->
                        <h2 class="woocommerce-loop-product__title">'.$product_name.'</h2>
                    </a>
                    <div class="hover-area">';                                    

            if($variant_options){

             $productStructure.= '<div class="value">
                            <select class="form-control" name="product_variants['.$product['id'].']" id="product_variants_'.$product_hash.'" onchange="update_price_by_variants(\''.$product_hash.'\')">
                            '.$variant_options .'
                            </select>
                            <a href="#" class="reset_variations" style="visibility: hidden;">Clear</a>
                        </div>';
            }

            $productStructure.= product_hidden_fields($product, $product_hash, $product_variants);
         $posSettings = pos_settings();
        if($item_quantity) {                   
        $productStructure.= '<big class="text-danger btn_outofstock_'.$product_hash.'" style="display: none;" >Out Of Stock</big>
                            <button class="button add_to_cart_button form-control btn_addtocart_'.$product_hash.'" rel="nofollow" onclick="add_to_cart(\''.$product_hash.'\')" >Add to cart</button>';
        } else { 
 if($posSettings->eshop_overselling){
                   $productStructure.= '<big class="text-danger btn_outofstock_'.$product_hash.'" style="display: none;" >Out Of Stock</big>
                            <button class="button add_to_cart_button form-control btn_addtocart_'.$product_hash.'" rel="nofollow" onclick="add_to_cart(\''.$product_hash.'\')" >Add to cart</button>';
     
            }else{
        $productStructure.= '<big class="text-danger btn_outofstock_'.$product_hash.'" >Out Of Stock</big>
                            <button class="button add_to_cart_button form-control btn_addtocart_'.$product_hash.'" rel="nofollow" style="display: none;" onclick="add_to_cart(\''.$product_hash.'\')" >Add to cart</button>';
           }
        }

        $productStructure.= '</div>
                </div>
                <!-- .product -->';
  
        }//end foreach.
        
        return $productStructure;
        
    }//end if
}


function product_hidden_fields($product, $pr_hash='', $variant = false) {
        
    $product_hash = $pr_hash ? '_'.$pr_hash : '';

    $hiddenValues = '<input type="hidden" value="1" name="quantity['.$product['id'].']" id="quantity'.$product_hash.'">
                    <input type="hidden" value="'.$product['tax_rate'].'" name="tax_rate['.$product['id'].']" id="tax_rate'.$product_hash.'">
                    <input type="hidden" value="'.$product['tax_method'].'" name="tax_method['.$product['id'].']" id="tax_method'.$product_hash.'">
                    <input type="hidden" value="'.$product['price'].'" name="price['.$product['id'].']" id="price'.$product_hash.'">
                    <input type="hidden" value="'.$product['id'].'" name="product_id['.$product['id'].']" id="product_id'.$product_hash.'">
                    <input type="hidden" value="'.$product['unit_price'].'" name="unit_price['.$product['id'].']" id="unit_price'.$product_hash.'">
                    <input type="hidden" value="'.$product['promo_price'].'" name="promotion_price['.$product['id'].']" id="promotion_price'.$product_hash.'">';

    $hiddenValues .= '<input type="hidden" value="'.(isset($variant['unit_quantity']) ? $variant['unit_quantity']:1) .'" name="variant_unit_quantity['.$product['id'].']" id="variant_unit_quantity'.$product_hash.'">';
    $hiddenValues .= '<input type="hidden" value="'.(isset($variant['id']) ? $variant['id']:0).'" name="variant_id['.$product['id'].']" id="variant_id'.$product_hash.'" >';
    $hiddenValues .= '<input type="hidden" value="'.(isset($variant['price']) ? $variant['price']:0).'" name="variant_unit_price['.$product['id'].']" id="variant_unit_price'.$product_hash.'">';
         
    return $hiddenValues;
}
    

if (!function_exists('CI')) {

    function CI() {

        $CI = & get_instance(); // making instance of CI

        return $CI; // Its returning an object for CI class
    }

}
if (!function_exists('latest_Products')) {
    function latest_Products($imagePath){
       $CI = CI();
       if (!isset($CI->db)) {
           return '';
       }
       $latestP = $CI->db->limit(6)->order_by('id','DESC')->get('sma_products')->result();
       $Settings = isset($CI->Settings) ? $CI->Settings : (object) array('symbol' => '');
    
       $latestProductsStructure = '';
       foreach($latestP as $items){

            $variants =   product_variants($items->id);
          $price = $items->price;
          if($variants){
              foreach($variants as $variant_price){
                 if(round($variant_price['price'])){
                    $price = $variant_price['price'];
                     break;
                 } 
              }
          }else{
              $price = $items->price;
          }
         
           $latestProductsStructure.='<div class="landscape-product-widget product">';
                $latestProductsStructure.='<a class="woocommerce-LoopProduct-link" href="'.baseurl("webshop/product_details/").md5($items->id).'">';
                     $latestProductsStructure.='<div class="media d-flex ">';
                        $latestProductsStructure.='<img class="wp-post-image m-0" src="'.$imagePath.$items->image.'" alt="'.$items->name.'">';
                        $latestProductsStructure.='<div class="media-body">';
                            $latestProductsStructure.='<span class="price">';
                                $latestProductsStructure.='<ins>';
                                     $latestProductsStructure.='<span class="amount"> '.$Settings->symbol.number_format($price,2).'</span>';
                                $latestProductsStructure.='</ins>';
                                 $latestProductsStructure.='<br/><ins>';
                                     $latestProductsStructure.='<span class="amount"> MRP : '.$Settings->symbol.' '.number_format($items->mrp,2).'</span>';
                                $latestProductsStructure.='</ins>';
//                                $latestProductsStructure.='<del>';
//                                     $latestProductsStructure.='<span class="amount">26.99</span>';
//                                $latestProductsStructure.='</del>';
                            $latestProductsStructure.='</span>';

                            $latestProductsStructure.='<h2 class="woocommerce-loop-product__title">'.$items->name.'</h2>';
                            $latestProductsStructure.='<div class="techmarket-product-rating">';
                                $latestProductsStructure.='<div title="Rated 0 out of 5" class="star-rating">';
                                     $latestProductsStructure.='<span style="width:0%">';
                                     $latestProductsStructure.='<strong class="rating">0</strong> out of 5</span>';
                                $latestProductsStructure.='</div>';
                                $latestProductsStructure.='<span class="review-count">(0)</span>';
                            $latestProductsStructure.='</div>';
                        
                        $latestProductsStructure.='</div>';
                     $latestProductsStructure.='</div>';
                $latestProductsStructure.='</a>';
           $latestProductsStructure.='</div>';
       }
       
       return $latestProductsStructure;
      
    }
}



if (!function_exists('rupeeFormat')) {  
 function rupeeFormat($number, $decimal=2, $prefix='&#x20B9;') {
      
       return $prefix.'&nbsp;'.number_format($number, $decimal, ".", ",");    
    }
}



if(!function_exists('product_variants')){
    function product_variants($product_id = NULL){
         $CI = CI();
         if (!isset($CI->db)) {
             return false;
         }
         $q = $CI->db->where('product_id', $product_id)->order_by('price', 'asc')->get('product_variants');

        if ($q && is_object($q) && $q->num_rows() > 0) {
            foreach ($q->result() as $row) {
                $data[] = (array) $row;
            }
            return $data;
        }
        return false;
    }
}


if(!function_exists('pos_settings')){
    function pos_settings(){
       $CI = CI();
       /* DB-less storefront: settings come from MY_Controller + Webshop_api_model (ElintOm getsettings API). */
       if (!isset($CI->db)) {
           if (isset($CI->webshop_model) && is_object($CI->webshop_model) && method_exists($CI->webshop_model, 'get_webshop_pos_settings')) {
               return $CI->webshop_model->get_webshop_pos_settings();
           }
           $row = new stdClass();
           $row->default_eshop_warehouse = '0';
           $row->default_eshop_biller = '0';
           $row->eshop_overselling = '0';
           $row->eshop_active = (isset($CI->Settings->active_webshop) ? (int) $CI->Settings->active_webshop : 1);
           return $row;
       }
       $q = $CI->db->select('default_eshop_warehouse, default_eshop_biller, eshop_overselling,eshop_active')->get('pos_settings');
    
         if ($q && is_object($q) && $q->num_rows() > 0) {
            return $q->row();
        }
        return FALSE; 
    }
}