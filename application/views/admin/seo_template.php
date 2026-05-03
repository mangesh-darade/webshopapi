<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= site_url('admin/dashboard') ?>">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= site_url('admin/pages') ?>">Site Pages</a></li>
            <li class="breadcrumb-item active">SEO Template</li>
        </ol>
    </nav>
    <h2 class="h3 fw-bold mb-0 text-dark">Apply One SEO Template To All Pages</h2>
    <p class="text-muted mb-0 mt-1">Set once and apply to all related theme pages.</p>
</div>

<?php if($this->session->flashdata('message')): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $this->session->flashdata('message') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if($this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $this->session->flashdata('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card bg-white p-4 border-0 shadow-sm" style="border-radius: 12px;">
    <form method="post" action="<?= site_url('admin/bulk_update_theme_seo') ?>">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <input type="hidden" name="bulk_save_seo" value="1">

        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Meta Title Template</label>
                <input type="text" class="form-control" name="template_meta_title" placeholder="{page} | Your Brand | Dubai">
                <small class="text-muted">Use <code>{page}</code> to auto insert page name.</small>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label fw-semibold">Meta Keywords Template</label>
                <input type="text" class="form-control" name="template_meta_keywords" placeholder="{page}, online store, pharmacy, dubai">
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Meta Description Template</label>
                <textarea class="form-control" rows="3" name="template_meta_description" placeholder="Explore {page} at Your Brand. Fast delivery and trusted quality in Dubai."></textarea>
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="only_empty" value="1" id="onlyEmptyFields">
                    <label class="form-check-label" for="onlyEmptyFields">
                        Update only empty SEO fields (keep existing values untouched)
                    </label>
                </div>
            </div>
        </div>

        <div class="text-end mt-3">
            <button type="submit" class="btn btn-primary fw-bold px-4">
                <i class="bi bi-magic me-1"></i> Apply Template To All Pages
            </button>
        </div>
    </form>
</div>

<div class="card bg-white p-4 border-0 shadow-sm mt-3" style="border-radius: 12px;">
    <h6 class="fw-bold mb-3">Template Cleanup</h6>
    <div class="d-flex flex-wrap gap-2">
        <form method="post" action="<?= site_url('admin/bulk_delete_theme_seo') ?>" onsubmit="return confirm('Clear template fields (title, keywords, description) for current theme pages?');">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <input type="hidden" name="bulk_delete_seo" value="1">
            <button type="submit" class="btn btn-outline-danger fw-bold px-4">
                <i class="bi bi-eraser me-1"></i> Clear Template Fields
            </button>
        </form>

        <form method="post" action="<?= site_url('admin/bulk_purge_theme_seo') ?>" onsubmit="return confirm('Hard delete SEO entries from JSON for current theme pages? This cannot be undone.');">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <input type="hidden" name="bulk_purge_seo" value="1">
            <button type="submit" class="btn btn-danger fw-bold px-4">
                <i class="bi bi-trash me-1"></i> Hard Delete SEO Entries
            </button>
        </form>
    </div>
</div>
