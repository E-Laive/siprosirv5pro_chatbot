<section class="content-grid equal-grid">
    <article class="content-card">
        <div class="card-heading"><span><small>VALIDASI</small><h2>Form interaktif</h2></span></div>
        <form class="sample-form" id="catalogValidationForm" novalidate>
            <label><span>Nama produk</span><input class="form-control" name="product_name" required minlength="3"><span class="invalid-feedback">Nama produk minimal tiga karakter.</span></label>
            <label><span>Harga jual</span><div class="input-group"><span class="input-group-text">Rp</span><input class="form-control" name="price" type="number" min="1" required><span class="invalid-feedback">Harga harus lebih dari nol.</span></div></label>
            <label><span>Kategori</span><select class="form-select" required><option value="">Pilih kategori</option><option>Makanan</option><option>Minuman</option></select><span class="invalid-feedback">Kategori wajib dipilih.</span></label>
            <label class="form-check"><input class="form-check-input" type="checkbox" required><span class="form-check-label">Saya memastikan data sudah benar.</span><span class="invalid-feedback">Konfirmasi data diperlukan.</span></label>
            <div class="form-actions"><button class="btn btn-light" type="reset">Reset</button><button class="btn btn-primary" type="submit"><i class="bi bi-check2"></i> Uji Validasi</button></div>
        </form>
    </article>
    <aside class="content-card">
        <div class="card-heading"><span><small>FIELD STATE</small><h2>Kondisi masukan</h2></span></div>
        <div class="sample-form"><label><span>Valid</span><input class="form-control is-valid" value="Data benar"><span class="valid-feedback">Data dapat digunakan.</span></label><label><span>Tidak valid</span><input class="form-control is-invalid" value=""><span class="invalid-feedback">Field ini wajib diisi.</span></label><label><span>Dengan bantuan</span><input class="form-control" placeholder="Kode toko"><small class="form-text">Gunakan 6–12 karakter tanpa spasi.</small></label></div>
    </aside>
</section>
