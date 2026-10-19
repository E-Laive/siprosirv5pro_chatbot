<div class="modal fade theme-settings-modal" id="themeSettingsModal" tabindex="-1" aria-labelledby="themeSettingsTitle" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header theme-settings-header">
                <span class="theme-settings-title-icon"><i class="bi bi-palette2"></i></span>
                <div>
                    <h2 class="modal-title" id="themeSettingsTitle">Pengaturan Tampilan</h2>
                    <p>Atur tema sistem atau setiap bagian aplikasi.</p>
                </div>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body theme-settings-body">
                <fieldset class="theme-mode-fieldset">
                    <legend>MODE TEMA</legend>
                    <div class="theme-mode-grid">
                        <label class="theme-mode-option">
                            <input type="radio" name="themeMode" value="system">
                            <span class="theme-mode-icon"><i class="bi bi-display"></i></span>
                            <span class="theme-mode-copy"><strong>Ikuti Sistem</strong><small>Menyesuaikan tema Windows atau browser secara otomatis.</small></span>
                            <span class="theme-radio-mark"></span>
                        </label>
                        <label class="theme-mode-option">
                            <input type="radio" name="themeMode" value="custom">
                            <span class="theme-mode-icon"><i class="bi bi-sliders"></i></span>
                            <span class="theme-mode-copy"><strong>Kustom</strong><small>Atur sidebar, topbar, konten, dan footer terpisah.</small></span>
                            <span class="theme-radio-mark"></span>
                        </label>
                    </div>
                </fieldset>

                <div class="theme-system-status" id="themeSystemStatus">
                    <span class="theme-system-icon"><i class="bi bi-pc-display-horizontal"></i></span>
                    <span><small>TEMA SISTEM TERDETEKSI</small><strong id="detectedSystemTheme">Gelap</strong></span>
                    <span class="theme-system-live"><i class="bi bi-broadcast-pin"></i>Aktif</span>
                </div>

                <div class="theme-preset-actions" role="group" aria-label="Tema semua bagian" aria-describedby="themePresetHelp">
                    <button class="btn" type="button" data-theme-preset="light" aria-pressed="false"><i class="bi bi-sun" aria-hidden="true"></i>Semua Terang</button>
                    <button class="btn" type="button" data-theme-preset="dark" aria-pressed="false"><i class="bi bi-moon-stars" aria-hidden="true"></i>Semua Gelap</button>
                </div>
                <p class="theme-preset-help" id="themePresetHelp">Pilih tema untuk semua bagian, lalu klik Terapkan.</p>

                <fieldset class="theme-region-fieldset" id="themeRegionFieldset">
                    <legend>KONFIGURASI KUSTOM</legend>
                    <div class="theme-region-list">
                        <?php
                        $regions = [
                            ['key' => 'sidebar', 'icon' => 'bi-layout-sidebar-inset', 'title' => 'Sidebar', 'copy' => 'Navigasi dan panel pengguna kiri'],
                            ['key' => 'topbar', 'icon' => 'bi-layout-text-window-reverse', 'title' => 'Topbar', 'copy' => 'Toolbar, pencarian, dan menu akun'],
                            ['key' => 'content', 'icon' => 'bi-window-stack', 'title' => 'Konten', 'copy' => 'Halaman, card, tabel, form, dan modal'],
                            ['key' => 'footer', 'icon' => 'bi-layout-text-window', 'title' => 'Footer', 'copy' => 'Informasi bagian bawah aplikasi'],
                        ];
                        ?>
                        <?php foreach ($regions as $region): ?>
                            <div class="theme-region-row" data-theme-region="<?= esc($region['key']) ?>">
                                <span class="theme-region-icon"><i class="bi <?= esc($region['icon']) ?>"></i></span>
                                <span class="theme-region-copy"><strong><?= esc($region['title']) ?></strong><small><?= esc($region['copy']) ?></small></span>
                                <span class="theme-segmented-control" role="group" aria-label="Tema <?= esc($region['title']) ?>">
                                    <label><input type="radio" name="theme_<?= esc($region['key']) ?>" value="light"><span><i class="bi bi-sun"></i>Terang</span></label>
                                    <label><input type="radio" name="theme_<?= esc($region['key']) ?>" value="dark"><span><i class="bi bi-moon-stars"></i>Gelap</span></label>
                                </span>
                            </div>
                        <?php endforeach ?>
                    </div>
                </fieldset>

                <div class="theme-preview-strip" aria-label="Ringkasan tema yang dipilih">
                    <span class="theme-preview-label">PREVIEW</span>
                    <?php foreach (['sidebar' => 'Sidebar', 'topbar' => 'Topbar', 'content' => 'Konten', 'footer' => 'Footer'] as $key => $label): ?>
                        <span class="theme-preview-item" data-theme-preview="<?= esc($key) ?>"><i></i><span><?= esc($label) ?></span><strong>Gelap</strong></span>
                    <?php endforeach ?>
                </div>
            </div>
            <div class="modal-footer theme-settings-footer">
                <button class="btn theme-reset-button" id="themeResetButton" type="button"><i class="bi bi-arrow-counterclockwise"></i>Reset ke Sistem</button>
                <span class="theme-footer-actions">
                    <button class="btn theme-cancel-button" type="button" data-bs-dismiss="modal">Batal</button>
                    <button class="btn btn-primary theme-apply-button" id="themeApplyButton" type="button"><i class="bi bi-check2"></i>Terapkan</button>
                </span>
            </div>
        </div>
    </div>
</div>
