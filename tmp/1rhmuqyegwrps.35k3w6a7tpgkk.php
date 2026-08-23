<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sistem OPTI Tracker BBSPJI Selulosa</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap 5 CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
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
            /* Modern Light Mesh Gradient: Lembut, Elegan & Tidak Polos */
            background-image: 
                radial-gradient(at 15% 15%, rgba(136, 19, 55, 0.09) 0px, transparent 55%),
                radial-gradient(at 85% 20%, rgba(217, 119, 6, 0.08) 0px, transparent 50%),
                radial-gradient(at 75% 85%, rgba(136, 19, 55, 0.07) 0px, transparent 55%),
                radial-gradient(at 20% 80%, rgba(59, 130, 246, 0.05) 0px, transparent 50%);
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
                linear-gradient(to right, rgba(15, 23, 42, 0.03) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(15, 23, 42, 0.03) 1px, transparent 1px);
            pointer-events: none;
            z-index: 1;
        }

        .login-box {
            width: 100%;
            max-width: 490px;
            position: relative;
            z-index: 2;
        }

        /* Card Container Bersih, Mewah & Natural */
        .login-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 2.75rem 2.5rem 2.25rem 2.5rem;
            box-shadow: 
                0 20px 40px -15px rgba(15, 23, 42, 0.07),
                0 10px 20px -5px rgba(15, 23, 42, 0.03);
            position: relative;
        }

        /* Brand Emblem Box */
        .brand-emblem {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #fff1f2 0%, #fde2e4 100%);
            border: 1.5px solid rgba(136, 19, 55, 0.15);
            color: var(--color-primary);
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.6rem;
            margin-bottom: 0.85rem;
            box-shadow: 0 4px 12px rgba(136, 19, 55, 0.08);
        }

        .brand-title {
            font-family: var(--font-display);
            font-weight: 800;
            font-size: 1.55rem;
            color: #0f172a;
            letter-spacing: -0.025em;
            margin-bottom: 0.25rem;
        }

        .brand-subtitle-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f1f5f9;
            color: #475569;
            font-size: 0.775rem;
            font-weight: 600;
            padding: 4px 12px;
            border-radius: 20px;
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-size: 0.825rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.35rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: #94a3b8;
            font-size: 1.05rem;
            pointer-events: none;
            transition: color 0.15s ease;
        }

        .form-control-custom {
            width: 100%;
            height: 48px;
            padding: 0.55rem 1rem 0.55rem 42px;
            background-color: #f8fafc;
            border: 1.5px solid #cbd5e1;
            border-radius: 10px;
            font-size: 0.925rem;
            color: #0f172a;
            font-family: var(--font-body);
            transition: all 0.2s ease-in-out;
        }

        .form-control-custom:focus {
            background-color: #ffffff;
            border-color: var(--color-primary);
            box-shadow: 0 0 0 4px rgba(136, 19, 55, 0.1);
            outline: none;
        }

        .form-control-custom:focus + .input-icon,
        .input-wrapper:focus-within .input-icon {
            color: var(--color-primary);
        }

        .btn-toggle-pwd {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            color: #94a3b8;
            padding: 6px 10px;
            font-size: 1.05rem;
            cursor: pointer;
            border-radius: 6px;
            transition: color 0.15s ease;
        }

        .btn-toggle-pwd:hover {
            color: #334155;
        }

        /* Submit Button */
        .btn-login {
            background: linear-gradient(135deg, var(--color-primary) 0%, #9f1239 100%);
            border: none;
            color: #ffffff;
            font-family: var(--font-display);
            font-weight: 700;
            font-size: 0.95rem;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px rgba(136, 19, 55, 0.22);
            cursor: pointer;
        }

        .btn-login:hover {
            background: linear-gradient(135deg, #700f2c 0%, #881337 100%);
            box-shadow: 0 6px 18px rgba(136, 19, 55, 0.32);
            color: #ffffff;
            transform: translateY(-1px);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        /* Quick Account Helper Section */
        .quick-accounts-section {
            border-top: 1px solid #f1f5f9;
            padding-top: 1.25rem;
            margin-top: 1.5rem;
        }

        .quick-badge {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            color: #475569;
            font-size: 0.76rem;
            font-weight: 600;
            padding: 6px 11px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.15s ease;
            user-select: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .quick-badge:hover {
            background: var(--color-primary-light);
            border-color: var(--color-primary);
            color: var(--color-primary);
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(136, 19, 55, 0.08);
        }

        .footer-text {
            color: #94a3b8;
            font-size: 0.775rem;
            text-align: center;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>

<div class="login-box">

    <!-- Login Card Elegan -->
    <div class="login-card">
        
        <!-- Header Branding di Dalam Card -->
        <div class="text-center">
            <div class="brand-emblem">
                <i class="bi bi-layers-fill"></i>
            </div>
            <h1 class="brand-title">OPTI TRACKER</h1>
            <div class="brand-subtitle-badge">
                <i class="bi bi-building text-secondary"></i> BBSPJIS &bull; Kemenperin RI
            </div>
        </div>

        <!-- Flash Alerts -->
        <?php if ($SESSION['flash_error']): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 small p-2 px-3 mb-3 border-0 rounded-3 shadow-sm" role="alert">
                <i class="bi bi-exclamation-circle-fill text-danger fs-5"></i>
                <div><?= ($SESSION['flash_error']) ?></div>
            </div>
            <?php unset($_SESSION['flash_error']) ?>
        <?php endif; ?>

        <?php if ($SESSION['flash_success']): ?>
            <div class="alert alert-success d-flex align-items-center gap-2 small p-2 px-3 mb-3 border-0 rounded-3 shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill text-success fs-5"></i>
                <div><?= ($SESSION['flash_success']) ?></div>
            </div>
            <?php unset($_SESSION['flash_success']) ?>
        <?php endif; ?>

        <!-- Form Input -->
        <form action="<?= ($BASE) ?>/login" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">

            <div class="mb-3">
                <label for="username" class="form-label">
                    <i class="bi bi-person text-primary"></i> Username / Login Balai
                </label>
                <div class="input-wrapper">
                    <i class="bi bi-person input-icon"></i>
                    <input type="text" class="form-control-custom" id="username" name="username" required placeholder="Masukkan username Anda" autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label for="password" class="form-label">
                    <i class="bi bi-key text-primary"></i> Kata Sandi (Password)
                </label>
                <div class="input-wrapper">
                    <i class="bi bi-lock input-icon"></i>
                    <input type="password" class="form-control-custom" id="password" name="password" required placeholder="Masukkan kata sandi" style="padding-right: 42px;">
                    <button type="button" class="btn-toggle-pwd" onclick="togglePasswordVisibility()" title="Lihat Kata Sandi">
                        <i class="bi bi-eye" id="togglePwdIcon"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-login">
                <span>Masuk ke Sistem</span>
                <i class="bi bi-box-arrow-in-right"></i>
            </button>
        </form>

        

    </div>

    <!-- Footer Copyright -->
    <div class="footer-text">
        &copy; 2026 BBSPJIS &bull; Kementerian Perindustrian RI
    </div>

</div>

<!-- Script Interaktif -->
<script>
function fillLogin(u, p) {
    var uf = document.getElementById('username');
    var pf = document.getElementById('password');
    uf.value = u;
    pf.value = p;
    uf.focus();
}

function togglePasswordVisibility() {
    var p = document.getElementById('password');
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
