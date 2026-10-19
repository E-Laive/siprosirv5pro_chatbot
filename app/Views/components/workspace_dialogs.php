<?php $catalog = config(\Config\TemplateCatalog::class); ?>
<div class="modal fade" id="menuSearchModal" tabindex="-1" aria-labelledby="menuSearchTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title" id="menuSearchTitle">Cari menu</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup pencarian menu"></button></div>
        <div class="modal-body"><label class="form-label" for="menuSearchInput">Nama halaman</label><input class="form-control" id="menuSearchInput" type="search" autocomplete="off" placeholder="Contoh: asisten ai, tabel, form, cetak" aria-describedby="menuSearchCount"><p class="form-text" id="menuSearchCount" role="status" aria-live="polite"></p>
            <div class="kit-menu-results"><?php if (service('accessPolicy')->allows('ai.chat')): ?><a class="spa-link" href="<?= site_url('asisten-ai') ?>" data-menu-search="ai asistan asisten analis chat pos penjualan stok laba piutang">AI ASISTAN</a><?php endif ?></div><p data-menu-search-empty hidden>Tidak ada menu yang sesuai. Coba kata lain.</p>
        </div>
    </div></div>
</div>
<div class="modal fade" id="workspaceConfirmModal" tabindex="-1" aria-labelledby="workspaceConfirmTitle" aria-describedby="workspaceConfirmMessage" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title" id="workspaceConfirmTitle">Buang perubahan?</h2></div>
        <div class="modal-body" id="workspaceConfirmMessage">Isian belum disimpan. Perubahan akan hilang jika Anda melanjutkan.</div>
        <div class="modal-footer"><button class="btn btn-light" type="button" data-workspace-cancel>Lanjut menyunting</button><button class="btn btn-danger" type="button" data-workspace-accept>Buang perubahan</button></div>
    </div></div>
</div>
<div class="kit-offline-banner" id="workspaceOffline" role="status" hidden>Koneksi terputus. Isian pada halaman ini tetap tersedia. Sambungkan kembali sebelum memuat halaman lain.</div>
