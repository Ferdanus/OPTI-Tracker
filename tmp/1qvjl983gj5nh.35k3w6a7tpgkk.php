<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mini OPTI Tracker BBSPJIS</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS & Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --color-primary: #881337;
            --color-primary-hover: #9f1239;
            --color-primary-dark: #4c0519;
            --color-primary-light: #fff1f2;
            --color-accent: #d97706;
            --font-display: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            --font-body: 'Inter', system-ui, -apple-system, sans-serif;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-body);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
            background-color: #f8fafc;
            background-image: 
                radial-gradient(at 15% 15%, rgba(136, 19, 55, 0.07) 0px, transparent 55%),
                radial-gradient(at 85% 20%, rgba(217, 119, 6, 0.05) 0px, transparent 50%),
                radial-gradient(at 75% 85%, rgba(136, 19, 55, 0.06) 0px, transparent 55%),
                radial-gradient(at 20% 80%, rgba(59, 130, 246, 0.03) 0px, transparent 50%);
            background-attachment: fixed;
            color: #0f172a;
        }

        /* Subtle Architectural Grid Texture */
        body::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background-size: 32px 32px;
            background-image: 
                linear-gradient(to right, rgba(15, 23, 42, 0.02) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(15, 23, 42, 0.02) 1px, transparent 1px);
            pointer-events: none;
            z-index: 1;
        }

        .login-box {
            width: 100%;
            max-width: 440px;
            position: relative;
            z-index: 2;
        }

        .login-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.85);
            border-radius: 20px;
            padding: 2.5rem 2.25rem;
            box-shadow: 
                0 25px 50px -15px rgba(15, 23, 42, 0.07),
                0 10px 25px -5px rgba(15, 23, 42, 0.02);
            position: relative;
        }

        .brand-emblem-wrap {
            display: inline-flex;
            padding: 5px;
            border-radius: 16px;
            background: linear-gradient(135deg, rgba(136, 19, 55, 0.08) 0%, rgba(159, 18, 57, 0.02) 100%);
            margin-bottom: 1rem;
        }

        .brand-emblem {
            width: 54px;
            height: 54px;
            background: linear-gradient(135deg, var(--color-primary) 0%, #9f1239 100%);
            border-radius: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            font-size: 1.55rem;
            box-shadow: 0 6px 16px rgba(136, 19, 55, 0.25);
        }

        .brand-title {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 1.55rem;
            color: #0f172a;
            letter-spacing: -0.03em;
            margin-bottom: 0.25rem;
        }

        .text-primary {
            color: var(--color-primary) !important;
        }

        .brand-subtitle-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.775rem;
            font-weight: 600;
            color: #64748b;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 1.75rem;
        }

        .brand-subtitle-badge i {
            color: var(--color-primary);
        }

        .form-label {
            font-size: 0.825rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.45rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .input-group-custom {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-left {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 1.1rem;
            z-index: 4;
            pointer-events: none;
            transition: color 0.15s ease;
        }

        .form-control-custom {
            width: 100%;
            height: 48px;
            padding: 10px 14px 10px 44px;
            font-size: 0.925rem;
            font-family: var(--font-body);
            color: #0f172a;
            background-color: #f8fafc;
            border: 1.5px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.2s ease;
        }

        .form-control-custom:focus {
            background-color: #ffffff;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 4px rgba(136, 19, 55, 0.08);
            outline: none;
        }

        .input-group-custom:focus-within .input-icon-left {
            color: var(--color-primary);
        }

        .btn-toggle-pwd {
            position: absolute;
            right: 8px;
            background: none;
            border: none;
            color: #94a3b8;
            padding: 6px 10px;
            font-size: 1.1rem;
            cursor: pointer;
            border-radius: 8px;
            z-index: 4;
            transition: color 0.15s ease;
        }

        .btn-toggle-pwd:hover {
            color: #334155;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--color-primary) 0%, #9f1239 100%);
            border: none;
            color: #ffffff;
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 0.95rem;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            transition: all 0.2s ease;
            box-shadow: 0 6px 18px rgba(136, 19, 55, 0.22);
            cursor: pointer;
            margin-top: 0.5rem;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #700f2c 0%, #881337 100%);
            box-shadow: 0 8px 22px rgba(136, 19, 55, 0.32);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-7px); }
            40%, 80% { transform: translateX(7px); }
        }

        .shake-anim {
            animation: shake 0.45s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
        }

        .swal2-popup.custom-swal-popup {
            font-family: var(--font-body) !important;
            border-radius: 20px !important;
            padding: 2rem 1.75rem !important;
            box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.35) !important;
        }

        .swal2-title.custom-swal-title {
            font-family: var(--font-display) !important;
            font-weight: 800 !important;
            font-size: 1.35rem !important;
            color: #0f172a !important;
            margin-top: 0.5rem !important;
        }

        .custom-swal-btn {
            background: linear-gradient(135deg, var(--color-primary) 0%, #9f1239 100%) !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 10px 26px !important;
            font-weight: 700 !important;
            font-size: 0.9rem !important;
            box-shadow: 0 4px 14px rgba(136, 19, 55, 0.25) !important;
            transition: all 0.2s ease !important;
        }

        .custom-swal-btn:hover {
            box-shadow: 0 6px 18px rgba(136, 19, 55, 0.35) !important;
            transform: translateY(-1px) !important;
        }

        .footer-text {
            color: #94a3b8 !important;
            font-size: 0.775rem !important;
            text-align: center !important;
            margin-top: 1.5rem !important;
            line-height: 1.5 !important;
            font-weight: 400 !important;
        }
    </style>
</head>
<body>

<div class="login-box">

    <!-- Flash Alerts -->
    <?php if ($SESSION['flash_success']): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3 rounded-3" role="alert">
            <div class="d-flex align-items-center justify-content-between">
                <div class="small">
                    <i class="bi bi-check-circle-fill text-success me-1"></i> <?= ($this->raw($SESSION['flash_success']))."
" ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        </div>
        <?php unset($_SESSION['flash_success']) ?>
    <?php endif; ?>

    <?php if ($SESSION['flash_error']): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal Masuk',
                    html: '<div class="text-secondary small mt-1" style="font-size: 0.92rem; line-height: 1.5;"><?= (addslashes($SESSION['flash_error'])) ?></div>',
                    confirmButtonText: 'Coba Lagi',
                    customClass: {
                        popup: 'custom-swal-popup',
                        title: 'custom-swal-title',
                        confirmButton: 'custom-swal-btn'
                    },
                    buttonsStyling: false,
                    backdrop: `rgba(15, 23, 42, 0.5)`
                });

                var card = document.querySelector('.login-card');
                if (card) {
                    card.classList.add('shake-anim');
                }
            });
        </script>
        <?php unset($_SESSION['flash_error']) ?>
    <?php endif; ?>

    <!-- Login Card Elegan -->
    <div class="login-card">
        
        <!-- Header Branding BBSPJIS -->
        <div class="text-center">
            <div class="brand-emblem-wrap">
                <div class="brand-emblem">
                    <i class="bi bi-layers-fill"></i>
                </div>
            </div>
            <h1 class="brand-title mb-4">OPTI Tracker</h1>
        </div>

        <!-- FORM INPUT LOGIN UTAMA -->
        <form action="<?= ($BASE) ?>/login" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">

            <div class="mb-4">
                <label for="inputUsername" class="form-label mb-2">
                    <i class="bi bi-person text-primary"></i> Username
                </label>
                <div class="input-group-custom">
                    <i class="bi bi-person input-icon-left"></i>
                    <input type="text" class="form-control-custom" id="inputUsername" name="login" required placeholder="Masukkan username" autofocus>
                </div>
            </div>

            <div class="mb-4 pb-1">
                <label for="inputPassword" class="form-label mb-2">
                    <i class="bi bi-shield-lock text-primary"></i> Password
                </label>
                <div class="input-group-custom">
                    <i class="bi bi-lock input-icon-left"></i>
                    <input type="password" class="form-control-custom" id="inputPassword" name="password" required placeholder="Masukkan password" style="padding-right: 44px;">
                    <button type="button" class="btn-toggle-pwd" onclick="togglePasswordVisibility()" title="Tampilkan / Sembunyikan Password">
                        <i class="bi bi-eye" id="togglePwdIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <span>Masuk ke Sistem</span>
                <i class="bi bi-arrow-right-short fs-5"></i>
            </button>
        </form>

    </div>

    <!-- Footer -->
    <div class="footer-text">
        &copy; 2026 Balai Besar Standardisasi dan Pelayanan Jasa Industri Selulosa (BBSPJIS)
    </div>

</div>

<!-- Script Interaktif -->
<script>
function togglePasswordVisibility() {
    var p = document.getElementById('inputPassword');
    var i = document.getElementById('togglePwdIcon');
    if (p.type === 'password') {
        p.type = 'text';
        i.className = 'bi bi-eye-slash';
    } else {
        p.type = 'password';
        i.className = 'bi bi-eye';
    }
}
</script>

<!-- Bootstrap JS CDN -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>