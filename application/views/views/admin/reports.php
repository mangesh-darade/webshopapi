<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3 fw-bold mb-0 text-dark">Analytics & Reports</h2>
    <div class="d-flex gap-2">
        <button class="btn btn-outline-secondary"><i class="bi bi-download me-1"></i> Export PDF</button>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-12 col-md-3">
        <div class="card bg-white p-4 text-center">
            <h6 class="text-muted mb-2 text-uppercase small fw-bold">Total Orders</h6>
            <h2 class="mb-0 fw-bold"><?= number_format($total_orders) ?></h2>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card bg-white p-4 text-center">
            <h6 class="text-muted mb-2 text-uppercase small fw-bold">Total Revenue</h6>
            <h2 class="mb-0 fw-bold text-success"><?= number_format(isset($total_revenue) ? $total_revenue : 0, 2) ?> AED</h2>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card bg-white p-4 text-center">
            <h6 class="text-muted mb-2 text-uppercase small fw-bold">Total Customers</h6>
            <h2 class="mb-0 fw-bold text-primary"><?= number_format(isset($total_customers) ? $total_customers : 0) ?></h2>
        </div>
    </div>
    <div class="col-12 col-md-3">
        <div class="card bg-white p-4 text-center">
            <h6 class="text-muted mb-2 text-uppercase small fw-bold">Pending Orders</h6>
            <h2 class="mb-0 fw-bold text-warning"><?= number_format(isset($pending_orders) ? $pending_orders : 0) ?></h2>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-12 col-xl-8">
        <div class="card bg-white p-0">
            <div class="p-4 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Sales (Last 6 Months)</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th class="ps-4">Month</th>
                            <th class="text-end">Orders</th>
                            <th class="text-end pe-4">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(!empty($monthly_stats)): foreach($monthly_stats as $ms): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= $ms['month'] ?></td>
                            <td class="text-end"><?= $ms['count'] ?></td>
                            <td class="text-end pe-4 text-success fw-bold"><?= number_format($ms['revenue'], 2) ?> AED</td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="3" class="text-center py-4 text-muted">No sales data available.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-12 col-xl-4">
        <div class="card bg-white p-0">
            <div class="p-4 border-bottom">
                <h6 class="fw-bold mb-0">Top 5 Products</h6>
            </div>
            <ul class="list-group list-group-flush">
                <?php if(!empty($top_products)): foreach($top_products as $idx => $tp): ?>
                <li class="list-group-item p-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <span class="badge bg-light text-dark border"><?= $idx+1 ?></span>
                        <span class="fw-bold"><?= $tp['name'] ?></span>
                    </div>
                    <span class="text-muted small"><?= $tp['total_qty'] ?> sold</span>
                </li>
                <?php endforeach; else: ?>
                <li class="list-group-item p-4 text-center text-muted">No product data available.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>
