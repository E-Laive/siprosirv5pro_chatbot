<?php
$errorPages = config('ErrorPages');
$selected = $errorPages->find(service('request')->getGet('code') ?? '404') ?? $errorPages->find(404);
?>
<section class="error-gallery" aria-label="Galeri halaman error">
    <form class="error-selector content-card" action="<?= site_url('pratinjau-error') ?>" method="get" data-error-gallery-form>
        <div class="card-heading"><span><small>HALAMAN PEMULIHAN</small><h2>Pilih kondisi</h2></span><i class="bi bi-window-x" aria-hidden="true"></i></div>
        <p class="error-selector-intro" id="errorSelectorHelp">Lihat pesan dan tindakan yang akan membantu pengguna melanjutkan.</p>
        <fieldset aria-describedby="errorSelectorHelp">
            <legend class="error-field-label">Kode halaman</legend>
            <div class="error-options">
                <?php foreach ($errorPages->pages as $code => $item): ?>
                    <label class="error-option">
                        <input type="radio" name="code" value="<?= $code ?>"<?= $code === $selected['code'] ? ' checked' : '' ?>>
                        <span><strong class="numeric"><?= $code ?></strong><span><?= esc($item['label']) ?></span></span>
                    </label>
                <?php endforeach ?>
            </div>
        </fieldset>
        <div class="error-selector-note"><i class="bi bi-shield-check" aria-hidden="true"></i><p>Pratinjau aman. Pilihan ini tidak mengakhiri sesi atau memicu error pada layanan.</p></div>
        <button class="btn btn-primary error-open-button" type="submit"><i class="bi bi-arrows-fullscreen" aria-hidden="true"></i>Buka halaman penuh</button>
    </form>
    <article class="error-preview-panel">
        <header class="error-preview-toolbar"><h2>Pratinjau tampilan</h2><span class="error-preview-badge">Contoh halaman</span></header>
        <div class="error-preview-canvas">
            <?php foreach ($errorPages->pages as $code => $item): ?>
                <?= view('components/error_surface', ['errorCode' => $code, 'isPreview' => true, 'embedded' => true, 'hidden' => $code !== $selected['code']]) ?>
            <?php endforeach ?>
        </div>
        <footer class="error-preview-caption"><i class="bi bi-circle-half" aria-hidden="true"></i><span>Mengikuti tema tampilan aplikasi</span><span class="error-live-status" role="status" aria-live="polite" data-error-live-status>Pratinjau <?= $selected['code'] ?> dipilih</span></footer>
    </article>
</section>
