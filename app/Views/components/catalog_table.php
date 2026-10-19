<?php
$statusClass = static fn ($status) => match ($status) { 'Aktif', 'Lunas' => 'success', 'Menipis', 'Sebagian', 'Perlu follow-up' => 'warning', 'Prioritas', 'Diproses' => 'info', default => 'neutral' };
$selectable = $report['key'] === 'pelanggan';
$columns = $report['columns'];
if ($selectable) { unset($columns['email']); }
if ($report['key'] === 'produk') { unset($columns['stock']); }
?>
<div class="table-responsive <?= esc($shell ?? '') ?>" tabindex="0" role="region" aria-label="<?= esc($report['title']) ?>, gulir untuk melihat semua kolom">
<table class="table align-middle mb-0 <?= esc($tableClass) ?>" data-catalog-table data-dataset="<?= esc($report['key'], 'attr') ?>">
<caption class="visually-hidden"><?= esc($report['title']) ?> — empat baris data contoh</caption>
<thead><tr>
<?php if ($selectable): ?><th scope="col" class="selection-cell"><input type="checkbox" class="form-check-input" data-select-all aria-label="Pilih semua pelanggan di halaman ini"></th><?php endif ?>
<?php foreach ($columns as $columnKey => [$label, $type]): ?><th scope="col" data-sort-column="<?= esc($columnKey, 'attr') ?>" aria-sort="none" class="<?= in_array($type, ['number', 'currency'], true) ? 'text-end' : '' ?>"><button type="button" class="kit-sort-button" data-sort-key="<?= esc($columnKey, 'attr') ?>" aria-label="Urutkan <?= esc($label, 'attr') ?>"><?= esc($label) ?><span aria-hidden="true">↕</span></button></th><?php endforeach ?>
<?php if ($report['key'] !== 'transaksi'): ?><th scope="col" class="text-end">Aksi</th><?php endif ?>
</tr></thead><tbody>
<?php foreach ($report['rows'] as $row): ?>
<tr data-row-key="<?= esc((string) ($row['sku'] ?? $row['username'] ?? $row['document'] ?? $row['email']), 'attr') ?>" data-values="<?= esc(json_encode($row, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT), 'attr') ?>" data-status="<?= esc($row['status']) ?>" data-role="<?= esc($row['role'] ?? '') ?>" data-search="<?= esc(mb_strtolower(implode(' ', $row))) ?>">
<?php if ($selectable): ?><td class="selection-cell"><input type="checkbox" class="form-check-input" data-select-row aria-label="Pilih <?= esc($row['name']) ?>"></td><?php endif ?>
<?php foreach ($columns as $key => [$label, $type]): ?>
<td class="<?= in_array($type, ['number', 'currency'], true) ? 'text-end numeric' : '' ?>">
<?php if ($key === 'status'): ?><span class="status-chip <?= $statusClass($row[$key]) ?>"><?= esc($row[$key]) ?></span>
<?php elseif ($key === 'name' && $selectable): ?><div class="modern-person"><span class="customer-avatar teal" aria-hidden="true"><?= esc(mb_substr($row['name'], 0, 1)) ?></span><span><strong><?= esc($row['name']) ?></strong><small><?= esc($row['email']) ?></small></span></div>
<?php elseif ($key === 'time'): ?><?= esc(mb_substr($row[$key], -5)) ?><small><?= esc(mb_substr($row[$key], 0, -6)) ?></small>
<?php elseif (in_array($key, ['name', 'document'], true)): ?><strong><?= esc($row[$key]) ?></strong><?php if ($key === 'document'): ?><small>Referensi <?= esc($row['reference']) ?></small><?php elseif ($report['key'] === 'produk'): ?><small>Stok <?= $row['stock'] ?> pcs</small><?php endif ?>
<?php else: ?><?= esc(\App\Services\CatalogData::display($row[$key], $type)) ?><?php if ($key === 'customer'): ?><small><?= esc($row['customer_note']) ?></small><?php endif ?><?php endif ?>
</td><?php endforeach ?>
<?php if ($report['key'] === 'produk'): ?><td class="text-end"><a class="icon-button spa-link" href="<?= site_url('katalog/template-form') ?>?sku=<?= rawurlencode($row['sku']) ?>" aria-label="Edit <?= esc($row['name']) ?>"><i class="bi bi-pencil" aria-hidden="true"></i></a></td>
<?php elseif ($report['key'] !== 'transaksi'): ?><td class="text-end"><button type="button" class="<?= $selectable ? 'table-more-button' : 'icon-button' ?>" data-demo-action="<?= $selectable ? 'Aksi pelanggan' : 'Edit ' . esc($report['key']) ?>" aria-label="<?= $selectable ? 'Aksi' : 'Edit' ?> <?= esc($row['name']) ?>"><i class="bi <?= $selectable ? 'bi-three-dots' : 'bi-pencil' ?>" aria-hidden="true"></i></button></td><?php endif ?>
</tr><?php endforeach ?>
</tbody></table></div>
