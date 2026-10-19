<?php
$catalogData = new \App\Services\CatalogData();
$exampleTransaction = $catalogData->report('transaksi')['rows'][0];
$exampleProducts = $catalogData->report('produk')['rows'];
$exampleQuantities = [5, 10, 5, 3];
$exampleSubtotal = 0;
foreach ($exampleProducts as $index => $product) {
    $exampleSubtotal += $product['price'] * $exampleQuantities[$index];
}
$formatMoney = static fn ($amount) => \App\Services\CatalogData::display($amount, 'currency');
?>
<article class="content-card modal-gallery" aria-labelledby="modalGalleryTitle">
    <div class="card-heading"><span><small>UKURAN & PENGGUNAAN</small><h2 id="modalGalleryTitle">Galeri Modal</h2></span><span class="demo-note">Contoh interaktif</span></div>
    <p class="muted-copy">Pilih ukuran berdasarkan tugas. Seluruh contoh mengikuti tema aplikasi dan tidak mengubah data operasional.</p>
    <div class="modal-gallery-grid">
        <section class="modal-gallery-example" aria-labelledby="modalSmallLabel">
            <span class="modal-size-label">KECIL · 300 PX</span>
            <h3 id="modalSmallLabel">Keputusan singkat</h3>
            <p>Konfirmasi satu tindakan dengan objek dan konsekuensi yang jelas.</p>
            <button type="button" class="btn btn-soft" data-bs-toggle="modal" data-bs-target="#catalogSmallModal">Modal Kecil <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
        </section>
        <section class="modal-gallery-example" aria-labelledby="modalMediumLabel">
            <span class="modal-size-label">SEDANG · 500 PX</span>
            <h3 id="modalMediumLabel">Form singkat</h3>
            <p>Tambah kategori atau satuan tanpa meninggalkan halaman daftar.</p>
            <button type="button" class="btn btn-soft" data-bs-toggle="modal" data-bs-target="#catalogMediumModal">Modal Sedang <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
        </section>
        <section class="modal-gallery-example" aria-labelledby="modalLargeLabel">
            <span class="modal-size-label">BESAR · 800 PX</span>
            <h3 id="modalLargeLabel">Detail dan rincian</h3>
            <p>Ringkasan transaksi beserta tabel item. Di ponsel, tampil layar penuh.</p>
            <button type="button" class="btn btn-soft" data-bs-toggle="modal" data-bs-target="#catalogLargeModal">Modal Besar <i class="bi bi-arrow-up-right" aria-hidden="true"></i></button>
        </section>
    </div>
    <p class="demo-note modal-gallery-note">Ukuran merupakan lebar maksimum pada layar yang sesuai. Klik di luar modal tidak menutupnya; gunakan tombol Tutup atau Batal.</p>
</article>

<div class="modal fade" id="catalogSmallModal" tabindex="-1" aria-labelledby="catalogSmallTitle" aria-describedby="catalogSmallDescription" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-sm modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title" id="catalogSmallTitle">Nonaktifkan produk?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup konfirmasi produk"></button></div>
            <div class="modal-body">
                <p><strong>Kopi Arabika 250g</strong><span class="modal-record-code">PRD-001</span></p>
                <p id="catalogSmallDescription">Produk tidak lagi muncul pada pilihan penjualan. Riwayat transaksi tetap tersedia.</p>
                <p class="demo-note mb-0">Simulasi konfirmasi. Data produk tidak diubah.</p>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal" data-modal-initial-focus>Batal</button><button type="button" class="btn btn-primary" data-modal-demo-confirm>Nonaktifkan</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="catalogMediumModal" tabindex="-1" aria-labelledby="catalogMediumTitle" aria-describedby="catalogMediumDescription" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content catalog-modal-form" id="catalogCategoryForm" data-modal-demo-form novalidate>
            <div class="modal-header"><h2 class="modal-title" id="catalogMediumTitle">Tambah kategori</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup form kategori"></button></div>
            <div class="modal-body">
                <p class="demo-note" id="catalogMediumDescription">Contoh form singkat dengan validasi. Penyimpanan hanya disimulasikan.</p>
                <div class="mb-3"><label class="form-label" for="catalogCategoryName">Nama kategori <span class="required-mark" aria-hidden="true">*</span></label><input type="text" class="form-control" id="catalogCategoryName" name="category_name" placeholder="Contoh: Minuman" required minlength="3" maxlength="60" aria-describedby="catalogCategoryNameHelp catalogCategoryNameError" data-modal-initial-focus><div class="form-text" id="catalogCategoryNameHelp">Wajib diisi, 3–60 karakter.</div><div class="invalid-feedback" id="catalogCategoryNameError">Masukkan nama kategori minimal tiga karakter.</div></div>
                <div class="mb-3"><label class="form-label" for="catalogCategoryDescription">Keterangan <span class="optional-label">opsional</span></label><textarea class="form-control" id="catalogCategoryDescription" name="description" rows="3" maxlength="200" placeholder="Keterangan singkat kategori" aria-describedby="catalogCategoryDescriptionHelp"></textarea><div class="form-text" id="catalogCategoryDescriptionHelp">Maksimal 200 karakter.</div></div>
                <div><label class="form-label" for="catalogCategoryStatus">Status</label><select class="form-select" id="catalogCategoryStatus" name="status"><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button><button type="submit" class="btn btn-primary">Simulasikan simpan</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="catalogLargeModal" tabindex="-1" aria-labelledby="catalogLargeTitle" aria-describedby="catalogLargeDescription" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content">
            <div class="modal-header"><div><h2 class="modal-title" id="catalogLargeTitle">Detail transaksi</h2><span class="modal-record-code"><?= esc($exampleTransaction['document']) ?></span></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup detail transaksi" data-modal-initial-focus></button></div>
            <div class="modal-body">
                <p class="demo-note" id="catalogLargeDescription">Data contoh transaksi. Gunakan pola ini untuk melihat ringkasan dan rincian item.</p>
                <dl class="modal-transaction-meta"><div><dt>Pelanggan</dt><dd><?= esc($exampleTransaction['customer']) ?></dd></div><div><dt>Waktu</dt><dd><?= esc($exampleTransaction['time']) ?></dd></div><div><dt>Kasir</dt><dd><?= esc($exampleTransaction['cashier']) ?></dd></div><div><dt>Status</dt><dd><span class="status-chip success"><?= esc($exampleTransaction['status']) ?></span></dd></div></dl>
                <p class="demo-note d-sm-none">Geser tabel ke samping untuk melihat seluruh kolom.</p>
                <div class="table-responsive" tabindex="0" role="region" aria-label="Rincian item transaksi, gulir untuk melihat semua kolom">
                    <table class="table app-table align-middle mb-0"><caption class="visually-hidden">Empat item contoh transaksi <?= esc($exampleTransaction['document']) ?></caption><thead><tr><th scope="col">Produk</th><th scope="col" class="text-end">Jumlah</th><th scope="col" class="text-end">Harga</th><th scope="col" class="text-end">Subtotal</th></tr></thead><tbody>
                    <?php foreach ($exampleProducts as $index => $product): ?>
                        <tr><td><strong><?= esc($product['name']) ?></strong><small class="modal-record-code"><?= esc($product['sku']) ?></small></td><td class="text-end numeric"><?= $exampleQuantities[$index] ?> pcs</td><td class="text-end numeric"><?= esc($formatMoney($product['price'])) ?></td><td class="text-end numeric"><?= esc($formatMoney($product['price'] * $exampleQuantities[$index])) ?></td></tr>
                    <?php endforeach ?>
                    </tbody></table>
                </div>
                <dl class="modal-transaction-totals"><div><dt>Subtotal item</dt><dd><?= esc($formatMoney($exampleSubtotal)) ?></dd></div><div><dt>Biaya layanan</dt><dd><?= esc($formatMoney($exampleTransaction['total'] - $exampleSubtotal)) ?></dd></div><div class="modal-grand-total"><dt>Total transaksi</dt><dd><?= esc($formatMoney($exampleTransaction['total'])) ?></dd></div></dl>
            </div>
            <div class="modal-footer"><span class="demo-note me-auto">Contoh tampilan detail, tanpa perubahan data.</span><button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tutup</button></div>
        </div>
    </div>
</div>
