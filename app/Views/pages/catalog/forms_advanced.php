<section class="showcase-stack">
<p class="demo-note">Contoh interaktif lokal. Pilihan tanggal dan nominal dapat diperiksa; gambar hanya dibaca sebagai preview pada perangkat ini.</p>
<form class="sample-form" data-kit-form data-kit-date-range data-dirty-form novalidate>
<div class="kit-validation-summary" data-form-errors role="alert" hidden></div>
<div class="content-grid equal-grid">
    <article class="content-card">
        <div class="card-heading"><span><small>PERIODE & NOMINAL</small><h2>Filter laporan</h2></span></div>
        <div class="form-group-grid">
            <div class="field-group"><label for="kitDateFrom">Tanggal awal *</label><input id="kitDateFrom" class="form-control" type="date" name="date_from" required value="2026-08-01"></div>
            <div class="field-group"><label for="kitDateTo">Tanggal akhir *</label><input id="kitDateTo" class="form-control" type="date" name="date_to" required value="2026-08-31" aria-describedby="kitDateHelp"><small id="kitDateHelp" class="form-text">Tanggal akhir harus sama atau setelah tanggal awal.</small></div>
            <div class="field-group"><label for="kitTime">Jam mulai</label><input id="kitTime" class="form-control" type="time" name="time" value="08:00"></div>
            <div class="field-group"><label for="kitRupiah">Batas nominal *</label><div class="input-group"><span class="input-group-text">Rp</span><input id="kitRupiah" class="form-control numeric" data-currency name="amount" inputmode="numeric" required pattern="[0-9.]+" value="1500000" aria-describedby="kitAmountHelp"></div><small id="kitAmountHelp" class="form-text" data-currency-value></small></div>
        </div>
    </article>
    <article class="content-card">
        <div class="card-heading"><span><small>PEMILIH DATA</small><h2>Pelanggan dan outlet</h2></span></div>
        <div class="field-group"><label for="kitCustomer">Cari pelanggan *</label><input id="kitCustomer" class="form-control" list="kitCustomers" name="customer" required placeholder="Ketik nama pelanggan" aria-describedby="kitCustomerHelp"><datalist id="kitCustomers"><option value="Andini Putri"></option><option value="Bima Store"></option><option value="Citra Lestari"></option><option value="Damar Niaga"></option></datalist><small id="kitCustomerHelp" class="form-text">Saran lokal empat pelanggan contoh. Nama baru juga dapat diketik.</small></div>
        <fieldset class="kit-multi-outlet"><legend>Outlet laporan</legend><label class="form-label" for="kitOutlets">Pilih satu atau beberapa outlet</label><select class="form-select" id="kitOutlets" name="outlets" multiple size="4" data-multiselect aria-describedby="kitOutletsHelp"><option value="utama" selected>Toko Utama</option><option value="cabang">Cabang Utara</option><option value="gudang">Gudang Pusat</option><option value="web">Toko Online</option></select><small class="form-text" id="kitOutletsHelp">Pada desktop, gunakan Ctrl/Cmd untuk beberapa pilihan. Pada ponsel gunakan pemilih bawaan.</small><p class="form-text" data-multiselect-summary role="status" aria-live="polite"></p></fieldset>
    </article>
</div>
<article class="content-card" data-upload-example>
    <div class="card-heading"><span><small>GAMBAR</small><h2>Preview berkas</h2></span></div>
    <div class="kit-upload-grid"><div><label class="form-label" for="kitImage">Gambar produk</label><input class="form-control" type="file" id="kitImage" name="image" accept="image/png,image/jpeg,image/webp" data-image-upload aria-describedby="kitImageHelp kitImageStatus"><small class="form-text" id="kitImageHelp">PNG, JPEG, atau WebP, maksimal 2 MB. Berkas tidak dikirim ke server.</small><p id="kitImageStatus" data-upload-status role="status" aria-live="polite">Belum ada gambar.</p><button type="button" class="btn btn-soft" data-file-remove disabled>Hapus pilihan gambar</button></div><img class="kit-image-preview" alt="Preview gambar yang dipilih" hidden></div>
</article>
<div class="kit-form-bottom"><p data-kit-result role="status" aria-live="polite"></p><button type="submit" class="btn btn-primary">Periksa Isian</button></div>
</form></section>
