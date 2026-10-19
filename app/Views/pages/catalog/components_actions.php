<section class="showcase-stack">
    <article class="content-card">
        <div class="card-heading"><span><small>BUTTON</small><h2>Jenis aksi</h2></span></div>
        <p class="component-label">Solid</p>
        <div class="component-row"><button class="btn btn-primary">Primary</button><button class="btn btn-secondary">Secondary</button><button class="btn btn-success">Success</button><button class="btn btn-warning">Warning</button><button class="btn btn-danger">Danger</button><button class="btn btn-info">Information</button></div>
        <hr class="demo-separator">
        <p class="component-label">Outline berdasarkan status</p>
        <div class="component-row outline-button-row">
            <button class="btn btn-demo-outline primary"><i class="bi bi-stars"></i> Primary</button>
            <button class="btn btn-demo-outline success"><i class="bi bi-check-lg"></i> Success</button>
            <button class="btn btn-demo-outline warning"><i class="bi bi-exclamation-triangle"></i> Warning</button>
            <button class="btn btn-demo-outline danger"><i class="bi bi-trash"></i> Danger</button>
            <button class="btn btn-demo-outline info"><i class="bi bi-info-circle"></i> Information</button>
            <button class="btn btn-demo-outline neutral"><i class="bi bi-three-dots"></i> Neutral</button>
        </div>
        <hr class="demo-separator">
        <p class="component-label">Ukuran dan kondisi</p>
        <div class="component-row"><button class="btn btn-soft"><i class="bi bi-plus-lg"></i> Dengan ikon</button><button class="btn btn-primary btn-sm">Kecil</button><button class="btn btn-primary">Normal</button><button class="btn btn-primary btn-lg">Besar</button><button class="btn btn-primary" disabled>Disabled</button><button class="btn btn-primary" disabled aria-busy="true"><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Loading</button></div>
    </article>
    <div class="content-grid equal-grid">
        <article class="content-card">
            <div class="card-heading"><span><small>BUTTON GROUP</small><h2>Aksi berkelompok</h2></span></div>
            <div class="btn-group" role="group" aria-label="Periode contoh"><button type="button" class="btn btn-outline-primary active" data-demo-choice aria-pressed="true">Hari</button><button type="button" class="btn btn-outline-primary" data-demo-choice aria-pressed="false">Minggu</button><button type="button" class="btn btn-outline-primary" data-demo-choice aria-pressed="false">Bulan</button></div>
            <div class="component-row mt-4"><button class="icon-button" aria-label="Edit"><i class="bi bi-pencil"></i></button><button class="icon-button" aria-label="Salin"><i class="bi bi-copy"></i></button><button class="icon-button danger" aria-label="Hapus"><i class="bi bi-trash"></i></button></div>
        </article>
        <article class="content-card">
            <div class="card-heading"><span><small>BADGE</small><h2>Status dan label</h2></span></div>
            <p class="component-label">Soft badge</p>
            <div class="component-row"><span class="status-chip success"><i class="bi bi-check-circle"></i>Aktif</span><span class="status-chip warning"><i class="bi bi-clock"></i>Pending</span><span class="status-chip danger"><i class="bi bi-x-circle"></i>Gagal</span><span class="status-chip info"><i class="bi bi-info-circle"></i>Baru</span><span class="status-chip neutral">Draft</span></div>
            <hr class="demo-separator">
            <p class="component-label">Outline badge</p>
            <div class="component-row outline-badge-row"><span class="status-chip outline primary"><i class="bi bi-stars"></i>Utama</span><span class="status-chip outline success"><i class="bi bi-check-circle"></i>Disetujui</span><span class="status-chip outline warning"><i class="bi bi-hourglass-split"></i>Menunggu</span><span class="status-chip outline danger"><i class="bi bi-x-octagon"></i>Ditolak</span><span class="status-chip outline info"><i class="bi bi-lightning"></i>Terbaru</span><span class="status-chip outline neutral"><i class="bi bi-file-earmark"></i>Arsip</span></div>
        </article>
    </div>
    <article class="content-card combination-group-card">
        <div class="card-heading"><span><small>TEXT & BUTTON GROUP</small><h2>Kombinasi input dan aksi</h2></span><span class="status-chip outline primary"><i class="bi bi-link-45deg"></i>Connected controls</span></div>
        <div class="combination-group-grid">
            <div class="combination-example">
                <div class="combination-copy"><strong>Text lalu button</strong><small>Pencarian atau eksekusi nilai input.</small></div>
                <label class="visually-hidden" for="actionProductSearch">Cari produk contoh</label><div class="input-group"><span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span><input id="actionProductSearch" class="form-control" placeholder="Cari produk"><button class="btn btn-primary" type="button" data-demo-action="Cari produk">Cari</button></div>
            </div>
            <div class="combination-example">
                <div class="combination-copy"><strong>Button lalu text</strong><small>Aksi pemilih ditempatkan sebelum nilai.</small></div>
                <div class="input-group"><button class="btn btn-demo-outline primary" type="button"><i class="bi bi-folder2-open"></i> Pilih</button><input class="form-control" value="dokumen-laporan.pdf" aria-label="Nama dokumen"></div>
            </div>
            <div class="combination-example">
                <div class="combination-copy"><strong>Button, text, button</strong><small>Kontrol kuantitas dengan nilai di tengah.</small></div>
                <div class="input-group quantity-group"><button class="btn btn-soft" type="button" data-quantity-step="-1" aria-label="Kurangi jumlah"><i class="bi bi-dash-lg" aria-hidden="true"></i></button><input class="form-control text-center" type="number" min="0" max="9999" value="12" inputmode="numeric" aria-label="Jumlah"><button class="btn btn-soft" type="button" data-quantity-step="1" aria-label="Tambah jumlah"><i class="bi bi-plus-lg" aria-hidden="true"></i></button></div>
            </div>
            <div class="combination-example">
                <div class="combination-copy"><strong>Text dengan dua button</strong><small>Simpan nilai atau buka pilihan aksi lain.</small></div>
                <div class="input-group"><input class="form-control" value="PROMO-AGUSTUS" aria-label="Kode promosi"><button class="btn btn-primary" type="button"><i class="bi bi-check2"></i> Terapkan</button><button class="btn btn-demo-outline neutral" type="button" aria-label="Pilihan lainnya"><i class="bi bi-three-dots"></i></button></div>
            </div>
        </div>
    </article>
</section>
