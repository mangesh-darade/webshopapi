<div class="row g-4 mb-4">
    <div class="col-12 col-md-4">
        <div class="card bg-white p-4 d-flex flex-row align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: #f0fdf4; color: #10b981;">
                <i class="bi bi-person-lines-fill h4 mb-0"></i>
            </div>
            <div>
                <h6 class="text-muted mb-1 fw-bold text-uppercase" style="font-size: 0.75rem;">Today's Inquiries</h6>
                <h3 class="mb-0 fw-bold"><?= isset($today_leads) ? $today_leads : 0 ?></h3>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card bg-white p-4 d-flex flex-row align-items-center gap-3">
            <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px; background-color: #f8fafc; color: #3b82f6;">
                <i class="bi bi-box-seam-fill h4 mb-0"></i>
            </div>
            <div>
                <h6 class="text-muted mb-1 fw-bold text-uppercase" style="font-size: 0.75rem;">Total Products</h6>
                <h3 class="mb-0 fw-bold"><?= isset($total_products) ? $total_products : 0; ?></h3>
            </div>
        </div>
    </div>
</div>

<div class="card bg-white border-0 shadow-sm p-4">
    <h5 class="fw-bold mb-3">Quick Actions</h5>
    <div class="d-flex flex-wrap gap-3">
        <a href="<?= site_url('admin/product_edit') ?>" class="btn btn-outline-primary"><i class="bi bi-plus"></i> Add Product</a>
        <a href="<?= site_url('admin/blogs') ?>" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> Write Post</a>
        <a href="<?= site_url('admin/leads') ?>" class="btn btn-outline-info"><i class="bi bi-envelope"></i> Check Leads</a>
    </div>
</div>

<div class="card bg-white border-0 shadow-sm p-4 mt-4">
    <h5 class="fw-bold mb-3">SEO Health Overview</h5>
    <div class="row g-3">
        <div class="col-12 col-md-3">
            <a href="<?= site_url('admin/seo_health_details?type=indexed_pages') ?>" class="p-3 rounded-3 border h-100 d-block text-decoration-none">
                <div class="text-muted small text-uppercase fw-bold">Indexed Pages</div>
                <div class="h3 mb-0 fw-bold text-success"><?= isset($seo_health['indexed_pages']) ? (int)$seo_health['indexed_pages'] : 0 ?></div>
                <div class="small mt-2 text-primary">Click to view details</div>
            </a>
        </div>
        <div class="col-12 col-md-3">
            <a href="<?= site_url('admin/seo_health_details?type=active_categories') ?>" class="p-3 rounded-3 border h-100 d-block text-decoration-none">
                <div class="text-muted small text-uppercase fw-bold">Active Categories</div>
                <div class="h3 mb-0 fw-bold text-primary"><?= isset($seo_health['active_categories']) ? (int)$seo_health['active_categories'] : 0 ?></div>
                <div class="small mt-2 text-primary">Click to view details</div>
            </a>
        </div>
        <div class="col-12 col-md-3">
            <a href="<?= site_url('admin/seo_health_details?type=active_products') ?>" class="p-3 rounded-3 border h-100 d-block text-decoration-none">
                <div class="text-muted small text-uppercase fw-bold">Active Products</div>
                <div class="h3 mb-0 fw-bold text-info"><?= isset($seo_health['active_products']) ? (int)$seo_health['active_products'] : 0 ?></div>
                <div class="small mt-2 text-primary">Click to view details</div>
            </a>
        </div>
        <div class="col-12 col-md-3">
            <a href="<?= site_url('admin/seo_health_details?type=excluded_inactive_pages') ?>" class="p-3 rounded-3 border h-100 d-block text-decoration-none">
                <div class="text-muted small text-uppercase fw-bold">Excluded (Inactive)</div>
                <div class="h3 mb-0 fw-bold text-danger"><?= isset($seo_health['excluded_inactive_pages']) ? (int)$seo_health['excluded_inactive_pages'] : 0 ?></div>
                <div class="small mt-2 text-primary">Click to view details</div>
            </a>
        </div>
    </div>
</div>

<div class="card bg-white border-0 shadow-sm p-4 mt-4">
    <h5 class="fw-bold mb-3">SEO Technical Tools</h5>
    <div class="d-flex flex-wrap gap-3">
        <a href="<?= site_url('sitemap-index.xml') ?>" target="_blank" class="btn btn-outline-success"><i class="bi bi-diagram-3"></i> Sitemap Index</a>
        <a href="<?= site_url('sitemap-pages.xml') ?>" target="_blank" class="btn btn-outline-success"><i class="bi bi-file-earmark-code"></i> Pages Sitemap</a>
        <a href="<?= site_url('sitemap-categories.xml') ?>" target="_blank" class="btn btn-outline-success"><i class="bi bi-list-ul"></i> Categories Sitemap</a>
        <a href="<?= site_url('sitemap-products.xml') ?>" target="_blank" class="btn btn-outline-success"><i class="bi bi-box-seam"></i> Products Sitemap</a>
        <a href="<?= site_url('robots.txt') ?>" target="_blank" class="btn btn-outline-dark"><i class="bi bi-robot"></i> Robots.txt</a>
    </div>
</div>
