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
                    return $r->main;
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
                    return $r['main'];
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

        $rows = array();
        if (isset($categoriesRaw['main']) && is_array($categoriesRaw['main'])) {
            $rows = $categoriesRaw['main'];
        } elseif ($this->is_list_array($categoriesRaw)) {
            $rows = $categoriesRaw;
        } else {
            foreach ($categoriesRaw as $k => $v) {
                if ($k === 'main' || $k === 'status' || $k === 'message') {
                    continue;
                }
                if (is_array($v) || is_object($v)) {
                    $rows[] = $v;
                }
            }
        }

        foreach ($rows as $row) {
            $obj = is_object($row) ? $row : (object) $row;
            $this->coerce_category_id_on_object($obj);
            if (!isset($obj->id) || $obj->id === '' || $obj->id === null) {
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
            $items[] = $a;
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
        if (!isset($a['price'])) {
            foreach (array('eshop_price', 'unit_price', 'sale_price') as $k) {
                if (isset($a[$k]) && $a[$k] !== '' && $a[$k] !== null) {
                    $a['price'] = $a[$k];
                    break;
                }
            }
        }
        if (!isset($a['price'])) {
            $a['price'] = 0;
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
        return $a;
    }

    public function normalize_product_images_from_api($raw) {
        if ($raw === null || $raw === false) {
            return array();
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
        $product = null;
        if (isset($res->product)) {
            $product = $res->product;
        } elseif (isset($res->result) && is_object($res->result) && isset($res->result->product)) {
            $product = $res->result->product;
        } elseif (isset($res->result) && (is_object($res->result) || is_array($res->result))) {
            $cand = is_array($res->result) ? $res->result : (array) $res->result;
            if (isset($cand['id']) || isset($cand['product_id']) || isset($cand['name']) || isset($cand['product_name'])) {
                $product = $res->result;
            }
        } elseif (isset($res->item)) {
            $product = $res->item;
        } elseif (isset($res->data)) {
            $d = $res->data;
            if (is_array($d) && $this->is_list_array($d) && count($d) === 1) {
                $product = $d[0];
            } elseif (is_array($d) && !$this->is_list_array($d)) {
                $product = $d;
            } elseif (is_object($d)) {
                $product = $d;
            }
        }
        if ($product === null) {
            return null;
        }
        if (is_string($product) && $product !== '') {
            $decoded = json_decode($product, true);
            if (is_array($decoded)) {
                $product = $decoded;
            }
        }
        if (!is_array($product) && !is_object($product)) {
            return null;
        }
        $item = is_array($product) ? $product : (array) $product;
        $item = $this->normalize_product_detail_item($item);

        $variants = array();
        if (isset($res->variants)) {
            $variants = $this->rows_to_assoc_arrays($res->variants);
        }
        $images = array();
        if (isset($res->images)) {
            $images = $this->normalize_product_images_from_api($res->images);
        } elseif (isset($res->product_images)) {
            $images = $this->normalize_product_images_from_api($res->product_images);
        }
        if (empty($images) && !empty($item['image'])) {
            $images[] = array('photo' => $item['image']);
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
        if (!is_array($rows)) {
            $rows = (array) $rows;
        }
        $out = array();
        foreach ($rows as $row) {
            $out[] = is_array($row) ? $row : (array) $row;
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
