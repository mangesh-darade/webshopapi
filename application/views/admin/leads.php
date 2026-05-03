<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 fw-bold mb-0 text-dark">Customer Inquiries</h2>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary btn-sm"><i class="bi bi-download me-1"></i> Export</button>
    </div>
</div>

<div class="card bg-white p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th class="ps-4">Date</th>
                    <th>Name</th>
                    <th>Contact</th>
                    <th>Message</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!empty($leads)): foreach($leads as $l): ?>
                <tr>
                    <td class="ps-4 text-muted small"><?= date('d M Y, H:i', strtotime($l['created_at'])) ?></td>
                    <td><div class="fw-bold"><?= $l['name'] ?></div></td>
                    <td>
                        <div class="small"><?= $l['email'] ?></div>
                        <div class="text-muted small"><?= $l['phone'] ?></div>
                    </td>
                    <td><div class="text-truncate" style="max-width: 250px;"><?= $l['message'] ?></div></td>
                    <td>
                        <span class="badge bg-warning-subtle text-warning border">New</span>
                    </td>
                    <td class="text-end pe-4">
                        <button class="btn btn-sm btn-light border"><i class="bi bi-reply"></i></button>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr>
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="bi bi-inbox d-block h1 mb-3 opacity-25"></i>
                        No new inquiries received.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
