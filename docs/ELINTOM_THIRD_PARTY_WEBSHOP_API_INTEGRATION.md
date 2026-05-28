# ElintOm API Integration Guide (Third-Party Webshop)

This document explains how to integrate any external webshop platform with the ElintOm webshop API.

It is based on the live implementation in:

- `../ElintOm/app/controllers/Webshop_api.php`
- `../ElintOm/app/models/Webshop_api_model.php`
- `../ElintOm/app/models/Webshop_model.php`
- `application/libraries/Elintom_api_client.php` (reference client from `webshopapi`)

---

## 1) Integration overview

Third-party webshop should call **one POST API endpoint** and pass an `action` name to execute API operations.

High-level flow:

1. Your webshop sends `POST` to ElintOm API endpoint.
2. API validates `privatekey`.
3. API executes action handler (catalog, customer, order, wishlist, review, leads).
4. API returns JSON response (`SUCCESS` or `ERROR`).

---

## 2) Base URL and endpoint

Use your ElintOm server base URL:

- Example base: `http://localhost/ElintOm`

Primary endpoint:

- `POST {BASE_URL}/webshop_api/index`

Notes:

- Some setups also accept `POST {BASE_URL}/webshop_api/action`, but use `/index` as standard.
- Legacy endpoint exists for fallback in internal client (`/api3/eshop`), but new integrations should use `webshop_api/index`.

---

## 3) Authentication

Every request must include:

- `privatekey` = ElintOm setting `sma_settings.api_privatekey`
- `action` = API action name (for example: `getproductslist`)

If key is invalid, API returns:

- `status: ERROR`
- `error_code: 102`
- `msg: Private key mismatch.`

---

## 4) Request and response format

## Request format

- Method: `POST`
- Content type: `application/x-www-form-urlencoded`
- Required core fields:
  - `privatekey`
  - `action`

Optional multi-tenant routing fields (recommended):

- `http_host`
- `shop_host`
- `subdomain`
- `shop_subdomain`

## Response envelope

Typical success:

```json
{
  "status": "SUCCESS",
  "...": "action specific payload"
}
```

Typical error:

```json
{
  "status": "ERROR",
  "error_code": 103,
  "msg": "Unknown action: ..."
}
```

Known platform-level error codes:

- `100` API private key not configured on ElintOm
- `102` Private key mismatch
- `103` Unknown action
- `500` Server/JSON/internal errors

---

## 5) Action catalog (supported actions)

Actions available in `Webshop_api.php`:

### Store / CMS

- `getsettings`
- `getcmspage`
- `getcmspages`
- `getnextref`

### Catalog

- `getcategories`
- `getsliders`
- `getproductslist`
- `getproductbyhash`
- `getentitytags`
- `searchproducts`

### Geo

- `getstates`
- `getcountries`

### Customer / Auth

- `getcustomer`
- `createcustomer`
- `logincheck`
- `adminlogincheck`
- `registercheck`
- `passwordotpsend`
- `customerresetpassword`

### Order notifications

- `notifywebshoporderwhatsapp`
- `notifywebshoporderemail`

### Addresses

- `getaddresses`
- `addaddress`
- `updateaddress`
- `deleteaddress`
- `setaddressdefault`

### Orders / payment

- `addorder`
- `getgatewaycredentials`
- `getorder`
- `getorderbytrackhash`
- `recordccavenuepayment`
- `cancelorder`
- `getcustomersales`

### Coupon

- `applycoupon`

### Wishlist

- `getwishlist`
- `addwishlist`
- `removewishlist`

### Company

- `getcompany`

### Reviews

- `submitproductreview`
- `getproductreviews`
- `getproductrating`

### Leads / contact

- `submitcontactlead`

---

## 6) Core actions with payload contracts

Below are the most important integration contracts.

## 6.1 `getsettings`

Purpose:

- Fetch storefront settings, gateways, and website settings.

Request fields:

- `privatekey`
- `action=getsettings`

---

## 6.2 `getproductslist`

Purpose:

- Fetch product list by category, brand, or explicit product IDs.

Request fields:

- `privatekey`
- `action=getproductslist`
- `by` one of: `category`, `brand`, `products`
- `byid` category id/hash, brand id/hash, or comma-separated product IDs
- `use_hash` `0|1`
- `limit` integer
- `page` integer

Important returned fields (per item):

- `id`
- `name`
- `eshop_name` (if configured in ElintOm products)
- `price` (uses e-shop price flow)
- `mrp`
- `image`
- `quantity`
- `variants` (when available)

Name rule expected on consumer side:

- Show `eshop_name` if non-empty
- Else show `name`

---

## 6.3 `getproductbyhash`

Purpose:

- Fetch one product detail by md5 hash of product id.

Request fields:

- `privatekey`
- `action=getproductbyhash`
- `product_hash` required
- `product_id` optional

Response generally contains:

- `item` (product)
- `variants`
- `images`
- `stocks`

---

## 6.4 `createcustomer` (`action=createcustomer`)

Required:

- `name`
- `phone`

Optional:

- `email`, `company`, `address`, `city`, `state`, `state_code`, `postal_code`, `country`, `password`

---

## 6.5 `addaddress` / `updateaddress`

Supported aliases:

- `customer_id` or `company_id`
- `email` or `email_id`

Main fields:

- `address_name`, `company_name`, `line1`, `line2`, `city`, `postal_code`, `state`, `state_code`, `country`, `phone`, `address_type`

---

## 6.6 `addorder`

Request fields:

- `privatekey`
- `action=addorder`
- `order` JSON string
- `items` JSON string (array)

Item JSON should include standard ERP-friendly fields such as:

- `product_id`, `product_name`, `quantity`, `unit_price`, `tax`, `discount`, etc.

Do not send custom UI-only fields in final payload.

---

## 6.7 `applycoupon`

Fields:

- `coupon_code`
- `cart_total`

---

## 6.8 `submitcontactlead`

Fields (supported set):

- `name` / `full_name`
- `phone` / `mobile`
- `email`
- `message` / `comments`
- `source`

---

## 7) cURL examples

## 7.1 Product list by category hash

```bash
curl -X POST "http://localhost/ElintOm/webshop_api/index" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "privatekey=YOUR_PRIVATE_KEY" \
  -d "action=getproductslist" \
  -d "by=category" \
  -d "byid=1" \
  -d "use_hash=0" \
  -d "limit=12" \
  -d "page=1"
```

## 7.2 Product detail by hash

```bash
curl -X POST "http://localhost/ElintOm/webshop_api/index" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "privatekey=YOUR_PRIVATE_KEY" \
  -d "action=getproductbyhash" \
  -d "product_hash=c4ca4238a0b923820dcc509a6f75849b"
```

## 7.3 Create order

```bash
curl -X POST "http://localhost/ElintOm/webshop_api/index" \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "privatekey=YOUR_PRIVATE_KEY" \
  -d "action=addorder" \
  --data-urlencode "order={\"customer_id\":123,\"reference_no\":\"ES-20260528-0001\"}" \
  --data-urlencode "items=[{\"product_id\":1,\"product_name\":\"Item A\",\"quantity\":1,\"unit_price\":100}]"
```

---

## 8) Recommended third-party integration architecture

Use a simple service layer in your webshop:

1. `ElintomApiService`
   - one `post(action, payload)` method
   - attaches `privatekey` and tenant fields
   - handles retries and timeout

2. Domain methods:
   - `getSettings()`
   - `getProductsList(filter)`
   - `getProductByHash(hash)`
   - `createCustomer(data)`
   - `createOrder(order, items)`
   - `applyCoupon(code, total)`

3. Data normalization:
   - enforce display name: `eshop_name || name`
   - enforce image fallback if missing
   - enforce numeric conversions (`price`, `mrp`, `quantity`)

---

## 9) Validation checklist before go-live

- API key works for all required actions.
- `getproductslist` returns products with correct `eshop_name` and `name`.
- Product display rule tested:
  - `eshop_name` filled -> shown
  - `eshop_name` blank -> fallback `name`
- Image URLs resolved and fallback image shown for missing files.
- Customer create/login/reset flows tested.
- Address CRUD tested with both `customer_id` and `company_id`.
- Order create + getorder + cancelorder tested.
- Coupon and wishlist tested.
- Contact lead submission tested.
- Logs monitored for JSON parse errors and unknown actions.

---

## 10) Troubleshooting quick map

- `Private key mismatch` -> wrong key or wrong environment.
- `Unknown action` -> typo or unsupported action name.
- Empty/HTML response -> backend fatal/route issue on ElintOm.
- Price/name mismatch -> verify ElintOm product fields (`eshop_price`, `eshop_name`) and list query output.
- Order insertion issues -> inspect JSON structure for `order` and `items`.

---

## 11) File-level reference map

- API router: `../ElintOm/app/controllers/Webshop_api.php`
- API business logic: `../ElintOm/app/models/Webshop_api_model.php`
- Product SQL source: `../ElintOm/app/models/Webshop_model.php`
- Client implementation reference: `application/libraries/Elintom_api_client.php`
- Response normalization reference: `application/libraries/Elintom_api_response.php`

