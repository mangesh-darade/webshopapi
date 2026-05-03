<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= site_url('admin/products') ?>">Products</a></li>
            <li class="breadcrumb-item active"><?= !empty($product) ? 'Edit Product' : 'New Product' ?></li>
        </ol>
    </nav>
    <h2 class="h3 fw-bold mb-0 text-dark"><?= !empty($product) ? 'Edit: '.$product['name'] : 'Create New Product' ?></h2>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card bg-white p-4">
            <form action="<?= site_url('admin/product_edit/'.(!empty($product) ? $product['id'] : '')) ?>" method="POST">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>" />
                
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-bold small text-muted text-uppercase">Product Name</label>
                        <input type="text" name="name" class="form-control" value="<?= !empty($product) ? $product['name'] : '' ?>" placeholder="e.g. Organic Honey" required>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted text-uppercase">Slug / URL Key</label>
                        <input type="text" name="slug" class="form-control" value="<?= !empty($product) ? $product['slug'] : '' ?>" placeholder="e.g. organic-honey">
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted text-uppercase">Category</label>
                        <select name="category" class="form-select">
                            <option value="">Select Category</option>
                            <option value="General" <?= (!empty($product) && $product['category'] == 'General') ? 'selected' : '' ?>>General</option>
                            <option value="Food" <?= (!empty($product) && $product['category'] == 'Food') ? 'selected' : '' ?>>Food</option>
                            <option value="Beauty" <?= (!empty($product) && $product['category'] == 'Beauty') ? 'selected' : '' ?>>Beauty</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold small text-muted text-uppercase">Description</label>
                        <textarea name="description" class="form-control" rows="5" placeholder="Detailed product specifications..."><?= !empty($product) ? $product['description'] : '' ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold small text-muted text-uppercase">Image URL</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light"><i class="bi bi-image"></i></span>
                            <input type="text" name="image_url" class="form-control" value="<?= !empty($product) ? $product['image_url'] : '' ?>" placeholder="https://example.com/image.jpg">
                        </div>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-top">
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="bi bi-check2-circle me-2"></i> Save Changes
                    </button>
                    <a href="<?= site_url('admin/products') ?>" class="btn btn-link text-muted">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <div class="col-12 col-xl-4">
        <div class="card bg-white p-4 mb-4">
            <h6 class="fw-bold mb-3">Live Preview</h6>
            <div class="rounded-3 bg-light overflow-hidden border mb-3">
                <div id="preview-image" style="height: 200px; display:flex; align-items:center; justify-content:center; background:#f8fafc;">
                    <?php if(!empty($product['image_url'])): ?>
                        <img src="<?= $product['image_url'] ?>" class="img-fluid" style="max-height:100%;" alt="">
                    <?php else: ?>
                        <i class="bi bi-image h1 text-muted opacity-25"></i>
                    <?php endif; ?>
                </div>
            </div>
            <h5 class="fw-bold mb-1" id="preview-name"><?= !empty($product) ? $product['name'] : 'Product Name' ?></h5>
            <p class="text-muted small" id="preview-category"><?= !empty($product) ? $product['category'] : 'Category' ?></p>
        </div>
    </div>
</div>
