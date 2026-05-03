# ElintOm Webshop_api — target JSON contract (reference)

This document lives in **WebshopAPI** so storefront and ElintOm developers share one checklist.
Implementing these shapes on the **ElintOm** `Webshop_api` controller allows WebshopAPI to drop defensive parsing over time (see `application/libraries/Elintom_api_response.php`).

## Transport

- **Endpoint**: configurable via `elintom_api_webshop_endpoint_path` (default `webshop_api/index`).
- **POST fields**: `privatekey`, `action`, plus tenant hints (`http_host`, etc.) as sent by `Elintom_api_client`.
- **Success**: root `status`: `"SUCCESS"` (case-insensitive OK).

## Actions — preferred shapes

### getsettings

- Normalized by `MY_Controller` into `$Settings`, `webshop_settings`, `pos_settings`.
- Include stable keys already consumed by the storefront: POS/webshop settings objects, optional `website_setting` array, optional absolute media base (`media_uploads_base_url` / `mdata_url`).

### getcategories

- Prefer a single list source: e.g. `categories` **or** `allcategories` as array of objects with at least `id`, `parent_id`, names and flags expected by the theme.
- Avoid embedding the same list only inside nested JSON strings unless documented.

### getproductslist

- Prefer `items` as a numeric array of product rows; include `items_total` and `page` when paginating.
- Product rows: stable `id` (or `product_id` mapped once server-side), prices, tax fields used by checkout.

### getstates / getcountries

- Prefer root `status` SUCCESS plus `states` / `countries` as arrays (objects or associative arrays) with stable keys (`id`, `name`, `country_id`, etc.).
- Implement **one** action name per verb on ElintOm (snake_case vs camelCase) so storefront does not need retries.

### getproductbyhash

- Prefer `product` object plus optional `images` / `variants` arrays rather than multiple alternate envelopes (`result`, string-encoded JSON, etc.).

## Legacy `api3/eshop`

Reserved for catalogue fallback only; geo actions often return **103**. Keep catalogue responses consistent where possible.

---

When ElintOm matches this contract, simplify `Elintom_api_response` and delete obsolete branches in consultation with production payloads.
