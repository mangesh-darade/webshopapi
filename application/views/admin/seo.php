<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= site_url('admin/dashboard') ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= site_url('admin/pages') ?>">Site Pages</a></li>
            <li class="breadcrumb-item active">SEO</li>
        </ol>
    </nav>
    <h2 class="h3 fw-bold mb-0 text-dark">
        Search Engine Optimization
        <?php if(!empty($seo_page)): ?>
            <span class="badge bg-primary fs-6 fw-normal ms-2 text-white px-3" style="border-radius: 8px;">File: <?= htmlspecialchars($seo_page) ?></span>
        <?php else: ?>
            <span class="badge bg-secondary fs-6 fw-normal ms-2 text-white px-3" style="border-radius: 8px;">Global Layout</span>
        <?php endif; ?>
    </h2>
</div>

<?php if($this->session->flashdata('message')): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $this->session->flashdata('message') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card bg-white p-4 mb-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <form action="<?= site_url('admin/seo') ?>" method="GET" id="seoPageSelector">
                <div class="mb-0">
                    <label class="form-label fw-bold small text-muted text-uppercase">Select Page to Edit</label>
                    <select name="page" class="form-select" onchange="document.getElementById('seoPageSelector').submit();">
                        <option value="">Global Layout (Default)</option>
                        <?php if(isset($theme_pages_dropdown) && is_array($theme_pages_dropdown)): ?>
                            <?php foreach($theme_pages_dropdown as $tp): ?>
                                <option value="<?= htmlspecialchars($tp) ?>" <?= (isset($seo_page) && $seo_page === $tp) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', basename($tp, '.php')))) ?> (<?= htmlspecialchars($tp) ?>)
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </form>
        </div>
        
        <div class="card bg-white p-4" style="border-radius: 12px; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
            <form action="<?= site_url('admin/seo') ?>" method="POST">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                <input type="hidden" name="save_seo" value="1">
                <?php if(!empty($seo_page)): ?>
                    <input type="hidden" name="page" value="<?= htmlspecialchars($seo_page) ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Meta Title</label>
                    <input type="text" name="meta_title" class="form-control" value="<?= isset($ws->meta_title) ? htmlspecialchars($ws->meta_title) : '' ?>" placeholder="e.g. ElintOm Default Shop" required>
                    <div class="form-text">The title that appears in search engine results.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Meta Description</label>
                    <textarea name="meta_description" class="form-control" rows="4" placeholder="Brief description of your shop..."><?= isset($ws->meta_description) ? htmlspecialchars($ws->meta_description) : '' ?></textarea>
                    <div class="form-text">Aim for 150-160 characters for best SEO results.</div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small text-muted text-uppercase">Meta Keywords</label>
                    <input type="text" name="meta_keywords" class="form-control" value="<?= isset($ws->meta_keywords) ? htmlspecialchars($ws->meta_keywords) : '' ?>" placeholder="e.g. shop, electronics, accessories">
                    <div class="form-text">Comma separated list of keywords relevant to your store.</div>
                </div>

                <?php if(!empty($seo_page)): ?>
                <div class="mb-4">
                    <label class="form-label fw-bold small text-muted text-uppercase">Meta robots</label>
                    <input type="text" name="meta_robots" class="form-control" value="<?= isset($ws->meta_robots) ? htmlspecialchars($ws->meta_robots) : '' ?>" placeholder="e.g. index,follow or noindex,nofollow">
                    <div class="form-text">Leave empty to use the default from <a href="<?= site_url('admin/seo_advanced') ?>">Advanced SEO</a>.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label fw-bold small text-muted text-uppercase">Extra Meta Tags</label>
                    <textarea name="meta_tags" class="form-control font-monospace" rows="3" placeholder='e.g. &lt;meta property="og:title" content="..."&gt;'><?= isset($ws->meta_tags) ? htmlspecialchars($ws->meta_tags) : '' ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small text-muted text-uppercase">Page Placement</label>
                    <?php $placement = isset($ws->page_placement) ? $ws->page_placement : 'none'; ?>
                    <select name="page_placement" class="form-select">
                        <option value="none" <?= $placement === 'none' ? 'selected' : '' ?>>Do not show in menus</option>
                        <option value="header" <?= $placement === 'header' ? 'selected' : '' ?>>Show in Header</option>
                        <option value="footer" <?= $placement === 'footer' ? 'selected' : '' ?>>Show in Footer</option>
                        <option value="both" <?= $placement === 'both' ? 'selected' : '' ?>>Show in Header + Footer</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold small text-muted text-uppercase">Page Status</label>
                    <?php $activeStatus = isset($ws->is_active) ? (int)$ws->is_active : 1; ?>
                    <select name="is_active" class="form-select">
                        <option value="1" <?= $activeStatus === 1 ? 'selected' : '' ?>>Active</option>
                        <option value="0" <?= $activeStatus === 0 ? 'selected' : '' ?>>Inactive</option>
                    </select>
                    <div class="form-text">Inactive pages are hidden from webshop and menu links.</div>
                </div>
                <?php endif; ?>

                <div class="pt-4 border-top">
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="bi bi-check2-circle me-2"></i> Save SEO Settings
                    </button>
                    <a href="<?= site_url('admin/dashboard') ?>" class="btn btn-link text-muted">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="col-12 col-xl-4">
        <div class="card bg-white p-4">
            <h6 class="fw-bold mb-3"><i class="bi bi-google me-2 text-primary"></i> Live Preview</h6>
            <div class="p-3 bg-light rounded text-break" style="font-family: Arial, sans-serif;">
                <div style="color: #1a0dab; font-size: 20px; font-weight: normal; margin-bottom: 2px;">
                    <?= isset($ws->meta_title) && $ws->meta_title ? htmlspecialchars($ws->meta_title) : 'Your Site Title Here' ?>
                </div>
                <div style="color: #006621; font-size: 14px; margin-bottom: 2px;">
                    <?= base_url() ?>
                </div>
                <div style="color: #545454; font-size: 14px; line-height: 1.4;">
                    <?= isset($ws->meta_description) && $ws->meta_description ? htmlspecialchars($ws->meta_description) : 'Your meta description will appear here on search results.' ?>
                </div>
            </div>
        </div>
    </div>
</div>
