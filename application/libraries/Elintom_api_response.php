<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Stateless parsers for ElintOm Webshop_api / api3 JSON shapes.
 * Used by Webshop_api_model only — keeps the model focused on HTTP orchestration.
 */
class Elintom_api_response {

    /** ElintOm may return status as Success / SUCCESS */
    public function api_status_ok($res) {
        return $res && isset($res->status) && strtoupper((string) $res->status) === 'SUCCESS';
    }

    /** Normalize json_decode shape so root is always stdClass (geo endpoints sometimes return arrays). */
    public function coerce_geo_api_root($res) {
        if ($res !== null && is_array($res)) {
            return json_decode(json_encode($res));
        }
        return $res;
    }

    /**
     * True if geo call is usable: SUCCESS, or a non-empty states/countries list is present (some handlers omit status).
     */
    public function api_geo_payload_usable($res, $plural) {
        $res = $this->coerce_geo_api_root($res);
        if (!$res || !is_object($res)) {
            return false;
        }
        if ($this->api_status_ok($res)) {
            return true;
        }
        $raw = $this->unwrap_geo_list_from_api_response($res, $plural);
        if ($raw === null) {
            return false;
        }
        if ($raw instanceof Traversable) {
            $raw = iterator_to_array($raw);
        } elseif (is_object($raw)) {
            $raw = (array) $raw;
        }
        return is_array($raw) && count($raw) > 0;
    }

    /** @return int */
    public function categories_main_count(array $tree) {
        return isset($tree['main']) && is_array($tree['main']) ? count($tree['main']) : 0;
    }

    /**
     * @param object|null $res decoded JSON from getcategories
     * @return mixed Raw subtree for normalize_categories_payload, or null
     */
    public function unwrap_categories_from_api_response($res) {
        if (!$res || !is_object($res)) {
            return null;
        }
        if (isset($res->allcategories)) {
            return $res->allcategories;
        }
        if (isset($res->category)) {
            $c = $res->category;
            if (is_string($c) && $c !== '') {
                $decoded = json_decode($c, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
            return $c;
        }
        if (isset($res->categories)) {
            return $res->categories;
        }
        if (isset($res->data)) {
            return $res->data;
        }
        if (isset($res->result)) {
            $r = $res->result;
            if (is_object($r)) {
                if (isset($r->categories)) {
                    return $r->categories;
                }
                if (isset($r->main) && is_array($r->main)) {
                    return $r;
                }
                if (isset($r->items)) {
                    return $r->items;
                }
                return $r;
            }
            if (is_array($r)) {
                if (isset($r['categories'])) {
                    return $r['categories'];
                }
                if (isset($r['main']) && is_array($r['main'])) {
                    return $r;
                }
                if ($this->is_list_array($r)) {
                    return $r;
                }
            }
        }
        if (isset($res->items)) {
            return $res->items;
        }
        return null;
    }

    /** Map API field aliases onto Webshop_model-style category rows (id, parent_id). */
    public function coerce_category_id_on_object($obj) {
        if (!is_object($obj)) {
            return;
        }
        if (!isset($obj->id) || $obj->id === '' || $obj->id === null) {
            if (isset($obj->category_id) && $obj->category_id !== '' && $obj->category_id !== null) {
                $obj->id = $obj->category_id;
            } elseif (isset($obj->CategoryID) && $obj->CategoryID !== '' && $obj->CategoryID !== null) {
                $obj->id = $obj->CategoryID;
            } elseif (isset($obj->cat_id) && $obj->cat_id !== '' && $obj->cat_id !== null) {
                $obj->id = $obj->cat_id;
            }
        }
        if (!isset($obj->parent_id)) {
            if (isset($obj->parentId)) {
                $obj->parent_id = $obj->parentId;
            } elseif (isset($obj->ParentID)) {
                $obj->parent_id = $obj->ParentID;
            } elseif (isset($obj->parent_category_id)) {
                $obj->parent_id = $obj->parent_category_id;
            }
        }
    }

    /**
     * Map API image aliases onto Webshop_model-style `image` (matches product rows: image + photo).
     */
    public function coerce_category_image_on_object($obj) {
        if (!is_object($obj)) {
            return;
        }
        $has = function ($o, $prop) {
            return isset($o->$prop) && trim((string) $o->$prop) !== '';
        };
        if ($has($obj, 'image')) {
            return;
        }
        foreach (array('photo', 'category_image', 'categoryImage', 'thumb', 'thumbnail', 'picture', 'icon', 'logo') as $p) {
            if ($has($obj, $p)) {
                $obj->image = $obj->$p;
                return;
            }
        }
    }

    /**
     * Map API description aliases onto sma_categories field names for storefront views.
     *
     * @param object $obj
     */
    public function coerce_category_descriptions_on_object($obj) {
        if (!is_object($obj)) {
            return;
        }
        $has = function ($o, $prop) {
            return isset($o->$prop) && trim((string) $o->$prop) !== '';
        };
        if (!$has($obj, 'short_description')) {
            foreach (array('ShortDescription', 'short_desc', 'excerpt', 'summary', 'tagline') as $p) {
                if ($has($obj, $p)) {
                    $obj->short_description = trim((string) $obj->$p);
                    break;
                }
            }
        }
        if (!$has($obj, 'long_description')) {
            foreach (array('LongDescription', 'long_desc', 'description', 'details', 'body') as $p) {
                if ($has($obj, $p)) {
                    $obj->long_description = trim((string) $obj->$p);
                    break;
                }
            }
        }
    }

    /**
     * True when category row should appear on the storefront (matches Webshop_model::get_categories() filters).
     * If in_eshop / is_active are absent, the row is kept for backward compatibility with older payloads.
     *
     * @param object $obj
     * @return bool
     */
    private function category_row_visible_for_webshop($obj) {
        if (isset($obj->in_eshop) && (int) $obj->in_eshop !== 1) {
            return false;
        }
        if (isset($obj->is_active) && (int) $obj->is_active !== 1) {
            return false;
        }
        return true;
    }

    /**
     * Flatten category rows from main, parent-id buckets, or a legacy list.
     *
     * @param array $categoriesRaw
     * @return array
     */
    /**
     * JSON objects under categories.main decode as stdClass; treat as a list for normalization.
     *
     * @param mixed $bucket
     * @return array
     */
    private function category_bucket_to_list($bucket) {
        if ($bucket === null) {
            return array();
        }
        if (is_object($bucket)) {
            $bucket = (array) $bucket;
        }
        return is_array($bucket) ? $bucket : array();
    }

    private function collect_category_rows_for_normalization(array $categoriesRaw) {
        $rows = array();
        $seen = array();

        $push = function ($row) use (&$rows, &$seen) {
            if (!is_array($row) && !is_object($row)) {
                return;
            }
            $probe = is_object($row) ? $row : (object) $row;
            $this->coerce_category_id_on_object($probe);
            if (!isset($probe->id) || $probe->id === '' || $probe->id === null) {
                return;
            }
            $k = is_numeric($probe->id) ? (string) (int) $probe->id : (string) $probe->id;
            if (isset($seen[$k])) {
                return;
            }
            $seen[$k] = true;
            $rows[] = $row;
        };

        if ($this->is_list_array($categoriesRaw)) {
            foreach ($categoriesRaw as $row) {
                $push($row);
            }
            return $rows;
        }

        if (isset($categoriesRaw['main'])) {
            foreach ($this->category_bucket_to_list($categoriesRaw['main']) as $row) {
                $push($row);
            }
        }

        foreach ($categoriesRaw as $k => $v) {
            if ($k === 'main' || $k === 'status' || $k === 'message') {
                continue;
            }
            if (!is_array($v) && !is_object($v)) {
                continue;
            }
            $isParentBucket = is_int($k) || (is_string($k) && ctype_digit($k));
            if (!$isParentBucket) {
                continue;
            }
            foreach ($this->category_bucket_to_list($v) as $row) {
                $push($row);
            }
        }

        return $rows;
    }

    /**
     * Build the same tree shape as Webshop_model::get_categories(): main + parent_id buckets.
     *
     * @param mixed $categoriesRaw API categories payload (object/array/list)
     * @return array
     */
    public function normalize_categories_payload($categoriesRaw) {
        $data = array('main' => array());
        if ($categoriesRaw === null) {
            return $data;
        }
        if (is_object($categoriesRaw)) {
            $categoriesRaw = (array) $categoriesRaw;
        }
        if (!is_array($categoriesRaw)) {
            return $data;
        }

        $rows = $this->collect_category_rows_for_normalization($categoriesRaw);

        foreach ($rows as $row) {
            $obj = is_object($row) ? $row : (object) $row;
            $this->coerce_category_id_on_object($obj);
            $this->coerce_category_image_on_object($obj);
            $this->coerce_category_descriptions_on_object($obj);
            if (!isset($obj->id) || $obj->id === '' || $obj->id === null) {
                continue;
            }
            if (!$this->category_row_visible_for_webshop($obj)) {
                continue;
            }
            $parent_id = isset($obj->parent_id) ? (int) $obj->parent_id : 0;
            if (!isset($obj->categoryActive)) {
                $obj->categoryActive = 'true';
            }
            if (!isset($obj->categoryInfoText)) {
                $obj->categoryInfoText = array('All*');
            }
            $idKey = is_numeric($obj->id) ? (int) $obj->id : $obj->id;
            if ($parent_id > 0) {
                if (!isset($data[$parent_id])) {
                    $data[$parent_id] = array();
                }
                $data[$parent_id][$idKey] = $obj;
            } else {
                $data['main'][$idKey] = $obj;
            }
        }

        foreach (array_keys($data) as $pk) {
            if ($pk === 'main') {
                continue;
            }
            if (!is_int($pk) && !(is_string($pk) && ctype_digit((string) $pk))) {
                unset($data[$pk]);
                continue;
            }
            $pid = (int) $pk;
            if (!isset($data['main'][$pid])) {
                unset($data[$pk]);
            }
        }

        // Only true parent rows (parent_id === 0) belong in `main`; never promote subcategories.
        if (empty($data['main']) && !empty($rows)) {
            foreach ($rows as $row) {
                $obj = is_object($row) ? $row : (object) $row;
                $this->coerce_category_id_on_object($obj);
                $this->coerce_category_image_on_object($obj);
                $this->coerce_category_descriptions_on_object($obj);
                if (!isset($obj->id) || $obj->id === '' || $obj->id === null) {
                    continue;
                }
                if (!$this->category_row_visible_for_webshop($obj)) {
                    continue;
                }
                $parent_id = isset($obj->parent_id) ? (int) $obj->parent_id : 0;
                if ($parent_id !== 0) {
                    continue;
                }
                if (!isset($obj->categoryActive)) {
                    $obj->categoryActive = 'true';
                }
                if (!isset($obj->categoryInfoText)) {
                    $obj->categoryInfoText = array('All*');
                }
                $idKey = is_numeric($obj->id) ? (int) $obj->id : $obj->id;
                $data['main'][$idKey] = $obj;
            }
        }

        return $data;
    }

    public function is_list_array(array $a) {
        if ($a === array()) {
            return true;
        }
        return array_keys($a) === range(0, count($a) - 1);
    }

    /**
     * @param mixed $result
     * @param int   $page
     * @return array
     */
    public function normalize_products_list_payload($result, $page = 1) {
        $empty = array('items' => array(), 'items_total' => 0, 'page' => $page);
        if ($result === null || $result === false) {
            return $empty;
        }
        if (is_object($result)) {
            $result = (array) $result;
        }
        if (!is_array($result)) {
            return $empty;
        }
        if (isset($result['items']) && is_array($result['items'])) {
            if (!isset($result['items_total'])) {
                $result['items_total'] = count($result['items']);
            }
            if (!isset($result['page'])) {
                $result['page'] = $page;
            }
            return $this->normalize_product_items_to_assoc($result);
        }
        if (isset($result['products']) && is_array($result['products'])) {
            $items = $result['products'];
            $out = array(
                'items'       => $items,
                'items_total' => isset($result['items_total']) ? (int) $result['items_total'] : count($items),
                'page'        => isset($result['page']) ? $result['page'] : $page,
            );
            return $this->normalize_product_items_to_assoc($out);
        }
        if ($this->is_list_array($result)) {
            return $this->normalize_product_items_to_assoc(array(
                'items'       => $result,
                'items_total' => count($result),
                'page'        => $page,
            ));
        }
        // ElintOm Webshop_model::get_products_list(use_hash=false) may nest rows under subcategory_id keys
        // (including "" when subcategory_id is null).
        $numericBuckets = array();
        $reservedKeys = array('page', 'items_total', 'items', 'products', 'status', 'msg');
        foreach ($result as $k => $v) {
            if (in_array($k, $reservedKeys, true)) {
                continue;
            }
            if (!is_array($v)) {
                continue;
            }
            $isBucket = is_numeric($k) || $k === '' || $k === '0';
            if (!$isBucket) {
                continue;
            }
            foreach ($v as $row) {
                if (is_array($row) || is_object($row)) {
                    $numericBuckets[] = $row;
                }
            }
        }
        if ($numericBuckets !== array()) {
            $itemsTotal = isset($result['items_total']) ? (int) $result['items_total'] : count($numericBuckets);
            return $this->normalize_product_items_to_assoc(array(
                'items'       => $numericBuckets,
                'items_total' => $itemsTotal > 0 ? $itemsTotal : count($numericBuckets),
                'page'        => isset($result['page']) ? $result['page'] : $page,
            ));
        }
        return $empty;
    }

    /** @return int */
    public function products_list_item_count(array $normalized) {
        return isset($normalized['items']) && is_array($normalized['items']) ? count($normalized['items']) : 0;
    }

    public function normalize_product_items_to_assoc(array $normalized) {
        if (!isset($normalized['items']) || !is_array($normalized['items'])) {
            return $normalized;
        }
        $items = array();
        foreach ($normalized['items'] as $row) {
            $a = is_array($row) ? $row : (array) $row;
            if (!isset($a['id'])) {
                if (isset($a['product_id'])) {
                    $a['id'] = $a['product_id'];
                } elseif (isset($a['code'])) {
                    $a['id'] = $a['code'];
                }
            }
            $items[] = $this->normalize_product_detail_item($a);
        }
        $normalized['items'] = $items;
        return $normalized;
    }

    /**
     * @param object|null $res
     * @param int         $page
     * @return mixed
     */
    public function unwrap_elintom_products_response($res) {
        if (!$res || !is_object($res)) {
            return null;
        }
        if (isset($res->result)) {
            return $res->result;
        }
        if (isset($res->items)) {
            return $res->items;
        }
        if (isset($res->products)) {
            return $res->products;
        }
        if (isset($res->product)) {
            return $res->product;
        }
        return null;
    }

    /** api3/eshop getallproducts — unwrap list */
    public function unwrap_legacy_products_payload($res) {
        if (!$res || !is_object($res)) {
            return null;
        }
        foreach (array('products', 'product', 'items', 'data', 'result') as $prop) {
            if (isset($res->$prop)) {
                return $res->$prop;
            }
        }
        return null;
    }

    public function normalize_product_detail_item(array $a) {
        if (isset($a['eshop_name']) && trim((string) $a['eshop_name']) !== '') {
            $a['name'] = trim((string) $a['eshop_name']);
        }
        if (!isset($a['id']) && isset($a['product_id'])) {
            $a['id'] = $a['product_id'];
        }
        if (!isset($a['name']) && isset($a['product_name'])) {
            $a['name'] = $a['product_name'];
        }
        if (!isset($a['name'])) {
            $a['name'] = '';
        }
        if (!isset($a['product_details']) || $a['product_details'] === null || $a['product_details'] === '') {
            foreach (array('details', 'description', 'long_description', 'body', 'short_description') as $k) {
                if (isset($a[$k]) && $a[$k] !== '' && $a[$k] !== null) {
                    $a['product_details'] = $a[$k];
                    break;
                }
            }
        }
        if (!isset($a['product_details'])) {
            $a['product_details'] = '';
        }
        $resolved_price = isset($a['price']) ? (float) $a['price'] : 0.0;
        if ($resolved_price <= 0) {
            foreach (array('eshop_price', 'unit_price', 'sale_price', 'mrp', 'regular_price') as $k) {
                if (isset($a[$k]) && $a[$k] !== '' && $a[$k] !== null && (float) $a[$k] > 0) {
                    $resolved_price = (float) $a[$k];
                    break;
                }
            }
        }
        if ($resolved_price > 0 && isset($a['promo_price']) && (float) $a['promo_price'] > 0
            && (float) $a['promo_price'] < $resolved_price) {
            $resolved_price = (float) $a['promo_price'];
        }
        $a['price'] = $resolved_price;
        if (isset($a['eshop_price']) && is_numeric($a['eshop_price']) && (float) $a['eshop_price'] > 0) {
            $a['price'] = (float) $a['eshop_price'];
        } elseif (isset($a['eshop_price']) && is_numeric($a['eshop_price']) && (float) $a['eshop_price'] <= 0) {
            $a['price'] = 0.0;
        }
        if (!isset($a['tax_rate'])) {
            $a['tax_rate'] = isset($a['tax']) ? $a['tax'] : 0;
        }
        if (!isset($a['tax_method'])) {
            $a['tax_method'] = 1;
        }
        if (!isset($a['promo_price'])) {
            $a['promo_price'] = 0;
        }
        if (!isset($a['cf1'])) {
            $a['cf1'] = '';
        }
        if (!isset($a['cf2'])) {
            $a['cf2'] = '';
        }
        if (!isset($a['cf3'])) {
            $a['cf3'] = '';
        }
        if (!isset($a['category_id'])) {
            $a['category_id'] = isset($a['category']) ? $a['category'] : (isset($a['categoryId']) ? $a['categoryId'] : 0);
        }
        if (!isset($a['subcategory_id'])) {
            $a['subcategory_id'] = isset($a['subcategory']) ? $a['subcategory'] : (isset($a['subcategoryId']) ? $a['subcategoryId'] : 0);
        }
        if (!isset($a['quantity']) || $a['quantity'] === '' || $a['quantity'] === null) {
            foreach (array('qty', 'stock', 'available_qty', 'quantity_balance', 'product_quantity') as $qk) {
                if (isset($a[$qk]) && $a[$qk] !== '' && is_numeric($a[$qk])) {
                    $a['quantity'] = $a[$qk];
                    break;
                }
            }
        }
        return $a;
    }

    public function normalize_product_images_from_api($raw) {
        if ($raw === null || $raw === false) {
            return array();
        }
        if (is_object($raw)) {
            $raw = (array) $raw;
        }
        // Accept wrapped payloads like {images:[...]} or a single image row object.
        if (is_array($raw) && isset($raw['images']) && (is_array($raw['images']) || is_object($raw['images']))) {
            $raw = is_array($raw['images']) ? $raw['images'] : (array) $raw['images'];
        }
        if (is_array($raw) && !$this->is_list_array($raw)) {
            $single = false;
            foreach (array('photo', 'image', 'filename', 'file_name', 'url', 'path') as $k) {
                if (isset($raw[$k]) && trim((string) $raw[$k]) !== '') {
                    $single = true;
                    break;
                }
            }
            if ($single) {
                $raw = array($raw);
            }
        }
        if (!is_array($raw)) {
            $raw = (array) $raw;
        }
        $out = array();
        foreach ($raw as $img) {
            $row = is_array($img) ? $img : (array) $img;
            $photo = '';
            if (isset($row['photo']) && $row['photo'] !== '') {
                $photo = $row['photo'];
            } elseif (isset($row['image']) && $row['image'] !== '') {
                $photo = $row['image'];
            } elseif (isset($row['filename']) && $row['filename'] !== '') {
                $photo = $row['filename'];
            } elseif (isset($row['file_name']) && $row['file_name'] !== '') {
                $photo = $row['file_name'];
            } elseif (isset($row['url']) && $row['url'] !== '') {
                $photo = $row['url'];
            } elseif (isset($row['path']) && $row['path'] !== '') {
                $photo = $row['path'];
            }
            if ($photo !== '') {
                $out[] = array('photo' => $photo);
            }
        }
        return $out;
    }

    /**
     * @param array $raw
     * @return array list of product rows
     */
    public function coerce_associative_product_map_to_rows($raw) {
        if (!is_array($raw) || $this->is_list_array($raw) || $raw === array()) {
            return array();
        }
        $rows = array();
        foreach ($raw as $key => $row) {
            if (in_array($key, array('items', 'products', 'page', 'items_total', 'count'), true)) {
                continue;
            }
            $a = is_array($row) ? $row : (array) $row;
            if (!isset($a['id']) && (is_int($key) || (is_string($key) && preg_match('/^\d+$/', $key)))) {
                $a['id'] = $key;
            }
            $rows[] = $a;
        }
        return $rows;
    }

    /**
     * @param object $res API response (getproductbyhash)
     * @return array|null Same shape as Webshop_model::get_product_by_hash()
     */
    public function product_detail_bundle_from_api_response($res) {
        if (!$res || !is_object($res)) {
            return null;
        }
        $bundle = array();

        if (isset($res->product)) {
            $bundle = is_object($res->product) ? (array) $res->product : (is_array($res->product) ? $res->product : array());
        } elseif (isset($res->result) && is_object($res->result) && isset($res->result->product)) {
            $bundle = is_object($res->result->product) ? (array) $res->result->product : (is_array($res->result->product) ? $res->result->product : array());
        } elseif (isset($res->result) && (is_object($res->result) || is_array($res->result))) {
            $bundle = is_array($res->result) ? $res->result : (array) $res->result;
        } elseif (isset($res->data)) {
            $d = $res->data;
            if (is_array($d) && $this->is_list_array($d) && count($d) === 1) {
                $bundle = is_array($d[0]) ? $d[0] : (array) $d[0];
            } elseif (is_array($d) && !$this->is_list_array($d)) {
                $bundle = $d;
            } elseif (is_object($d)) {
                $bundle = (array) $d;
            }
        } elseif (isset($res->item)) {
            $bundle = array('item' => $res->item);
        }

        if (empty($bundle)) {
            return null;
        }

        // Some APIs return the product bundle as a JSON string.
        if (count($bundle) === 1 && isset($bundle[0]) && is_string($bundle[0])) {
            $decoded = json_decode($bundle[0], true);
            if (is_array($decoded)) {
                $bundle = $decoded;
            }
        }
        foreach (array('product', 'result', 'data') as $wrapKey) {
            if (isset($bundle[$wrapKey]) && (is_array($bundle[$wrapKey]) || is_object($bundle[$wrapKey]))) {
                $wrapped = is_array($bundle[$wrapKey]) ? $bundle[$wrapKey] : (array) $bundle[$wrapKey];
                if (!empty($wrapped)) {
                    $bundle = $wrapped;
                    break;
                }
            }
        }

        $itemRaw = null;
        if (isset($bundle['item'])) {
            $itemRaw = $bundle['item'];
        } else {
            // Flat product row shape.
            $itemRaw = $bundle;
        }
        if (is_string($itemRaw) && $itemRaw !== '') {
            $decoded = json_decode($itemRaw, true);
            if (is_array($decoded)) {
                $itemRaw = $decoded;
            }
        }
        if (!is_array($itemRaw) && !is_object($itemRaw)) {
            return null;
        }
        $item = is_array($itemRaw) ? $itemRaw : (array) $itemRaw;
        $item = $this->normalize_product_detail_item($item);

        $variants = array();
        if (isset($bundle['variants'])) {
            $variants = $this->rows_to_assoc_arrays($bundle['variants']);
        } elseif (isset($res->variants)) {
            $variants = $this->rows_to_assoc_arrays($res->variants);
        }
        if (!empty($variants)) {
            $CI = get_instance();
            if (!function_exists('webshop_normalize_variant_row')) {
                $CI->load->helper('webshop');
            }
            if (function_exists('webshop_normalize_variant_row')) {
                foreach ($variants as $vk => $vrow) {
                    $variants[$vk] = webshop_normalize_variant_row(is_array($vrow) ? $vrow : (array) $vrow);
                }
            }
        }
        if (!empty($variants) && isset($item['eshop_price']) && is_numeric($item['eshop_price']) && (float) $item['eshop_price'] <= 0) {
            $item['price'] = 0.0;
        }
        $images = array();
        if (isset($bundle['images'])) {
            $images = $this->normalize_product_images_from_api($bundle['images']);
        } elseif (isset($bundle['product_images'])) {
            $images = $this->normalize_product_images_from_api($bundle['product_images']);
        } elseif (isset($res->images)) {
            $images = $this->normalize_product_images_from_api($res->images);
        } elseif (isset($res->product_images)) {
            $images = $this->normalize_product_images_from_api($res->product_images);
        }
        if (empty($images) && !empty($item['image'])) {
            $images[] = array('photo' => $item['image']);
        }
        if (empty($images)) {
            foreach (array('images', 'product_images', 'gallery_images', 'photos') as $imgKey) {
                if (isset($item[$imgKey])) {
                    $images = $this->normalize_product_images_from_api($item[$imgKey]);
                    if (!empty($images)) {
                        break;
                    }
                }
            }
        }

        return array(
            'item'       => $item,
            'variants'   => $variants,
            'images'     => $images,
            'stocks'     => array(),
        );
    }

    public function rows_to_assoc_arrays($rows) {
        if ($rows === null || $rows === false) {
            return array();
        }
        if (is_object($rows)) {
            $rows = json_decode(json_encode($rows), true);
        }
        if (!is_array($rows)) {
            return array();
        }
        $out = array();
        foreach ($rows as $key => $row) {
            if (!is_array($row) && !is_object($row)) {
                continue;
            }
            $a = is_array($row) ? $row : (array) $row;
            if (is_string($key) && trim($key) !== '' && !is_numeric($key)) {
                if (!isset($a['name']) || trim((string) $a['name']) === '') {
                    $a['name'] = trim($key);
                }
            }
            if (!isset($a['id']) && is_numeric($key) && (int) $key > 0) {
                $a['id'] = (int) $key;
            }
            $out[] = $a;
        }
        return $out;
    }

    /**
     * @param object|null $res
     * @param string      $plural 'states' or 'countries'
     * @return mixed|null
     */
    public function unwrap_geo_list_from_api_response($res, $plural) {
        if (!$res || !is_object($res)) {
            return null;
        }
        $singular = ($plural === 'states') ? 'state' : 'country';

        if (isset($res->{$plural})) {
            $v = $res->{$plural};
            if (is_string($v) && $v !== '') {
                $d = json_decode($v, true);
                if (is_array($d)) {
                    return $d;
                }
            }
            return $v;
        }
        if (isset($res->{$singular})) {
            $v = $res->{$singular};
            if (is_string($v) && $v !== '') {
                $d = json_decode($v, true);
                if (is_array($d)) {
                    return $d;
                }
            }
            return $v;
        }
        if (isset($res->data)) {
            $d = $res->data;
            if (is_object($d)) {
                if (isset($d->{$plural})) {
                    return $d->{$plural};
                }
                if (isset($d->{$singular})) {
                    return $d->{$singular};
                }
                return $d;
            }
            if (is_array($d)) {
                if (isset($d[$plural])) {
                    return $d[$plural];
                }
                if (isset($d[$singular])) {
                    return $d[$singular];
                }
                if ($this->is_list_array($d)) {
                    return $d;
                }
            }
        }
        if (isset($res->result)) {
            $r = $res->result;
            if (is_string($r) && $r !== '') {
                $decoded = json_decode($r);
                if ($decoded !== null && is_object($decoded)) {
                    return $this->unwrap_geo_list_from_api_response($decoded, $plural);
                }
            }
            if (is_object($r)) {
                if (isset($r->{$plural})) {
                    return $r->{$plural};
                }
                if (isset($r->{$singular})) {
                    return $r->{$singular};
                }
                if (isset($r->items)) {
                    return $r->items;
                }
                return $r;
            }
            if (is_array($r)) {
                if (isset($r[$plural])) {
                    return $r[$plural];
                }
                if (isset($r[$singular])) {
                    return $r[$singular];
                }
                if ($this->is_list_array($r)) {
                    return $r;
                }
            }
        }
        if (isset($res->items)) {
            return $res->items;
        }
        return null;
    }

    /**
     * @param mixed $rows
     * @return array
     */
    public function countries_to_objects($rows) {
        if ($rows === false || $rows === null) {
            return array();
        }
        if (!is_array($rows)) {
            $rows = array($rows);
        }
        $out = array();
        foreach ($rows as $row) {
            if (is_object($row)) {
                $o = $row;
                if (!isset($o->id) && isset($o->country_id)) {
                    $o->id = $o->country_id;
                }
                if (!isset($o->phone_digits)) {
                    $o->phone_digits = isset($o->phoneDigits) ? $o->phoneDigits : '';
                }
                $out[] = $o;
                continue;
            }
            $a = is_array($row) ? $row : (array) $row;
            $obj = new stdClass();
            $obj->id = isset($a['id']) ? $a['id'] : (isset($a['country_id']) ? $a['country_id'] : '');
            $obj->code = isset($a['code']) ? $a['code'] : (isset($a['country_code']) ? $a['country_code'] : '');
            $obj->name = isset($a['name']) ? $a['name'] : (isset($a['country_name']) ? $a['country_name'] : '');
            $obj->phone_digits = isset($a['phone_digits']) ? $a['phone_digits'] : (isset($a['phoneDigits']) ? $a['phoneDigits'] : '');
            $out[] = $obj;
        }
        return $out;
    }

    /**
     * @param mixed $raw API list or legacy structures
     * @return array<string|int,array>
     */
    public function normalize_states_map($raw) {
        if ($raw === null || $raw === false || $raw === array()) {
            return array();
        }
        if (is_object($raw)) {
            $raw = (array) $raw;
        }
        if (!is_array($raw)) {
            return array();
        }
        $out = array();
        foreach ($raw as $k => $row) {
            $o = is_array($row) ? $row : (array) $row;
            if (!isset($o['name']) || $o['name'] === '') {
                if (isset($o['state_name'])) {
                    $o['name'] = $o['state_name'];
                } elseif (isset($o['StateName'])) {
                    $o['name'] = $o['StateName'];
                }
            }
            if (!isset($o['code']) || $o['code'] === '') {
                if (isset($o['state_code'])) {
                    $o['code'] = $o['state_code'];
                } elseif (isset($o['StateCode'])) {
                    $o['code'] = $o['StateCode'];
                }
            }
            if (!isset($o['country_id'])) {
                if (isset($o['countryid'])) {
                    $o['country_id'] = $o['countryid'];
                } elseif (isset($o['countryId'])) {
                    $o['country_id'] = $o['countryId'];
                }
            }
            $id = isset($o['id']) ? $o['id'] : (isset($o['state_id']) ? $o['state_id'] : $k);
            $out[$id] = $o;
        }
        return $out;
    }
}
