<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
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
    <section class="login-panel setup-panel" aria-labelledby="setupTitle">
        <aside class="login-context">
            <div>
                <span class="brand-mark"><img src="<?= base_url('assets/img/siprosir-logo.png') ?>" alt="Logo SIPROSIR"></span>
                <p class="context-eyebrow">PERSIAPAN APLIKASI</p>
                <h1>Administrator pertama</h1>
                <p>Pembuatan akun awal dilakukan melalui terminal pada server aplikasi.</p>
            </div>
            <small>CodeIgniter <?= \CodeIgniter\CodeIgniter::CI_VERSION ?> · CLI Provisioning</small>
        </aside>
        <div class="login-form-pane setup-form-pane">
            <div class="login-form-wrap">
                <p class="form-eyebrow">PROVISIONING SERVER</p>
                <h2 id="setupTitle">Setup web ditutup</h2>
                <p class="lead-copy">Pengelola server dapat menjalankan migration lalu provisioning satu kali. Password diminta secara tersembunyi.</p>
                <pre class="setup-command"><code>php spark migrate
php spark app:provision-admin</code></pre>
                <a class="btn btn-login w-100 mt-3 d-inline-flex align-items-center justify-content-center" href="<?= site_url('login') ?>"><i class="bi bi-arrow-left me-2" aria-hidden="true"></i>Kembali ke login</a>
            </div>
        </div>
    </section>
</main>
</body>
</html>
