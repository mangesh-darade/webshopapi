<?php
$ex = isset($seo_ext) && is_array($seo_ext) ? $seo_ext : [];
$chk = function ($key) use ($ex) {
    return !empty($ex[$key]) ? ' checked' : '';
};
$val = function ($key) use ($ex) {
    return isset($ex[$key]) ? htmlspecialchars((string)$ex[$key], ENT_QUOTES, 'UTF-8') : '';
};
?>
<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= site_url('admin/dashboard') ?>">Home</a></li>
            <li class="breadcrumb-item active">Advanced SEO</li>
        </ol>
    </nav>
    <h2 class="h3 fw-bold mb-0 text-dark">Advanced SEO Configuration</h2>
    <p class="text-muted small mb-0">Global and sitewide tags are merged into every webshop page that uses the SEO injector. Per-page title/description still come from <a href="<?= site_url('admin/seo') ?>">SEO Engine</a>.</p>
</div>

<?php if ($this->session->flashdata('message')): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $this->session->flashdata('message') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card bg-white p-4">
            <form action="<?= site_url('admin/seo_advanced') ?>" method="POST">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                <input type="hidden" name="save_advanced_seo" value="1">

                <h5 class="fw-bold mb-3 text-dark">Open Graph / Social Meta</h5>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Default OG type</label>
                    <input type="text" name="og_type_default" class="form-control" value="<?= $val('og_type_default') ?>" placeholder="website">
                    <div class="form-text">Product and blog views override this automatically when applicable.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">OG: Title</label>
                    <input type="text" name="og_title" class="form-control" value="<?= isset($ws->og_title) ? htmlspecialchars($ws->og_title) : '' ?>" placeholder="Social Media Sharing Title">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">OG: Description</label>
                    <textarea name="og_description" class="form-control" rows="3" placeholder="Social Media Description..."><?= isset($ws->og_description) ? htmlspecialchars($ws->og_description) : '' ?></textarea>
                </div>

                <div class="mb-4 border-bottom pb-4">
                    <label class="form-label fw-bold small text-muted text-uppercase">OG: Image URL</label>
                    <input type="text" name="og_image" class="form-control" value="<?= isset($ws->og_image) ? htmlspecialchars((string)$ws->og_image) : '' ?>" placeholder="https://example.com/social-banner.jpg">
                    <div class="form-text">Image shown when shared on Facebook, Twitter, LinkedIn, etc.</div>
                </div>

                <h5 class="fw-bold mb-3 text-dark">Technical SEO</h5>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Canonical URL Prefix</label>
                    <input type="text" name="canonical_url" class="form-control" value="<?= isset($ws->canonical_url) ? htmlspecialchars((string)$ws->canonical_url) : '' ?>" placeholder="<?= base_url() ?>">
                    <div class="form-text">Optional. If set, canonical URLs use this origin plus the current path. Otherwise the live <code>current_url()</code> is used.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Default meta robots</label>
                    <input type="text" name="meta_robots_default" class="form-control" value="<?= $val('meta_robots_default') ?>" placeholder="index,follow">
                    <div class="form-text">Overridden per page in SEO Engine → Meta robots (when set).</div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="hreflang_enabled" value="1" id="hreflang_enabled"<?= $chk('hreflang_enabled') ?>>
                        <label class="form-check-label" for="hreflang_enabled">Emit <code>&lt;link rel="alternate" hreflang="…"&gt;</code> for current URL</label>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Hreflang codes (comma-separated)</label>
                    <input type="text" name="hreflang_codes" class="form-control" value="<?= $val('hreflang_codes') ?>" placeholder="en,x-default">
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Geo region</label>
                        <input type="text" name="geo_region" class="form-control" value="<?= $val('geo_region') ?>" placeholder="AE-DU">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">Geo position</label>
                        <input type="text" name="geo_position" class="form-control" value="<?= $val('geo_position') ?>" placeholder="25.2048;55.2708">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted text-uppercase">ICBM</label>
                        <input type="text" name="geo_icbm" class="form-control" value="<?= $val('geo_icbm') ?>" placeholder="25.2048, 55.2708">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Copyright meta</label>
                    <input type="text" name="meta_copyright" class="form-control" value="<?= $val('meta_copyright') ?>" placeholder="© 2026 Company Name">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Theme color (meta)</label>
                    <input type="text" name="meta_theme_color" class="form-control" value="<?= $val('meta_theme_color') ?>" placeholder="#0f172a">
                    <div class="form-text"><code>&lt;meta name="theme-color"&gt;</code> for mobile browser chrome (separate from CSS theme).</div>
                </div>

                <div class="mb-4 border-bottom pb-4">
                    <label class="form-label fw-bold small text-muted text-uppercase">Global Schema.org (JSON-LD)</label>
                    <textarea name="schema_markup" class="form-control font-monospace text-sm" rows="6" placeholder="{&#10;  &quot;@context&quot;: &quot;https://schema.org&quot;,&#10;  &quot;@type&quot;: &quot;Organization&quot;,&#10;  ...&#10;}"><?= isset($ws->schema_markup) ? htmlspecialchars($ws->schema_markup) : '' ?></textarea>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="inject_global_schema" value="1" id="inject_global_schema"<?= $chk('inject_global_schema') ?>>
                        <label class="form-check-label" for="inject_global_schema">Inject this JSON-LD on all webshop pages</label>
                    </div>
                </div>

                <h5 class="fw-bold mb-3 text-dark">Homepage Pharmacy schema (JSON-LD)</h5>
                <div class="mb-4">
                    <textarea name="homepage_pharmacy_schema" class="form-control font-monospace text-sm" rows="5" placeholder='{ "@context": "https://schema.org", "@type": "Pharmacy", ... }'><?= isset($ex['homepage_pharmacy_schema']) ? htmlspecialchars((string)$ex['homepage_pharmacy_schema']) : '' ?></textarea>
                    <div class="form-text">Injected only on the shop homepage. Must be one valid JSON object.</div>
                </div>

                <h5 class="fw-bold mb-3 text-dark">Blog: FAQ schema (JSON-LD)</h5>
                <div class="mb-4">
                    <textarea name="blog_faq_schema" class="form-control font-monospace text-sm" rows="5" placeholder='{ "@context": "https://schema.org", "@type": "FAQPage", "mainEntity": [ ... ] }'><?= isset($ex['blog_faq_schema']) ? htmlspecialchars((string)$ex['blog_faq_schema']) : '' ?></textarea>
                    <div class="form-text">Injected on blog article pages only. Public feed: <a href="<?= site_url('blog-rss.xml') ?>" target="_blank" rel="noopener"><?= site_url('blog-rss.xml') ?></a></div>
                </div>

                <h5 class="fw-bold mb-3 text-dark">Structured data toggles</h5>
                <div class="mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="enable_product_jsonld" value="1" id="enable_product_jsonld"<?= $chk('enable_product_jsonld') ?>>
                        <label class="form-check-label" for="enable_product_jsonld">Product JSON-LD on product detail pages</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="enable_article_jsonld" value="1" id="enable_article_jsonld"<?= $chk('enable_article_jsonld') ?>>
                        <label class="form-check-label" for="enable_article_jsonld">Article JSON-LD on blog posts</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="enable_rss_link" value="1" id="enable_rss_link"<?= $chk('enable_rss_link') ?>>
                        <label class="form-check-label" for="enable_rss_link">RSS <code>&lt;link&gt;</code> on blog list and posts</label>
                    </div>
                </div>
                <div class="mb-4 border-bottom pb-4">
                    <label class="form-label fw-bold small text-muted text-uppercase">RSS link title</label>
                    <input type="text" name="rss_feed_title" class="form-control" value="<?= $val('rss_feed_title') ?>" placeholder="Blog">
                </div>

                <h5 class="fw-bold mb-3 text-dark">AI discovery meta (optional)</h5>
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label small text-muted text-uppercase">AI Entity</label>
                        <input type="text" name="ai_entity" class="form-control" value="<?= $val('ai_entity') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted text-uppercase">AI Summary</label>
                        <input type="text" name="ai_summary" class="form-control" value="<?= $val('ai_summary') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted text-uppercase">AI Category</label>
                        <input type="text" name="ai_category" class="form-control" value="<?= $val('ai_category') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted text-uppercase">AI Industry</label>
                        <input type="text" name="ai_industry" class="form-control" value="<?= $val('ai_industry') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted text-uppercase">AI Brand</label>
                        <input type="text" name="ai_brand" class="form-control" value="<?= $val('ai_brand') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted text-uppercase">AI Purpose</label>
                        <input type="text" name="ai_purpose" class="form-control" value="<?= $val('ai_purpose') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted text-uppercase">AI Keyphrase</label>
                        <input type="text" name="ai_keyphrase" class="form-control" value="<?= $val('ai_keyphrase') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted text-uppercase">AI Context</label>
                        <input type="text" name="ai_context" class="form-control" value="<?= $val('ai_context') ?>">
                    </div>
                </div>

                <div class="pt-4 border-top">
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="bi bi-check2-circle me-2"></i> Save Advanced Settings
                    </button>
                    <a href="<?= site_url('admin/dashboard') ?>" class="btn btn-link text-muted">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
