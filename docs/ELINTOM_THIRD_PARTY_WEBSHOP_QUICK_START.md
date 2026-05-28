# ElintOm API Quick Start (Third-Party Webshop)

This is the minimum implementation guide for external teams.

Base endpoint:

- `POST {BASE_URL}/webshop_api/index`
- Content type: `application/x-www-form-urlencoded`

Required on every call:

- `privatekey`
- `action`

---

## 1) Verify connectivity

Call:

- `action=getsettings`

Success means auth + route are correct.

---

## 2) Load catalog

### Get categories

- `action=getcategories`

### Get product list for category page

- `action=getproductslist`
- `by=category`
- `byid=<category_id_or_hash>`
- `use_hash=0|1`
- `limit=12`
- `page=1`

Display name rule:

- show `eshop_name` if non-empty
- else show `name`

---

## 3) Product detail page

Call:

- `action=getproductbyhash`
- `product_hash=<md5(product_id)>`

Use response blocks:

- `item` (main product)
- `variants`
- `images`
- `stocks`

---

## 4) Customer + address

### Register

- `action=createcustomer` (required: `name`, `phone`)

### Login

- `action=logincheck` (required: `login`, `password`)

### Address add

- `action=addaddress`
- pass `customer_id` (or `company_id`) + address fields

---

## 5) Place order

Call:

- `action=addorder`
- `order=<json_string>`
- `items=<json_array_string>`

Then track:

- `action=getorder` with `order_id` or `reference_no`

Optional cancel before payment:

- `action=cancelorder`

---

## 6) Optional commerce features

- Coupon: `action=applycoupon`
- Wishlist: `getwishlist`, `addwishlist`, `removewishlist`
- Reviews: `submitproductreview`, `getproductreviews`, `getproductrating`
- Lead/contact: `submitcontactlead`

---

## 7) Go-live checklist (minimum)

- `getsettings` works in production URL.
- Product list and detail both return valid JSON.
- Name display rule verified (`eshop_name` fallback to `name`).
- Image fallback tested for missing files.
- Customer register/login/address flow tested.
- addorder + getorder tested with real tax/price data.
- Error logging enabled for API failures.

---

## 8) Useful docs

- Deep integration doc:
  - `docs/ELINTOM_THIRD_PARTY_WEBSHOP_API_INTEGRATION.md`
- OpenAPI spec:
  - `docs/ELINTOM_THIRD_PARTY_WEBSHOP_API_OPENAPI.yaml`
- Postman collection:
  - `docs/ELINTOM_THIRD_PARTY_WEBSHOP_POSTMAN_COLLECTION.json`

