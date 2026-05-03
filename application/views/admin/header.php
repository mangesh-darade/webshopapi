<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ElintOm Admin Panel (MVC Integrated)</title>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --sidebar-width: 280px;
            --bg-light: #f8fafc;
            --sidebar-bg: #ffffff;
            --sidebar-text: #64748b;
            --sidebar-hover: #f1f5f9;
            --sidebar-active-bg: #f0fdf4;
            --sidebar-active-text: #10b981;
            --card-shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-light);
            color: #1e293b;
            overflow-x: hidden;
        }

        /* Sidebar Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            height: 100vh;
            max-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            background: var(--sidebar-bg);
            border-right: 1px solid #e2e8f0;
            padding: 1.5rem;
            z-index: 1000;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .sidebar-scroll {
            flex: 1 1 auto;
            min-height: 0;
            overflow-y: auto;
            overflow-x: hidden;
            -webkit-overflow-scrolling: touch;
            overscroll-behavior: contain;
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 transparent;
            margin-right: -0.35rem;
            padding-right: 0.35rem;
        }

        .sidebar-scroll::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar-scroll::-webkit-scrollbar-track {
            background: transparent;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 6px;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .brand-logo {
            font-weight: 800;
            font-size: 1.5rem;
            color: #0f172a;
            letter-spacing: -1px;
            margin-bottom: 2.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            flex-shrink: 0;
        }

        .brand-logo i { color: var(--primary); font-size: 1.75rem; }

        .nav-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #94a3b8;
            font-weight: 700;
            margin-bottom: 1rem;
            padding-left: 0.75rem;
        }

        .sidebar-nav { display: flex; flex-direction: column; gap: 0.35rem; }

        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            text-decoration: none !important;
            border-radius: 10px;
            color: var(--sidebar-text);
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-link i { font-size: 1.1rem; }

        .sidebar-link:hover {
            background: var(--sidebar-hover);
            color: #0f172a;
        }

        .sidebar-link.active {
            background: var(--sidebar-active-bg);
            color: var(--sidebar-active-text);
        }

        /* Content Area Container Styles */
        .page-content {
            margin-left: var(--sidebar-width);
            padding: 2.5rem;
            min-height: 100vh;
        }

        .top-navbar {
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Glass Cards Styles */
        .card {
            border: none;
            box-shadow: var(--card-shadow);
            border-radius: 16px;
            transition: transform 0.2s;
        }
        
        /* Premium Table Styles */
        .table {
            --bs-table-hover-bg: #f8fafc;
        }
        .table thead th {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            text-transform: uppercase;
            font-size: 0.65rem;
            font-weight: 700;
            color: #64748b;
            letter-spacing: 0.5px;
            padding: 1.25rem 1rem;
        }
        .table tbody td { padding: 1.25rem 1rem; border-color: #f1f5f9; }

        /* Modern Inputs Styles */
        .form-control, .form-select {
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid #e2e8f0;
            background-color: #ffffff;
            box-shadow: none !important;
        }
        .form-control:focus { border-color: var(--primary); border-width: 2px; }

        .btn-primary { 
            background: var(--primary); 
            border: none; 
            padding: 0.6rem 1.25rem; 
            border-radius: 10px; 
            font-weight: 600;
        }
        .btn-primary:hover { background: var(--primary-dark); }
        
        .badge { border-radius: 8px; padding: 0.4em 0.8em; font-weight: 600; }

        .sidebar-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s;
            pointer-events: none;
        }

        body.admin-sidebar-open .sidebar-overlay {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }

        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                width: min(100%, var(--sidebar-width));
                max-width: 320px;
                max-height: 100dvh;
                height: 100dvh;
            }
            body.admin-sidebar-open .sidebar {
                transform: translateX(0);
                box-shadow: 4px 0 24px rgba(0, 0, 0, 0.12);
            }
            body.admin-sidebar-open {
                overflow: hidden;
            }
            .page-content { margin-left: 0; padding: 1.5rem; }
        }
    </style>
</head>
<body>

    <div class="sidebar-overlay" id="adminSidebarOverlay" aria-hidden="true"></div>

    <!-- Sidebar Start -->
    <div class="sidebar" id="adminSidebar">
        <div class="brand-logo">
            <i class="bi bi-shield-check"></i> ElintOm Admin
            <button type="button" class="btn btn-sm btn-light border ms-auto d-lg-none admin-sidebar-toggle p-2" aria-label="Close menu">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>

        <div class="sidebar-scroll">
        <div class="nav-label">Operations</div>
        <div class="sidebar-nav mb-4">
            <?php $current_page = $this->router->fetch_method(); ?>
            <a href="<?= site_url('admin/dashboard') ?>" class="sidebar-link <?= $current_page == 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-house-door-fill"></i> Home
            </a>
            <a href="<?= site_url('admin/leads') ?>" class="sidebar-link <?= $current_page == 'leads' ? 'active' : '' ?>">
                <i class="bi bi-inboxes-fill"></i> Inquiries
                <?php
                if(isset($today_leads) && $today_leads > 0) {
                    echo "<span class='badge bg-danger ms-auto'>$today_leads</span>";
                }
                ?>
            </a>
            <a href="<?= site_url('admin/products') ?>" class="sidebar-link <?= in_array($current_page, ['products', 'product_edit']) ? 'active' : '' ?>">
                <i class="bi bi-tags-fill"></i> Products
            </a>
        </div>

        <div class="nav-label">Content & Marketing</div>
        <div class="sidebar-nav mb-4">
            <a href="<?= site_url('admin/pages') ?>" class="sidebar-link <?= $current_page == 'pages' ? 'active' : '' ?>">
                <i class="bi bi-layout-text-window-reverse"></i> Site Pages
            </a>
            <a href="<?= site_url('admin/seo_template') ?>" class="sidebar-link <?= $current_page == 'seo_template' ? 'active' : '' ?>">
                <i class="bi bi-magic"></i> SEO Template
            </a>
            <a href="<?= site_url('admin/blogs') ?>" class="sidebar-link <?= in_array($current_page, ['blogs', 'blog_edit']) ? 'active' : '' ?>">
                <i class="bi bi-journal-richtext"></i> Blog Posts
            </a>
            <a href="<?= site_url('admin/seo') ?>" class="sidebar-link <?= $current_page == 'seo' ? 'active' : '' ?>">
                <i class="bi bi-search"></i> SEO Engine
            </a>
            <a href="<?= site_url('admin/seo_advanced') ?>" class="sidebar-link <?= $current_page == 'seo_advanced' ? 'active' : '' ?>">
                <i class="bi bi-gear-wide-connected"></i> Advanced SEO
            </a>
            <a href="<?= site_url('sitemap-index.xml') ?>" target="_blank" class="sidebar-link">
                <i class="bi bi-diagram-3"></i> Sitemap Index
            </a>
            <a href="<?= site_url('sitemap-pages.xml') ?>" target="_blank" class="sidebar-link">
                <i class="bi bi-file-earmark-code"></i> Pages Sitemap
            </a>
            <a href="<?= site_url('sitemap-categories.xml') ?>" target="_blank" class="sidebar-link">
                <i class="bi bi-list-ul"></i> Categories Sitemap
            </a>
            <a href="<?= site_url('sitemap-products.xml') ?>" target="_blank" class="sidebar-link">
                <i class="bi bi-box-seam"></i> Products Sitemap
            </a>
            <a href="<?= site_url('robots.txt') ?>" target="_blank" class="sidebar-link">
                <i class="bi bi-robot"></i> Robots.txt
            </a>
            <a href="<?= site_url('admin/reports') ?>" class="sidebar-link <?= $current_page == 'reports' ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow"></i> Analytics & Reports
            </a>
        </div>

        <div class="nav-label">Settings</div>
        <div class="sidebar-nav">
            <a href="<?= site_url('admin/email_settings') ?>" class="sidebar-link <?= $current_page == 'email_settings' ? 'active' : '' ?>">
                <i class="bi bi-envelope-open-fill"></i> Email Setup
            </a>
             <a href="<?= site_url('welcome') ?>" class="sidebar-link text-primary mt-2">
                <i class="bi bi-box-arrow-left"></i> POS Portal
            </a>
            <a href="<?= site_url('admin/logout') ?>" class="sidebar-link text-danger">
                <i class="bi bi-power"></i> Log Out
            </a>
        </div>
        </div>
    </div>
    <!-- Sidebar End -->

    <!-- Main Wrapper Start -->
    <div class="page-content">
        <header class="top-navbar">
            <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
                <button type="button" class="btn btn-light border d-lg-none flex-shrink-0 admin-sidebar-toggle px-2 py-2" aria-expanded="false" aria-controls="adminSidebar" aria-label="Open menu">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <div class="min-w-0">
                <h1 class="h4 fw-bold mb-0">Hello, Admin <span aria-hidden="true">&#128075;</span></h1>
                <p class="small text-muted mb-0">Your CMS health is currently great.</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end d-none d-sm-block">
                    <div class="fw-bold small"><?= $this->session->userdata('username') ?: 'Administrator' ?></div>
                    <div class="text-muted" style="font-size: 0.7rem;">Super Admin</div>
                </div>
                <div class="dropdown">
                    <div class="bg-white rounded-circle p-2 shadow-sm border" style="width:45px; height:45px; display:flex; align-items:center; justify-content:center; cursor:pointer;" data-bs-toggle="dropdown">
                        <i class="bi bi-person h5 mb-0 text-muted"></i>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" style="border-radius: 12px;">
                        <li><a class="dropdown-item py-2" href="#"><i class="bi bi-person me-2"></i> Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="<?= site_url('admin/logout') ?>"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
