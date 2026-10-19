<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#172033">
    <meta name="csrf-token-name" content="<?= csrf_token() ?>">
    <meta name="csrf-token-value" content="<?= csrf_hash() ?>">
    <title><?= esc($title) ?> · AI ASISTAN</title>
    <link rel="preload" href="<?= base_url('assets/fonts/inter/InterVariable.woff2') ?>" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="<?= base_url('assets/css/fonts.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/fonts.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/bootstrap.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/catalog.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/catalog.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/ai-chat.css') ?>?v=<?= is_file(FCPATH . 'assets/css/ai-chat.css') ? filemtime(FCPATH . 'assets/css/ai-chat.css') : 1 ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/print-documents.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/print-documents.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/error-pages.css') ?>?v=<?= filemtime(FCPATH . 'assets/css/error-pages.css') ?>">
    <script src="<?= base_url('assets/js/theme-init.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/theme-init.js') ?>"></script>
</head>
<body class="app-body<?= $activeMenu === 'asisten-ai' ? ' ai-page' : '' ?>" data-user-id="<?= (int) session('auth_user_id') ?>">
<div id="appShell">
    <?= view('layout/topbar', ['user' => $user]) ?>
    <?= view('layout/sidebar', ['activeMenu' => $activeMenu, 'user' => $user]) ?>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <main class="content-wrapper" id="contentWrapper">
        <header class="page-header">
            <div>
                <p class="page-eyebrow">KERANGKA APLIKASI</p>
                <h1 id="pageTitle"><?= esc($pageTitle) ?></h1>
                <p id="pageSubtitle"><?= esc($pageSubtitle) ?></p>
            </div>
        </header>
        <div id="spaPageContent" tabindex="-1">
            <?= view($contentView, get_defined_vars()) ?>
        </div>
    </main>

    <footer class="app-footer"><strong>AI ASISTAN</strong><span>Analisis data POS</span></footer>
</div>
<?= view('layout/theme_modal') ?>
<?= view('components/workspace_dialogs') ?>
<div class="spa-loader" id="spaLoader" aria-hidden="true"><span></span></div>
<div class="catalog-alert-stack" id="appNotificationStack" aria-live="polite" aria-relevant="additions"></div>
<script src="<?= base_url('assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>" defer></script>
<script src="<?= base_url('assets/js/http.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/http.js') ?>" defer></script>
<script src="<?= base_url('assets/js/notifications.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/notifications.js') ?>" defer></script>
<script src="<?= base_url('assets/js/form-guard.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/form-guard.js') ?>" defer></script>
<script src="<?= base_url('assets/js/draft-store.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/draft-store.js') ?>" defer></script>
<script src="<?= base_url('assets/js/app.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/app.js') ?>" defer></script>
<script src="<?= base_url('assets/js/ui-model.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/ui-model.js') ?>" defer></script>
<script src="<?= base_url('assets/js/catalog-tables.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/catalog-tables.js') ?>" defer></script>
<script src="<?= base_url('assets/js/advanced-fields.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/advanced-fields.js') ?>" defer></script>
<script src="<?= base_url('assets/js/print-documents.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/print-documents.js') ?>" defer></script>
<script src="<?= base_url('assets/js/ai-chat.js') ?>?v=<?= is_file(FCPATH . 'assets/js/ai-chat.js') ? filemtime(FCPATH . 'assets/js/ai-chat.js') : 1 ?>" defer></script>
<script src="<?= base_url('assets/js/workspace.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/workspace.js') ?>" defer></script>
<script src="<?= base_url('assets/js/catalog.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/catalog.js') ?>" defer></script>
<script src="<?= base_url('assets/js/menu-search.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/menu-search.js') ?>" defer></script>
<script src="<?= base_url('assets/js/error-gallery.js') ?>?v=<?= filemtime(FCPATH . 'assets/js/error-gallery.js') ?>" defer></script>
</body>
</html>
