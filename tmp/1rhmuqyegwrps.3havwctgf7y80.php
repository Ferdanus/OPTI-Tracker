<!DOCTYPE html>
<html lang="id" class="h-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ($page_title) ?></title>
    <!-- Google Fonts: Lora (Prestigious Serif) & Inter (Modern Sans-Serif) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:ital,wght@0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --color-primary: #a41e22;          /* maroon */
            --color-primary-dark: #6e1114;     /* maroon gelap */
            --color-accent: #d4a92e;           /* emas */
            --color-text-dark: #1f2937;
            --color-text-muted: #6b7280;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: var(--color-text-dark);
            background-color: #f8fafc;
        }

        /* Split Screen Container */
        .login-wrapper {
            min-height: 100vh;
        }

        /* Left Pane (Branding & Pattern) */
        .login-left-pane {
            background: radial-gradient(circle at 20% 30%, rgba(212, 169, 46, 0.09) 0%, transparent 40%),
                        radial-gradient(circle at 80% 70%, rgba(212, 169, 46, 0.05) 0%, transparent 50%),
                        linear-gradient(135deg, #7a1418 0%, #3a0507 100%);
            border-right: 5px solid var(--color-accent);
            position: relative;
            overflow: hidden;
        }

        /* Grid Pattern Overlay */
        .login-left-pane::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-image: radial-gradient(rgba(212, 169, 46, 0.15) 1.5px, transparent 1.5px);
            background-size: 32px 32px;
            opacity: 0.7;
            pointer-events: none;
        }

        .left-content {
            z-index: 10;
            max-width: 520px;
        }



        .title-gov {
            font-family: 'Lora', serif;
            font-weight: 700;
            color: #ffffff;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
        }

        /* Right Pane (Form) */
        .login-right-pane {
            background-color: #ffffff;
        }

        .form-container {
            max-width: 400px;
            width: 100%;
        }

        .form-heading {
            font-family: 'Lora', serif;
            font-weight: 700;
            color: var(--color-primary-dark);
        }

        /* Floating Input Styling Customization */
        .form-floating > .form-control {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 0.95rem;
            transition: all 0.2s ease-in-out;
            padding-left: 2.75rem;
        }

        .form-floating > .form-control:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 4px rgba(164, 30, 34, 0.08);
        }

        .form-floating > label {
            padding-left: 2.75rem;
            color: var(--color-text-muted);
            font-size: 0.9rem;
        }

        /* Input icons */
        .input-icon-wrapper {
            position: relative;
        }

        .input-icon-wrapper i.input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--color-text-muted);
            z-index: 10;
            font-size: 1.1rem;
        }

        /* Custom premium button */
        .btn-premium {
            background-color: var(--color-primary);
            border: 1px solid var(--color-primary);
            color: #ffffff;
            font-weight: 600;
            border-radius: 0.5rem;
            padding: 0.75rem;
            transition: all 0.25s ease-in-out;
            box-shadow: 0 4px 6px rgba(164, 30, 34, 0.15);
        }

        .btn-premium:hover, .btn-premium:focus {
            background-color: var(--color-primary-dark);
            border-color: var(--color-primary-dark);
            color: #ffffff;
            transform: translateY(-1px);
            box-shadow: 0 6px 15px rgba(164, 30, 34, 0.25);
        }

        .btn-premium:active {
            transform: translateY(0);
        }

        /* Mobile Adjustments */
        @media (max-width: 991.98px) {
            .login-left-pane {
                display: none !important;
            }
            .login-right-pane {
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: #f8fafc;
            }
            .form-container {
                background: #ffffff;
                padding: 2.5rem 2rem;
                border-radius: 0.75rem;
                box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            }
        }
    </style>
</head>
<body class="h-100">

    <div class="container-fluid p-0 h-100">
        <div class="row g-0 login-wrapper">
            
            <!-- Kiri: Info Instansi & Aksen Glow Emas (Hanya desktop) -->
            <div class="col-lg-6 d-none d-lg-flex flex-column justify-content-between p-5 text-white login-left-pane">
                <!-- Top Header Logos -->
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex gap-3 fs-5">
                        <i class="bi bi-shield-shaded text-white" title="Kemenperin"></i>
                        <i class="bi bi-bank text-warning" style="color: var(--color-accent) !important;"></i>
                    </div>
                    <div>
                        <span class="text-uppercase fw-semibold text-white-50" style="font-size: 0.75rem; letter-spacing: 1px;">Sistem Layanan OPTI</span>
                    </div>
                </div>

                <!-- Mid Branding Info -->
                <div class="left-content my-auto">
                    <h1 class="display-5 title-gov mb-3">SISTEM OPTI TRACKER</h1>
                    <p class="lead opacity-75 fs-6 lh-lg mb-0" style="font-weight: 300;">
                        Portal internal monitoring dan verifikasi alur kerja Petunjuk Operasional (PO) di Balai Besar Standardisasi dan Pelayanan Jasa Industri Selulosa (BBSPJI Selulosa) &mdash; Kementerian Perindustrian Republik Indonesia.
                    </p>
                </div>

                <!-- Bottom Footer Info -->
                <div class="d-flex justify-content-between align-items-center text-white-50 small">
                    <span>&copy; 2026 BBSPJI Selulosa</span>
                    <span class="d-flex align-items-center gap-1"><i class="bi bi-patch-check-fill text-warning" style="color: var(--color-accent) !important;"></i> Standardisasi Industri</span>
                </div>
            </div>

            <!-- Kanan: Form Autentikasi -->
            <div class="col-lg-6 login-right-pane d-flex align-items-center justify-content-center p-4 p-md-5">
                <div class="form-container">
                    
                    <!-- Mobile Logo Header (Hanya muncul di mobile) -->
                    <div class="d-block d-lg-none text-center mb-4">
                        <div class="d-inline-flex bg-danger bg-opacity-10 p-3 rounded-circle mb-3" style="color: var(--color-primary);">
                            <i class="bi bi-shield-lock-fill fs-1"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-1">SISTEM OPTI TRACKER</h4>
                        <div class="small text-muted">BBSPJI Selulosa &bull; Kemenperin RI</div>
                    </div>

                    <!-- Heading Utama -->
                    <div class="mb-4 text-center text-lg-start">
                        <h2 class="form-heading mb-1">Masuk ke Portal</h2>
                        <p class="text-muted small">Silakan masukkan kredensial resmi untuk mengakses data.</p>
                    </div>

                    <!-- Notifikasi Sukses / Error -->
                    <?php if ($SESSION['flash_error']): ?>
                        <div class="alert alert-danger d-flex align-items-start small p-3 mb-4 border-0 rounded-3 bg-danger bg-opacity-10 text-danger" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-2 mt-0.5"></i>
                            <div><?= ($SESSION['flash_error']) ?></div>
                        </div>
                        <?php unset($_SESSION['flash_error']) ?>
                    <?php endif; ?>
                    
                    <?php if ($SESSION['flash_success']): ?>
                        <div class="alert alert-success d-flex align-items-start small p-3 mb-4 border-0 rounded-3 bg-success bg-opacity-10 text-success" role="alert">
                            <i class="bi bi-check-circle-fill me-2 mt-0.5"></i>
                            <div><?= ($SESSION['flash_success']) ?></div>
                        </div>
                        <?php unset($_SESSION['flash_success']) ?>
                    <?php endif; ?>

                    <!-- Form Login -->
                    <form action="<?= ($BASE) ?>/login" method="POST" autocomplete="off">
                        <!-- CSRF Token -->
                        <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">

                        <!-- Input Username -->
                        <div class="input-icon-wrapper mb-3">
                            <i class="bi bi-person input-icon"></i>
                            <div class="form-floating">
                                <input type="text" class="form-control" id="username" name="username" required placeholder="username" autofocus>
                                <label for="username">Username</label>
                            </div>
                        </div>

                        <!-- Input Password -->
                        <div class="input-icon-wrapper mb-4">
                            <i class="bi bi-lock input-icon"></i>
                            <div class="form-floating">
                                <input type="password" class="form-control" id="password" name="password" required placeholder="password">
                                <label for="password">Password</label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-premium w-100 py-3 mb-3">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Masuk Sistem
                        </button>
                    </form>

                    <div class="text-center d-block d-lg-none mt-4 text-muted small" style="font-size: 0.75rem;">
                        &copy; 2026 BBSPJI Selulosa. Hak Cipta Dilindungi.
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Spinner Loading ketika Form disubmit
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && this.checkValidity()) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Mengautentikasi...';
            }
        });
    </script>
</body>
</html>
