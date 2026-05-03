<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= site_url('admin/blogs') ?>">Blog Posts</a></li>
            <li class="breadcrumb-item active"><?= !empty($blog) ? 'Edit Post' : 'New Post' ?></li>
        </ol>
    </nav>
    <h2 class="h3 fw-bold mb-0 text-dark"><?= !empty($blog) ? 'Edit: '.htmlspecialchars($blog['title']) : 'Create New Post' ?></h2>
    <div class="text-muted small mt-1">
        Saving to Theme:
        <span class="badge bg-info-subtle text-info"><?= htmlspecialchars(isset($current_webshop_theme) ? (string)$current_webshop_theme : 'default') ?></span>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card bg-white p-4">
            <form action="<?= site_url('admin/blog_edit/'.(!empty($blog) ? $blog['id'] : '')) ?>" method="POST">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Post Title</label>
                    <input type="text" name="title" class="form-control" value="<?= htmlspecialchars($blog['title'] ?? '') ?>" placeholder="e.g. 10 Tips for Healthy Living" required>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Content</label>
                    <textarea name="content" class="form-control" rows="12" placeholder="Write your post content here..."><?= htmlspecialchars($blog['content'] ?? '') ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold small text-muted text-uppercase">Featured Image URL</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-image"></i></span>
                        <input type="text" name="image" class="form-control" value="<?= $blog['image'] ?? '' ?>" placeholder="https://example.com/image.jpg">
                    </div>
                </div>

                <div class="form-check form-switch mb-4">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" <?= (!empty($blog['is_active'])) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-bold" for="is_active">Published</label>
                </div>

                <div class="pt-4 border-top">
                    <button type="submit" class="btn btn-primary px-4 py-2">
                        <i class="bi bi-check2-circle me-2"></i> Save Post
                    </button>
                    <a href="<?= site_url('admin/blogs') ?>" class="btn btn-link text-muted">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card bg-white p-4">
            <h6 class="fw-bold mb-3">Publishing Tips</h6>
            <ul class="list-unstyled text-muted small">
                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Use a compelling title</li>
                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Add a featured image</li>
                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Keep content concise</li>
                <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i> Toggle Published when ready</li>
            </ul>
        </div>
    </div>
</div>
