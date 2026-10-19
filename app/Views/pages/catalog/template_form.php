<?php $products = (new \App\Services\CatalogData())->report('produk')['rows']; ?>
<section class="showcase-stack">
<p class="demo-note">Alur form produk contoh. Draft tersimpan pada sesi tab ini; Simpan Produk menampilkan hasil simulasi tanpa menulis database.</p>
<nav aria-label="Breadcrumb"><ol class="breadcrumb"><li class="breadcrumb-item"><a class="spa-link" href="<?= site_url('katalog/tabel-data') ?>">Daftar produk</a></li><li class="breadcrumb-item active" aria-current="page">Form produk</li></ol></nav>
<div class="content-grid equal-grid">
    <article class="content-card">
        <div class="card-heading"><span><small>FORM PRODUK</small><h2 data-product-form-title>Tambah produk</h2></span><span class="status-chip neutral" data-product-status>Belum disimpan</span></div>
        <form class="sample-form" id="sampleProductForm" data-product-form data-dirty-form data-products="<?= esc(json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), 'attr') ?>" novalidate>
            <div class="kit-draft-banner" data-draft-banner role="status" aria-live="polite" hidden>
                <div class="kit-draft-banner-copy">
                    <i class="bi bi-journal-text" aria-hidden="true"></i>
                    <span><strong>Draft sesi ditemukan</strong><small>Ada isian produk yang belum selesai disimpan pada tab ini.</small></span>
                </div>
                <div class="kit-draft-banner-actions">
                    <button class="btn btn-soft btn-sm" type="button" data-load-draft hidden><i class="bi bi-arrow-clockwise" aria-hidden="true"></i> Muat Draft</button>
                    <button class="btn btn-light btn-sm" type="button" data-discard-draft><i class="bi bi-x-lg" aria-hidden="true"></i> Abaikan</button>
                </div>
            </div>
            <div class="kit-validation-summary" data-form-errors role="alert" hidden></div>
            <div class="form-pair"><label><span>SKU *</span><input class="form-control" name="sku" value="PRD-005" required pattern="[A-Za-z0-9-]{3,30}" maxlength="30"><small class="form-text">3–30 huruf, angka, atau tanda hubung.</small></label><label><span>Barcode</span><input class="form-control" name="barcode" maxlength="40" placeholder="Pindai atau masukkan"></label></div>
            <label><span>Nama produk *</span><input class="form-control" name="name" required minlength="3" maxlength="100" placeholder="Nama produk"><span class="invalid-feedback">Nama produk minimal tiga karakter.</span></label>
            <div class="form-pair"><label><span>Kategori *</span><select class="form-select" name="category" required><option value="">Pilih kategori</option><option>Makanan</option><option>Minuman</option><option>Pelengkap</option></select><span class="invalid-feedback">Pilih kategori produk.</span></label><label><span>Satuan</span><select class="form-select" name="unit"><option>Pcs</option><option>Box</option><option>Kg</option></select></label></div>
            <div class="form-pair"><label><span>Harga beli</span><div class="input-group"><span class="input-group-text">Rp</span><input class="form-control numeric" name="cost" type="number" min="0" max="999999999999" step="1" value="0"></div></label><label><span>Harga jual *</span><div class="input-group"><span class="input-group-text">Rp</span><input class="form-control numeric" name="price" type="number" min="1" max="999999999999" step="1" required></div><span class="invalid-feedback">Harga jual harus berupa rupiah bulat, minimal 1.</span></label></div>
            <label><span>Deskripsi</span><textarea class="form-control" name="description" rows="3" maxlength="300"></textarea></label>
            <p class="form-text" data-draft-status role="status" aria-live="polite"></p>
            <div class="form-actions"><button class="btn btn-light" type="button" data-product-cancel data-cancel-url="<?= site_url('katalog/tabel-data') ?>">Batal</button><button class="btn btn-soft" type="submit" name="intent" value="draft">Simpan Draft</button><button class="btn btn-primary" type="submit" name="intent" value="save">Simpan Produk</button></div>
        </form>
    </article>
    <aside class="showcase-stack">
        <article class="content-card"><div class="card-heading"><span><small>HASIL</small><h2>Ringkasan produk</h2></span></div><p class="muted-copy" data-product-result-note>Isi form dan simpan untuk melihat hasil contoh.</p><dl class="detail-grid" data-product-result hidden><div><dt>SKU</dt><dd data-result="sku"></dd></div><div><dt>Nama produk</dt><dd data-result="name"></dd></div><div><dt>Kategori</dt><dd data-result="category"></dd></div><div><dt>Satuan</dt><dd data-result="unit"></dd></div><div><dt>Harga beli</dt><dd class="numeric" data-result="cost"></dd></div><div><dt>Harga jual</dt><dd class="numeric" data-result="price"></dd></div></dl><a class="btn btn-soft spa-link" href="<?= site_url('katalog/tabel-data') ?>">Kembali ke daftar</a></article>
        <article class="content-card"><div class="card-heading"><span><small>PENGATURAN</small><h2>Perilaku produk</h2></span></div><p class="demo-note">Pilihan contoh ikut disimpan pada draft dan diperiksa bersama form.</p><div class="choice-stack"><label class="form-check form-switch"><input class="form-check-input" form="sampleProductForm" name="show_pos" type="checkbox" checked><span class="form-check-label">Tampilkan di POS</span></label><label class="form-check form-switch"><input class="form-check-input" form="sampleProductForm" name="track_stock" type="checkbox" checked><span class="form-check-label">Pantau stok minimum</span></label><label class="form-check form-switch"><input class="form-check-input" form="sampleProductForm" name="free_price" type="checkbox"><span class="form-check-label">Izinkan harga bebas</span></label></div></article>
    </aside>
</div></section>
