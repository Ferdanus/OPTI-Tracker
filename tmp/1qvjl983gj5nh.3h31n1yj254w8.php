<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ($page_title ?: 'Mini OPTI Tracker') ?></title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        :root {
            --color-primary: #a41e22;          /* merah maroon utama - header aksen, tombol utama */
            --color-primary-light: #c8282d;    /* merah maroon lebih terang untuk hover */
            --color-primary-dark: #7a1418;     /* merah lebih gelap - footer background dasar */
            --color-primary-darker: #5c0f12;   /* untuk gradasi/tekstur diagonal footer */
            --color-accent: #d4a92e;           /* emas - dipakai terbatas untuk aksen kecil (badge, ikon) */
            --color-bg: #f7f7f8;               /* background halaman konten */
            --color-bg-card: #ffffff;
            --color-header-bg: #ffffff;        /* header sekarang putih, bukan gelap */
            --color-text: #222222;
            --color-text-on-primary: #ffffff;
            --color-muted: #6c757d;
            --color-border: #e2e2e4;
        }

        body {
            background-color: var(--color-bg);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            color: var(--color-text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        /* Override Bootstrap Primary Color Utilities to Match Maroon Theme */
        .text-primary {
            color: var(--color-primary) !important;
        }
        .bg-primary {
            background-color: var(--color-primary) !important;
        }
        .bg-primary.bg-opacity-10 {
            background-color: rgba(164, 30, 34, 0.1) !important;
        }
        .border-primary, .border-primary-subtle {
            border-color: var(--color-primary) !important;
        }
        .btn-primary {
            background-color: var(--color-primary) !important;
            border-color: var(--color-primary) !important;
            color: var(--color-text-on-primary) !important;
        }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: var(--color-primary-light) !important;
            border-color: var(--color-primary-light) !important;
            color: var(--color-text-on-primary) !important;
        }
        .btn-outline-primary {
            color: var(--color-primary) !important;
            border-color: var(--color-primary) !important;
        }
        .btn-outline-primary:hover, .btn-outline-primary:focus, .btn-outline-primary:active {
            background-color: var(--color-primary) !important;
            border-color: var(--color-primary) !important;
            color: var(--color-text-on-primary) !important;
        }
        a {
            color: var(--color-primary);
        }
        a:hover {
            color: var(--color-primary-light);
        }

        /* Navbar Header Resmi Pemerintahan (BBSPJIS Style - Putih) */
        .navbar-gov {
            background-color: var(--color-header-bg);
            border-bottom: 4px solid var(--color-primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
        }
        .navbar-brand-container {
            display: flex;
            align-items: center;
        }
        .navbar-brand-logos {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-right: 12px;
            padding-right: 12px;
            border-right: 2px solid var(--color-border);
        }
        .navbar-brand-logos i {
            color: var(--color-primary);
            font-size: 1.5rem;
            line-height: 1;
        }
        .navbar-brand-logos .bi-patch-check-fill {
            color: var(--color-accent);
            font-size: 1.15rem;
        }
        .navbar-gov .navbar-brand-text .brand-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--color-primary);
            letter-spacing: -0.2px;
            line-height: 1.2;
            text-transform: uppercase;
        }
        .navbar-gov .navbar-brand-text .brand-subtitle {
            font-size: 0.65rem;
            font-weight: 600;
            color: var(--color-muted);
            letter-spacing: 0.2px;
            line-height: 1.2;
        }
        
        /* Menu Navigasi Kanan */
        .navbar-gov .nav-link {
            color: #333333;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            transition: all 0.2s ease-in-out;
            position: relative;
        }
        .navbar-gov .nav-link:hover {
            color: var(--color-primary);
        }
        .navbar-gov .nav-link.active {
            color: var(--color-primary);
            font-weight: 700;
        }
        .navbar-gov .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 3px;
            bottom: 4px; /* Didekatkan ke teks baseline agar pas dan tidak menempel garis bawah navbar */
            left: 50%;
            transform: translateX(-50%);
            background-color: var(--color-primary);
            border-radius: 4px;
            transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .navbar-gov .nav-link:hover::after,
        .navbar-gov .nav-link.active::after {
            width: calc(100% - 2rem); /* Melebar tepat sepanjang teks (dikurangi padding kiri-kanan masing-masing 1rem) */
        }

        /* Widget User Premium di Navbar */
        .nav-user-link {
            padding: 6px 12px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .nav-user-link:hover {
            background-color: rgba(164, 30, 34, 0.05);
        }
        .nav-user-link::after {
            display: inline-block;
            margin-left: 0.4em;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
            color: var(--color-muted);
            transition: transform 0.2s;
        }
        .nav-user-link[aria-expanded="true"]::after {
            transform: rotate(180deg);
        }

        /* Style & Animasi Premium Dropdown User (Kompatibel dengan Popper.js) */
        .navbar-gov .dropdown-menu {
            display: block;
            visibility: hidden;
            opacity: 0;
            margin-top: 15px !important;
            transition: opacity 0.2s ease, margin-top 0.2s ease, visibility 0.2s ease;
            pointer-events: none;
        }
        .navbar-gov .dropdown-menu.show {
            visibility: visible;
            opacity: 1;
            margin-top: 8px !important;
            pointer-events: auto;
        }
        .navbar-gov .dropdown-menu-end {
            right: 0 !important;
            left: auto !important;
        }
        .navbar-gov .dropdown-item {
            color: var(--color-text-dark);
            transition: all 0.15s ease-in-out;
            font-weight: 500;
        }
        .navbar-gov .dropdown-item:hover {
            background-color: rgba(164, 30, 34, 0.04) !important;
            color: var(--color-primary-dark) !important;
        }
        .navbar-gov .dropdown-item:hover i {
            color: var(--color-accent) !important;
        }
        /* Tombol Kembali Premium */
        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--color-text-dark);
            background-color: #ffffff;
            border: 1px solid rgba(164, 30, 34, 0.12);
            border-radius: 10px !important;
            text-decoration: none;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.04);
        }
        .btn-back i {
            transition: transform 0.2s ease;
            color: var(--color-primary);
        }
        .btn-back:hover {
            background-color: rgba(164, 30, 34, 0.03);
            color: var(--color-primary-dark);
            border-color: var(--color-primary);
            box-shadow: 0 4px 12px rgba(164, 30, 34, 0.08);
        }
        .btn-back:hover i {
            transform: translateX(-4px); /* Efek visual panah mundur */
        }
        .btn-back:active {
            transform: scale(0.96);
        }

        /* Mobile Layout & Responsiveness Optimization */
        @media (max-width: 991.98px) {
            /* Non-sticky navbar di mobile untuk mencegah tabrakan scroll browser */
            .navbar-gov.sticky-top {
                position: relative !important;
                top: auto !important;
            }

            /* Hapus garis penyorot underline horizontal di mobile */
            .navbar-gov .nav-link::after {
                display: none !important;
            }

            /* Tata Letak & Gaya Nav Link Vertikal (Tab Premium) */
            .navbar-gov .nav-link {
                padding: 12px 16px !important;
                border-radius: 8px !important;
                font-weight: 600 !important;
                margin-bottom: 6px !important;
                color: var(--color-text-dark) !important;
                border-left: 4px solid transparent !important;
                transition: all 0.2s ease-in-out;
            }
            .navbar-gov .nav-link:hover {
                background-color: rgba(164, 30, 34, 0.03) !important;
                color: var(--color-primary) !important;
            }
            .navbar-gov .nav-link.active {
                background-color: rgba(164, 30, 34, 0.06) !important;
                color: var(--color-primary) !important;
                border-left-color: var(--color-primary) !important;
                border-radius: 0 8px 8px 0 !important;
                padding-left: 12px !important; /* Kompensasi ketebalan border kiri */
            }

            /* Seamless Flat Menu Collapse Mobile (Mencegah Mismatch Tinggi & Bug Scroll Jump) */
            .navbar-gov .navbar-collapse {
                background: #ffffff !important;
                border-bottom: 1px solid var(--color-border) !important;
                border-top: none !important;
                border-left: none !important;
                border-right: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                padding: 0 !important; /* Wajib nol agar tinggi kalkulasi JS Bootstrap sinkron */
            }
            .navbar-gov .collapsing {
                background: #ffffff !important;
                border-bottom: 1px solid var(--color-border) !important;
                border-top: none !important;
                border-left: none !important;
                border-right: none !important;
                border-radius: 0 !important;
                margin: 0 !important;
                box-shadow: none !important;
                padding: 0 !important;
                height: 0;
                overflow: hidden;
                /* Paksa transition height agar tidak patah/snap instan pada perangkat mobile */
                transition: height 0.35s cubic-bezier(0.4, 0, 0.2, 1) !important;
            }

            /* Spacing internal diatur pada level kontainer anak */
            .navbar-gov .navbar-nav {
                padding: 0.75rem 1rem 0.25rem 1rem !important;
                margin: 0 !important;
            }
            .navbar-gov .dropdown {
                padding: 0.25rem 1rem 1.25rem 1rem !important;
                position: relative;
            }

            /* Widget Profil Akun User di Mobile */
            .navbar-gov .nav-user-link {
                padding: 12px 16px;
                background-color: #f8fafc;
                border: 1px solid var(--color-border);
                border-radius: 10px;
                width: 100%;
                justify-content: space-between;
                font-weight: 600;
            }
            .navbar-gov .nav-user-link .nav-avatar {
                border-color: var(--color-primary) !important;
            }

            /* Animasi Slide-Down + Fade-In Dropdown Akun di Mobile */
            .navbar-gov .dropdown-menu {
                position: absolute !important;
                float: none;
                width: 100% !important;
                margin-top: 0.5rem !important;
                box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08) !important;
                border: 1px solid var(--color-border) !important;
                background-color: #ffffff !important;
                border-radius: 12px !important;
                transform: translateY(-10px) !important;
                visibility: hidden !important;
                opacity: 0 !important;
                display: block !important;
                pointer-events: none !important;
                transition: opacity 0.25s cubic-bezier(0.4, 0, 0.2, 1), transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), visibility 0.25s;
                z-index: 1050;
            }
            .navbar-gov .dropdown-menu.show {
                visibility: visible !important;
                opacity: 1 !important;
                transform: translateY(0) !important;
                pointer-events: auto !important;
            }
        }

        @media (max-width: 575.98px) {
            .navbar-brand-logos {
                gap: 4px;
                margin-right: 8px;
                padding-right: 8px;
            }
            .navbar-brand-logos i {
                font-size: 1.15rem;
            }
            .navbar-brand-logos .bi-patch-check-fill {
                font-size: 0.9rem;
            }
            .navbar-gov .navbar-brand-text .brand-title {
                font-size: 0.8rem;
            }
            .navbar-gov .navbar-brand-text .brand-subtitle {
                font-size: 0.55rem;
                letter-spacing: -0.2px;
                white-space: nowrap;
            }
            .navbar-gov .container {
                padding-left: 12px;
                padding-right: 12px;
            }
            .navbar-toggler {
                padding: 4px 8px;
                font-size: 0.9rem;
            }
        }

        .main-content {
            flex: 1;
            padding-bottom: 3rem;
        }

        .card {
            border: 1px solid var(--color-border);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.03);
            border-radius: 0.375rem;
            background-color: var(--color-bg-card);
        }

        .card-header {
            background-color: #ffffff;
            border-bottom: 1px solid var(--color-border);
            font-weight: 600;
            color: var(--color-primary);
        }

        /* Override Global Bootstrap Buttons */
        .btn-primary {
            background-color: var(--color-primary);
            border-color: var(--color-primary);
            color: var(--color-text-on-primary);
            font-weight: 500;
            transition: all 0.2s ease-in-out;
        }
        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: var(--color-primary-light) !important;
            border-color: var(--color-primary-light) !important;
            box-shadow: 0 0 0 0.25rem rgba(164, 30, 34, 0.25) !important;
        }
        .btn-outline-primary {
            color: var(--color-primary);
            border-color: var(--color-primary);
        }
        .btn-outline-primary:hover, .btn-outline-primary:active {
            background-color: var(--color-primary);
            border-color: var(--color-primary);
            color: #ffffff;
        }

        /* Override Form Controls */
        .form-control:focus, .form-select:focus {
            border-color: var(--color-primary);
            box-shadow: 0 0 0 0.25rem rgba(164, 30, 34, 0.15);
        }

        /* Styling Badge Status PO Formal */
        .badge-status-proposal { background-color: var(--color-muted); color: #ffffff; }
        .badge-status-kontrak { background-color: var(--color-accent); color: #ffffff; }
        .badge-status-po_terbit { background-color: #1d4ed8; color: #ffffff; } /* Biru Formal */
        .badge-status-distribusi { background-color: #6b21a8; color: #ffffff; } /* Ungu Tua Muted */
        .badge-status-selesai { background-color: #166534; color: #ffffff; }    /* Hijau Tua */

        /* Breadcrumb Style */
        .breadcrumb-container {
            background-color: #ffffff;
            border-bottom: 1px solid var(--color-border);
        }
        .breadcrumb-item a {
            color: var(--color-muted);
            transition: color 0.15s ease;
        }
        .breadcrumb-item a:hover {
            color: var(--color-primary);
        }
        .breadcrumb-item.active {
            color: var(--color-primary) !important;
        }

        /* Styling Tabel */
        .table thead th {
            background-color: #f8f9fa;
            color: var(--color-primary);
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            border-bottom: 2px solid var(--color-border);
            padding: 12px;
        }
        .table-hover tbody tr {
            transition: background-color 0.15s ease-in-out;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(164, 30, 34, 0.03) !important;
        }

        /* Footer Resmi Pemerintahan (Maroon Diagonal Texture) */
        .footer-gov {
            background-color: var(--color-primary-dark);
            background-image: repeating-linear-gradient( 
                135deg, 
                rgba(255,255,255,0.03) 0px, 
                rgba(255,255,255,0.03) 2px, 
                transparent 2px, 
                transparent 12px 
            );
            color: #ffffff;
            border-top: 4px solid var(--color-accent);
        }
        .footer-gov h6 {
            color: var(--color-accent);
            font-weight: 700;
            border-left: 3px solid var(--color-accent);
            padding-left: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .footer-gov a {
            color: #e2e8f0;
            text-decoration: none;
            transition: color 0.2s;
        }
        .footer-gov a:hover {
            color: var(--color-accent);
        }
        .footer-bottom {
            background-color: var(--color-primary-darker);
            color: #cbd5e1;
            font-size: 0.775rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        /* Responsivitas Tabel di Mobile-first */
        @media (max-width: 767.98px) {
            .table-responsive table, 
            .table-responsive thead, 
            .table-responsive tbody, 
            .table-responsive th, 
            .table-responsive td, 
            .table-responsive tr {
                display: block;
            }
            .table-responsive thead {
                display: none; /* Sembunyikan header tabel di mobile */
            }
            .table-responsive tr {
                margin-bottom: 1rem;
                border: 1px solid var(--color-border);
                border-radius: 0.375rem;
                background-color: #ffffff;
                padding: 10px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            }
            .table-responsive td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                border: none;
                border-bottom: 1px solid #f1f3f5;
                padding: 8px 12px;
                text-align: right;
                font-size: 0.85rem;
            }
            .table-responsive td:last-child {
                border-bottom: none;
                justify-content: center;
                padding-top: 12px;
            }
            /* Gunakan data-label untuk memunculkan judul kolom di sebelah kiri */
            .table-responsive td::before {
                content: attr(data-label);
                font-weight: 700;
                text-align: left;
                text-transform: uppercase;
                font-size: 0.725rem;
                color: var(--color-muted);
                margin-right: 15px;
            }
        }

        /* Transisi & Hover Animasi Halus */
        .nav-link, .btn, .form-control, .form-select, .card, tr {
            transition: all 0.2s ease-in-out;
        }
    </style>
    <!-- AOS CSS CDN -->
    <link href="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar Header Instansi Pemerintah -->
    <nav class="navbar navbar-expand-lg navbar-light navbar-gov sticky-top">
        <div class="container">
            <a class="navbar-brand navbar-brand-container" href="<?= ($BASE) ?>/po">
                <!-- Deretan placeholder logo horizontal (referensi web instansi) -->
                <div class="navbar-brand-logos">
                    <i class="bi bi-shield-shaded" title="Logo Kemenperin"></i>
                    <i class="bi bi-bank" title="Logo Balai"></i>
                    <i class="bi bi-patch-check-fill" title="Logo WBK"></i>
                </div>
                <div class="navbar-brand-text">
                    <div class="brand-title">Sistem OPTI Tracker</div>
                    <div class="brand-subtitle">BBSPJI SELULOSA &bull; KEMENTERIAN PERINDUSTRIAN RI</div>
                </div>
            </a>
            <?php if ($SESSION['user_id']): ?>
                <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarMain">
                    <!-- Menu navigasi horizontal dipindah ke kanan -->
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 me-3">
                        <li class="nav-item">
                            <a class="nav-link <?= ($active_menu == 'po' ? 'active' : '') ?>" href="<?= ($BASE) ?>/po">
                                Petunjuk Operasional
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($active_menu == 'order' ? 'active' : '') ?>" href="<?= ($BASE) ?>/order">
                                Order Layanan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= ($active_menu == 'klien' ? 'active' : '') ?>" href="<?= ($BASE) ?>/klien">
                                Klien
                            </a>
                        </li>
                    </ul>
                    <!-- Widget Profil User Premium -->
                    <div class="dropdown ms-lg-3">
                        <a class="nav-user-link d-flex align-items-center gap-2 text-decoration-none dropdown-toggle" href="#" role="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if ($SESSION['foto_profil'] && file_exists($SESSION['foto_profil'])): ?>
                                
                                    <img src="<?= ($BASE) ?>/<?= ($SESSION['foto_profil']) ?>" class="rounded-circle" style="width: 32px; height: 32px; object-fit: cover; border: 1px solid rgba(164, 30, 34, 0.15);" alt="Foto Profil">
                                
                                <?php else: ?>
                                    <div class="nav-avatar bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.85rem; border: 1px solid rgba(164, 30, 34, 0.15);">
                                        <?php $initials = implode('', array_map(function($w) { return strtoupper($w[0] ?? ''); }, explode(' ', $_SESSION['nama_lengkap']))) ?>
                                        <?= (substr($initials, 0, 2))."
" ?>
                                    </div>
                                
                            <?php endif; ?>
                            <div class="d-none d-md-block text-start" style="line-height: 1.2;">
                                <div class="nav-user-name fw-semibold text-dark" style="font-size: 0.875rem;"><?= (htmlspecialchars($SESSION['nama_lengkap'])) ?></div>
                                <div class="nav-user-role text-muted" style="font-size: 0.725rem;">Administrator</div>
                            </div>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2 py-0" aria-labelledby="userMenuDropdown" style="min-width: 220px; overflow: hidden; border: 1px solid rgba(0,0,0,0.08) !important;">
                            <li>
                                <div class="dropdown-header d-flex flex-column px-4 py-3 bg-light bg-opacity-70" style="line-height: 1.3;">
                                    <span class="fw-bold text-dark" style="font-size: 0.9rem;"><?= (htmlspecialchars($SESSION['nama_lengkap'])) ?></span>
                                    <span class="text-muted small" style="font-size: 0.75rem;">@<?= ($SESSION['username']) ?></span>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider my-0" style="opacity: 0.08;"></li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center gap-3" href="<?= ($BASE) ?>/profil" style="font-size: 0.875rem; padding: 0.75rem 1.25rem;">
                                    <i class="bi bi-person-gear text-secondary fs-6"></i>
                                    <span>Edit Profil</span>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider my-0" style="opacity: 0.08;"></li>
                            <li>
                                <!-- Logout Form POST (CSRF Secured) -->
                                <form action="<?= ($BASE) ?>/logout" method="POST" id="logoutForm" class="m-0">
                                    <input type="hidden" name="csrf_token" value="<?= ($csrf_token) ?>">
                                    <button type="submit" class="dropdown-item d-flex align-items-center gap-3 text-danger w-100 text-start border-0 bg-transparent" style="font-size: 0.875rem; padding: 0.75rem 1.25rem;">
                                        <i class="bi bi-box-arrow-right fs-6"></i>
                                        <span>Keluar / Logout</span>
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <?php if ($SESSION['user_id']): ?>
        <!-- Breadcrumb Navigasi di Bawah Header -->
        <div class="breadcrumb-container py-2 shadow-sm mb-4">
            <div class="container">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 small">
                        <li class="breadcrumb-item">
                            <a href="<?= ($BASE) ?>/po" class="text-decoration-none">
                                <i class="bi bi-house-door-fill me-1"></i>Beranda
                            </a>
                        </li>
                        
                        <?php if (preg_match('/^\/klien/', $PATH)): ?>
                            <?php if ($PATH == '/klien'): ?>
                                
                                    <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Daftar Klien</li>
                                
                                <?php else: ?>
                                    <li class="breadcrumb-item"><a href="<?= ($BASE) ?>/klien" class="text-decoration-none">Daftar Klien</a></li>
                                    <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Tambah Klien</li>
                                
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (preg_match('/^\/order/', $PATH)): ?>
                            <?php if ($PATH == '/order'): ?>
                                
                                    <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Order Layanan</li>
                                
                                <?php else: ?>
                                    <li class="breadcrumb-item"><a href="<?= ($BASE) ?>/order" class="text-decoration-none">Order Layanan</a></li>
                                    <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Tambah Order</li>
                                
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (preg_match('/^\/po/', $PATH)): ?>
                            <?php if ($PATH == '/po'): ?>
                                
                                    <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Daftar Petunjuk Operasional (PO)</li>
                                
                                <?php else: ?>
                                    <li class="breadcrumb-item"><a href="<?= ($BASE) ?>/po" class="text-decoration-none">Daftar PO</a></li>
                                    <li class="breadcrumb-item active text-dark fw-semibold" aria-current="page">Detail PO (<?= ($po['nomor_po']) ?>)</li>
                                
                            <?php endif; ?>
                        <?php endif; ?>
                    </ol>
                </nav>
            </div>
        </div>
    <?php endif; ?>

    <!-- Main Container -->
    <main class="main-content container">
        <!-- Flash Message Notification -->
        <?php if ($SESSION['flash_success']): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= ($SESSION['flash_success'])."
" ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_success']) ?>
        <?php endif; ?>

        <?php if ($SESSION['flash_error']): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i><?= ($SESSION['flash_error'])."
" ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['flash_error']) ?>
        <?php endif; ?>

        <?php if ($db_error): ?>
            <div class="alert alert-warning" role="alert">
                <h5 class="alert-heading"><i class="bi bi-database-slash me-2"></i>Koneksi Database Belum Aktif</h5>
                <p class="mb-1">Aplikasi belum terhubung ke MySQL: <code><?= ($db_error) ?></code></p>
                <hr>
                <p class="small mb-0">Pastikan MySQL di XAMPP sudah berjalan dan database <code>mini_opti_tracker</code> sudah dibuat (gunakan file <code>schema.sql</code>).</p>
            </div>
        <?php endif; ?>

        <!-- Dynamic Content View -->
        <?php echo $this->render($content,NULL,get_defined_vars(),0); ?>
    </main>

    <!-- Footer Resmi Pemerintahan -->
    <footer class="footer-gov mt-auto">
        <div class="container py-4">
            <div class="row g-4">
                <div class="col-md-8">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-shield-shaded text-warning fs-3 me-2"></i>
                        <span class="fw-bold fs-5 text-white">SISTEM OPTI TRACKER</span>
                    </div>
                    <p class="small text-white-50" style="max-width: 650px;">
                        Sistem Informasi OPTI Tracker merupakan platform resmi Balai Besar Standardisasi dan Pelayanan Jasa Industri Selulosa (BBSPJI Selulosa) Kementerian Perindustrian RI untuk pemantauan dan pelacakan alur proses dokumen Petunjuk Operasional (PO) layanan jasa industri secara transparan, terintegrasi, dan akuntabel.
                    </p>
                </div>
                <div class="col-md-4">
                    <h6 class="text-uppercase mb-3 fs-6">Navigasi Cepat</h6>
                    <ul class="list-unstyled small">
                        <li class="mb-2"><a href="<?= ($BASE) ?>/po">Daftar PO & Dashboard</a></li>
                        <li class="mb-2"><a href="<?= ($BASE) ?>/order">Permintaan Order Layanan</a></li>
                        <li class="mb-2"><a href="<?= ($BASE) ?>/klien">Manajemen Data Klien</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="footer-bottom py-3 text-center">
            <div class="container text-white-50">
                <span class="small">&copy; 2026 Balai Besar Standardisasi dan Pelayanan Jasa Industri Selulosa - Kementerian Perindustrian RI. Hak Cipta Dilindungi Undang-Undang.</span>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 JS Bundle CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS JS CDN -->
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    <script>
        // Inisialisasi AOS (Animate On Scroll)
        AOS.init({
            duration: 500,
            once: true,
            easing: 'ease-in-out'
        });

        // Spinner Loading ketika Form disubmit
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = this.querySelector('button[type="submit"]');
                if (submitBtn) {
                    if (this.checkValidity()) {
                        submitBtn.disabled = true;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Memproses...';
                    }
                }
            });
        });
    </script>
</body>
</html>
