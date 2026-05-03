<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="h3 fw-bold mb-0 text-dark">Blog Posts</h2>
        <div class="text-muted small mt-1">
            Active Theme:
            <span class="badge bg-info-subtle text-info"><?= htmlspecialchars(isset($current_webshop_theme) ? (string)$current_webshop_theme : 'default') ?></span>
        </div>
    </div>
    <a href="<?= site_url('admin/blog_edit') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-2"></i> New Post
    </a>
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

<?php if($this->input->get('deleted')): ?>
    <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm">
        <i class="bi bi-trash-fill me-2"></i> Blog post deleted.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card bg-white p-0 overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Post</th>
                    <th>Slug</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($blogs)): foreach($blogs as $b): ?>
                <tr>
                    <td class="ps-4">
                        <div class="d-flex align-items-center gap-3">
                            <?php if(!empty($b['image'])): ?>
                                <img src="<?= $b['image'] ?>" class="rounded-3 border" style="width:48px;height:48px;object-fit:cover;" alt="">
                            <?php else: ?>
                                <div class="rounded-3 bg-light border d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                                    <i class="bi bi-journal text-muted"></i>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($b['title']) ?></div>
                                <div class="text-muted small"><?= substr(strip_tags($b['content'] ?? ''), 0, 60) ?>...</div>
                            </div>
                        </div>
                    </td>
                    <td><code class="small"><?= $b['slug'] ?? '—' ?></code></td>
                    <td>
                        <?php if(!empty($b['is_active'])): ?>
                            <span class="badge bg-success-subtle text-success">Published</span>
                        <?php else: ?>
                            <span class="badge bg-secondary-subtle text-secondary">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= !empty($b['updated_at']) ? date('d M Y', strtotime($b['updated_at'])) : '—' ?></td>
                    <td class="text-end pe-4">
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light border p-2" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px;">
                                <li><a class="dropdown-item py-2" href="<?= site_url('admin/blog_edit/'.$b['id']) ?>"><i class="bi bi-pencil me-2"></i> Edit</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item py-2 text-danger" href="<?= site_url('admin/blogs?delete='.$b['id']) ?>" onclick="return confirm('Delete this post?')"><i class="bi bi-trash me-2"></i> Delete</a></li>
                            </ul>
                        </div>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="5" class="text-center py-5 text-muted">
                        <i class="bi bi-journal-richtext d-block h1 mb-3 opacity-25"></i>
                        No blog posts yet. <a href="<?= site_url('admin/blog_edit') ?>">Create your first post →</a>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
