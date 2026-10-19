<section class="showcase-stack">
    <div class="content-grid equal-grid">
        <article class="content-card">
            <div class="card-heading"><span><small>INPUT</small><h2>Field dasar</h2></span></div>
            <div class="sample-form">
                <label><span>Nama lengkap</span><input class="form-control" value="Andi Setiawan"></label>
                <label><span>Email</span><div class="input-group"><span class="input-group-text"><i class="bi bi-envelope"></i></span><input type="email" class="form-control" placeholder="nama@contoh.com"></div></label>
                <label><span>Kategori</span><select class="form-select"><option>Pilih kategori</option><option>Retail</option><option>Grosir</option></select></label>
                <label><span>Keterangan</span><textarea class="form-control" rows="3" placeholder="Tulis keterangan"></textarea></label>
                <label><span>Upload berkas</span><input type="file" class="form-control"></label>
            </div>
        </article>
        <article class="content-card">
            <div class="card-heading"><span><small>CHOICE</small><h2>Pilihan dan kondisi</h2></span></div>
            <div class="choice-stack">
                <fieldset><legend>Status akun</legend><label class="form-check"><input class="form-check-input" type="radio" name="status-demo" checked><span class="form-check-label">Aktif</span></label><label class="form-check"><input class="form-check-input" type="radio" name="status-demo"><span class="form-check-label">Nonaktif</span></label></fieldset>
                <fieldset><legend>Hak akses</legend><label class="form-check"><input class="form-check-input" type="checkbox" checked><span class="form-check-label">Transaksi penjualan</span></label><label class="form-check"><input class="form-check-input" type="checkbox"><span class="form-check-label">Laporan keuangan</span></label></fieldset>
                <fieldset><legend>Pengaturan</legend><label class="form-check form-switch"><input class="form-check-input" type="checkbox" checked><span class="form-check-label">Notifikasi aktif</span></label><label class="form-check form-switch"><input class="form-check-input" type="checkbox"><span class="form-check-label">Mode otomatis</span></label></fieldset>
                <label><span>Readonly</span><input class="form-control" value="ID-000124" readonly></label><label><span>Disabled</span><input class="form-control" value="Tidak dapat diubah" disabled></label>
            </div>
        </article>
    </div>
    <article class="content-card form-group-showcase">
        <div class="card-heading"><span><small>FORM GROUP</small><h2>Field berlabel dan input addon</h2></span><span class="status-chip outline info"><i class="bi bi-grid-3x3-gap"></i>Reusable pattern</span></div>
        <div class="form-group-grid">
            <div class="field-group">
                <div class="field-label-row"><label for="groupBusinessName">Nama usaha <span class="required-mark">*</span></label><small>Wajib diisi</small></div>
                <input class="form-control" id="groupBusinessName" value="SIPROSIR Mart" aria-describedby="groupBusinessHelp">
                <small class="field-help" id="groupBusinessHelp">Nama ini ditampilkan pada transaksi dan laporan.</small>
            </div>
            <div class="field-group">
                <div class="field-label-row"><label for="groupPhone">Nomor WhatsApp</label><small>Opsional</small></div>
                <div class="input-group"><span class="input-group-text">+62</span><input class="form-control" id="groupPhone" inputmode="tel" value="812 3456 7890"><span class="input-group-text"><i class="bi bi-whatsapp"></i></span></div>
                <small class="field-help">Gunakan nomor aktif untuk menerima notifikasi.</small>
            </div>
            <div class="field-group">
                <div class="field-label-row"><label for="groupSubdomain">Subdomain toko</label><small>Alamat publik</small></div>
                <div class="input-group"><input class="form-control" id="groupSubdomain" value="siprosir-mart"><span class="input-group-text">.contoh.com</span></div>
                <small class="field-help valid"><i class="bi bi-check-circle"></i> Subdomain tersedia.</small>
            </div>
            <div class="field-group">
                <div class="field-label-row"><label for="groupSearch">Cari pelanggan</label><small>Dengan aksi</small></div>
                <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input class="form-control" id="groupSearch" placeholder="Nama, email, atau telepon"><button class="btn btn-primary" type="button">Cari</button></div>
                <small class="field-help">Tekan tombol Cari untuk menjalankan pencarian.</small>
            </div>
        </div>
    </article>
    <div class="content-grid equal-grid">
        <article class="content-card">
            <div class="card-heading"><span><small>SECTION GROUP</small><h2>Kelompok data terstruktur</h2></span></div>
            <fieldset class="form-section-panel">
                <legend><span><i class="bi bi-shop"></i></span><strong>Informasi outlet</strong><small>Identitas dasar cabang usaha</small></legend>
                <div class="form-section-grid">
                    <div class="field-group"><label for="sectionCode">Kode outlet</label><input class="form-control" id="sectionCode" value="OUT-001"></div>
                    <div class="field-group"><label for="sectionType">Jenis outlet</label><select class="form-select" id="sectionType"><option>Toko utama</option><option>Cabang</option><option>Gudang</option></select></div>
                    <div class="field-group field-span-full"><label for="sectionAddress">Alamat lengkap</label><textarea class="form-control" id="sectionAddress" rows="2">Jl. Contoh Usaha No. 21, Jakarta</textarea></div>
                </div>
            </fieldset>
        </article>
        <article class="content-card">
            <div class="card-heading"><span><small>INLINE GROUP</small><h2>Filter dalam satu baris</h2></span></div>
            <div class="inline-form-panel">
                <div class="field-group"><label for="inlinePeriod">Periode</label><select class="form-select" id="inlinePeriod"><option>Bulan ini</option><option>Bulan lalu</option><option>Tahun ini</option></select></div>
                <div class="field-group"><label for="inlineStatus">Status</label><select class="form-select" id="inlineStatus"><option>Semua</option><option>Aktif</option><option>Nonaktif</option></select></div>
                <button class="btn btn-primary" type="button"><i class="bi bi-funnel"></i> Terapkan</button>
            </div>
            <div class="group-note"><i class="bi bi-info-circle"></i><span>Cocok untuk filter laporan, toolbar tabel, atau pencarian cepat yang tetap memiliki label jelas.</span></div>
        </article>
    </div>
</section>
