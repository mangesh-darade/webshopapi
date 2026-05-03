<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 fw-bold mb-0 text-dark">Product Catalog</h2>
    <a href="<?= site_url('admin/product_edit') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i> Add New Product
    </a>
</div>

<?php if($this->input->get('saved')): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i> Product saved successfully!
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if($this->input->get('deleted')): ?>
    <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm" role="alert">
        <i class="bi bi-trash-fill me-2"></i> Product deleted successfully.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<div class="card bg-white p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Product Info</th>
                    <th>Category</th>
                    <th>Pricing</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($products)): foreach($products as $p): ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center">
                            <div class="rounded-3 bg-light p-1 me-3 border" style="width:48px; height:48px; display:flex; align-items:center; justify-content:center;">
                                <?php if(!empty($p['image_url'])): ?>
                                    <img src="<?= $p['image_url'] ?>" class="img-fluid rounded" alt="">
                                <?php else: ?>
                                    <i class="bi bi-image text-muted"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="fw-bold text-dark"><?= $p['name'] ?></div>
                                <div class="text-muted small"><?= $p['code'] ?: 'No Code' ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="badge bg-light text-dark border"><?= $p['category'] ?: 'General' ?></span>
                    </td>
                    <td>
                        <div class="fw-bold text-dark"><?= number_format($p['price'], 2) ?> AED</div>
                        <div class="text-muted small">Cost: <?= number_format($p['cost'], 2) ?></div>
                    </td>
                    <td>
                        <span class="badge bg-success-subtle text-success">Active</span>
                    </td>
                    <td class="text-end pe-4">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light border p-2" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius: 12px;">
                                <li><a class="dropdown-item py-2" href="<?= site_url('admin/product_edit/'.$p['id']) ?>"><i class="bi bi-pencil me-2"></i> Edit Product</a></li>
                                <li><a class="dropdown-item py-2" target="_blank" href="<?= site_url('products/'.$p['id']) ?>"><i class="bi bi-eye me-2"></i> View on Webshop</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger" href="<?= site_url('admin/products?delete='.$p['id']) ?>" onclick="return confirm('Are you sure you want to delete this product?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">
                        <i class="bi bi-search d-block h1 mb-3 opacity-25"></i>
                        No products found in the catalog.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
