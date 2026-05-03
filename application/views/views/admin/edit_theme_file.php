<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1">
                <li class="breadcrumb-item"><a href="<?= site_url('admin/dashboard') ?>" class="text-decoration-none">Home</a></li>
                <li class="breadcrumb-item"><a href="<?= site_url('admin/pages') ?>" class="text-decoration-none">Site Pages</a></li>
                <li class="breadcrumb-item active">Code Editor</li>
            </ol>
        </nav>
        <h2 class="h3 fw-bold mb-0 text-dark d-flex align-items-center">
            <i class="bi bi-code-slash text-primary me-2"></i> Editing: <?= htmlspecialchars($file_name) ?>
        </h2>
    </div>
    <a href="<?= site_url('admin/pages') ?>" class="btn btn-outline-secondary px-4 py-2 fw-bold" style="border-radius: 8px;">
        <i class="bi bi-arrow-left me-2"></i> Back to Pages
    </a>
</div>

<?php if($this->session->flashdata('message')): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" style="border-radius: 8px;">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $this->session->flashdata('message') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if($this->session->flashdata('error')): ?>
    <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" style="border-radius: 8px;">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $this->session->flashdata('error') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card p-0 border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center px-4 py-3 border-0">
        <span class="font-monospace small">~ /themes/default/views/webshop/<?= htmlspecialchars($file_name) ?></span>
        <span class="badge bg-secondary">PHP Parser</span>
    </div>
    
    <form action="<?= site_url('admin/edit_theme_file?file=' . urlencode($file_name)) ?>" method="POST" id="codeEditorForm" class="m-0">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <input type="hidden" name="save_file" value="1">
        
        <textarea name="file_content" class="form-control font-monospace border-0" 
                  style="min-height: 50vh; border-radius: 0; background: #1e1e1e; color: #d4d4d4; padding: 20px; font-size: 14px; line-height: 1.6; resize: vertical;" 
                  spellcheck="false"><?= htmlspecialchars($file_content) ?></textarea>
        
        <div class="bg-light p-3 border-top d-flex justify-content-between align-items-center">
            <span class="text-muted small"><i class="bi bi-info-circle me-1"></i> Warning: Syntax errors can crash your webshop. Save carefully.</span>
            <button type="submit" class="btn btn-primary px-4 fw-bold" style="border-radius: 8px;">
                <i class="bi bi-save me-2"></i> Save Changes
            </button>
        </div>
    </form>
</div>

<!-- Enable basic tab insertion in textarea -->
<script>
document.querySelector('textarea[name="file_content"]').addEventListener('keydown', function(e) {
    if (e.key == 'Tab') {
        e.preventDefault();
        var start = this.selectionStart;
        var end = this.selectionEnd;
        // set textarea value to: text before caret + tab + text after caret
        this.value = this.value.substring(0, start) + "\t" + this.value.substring(end);
        // put caret at right position again
        this.selectionStart = this.selectionEnd = start + 1;
    }
});
</script>
