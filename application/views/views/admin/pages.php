<style>
.action-btn {
    width: 60px;
    height: 56px;
    border-radius: 30px;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.2;
    background: #fff;
    border: 1px solid #e2e8f0;
    transition: all 0.2s;
}
.action-btn i {
    font-size: 18px;
    margin-bottom: 3px;
}
.action-btn.edit {
    color: #2563eb;
    border-color: #bfdbfe;
}
.action-btn.edit:hover {
    background: #eff6ff;
}
.action-btn.seo {
    color: #0f172a;
    border-color: #cbd5e1;
}
.action-btn.seo:hover {
    background: #f8fafc;
}
.action-btn.view {
    width: 40px;
    height: 56px;
    border-radius: 20px;
    color: #64748b;
    border-color: #e2e8f0;
}
.action-btn.view i {
    margin-bottom: 0;
}
.action-btn.view:hover {
    background: #f1f5f9;
}
.path-badge {
    background: #f8fafc;
    color: #64748b;
    padding: 6px 12px;
    border-radius: 6px;
    font-family: 'Courier New', Courier, monospace;
    font-size: 13px;
    font-weight: 500;
}
.type-badge {
    background: #fff;
    border: 1px solid #e2e8f0;
    color: #334155;
    padding: 6px 16px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 600;
}
.status-badge {
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 700;
    display: inline-block;
}
.status-badge.active {
    background: #ecfdf5;
    color: #047857;
    border: 1px solid #a7f3d0;
}
.status-badge.inactive {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
.row-title {
    color: #2563eb;
    font-weight: 800;
    font-size: 17px;
    margin-bottom: 5px;
}
.row-subtitle {
    color: #64748b;
    font-size: 14px;
}
.table-custom-header th {
    background: #fff !important;
    border-bottom: 1px solid #f1f5f9 !important;
    padding: 1.5rem 1rem !important;
    font-size: 0.75rem !important;
    font-weight: 800 !important;
    letter-spacing: 1px !important;
    color: #94a3b8 !important;
}
.table-custom-row td {
    padding: 1.5rem 1rem !important;
    border-color: #f8fafc !important;
    vertical-align: middle;
}
</style>

<div class="d-flex justify-content-between align-items-end mb-4">
    <div>
        <h2 class="h3 fw-bold mb-1 text-dark">Theme Pages</h2>
        <p class="text-muted mb-0">Showing only pages from the currently selected webshop theme.</p>
    </div>
    <div class="d-flex gap-2">
        <button form="bulkSeoRemoveForm" type="submit" class="btn btn-danger fw-bold shadow-sm" onclick="return confirm('Are you sure you want to remove SEO data for selected pages?');" style="border-radius: 8px; padding: 10px 20px;">
            <i class="bi bi-trash me-2"></i> Remove SEO Data
        </button>
        <button type="button" class="btn fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#createThemePageModal" style="background-color: #10b981; color: white; border-radius: 8px; padding: 10px 20px;">
            <i class="bi bi-plus-lg me-2"></i> Add Theme Page
        </button>
    </div>
</div>

<div class="modal fade" id="createThemePageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow" style="border-radius: 14px;">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold">Create New Theme Page</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="<?= site_url('admin/create_theme_page') ?>">
                <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
                <input type="hidden" name="create_theme_page" value="1">
                <div class="modal-body pt-3">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Page Name</label>
                            <input type="text" name="page_name" class="form-control" required placeholder="About Us 2">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Page Slug</label>
                            <input type="text" name="page_slug" class="form-control" placeholder="about_us_2">
                            <small class="text-muted">Used as file name and webshop URL segment.</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Page Title</label>
                            <input type="text" name="page_title" class="form-control" placeholder="About Us - Special Edition">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Page Placement</label>
                            <select name="page_placement" class="form-select">
                                <option value="none">Do not show in menus</option>
                                <option value="header">Show in Header</option>
                                <option value="footer">Show in Footer</option>
                                <option value="both">Show in Header + Footer</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="is_active" class="form-select">
                                <option value="1" selected>Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">
                        <i class="bi bi-file-earmark-plus me-1"></i> Create Page
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card bg-white p-4 border-0 shadow-sm" style="border-radius: 12px;">
    <div class="table-responsive">
        <form method="post" action="<?= site_url('admin/bulk_remove_seo_data') ?>" id="bulkSeoRemoveForm">
            <input type="hidden" name="<?= $this->security->get_csrf_token_name() ?>" value="<?= $this->security->get_csrf_hash() ?>">
            <table class="table table-hover mb-0" id="pagesTable">
                <thead class="table-custom-header">
                    <tr>
                        <th class="ps-4" style="width: 40px;">
                            <input class="form-check-input" type="checkbox" id="selectAllPages">
                        </th>
                        <th>TITLE</th>
                        <th>RELATIVE PATH</th>
                        <th>TYPE</th>
                        <th class="text-end pe-4">MANAGE SETTINGS</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Theme PHP Files -->
                    <?php if(!empty($theme_pages)): foreach($theme_pages as $tp): ?>
                    <?php 
                        $clean_name = str_replace('.php', '', $tp['filename']);
                        $is_index = ($clean_name === 'index');
                        $path_display = $is_index ? '/' : '/' . $clean_name;
                    ?>
                    <tr class="table-custom-row">
                        <td class="ps-4">
                            <input class="form-check-input page-checkbox" type="checkbox" name="selected_pages[]" value="<?= htmlspecialchars($tp['filename']) ?>">
                        </td>
                        <td>
                            <div class="row-title"><?= htmlspecialchars($tp['name']) ?></div>
                            <div class="row-subtitle"><?= htmlspecialchars($tp['filename']) ?></div>
                        </td>
                        <td>
                            <span class="path-badge"><?= $path_display ?></span>
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-2" style="align-items: flex-start;">
                                <span class="type-badge"><?= $is_index ? 'Core' : 'Custom' ?></span>
                                <?php $isActive = !isset($tp['is_active']) || (int)$tp['is_active'] === 1; ?>
                                <span class="status-badge <?= $isActive ? 'active' : 'inactive' ?>">
                                    <?= $isActive ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?= site_url('admin/seo?page=' . urlencode($tp['filename'])) ?>" class="action-btn seo">
                                    <i class="bi bi-search"></i>
                                    SEO
                                </a>
                                <a href="<?= site_url('webshop/' . ($is_index ? '' : $clean_name)) ?>" target="_blank" class="action-btn view">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </form>
    </div>
</div>

<!-- DataTables & jQuery Requirements -->
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css"/>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Master Checkbox Logic (Vanilla JS)
        const selectAll = document.getElementById('selectAllPages');
        
        if (selectAll) {
            selectAll.addEventListener('change', function() {
                // IMPORTANT: If DataTable is active, querySelectorAll only gets the current page.
                // We should select all checkboxes in the DOM or let DataTables handle it.
                // Since this form posts normally, all checkboxes inside the form are needed.
                // We'll use the DataTables API if available to check across all pages.
                const dt = $('#pagesTable').DataTable();
                if($.fn.DataTable.isDataTable('#pagesTable')) {
                    var cells = dt.cells().nodes();
                    $(cells).find('.page-checkbox').prop('checked', selectAll.checked);
                } else {
                    const checkboxes = document.querySelectorAll('.page-checkbox');
                    checkboxes.forEach(cb => cb.checked = selectAll.checked);
                }
            });
        }
        
        // DataTables Initialization
        if (typeof $ !== 'undefined') {
            $('#pagesTable').DataTable({
                "pageLength": 25,
                "ordering": true,
                "responsive": true,
                "columnDefs": [
                    { "orderable": false, "targets": [0, 4] }
                ]
            });
        }
    });
</script>
