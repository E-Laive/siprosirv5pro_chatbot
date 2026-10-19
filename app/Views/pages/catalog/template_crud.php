<?php $report = (new \App\Services\CatalogData())->report('pengguna'); ?>
<section class="content-card">
<div class="card-heading"><span><small>TEMPLATE CRUD</small><h2>Manajemen pengguna</h2></span><div class="table-heading-actions"><?= view('components/export_actions', ['dataset' => 'pengguna', 'title' => 'pengguna contoh']) ?><button type="button" class="btn btn-primary btn-sm" data-demo-action="Tambah pengguna"><i class="bi bi-person-plus"></i> Tambah Pengguna</button></div></div>
<p class="demo-note">Contoh visual CRUD. Pencarian, filter peran, dan ekspor berfungsi pada data contoh; aksi edit tidak mengubah akun aplikasi.</p>
<div class="table-toolbar"><label><span>Pencarian</span><div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input type="search" class="form-control" data-table-search placeholder="Cari pengguna"></div></label><label><span>Peran</span><select class="form-select" data-table-role><option value="">Semua peran</option><option>Administrator</option><option>Kasir</option><option>Gudang</option></select></label></div>
<?= view('components/catalog_table', ['report' => $report, 'tableClass' => 'app-table data-demo-table', 'shell' => '']) ?>
<?= view('components/table_controls', ['title' => 'pengguna']) ?>
</section>
