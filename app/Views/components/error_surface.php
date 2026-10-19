<?php
$page = config('ErrorPages')->find($errorCode ?? 500) ?? config('ErrorPages')->find(500);
$embedded = (bool) ($embedded ?? false);
$base = rtrim((string) config(\Config\App::class)->baseURL, '/');
$loginAction = in_array($page['code'], [401, 419], true);
$titleId = 'error-title-' . $page['code'];
?>
<section class="error-surface<?= $embedded ? ' error-surface-embedded' : '' ?>" aria-labelledby="<?= $titleId ?>"<?= !empty($hidden) ? ' hidden' : '' ?> data-error-preview="<?= $page['code'] ?>">
    <div class="error-identity" aria-hidden="true">
        <div class="error-document"><i class="bi <?= esc($page['icon']) ?>"></i><span class="error-document-line"></span><span class="error-document-line short"></span></div>
        <strong class="error-number numeric"><?= $page['code'] ?></strong>
        <span class="error-category"><?= esc($page['category']) ?></span>
    </div>
    <div class="error-message">
        <p class="error-eyebrow"><?= !empty($isPreview) ? 'PRATINJAU HALAMAN' : 'KAMI MEMBANTU ANDA MELANJUTKAN' ?></p>
        <<?= $embedded ? 'h3' : 'h1' ?> id="<?= $titleId ?>"><?= esc($page['title']) ?></<?= $embedded ? 'h3' : 'h1' ?>>
        <p class="error-description"><?= esc($page['description']) ?></p>
        <div class="error-next"><i class="bi bi-info-circle" aria-hidden="true"></i><div><strong>Langkah selanjutnya</strong><p><?= esc($page['next']) ?></p></div></div>
        <div class="error-actions">
            <a class="error-action error-action-primary" href="<?= esc($base) ?>/<?= $loginAction ? 'login' : 'dashboard' ?>"><i class="bi <?= $loginAction ? 'bi-box-arrow-in-right' : 'bi-house-door' ?>" aria-hidden="true"></i><?= $loginAction ? 'Buka halaman login' : 'Ke dashboard' ?></a>
            <?php if (!empty($isPreview) && !$embedded): ?>
                <a class="error-action" href="<?= esc($base) ?>/katalog/galeri-error?code=<?= $page['code'] ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i>Kembali ke galeri</a>
            <?php elseif ($loginAction): ?>
                <a class="error-action" href="<?= esc($base) ?>/dashboard">Ke dashboard</a>
            <?php endif ?>
        </div>
        <p class="error-footnote">Kode <?= $page['code'] ?> <span aria-hidden="true">·</span> <?= !empty($isPreview) ? 'Contoh tampilan untuk kondisi ini.' : 'Sampaikan kode ini jika memerlukan bantuan.' ?></p>
    </div>
</section>
