<!doctype html>
<html lang="id" >
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#172033">
    <title><?= esc($title) ?></title>
    <link rel="preload" href="<?= base_url('assets/fonts/inter/InterVariable.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= base_url('assets/css/fonts.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/fonts.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/login.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/login.css') ?>">
    <script src="<?= base_url('assets/js/theme-init.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/theme-init.js') ?>"></script>
</head>
<body>
<main class="login-shell">
    <section class="login-panel" aria-labelledby="loginTitle">
        <aside class="login-context">
            <div>
                <span class="brand-mark"><img src="<?= base_url('assets/img/siprosir-logo.png') ?>" alt="Logo SIPROSIR"></span>
                <p class="context-eyebrow">KERANGKA APLIKASI</p>
                <h1>SIPROSIR PRO V5</h1>
                <p>Masuk untuk menganalisis penjualan, stok, dan data POS Anda.</p>
                <div class="security-points">
                    <div><i class="bi bi-shield-check"></i><span>Login dilindungi pembatasan percobaan.</span></div>
                    <div><i class="bi bi-database-check"></i><span>Akun diverifikasi langsung dari database.</span></div>
                    <div><i class="bi bi-window-stack"></i><span>Dashboard bekerja tanpa reload penuh.</span></div>
                </div>
            </div>
            <small>AI ASISTAN · Analisis data POS</small>
        </aside>

        <div class="login-form-pane">
            <div class="login-form-wrap">
                <p class="form-eyebrow">AKSES APLIKASI</p>
                <h2 id="loginTitle">Masuk ke akun Anda</h2>
                <p class="lead-copy">Gunakan akun yang sudah tersimpan pada database kerangka.</p>
                <div id="alertMessage" class="mb-3" aria-live="polite"></div>
                <?php if (session('error')): ?><div class="login-alert mb-3"><?= esc(session('error')) ?></div><?php endif; ?>

                <form id="loginForm" action="<?= site_url('login') ?>" method="post" novalidate>
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label" for="username">USERNAME</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input id="username" type="text" name="username" class="form-control" maxlength="80" placeholder="Masukkan username" autocomplete="username" required autofocus>
                        </div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label" for="password">PASSWORD</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input id="password" type="password" name="password" class="form-control" maxlength="128" placeholder="Masukkan password" autocomplete="current-password" required>
                            <button type="button" class="btn password-toggle" id="togglePassword" aria-label="Tampilkan password"><i class="bi bi-eye"></i></button>
                        </div>
                        <div class="caps-warning" id="capsWarning"></div>
                    </div>
                    <button type="submit" class="btn btn-login w-100 mt-3" id="btnSubmit">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Masuk
                    </button>
                </form>
            </div>
        </div>
    </section>
</main>
<script src="<?= base_url('assets/js/http.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/http.js') ?>" defer></script>
<script src="<?= base_url('assets/js/login.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/login.js') ?>" defer></script>
</body>
</html>
