<div class="mb-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= site_url('admin/dashboard') ?>">Home</a></li>
            <li class="breadcrumb-item active">Email Settings</li>
        </ol>
    </nav>
    <h2 class="h3 fw-bold mb-0 text-dark">SMTP & Email Configuration</h2>
</div>

<?php if($this->session->flashdata('message')): ?>
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm">
        <i class="bi bi-check-circle-fill me-2"></i> <?= $this->session->flashdata('message') ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card bg-white p-4 max-w-3xl">
    <form action="<?= site_url('admin/email_settings') ?>" method="POST">
        <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
        <input type="hidden" name="save_email" value="1">

        <h5 class="fw-bold mb-3 text-dark">SMTP Server Identity</h5>
        
        <div class="row g-3 mb-4">
            <div class="col-md-8">
                <label class="form-label fw-bold small text-muted text-uppercase">SMTP Host</label>
                <input type="text" name="smtp_host" class="form-control" value="<?= isset($ws->smtp_host) ? htmlspecialchars($ws->smtp_host) : '' ?>" placeholder="e.g. smtp.gmail.com">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted text-uppercase">SMTP Port</label>
                <input type="text" name="smtp_port" class="form-control" value="<?= isset($ws->smtp_port) ? htmlspecialchars($ws->smtp_port) : '' ?>" placeholder="e.g. 587 or 465">
            </div>
        </div>

        <div class="row g-3 mb-4 border-bottom pb-4">
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted text-uppercase">Encryption</label>
                <select name="smtp_encryption" class="form-select">
                    <option value="" <?= (empty($ws->smtp_encryption)) ? 'selected' : '' ?>>None</option>
                    <option value="tls" <?= (isset($ws->smtp_encryption) && $ws->smtp_encryption == 'tls') ? 'selected' : '' ?>>TLS (Recommended)</option>
                    <option value="ssl" <?= (isset($ws->smtp_encryption) && $ws->smtp_encryption == 'ssl') ? 'selected' : '' ?>>SSL</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted text-uppercase">SMTP Username</label>
                <input type="text" name="smtp_user" class="form-control" value="<?= isset($ws->smtp_user) ? htmlspecialchars($ws->smtp_user) : '' ?>" placeholder="username@example.com">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted text-uppercase">SMTP Password</label>
                <input type="password" name="smtp_pass" class="form-control" value="<?= isset($ws->smtp_pass) ? htmlspecialchars($ws->smtp_pass) : '' ?>" placeholder="••••••••">
            </div>
        </div>

        <h5 class="fw-bold mb-3 text-dark">Sender Information</h5>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label fw-bold small text-muted text-uppercase">From Email</label>
                <input type="email" name="from_email" class="form-control" value="<?= isset($ws->from_email) ? htmlspecialchars($ws->from_email) : '' ?>" placeholder="sales@yourshop.com">
                <div class="form-text">The address that customers see.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small text-muted text-uppercase">From Name</label>
                <input type="text" name="from_name" class="form-control" value="<?= isset($ws->from_name) ? htmlspecialchars($ws->from_name) : '' ?>" placeholder="ElintOm Webshop">
                <div class="form-text">The name displayed in the inbox.</div>
            </div>
        </div>

        <div class="pt-4 border-top d-flex justify-content-between align-items-center">
            <div>
                <button type="submit" class="btn btn-primary px-4 py-2">
                    <i class="bi bi-save me-2"></i> Save Settings
                </button>
                <a href="<?= site_url('admin/dashboard') ?>" class="btn btn-link text-muted">Cancel</a>
            </div>
            <button type="button" class="btn btn-outline-secondary" onclick="alert('Sending test email... Feature coming soon.')">
                <i class="bi bi-envelope-check me-2"></i> Send Test Email
            </button>
        </div>
    </form>
</div>
