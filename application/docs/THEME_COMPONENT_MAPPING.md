# Theme and Component Mapping (`plane_vanila_theme` -> `plane_vanila_theme/<theme>/components`)

This note explains how storefront themes in `application/views/plane_vanila_theme` use reusable components from theme-specific component directories.

## 1) View resolution flow

- Main entry is `Webshop::load_view($method, $data)` in `application/controllers/Webshop.php`.
- It calls `resolve_webshop_view_path($method)`.
- `resolve_plane_vanila_view_path($method)` is checked first and can override only this allowlist:
  - `restaurant/index`
  - `nw_theme/index`
  - `gulfpharmacy_theme/index`
  - `nw_theme/product_details`
  - `gulfpharmacy_theme/product_details`
- If matching file exists under `application/views/plane_vanila_theme/...`, that file is rendered.
- Otherwise fallback is `application/views/webshop/...`.

## 2) Dynamic section -> component mapping

Implemented in `application/libraries/Webshop_section_engine.php`:

- Section type to component view mapping (resolves to theme-specific path):
  - `html_block` -> `components/html_block`
  - `product_grid` -> `components/product_grid`
  - `product_carousel` -> `components/product_carousel`
  - `category_grid` -> `components/category_grid`
  - `category_carousel` -> `components/category_carousel`
  - `banner` -> `components/banner`
  - `hero_banner` -> `components/banner`
- Rendering path:
  - `render_components($sections, $data)` sorts sections by `sort_order` and renders each mapped component.
  - Unknown section types are skipped.

## 3) Home/CMS data preparation used by themes

In `Webshop::index()` and dynamic CMS flow in `Webshop::cms_page($slug)`:

- CMS section/body data is converted into theme-friendly flags/variables:
  - `home_has_category_grid`
  - `home_has_product_grid`
  - `home_has_header_section`
  - `home_has_footer_section`
  - `home_section_html_block`
  - `home_category_grid_title`
  - `home_product_grid_title`
  - `cms_header_sections_html`
  - `cms_footer_sections_html`
- Local dynamic body HTML can be generated via:
  - `$this->webshop_section_engine->render_components($bodyForRender, $this->data)`

## 4) `plane_vanila_theme` files and how they map components

### A) `plane_vanila_theme/gulfpharmacy_theme/index.php`

- Uses local theme header/footer includes:
  - `plane_vanila_theme/gulfpharmacy_theme/header.php`
  - `plane_vanila_theme/gulfpharmacy_theme/footer.php`
- Component usage:
  - Explicitly renders `webshop/components/product_showcase`.
  - `product_showcase` internally renders:
    - `webshop/components/product_carousel`
    - `webshop/components/product_grid`
- Category display is custom markup in this theme file (not directly using `category_grid.php` component).
- Body HTML comes from `home_section_html_block` / CMS content and is inserted directly.

### B) `plane_vanila_theme/restaurant/index.php`

- Does not directly call `webshop/components/*` in the template.
- Receives pre-rendered dynamic body content via `home_section_html_block` (generated in controller using `Webshop_section_engine::render_components()` when needed).
- So component usage is indirect: controller -> section engine -> `webshop/components/*` -> HTML inserted in theme.

### C) `plane_vanila_theme/nw_theme/index.php`

- Thin delegator: it forwards rendering to:
  - `webshop/nw_theme/index`
- Because of this, component usage happens inside `webshop/nw_theme/index` (or via prebuilt HTML variables), not in the `plane_vanila_theme` wrapper itself.

### D) Product details theme shell

- `webshop/components/product_details.php` is a full page shell for product detail.
- It uses `plane_vanila_theme/<theme>_theme/header.php` and footer for `nw` or `gulfpharmacy` themes.
- It renders detail body via:
  - `webshop/components/theme_product_details`

## 5) Quick matrix

- `plane_vanila_theme/gulfpharmacy_theme/index.php`
  - direct: `webshop/components/product_showcase`
  - indirect via showcase: `product_carousel`, `product_grid`
- `plane_vanila_theme/restaurant/index.php`
  - indirect: rendered component HTML from `Webshop_section_engine::render_components()`
- `plane_vanila_theme/nw_theme/index.php`
  - wrapper only; delegates to `webshop/nw_theme/index`
- `Webshop_section_engine`
  - canonical section-type -> component resolver for dynamic CMS sections

## 6) Practical takeaway

If you add a new CMS section type:

1. Add section type normalization/mapping in `Webshop_section_engine::resolve_component_view()`.
2. Create the new component view under `application/views/webshop/components`.
3. Ensure target theme either:
   - renders `home_section_html_block`, or
   - directly includes that component view.

