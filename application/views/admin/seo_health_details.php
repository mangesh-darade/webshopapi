<div class="mb-4 d-flex justify-content-between align-items-center">
    <div>
        <h2 class="h3 fw-bold mb-1 text-dark"><?= isset($seo_detail_title) ? htmlspecialchars($seo_detail_title) : 'SEO Health Details' ?></h2>
        <p class="text-muted mb-0">Total records: <strong><?= isset($seo_detail_count) ? (int)$seo_detail_count : 0 ?></strong></p>
    </div>
    <a href="<?= site_url('admin/dashboard') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
    </a>
</div>

<div class="card bg-white border-0 shadow-sm p-0" style="border-radius: 12px;">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th class="text-uppercase small text-muted">#</th>
                    <?php if (!empty($seo_detail_columns) && is_array($seo_detail_columns)): ?>
                        <?php foreach ($seo_detail_columns as $column): ?>
                            <th class="text-uppercase small text-muted"><?= htmlspecialchars($column) ?></th>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($seo_detail_rows) && is_array($seo_detail_rows)): ?>
                    <?php foreach ($seo_detail_rows as $index => $row): ?>
                        <tr>
                            <td><?= (int)$index + 1 ?></td>
                            <td><?= htmlspecialchars(isset($row['source']) ? $row['source'] : (isset($row['id']) ? (string)$row['id'] : '-')) ?></td>
                            <td><?= htmlspecialchars(isset($row['name']) ? $row['name'] : '-') ?></td>
                            <td><?= htmlspecialchars(isset($row['value']) ? $row['value'] : '-') ?></td>
                            <td>
                                <?php $status = isset($row['status']) ? $row['status'] : '-'; ?>
                                <span class="badge <?= $status === 'Excluded' ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No records found for this metric.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
