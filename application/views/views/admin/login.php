<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | ElintOm CMS</title>
    <!-- Google Fonts: Inter & Outfit -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Outfit:wght@700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --glass-bg: rgba(255, 255, 255, 0.8);
            --glass-border: rgba(255, 255, 255, 0.3);
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            margin: 0;
        }

        .login-wrapper {
            width: 100%;
            max-width: 450px;
            animation: fadeIn 0.8s cubic-bezier(0.22, 1, 0.36, 1);
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            padding: 3rem;
            text-align: center;
        }

        .brand-logo {
            width: 64px;
            height: 64px;
            background: #ffffff;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.1);
        }

        .brand-logo i {
            font-size: 2rem;
            color: var(--primary);
        }

        .login-title {
            font-family: 'Outfit', sans-serif;
            font-size: 1.75rem;
            color: #0f172a;
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .login-subtitle {
            color: #64748b;
            font-size: 0.95rem;
            margin-bottom: 2.5rem;
        }

        .form-floating > .form-control {
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            padding: 1rem 1rem;
            height: calc(3.5rem + 2px);
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.5);
            transition: all 0.2s ease;
        }

        .form-floating > .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            background: #ffffff;
        }

        .form-floating > label {
            padding: 1rem 1rem;
            color: #94a3b8;
        }

        .btn-login {
            background: var(--primary);
            color: #ffffff;
            border: none;
            padding: 1rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            width: 100%;
            margin-top: 1rem;
            transition: all 0.2s ease;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }

        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(16, 185, 129, 0.3);
            color: #fff;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            border-radius: 12px;
            font-size: 0.9rem;
            padding: 1rem;
            margin-bottom: 2rem;
            border: none;
            text-align: left;
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
        }

        .copyright {
            margin-top: 2rem;
            color: #94a3b8;
            font-size: 0.85rem;
        }
    </style>
</head>
<body>

    <div class="login-wrapper">
        <div class="login-card">
            <div class="brand-logo">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            
            <h1 class="login-title">Admin Access</h1>
            <p class="login-subtitle">Secure management portal for ElintOm CMS</p>

            <?php if($this->session->flashdata('error')): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <?= $this->session->flashdata('error') ?>
                </div>
            <?php endif; ?>

            <form action="<?= site_url('admin/login') ?>" method="POST">
                <!-- CI CSRF -->
                <input type="hidden" name="<?= $this->security->get_csrf_token_name(); ?>" value="<?= $this->security->get_csrf_hash(); ?>" />
                
                <div class="form-floating mb-3">
                    <input type="text" name="identity" class="form-control" id="identity" placeholder="Username / Email" required autofocus>
                    <label for="identity">Username / Email</label>
                </div>
                
                <div class="form-floating mb-4">
                    <input type="password" name="password" class="form-control" id="password" placeholder="Password" required>
                    <label for="password">Password</label>
                </div>

                <div class="form-check text-start mb-4">
                    <input class="form-check-input" type="checkbox" name="remember" id="remember">
                    <label class="form-check-label text-muted small" for="remember">
                        Keep me signed in
                    </label>
                </div>

                <button type="submit" class="btn btn-login">
                    Sign In to Dashboard <i class="bi bi-arrow-right ms-2"></i>
                </button>
            </form>
            <div class="mt-4">
                <a href="<?= site_url('admin/register') ?>" class="text-muted text-decoration-none small fw-semibold">
                    Need an admin account? <span style="color: var(--primary);">Register here</span> <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>
        
        <p class="copyright">&copy; <?= date('Y') ?> ElintOm Webshop. All rights reserved.</p>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
