# ElintOm Third-Party Webshop UAT Checklist

Use this checklist to validate third-party webshop integration with ElintOm API before go-live.

Related docs:

- `docs/ELINTOM_THIRD_PARTY_WEBSHOP_QUICK_START.md`
- `docs/ELINTOM_THIRD_PARTY_WEBSHOP_API_INTEGRATION.md`
- `docs/ELINTOM_THIRD_PARTY_WEBSHOP_API_OPENAPI.yaml`
- `docs/ELINTOM_THIRD_PARTY_WEBSHOP_POSTMAN_COLLECTION.json`

---

## 0) Prerequisites

- [ ] ElintOm API base URL available (example: `http://localhost/ElintOm`)
- [ ] API private key available from ElintOm settings
- [ ] Third-party platform can send `POST` form-urlencoded requests
- [ ] Test products, categories, customer data seeded
- [ ] At least one product with `eshop_name` and one without `eshop_name`

---

## 1) Connectivity and auth

## 1.1 Get settings (happy path)

- Action: `getsettings`
- Expected:
  - [ ] HTTP 200
  - [ ] JSON with `status = SUCCESS`
  - [ ] returns settings payload

## 1.2 Invalid private key

- Same call with wrong key
- Expected:
  - [ ] `status = ERROR`
  - [ ] `error_code = 102`

---

## 2) Catalog validation

## 2.1 Get categories

- Action: `getcategories`
- Expected:
  - [ ] `status = SUCCESS`
  - [ ] categories list returned

## 2.2 Product list by category

- Action: `getproductslist` with `by=category`
- Expected:
  - [ ] items returned
  - [ ] pagination fields (`items_total`, `page`)
  - [ ] each item has `id`, `name`, `price`, `image` (or fallback behavior)

## 2.3 Product detail by hash

- Action: `getproductbyhash`
- Expected:
  - [ ] `status = SUCCESS`
  - [ ] `item`, `variants`, `images`, `stocks` returned

## 2.4 Name display rule (business-critical)

Test two products:

1) `eshop_name` filled  
2) `eshop_name` blank

Expected in webshop:

- [ ] product 1 shows `eshop_name`
- [ ] product 2 shows `name` fallback

---

## 3) Image fallback validation

- Use a product with missing image file
- Expected:
  - [ ] no broken image UI in cards/details
  - [ ] fallback image shown (`uploads/thumbs/no_image.png` or configured fallback)
  - [ ] no user-facing blank media blocks

---

## 4) Customer and auth flow

## 4.1 Create customer

- Action: `createcustomer`
- Expected:
  - [ ] `status = SUCCESS`
  - [ ] customer object returned

## 4.2 Login

- Action: `logincheck`
- Expected:
  - [ ] valid credentials => `SUCCESS`
  - [ ] invalid credentials => `ERROR`

## 4.3 Register duplicate check

- Action: `registercheck`
- Expected:
  - [ ] duplicate phone/email correctly flagged

---

## 5) Address flow

## 5.1 Add address

- Action: `addaddress`
- Expected:
  - [ ] `SUCCESS`
  - [ ] new address visible in `getaddresses`

## 5.2 Update address

- Action: `updateaddress`
- Expected:
  - [ ] edited fields persisted

## 5.3 Delete and default

- Actions: `deleteaddress`, `setaddressdefault`
- Expected:
  - [ ] deleted address removed
  - [ ] default address correctly marked

---

## 6) Order flow (critical)

## 6.1 Create order

- Action: `addorder`
- Expected:
  - [ ] `status = SUCCESS`
  - [ ] `order_id` or `sale_id` returned
  - [ ] order visible in ElintOm sales/order list

## 6.2 Fetch order

- Action: `getorder`
- Expected:
  - [ ] order fetched by `order_id` and/or `reference_no`

## 6.3 Cancel order (if unpaid)

- Action: `cancelorder`
- Expected:
  - [ ] cancellation accepted for eligible statuses
  - [ ] order status updated in ElintOm

---

## 7) Commerce utility flow

## 7.1 Coupon

- Action: `applycoupon`
- Expected:
  - [ ] valid coupon applies
  - [ ] invalid/expired coupon returns proper error

## 7.2 Wishlist

- Actions: `getwishlist`, `addwishlist`, `removewishlist`
- Expected:
  - [ ] add/remove reflected correctly

## 7.3 Reviews

- Actions: `submitproductreview`, `getproductreviews`, `getproductrating`
- Expected:
  - [ ] submitted review retrievable
  - [ ] rating updates as expected

## 7.4 Contact lead

- Action: `submitcontactlead`
- Expected:
  - [ ] lead inserted/updated as per backend logic
  - [ ] expected success response

---

## 8) Error handling checks

- [ ] Unknown action returns `error_code=103`
- [ ] Missing required fields return clear `ERROR` response
- [ ] Non-JSON or empty body errors are logged and surfaced correctly in client

---

## 9) Performance and stability checks

- [ ] Product list API works under expected load (basic burst test)
- [ ] No timeout in key flows (catalog/order/customer)
- [ ] API retries (if any) do not create duplicate orders

---

## 10) Security checks

- [ ] Private key never exposed in frontend logs/UI
- [ ] HTTPS enabled in production
- [ ] No sensitive data leaked in error messages

---

## 11) Go-live sign-off

- [ ] Dev sign-off complete
- [ ] QA sign-off complete
- [ ] Third-party team sign-off complete
- [ ] UAT evidence archived (request/response screenshots/logs)
- [ ] Production credentials and endpoint validated
- [ ] Rollback owner and contact defined

---

## UAT sign-off table

| Area | Owner | Status (Pass/Fail) | Notes |
|---|---|---|---|
| Connectivity/Auth |  |  |  |
| Catalog |  |  |  |
| Name Rule (`eshop_name`) |  |  |  |
| Images/Fallback |  |  |  |
| Customer/Auth |  |  |  |
| Address |  |  |  |
| Order |  |  |  |
| Coupon/Wishlist/Review |  |  |  |
| Lead Submission |  |  |  |
| Error Handling |  |  |  |
| Security |  |  |  |
| Final Go-live |  |  |  |

