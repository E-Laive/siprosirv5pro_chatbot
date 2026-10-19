<?php $initial = strtoupper(substr((string) $user['name'], 0, 1)); ?>
<header class="topbar" id="topbar">
    <button class="topbar-button" id="toggleSidebar" type="button" aria-label="Buka atau kecilkan sidebar"><i class="bi bi-list"></i></button>
    <button class="command-box" type="button" data-bs-toggle="modal" data-bs-target="#menuSearchModal" aria-label="Cari menu atau halaman"><i class="bi bi-search" aria-hidden="true"></i><span class="d-none d-sm-inline">Cari menu atau halaman...</span><kbd class="d-none d-sm-inline">Ctrl K</kbd></button>
    <div class="topbar-actions">
        <button class="topbar-button" id="themeSettingsToggle" type="button" data-bs-toggle="modal" data-bs-target="#themeSettingsModal" aria-label="Pengaturan tema"><i class="bi bi-palette2"></i></button>
        <button class="topbar-button d-none d-sm-grid" id="fullscreenToggle" type="button" aria-label="Layar penuh"><i class="bi bi-arrows-fullscreen"></i></button>
        <span class="topbar-divider"></span>
        <div class="dropdown">
            <button class="user-menu" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Buka menu akun">
                <span class="user-copy d-none d-md-block"><strong><?= esc($user['name']) ?></strong><small><?= esc($user['role']) ?></small></span>
                <span class="user-status-wrap">
                    <span class="user-avatar"><?= esc($initial) ?></span>
                    <span class="user-status-dot" aria-label="Sesi aktif"></span>
                </span>
                <i class="bi bi-chevron-down user-menu-chevron"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-end profile-dropdown">
                <div class="profile-summary">
                    <span class="profile-avatar-wrap">
                        <span class="user-avatar large"><?= esc($initial) ?></span>
                        <span class="user-status-dot large" aria-hidden="true"></span>
                    </span>
                    <span class="profile-summary-copy">
                        <small class="profile-kicker">AKUN AKTIF</small>
                        <strong><?= esc($user['name']) ?></strong>
                        <span class="profile-role"><i class="bi bi-shield-check"></i><?= esc($user['role']) ?></span>
                    </span>
                </div>
                <div class="profile-meta-grid">
                    <span class="profile-meta-item"><i class="bi bi-check-circle"></i><span><small>Status</small><strong>Sesi aktif</strong></span></span>
                    <span class="profile-meta-item"><i class="bi bi-grid-1x2"></i><span><small>Aplikasi</small><strong>AI ASISTAN</strong></span></span>
                </div>
                <div class="profile-section-label">PREFERENSI</div>
                <button class="dropdown-item profile-action" id="profileThemeSettingsAction" type="button" data-bs-toggle="modal" data-bs-target="#themeSettingsModal">
                    <span class="profile-action-icon"><i class="bi bi-palette2"></i></span>
                    <span class="profile-action-copy"><strong>Pengaturan Tema</strong><small>Sistem atau empat bagian aplikasi</small></span>
                    <span class="profile-action-state" id="themeModeState">Kustom</span>
                </button>
                <div class="profile-dropdown-divider"></div>
                <form class="profile-logout-form" action="<?= site_url('logout') ?>" method="post" data-no-spa>
                    <?= csrf_field() ?>
                    <button class="dropdown-item logout-button" type="submit">
                        <span class="profile-action-icon danger"><i class="bi bi-box-arrow-right"></i></span>
                        <span class="profile-action-copy"><strong>Keluar Sistem</strong><small>Akhiri sesi pada perangkat ini</small></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
