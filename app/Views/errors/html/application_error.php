<?php
$base = rtrim((string) config(\Config\App::class)->baseURL, '/');
$page = config('ErrorPages')->find($errorCode ?? 500) ?? config('ErrorPages')->find(500);
$isPreview = (bool) ($isPreview ?? false);
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex, nofollow">
    <title><?= $isPreview ? 'Pratinjau ' : '' ?><?= $page['code'] ?> · <?= esc($page['title']) ?> · SIPROSIR</title>
    <link rel="stylesheet" href="<?= esc($base) ?>/assets/css/fonts.css">
    <link rel="stylesheet" href="<?= esc($base) ?>/assets/vendor/bootstrap-icons/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= esc($base) ?>/assets/css/app.css">
    <link rel="stylesheet" href="<?= esc($base) ?>/assets/css/error-pages.css">
    <script src="<?= esc($base) ?>/assets/js/theme-init.js"></script>
</head>
<body class="application-error">
    <div class="error-page-shell">
        <header class="error-page-brand"><a href="<?= esc($base) ?>/dashboard"><span class="error-brand-mark" aria-hidden="true">S</span><span>Kerangka SIPROSIR<small>Aplikasi &amp; layanan</small></span></a><?php if ($isPreview): ?><span class="error-preview-badge">Mode pratinjau</span><?php endif ?></header>
        <main>
            <?php if ($isPreview): ?><p class="error-preview-notice"><i class="bi bi-eye" aria-hidden="true"></i> Ini contoh halaman <?= $page['code'] ?>. Sesi dan layanan aplikasi tetap berjalan.</p><?php endif ?>
            <?= view('components/error_surface', ['errorCode' => $page['code'], 'isPreview' => $isPreview, 'embedded' => false, 'hidden' => false]) ?>
        </main>
        <footer class="error-page-footer"><span>Kerangka SIPROSIR</span><span>Halaman bantuan &amp; pemulihan</span></footer>
    </div>
</body>
</html>
