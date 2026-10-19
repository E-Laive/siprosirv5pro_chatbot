<!doctype html>
<html lang="id"><head><meta charset="utf-8"><style>
@page { margin: 30px; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #172033; }
h1 { font-size: 20px; margin: 0 0 6px; }
p { color: #526076; margin: 5px 0; }
table { border-collapse: collapse; width: 100%; margin-top: 20px; }
thead { display: table-header-group; }
th { background: #176b87; color: white; text-align: left; }
th, td { padding: 9px 7px; border-bottom: 1px solid #dde4ec; overflow-wrap: break-word; }
tbody tr:nth-child(even) { background: #f3f5f8; }
tr { page-break-inside: avoid; }
.numeric { text-align: right; white-space: nowrap; }
.footer { margin-top: 18px; font-size: 8px; }
</style></head><body>
<h1><?= esc($report['title']) ?></h1>
<p><?= esc($report['note']) ?></p>
<p><?= esc($filterLabel) ?></p>
<table><thead><tr><?php foreach ($report['columns'] as [$label, $type]): ?><th class="<?= in_array($type, ['number', 'currency'], true) ? 'numeric' : '' ?>"><?= esc($label) ?></th><?php endforeach ?></tr></thead>
<tbody><?php foreach ($report['rows'] as $row): ?><tr><?php foreach ($report['columns'] as $key => [$label, $type]): ?><td class="<?= in_array($type, ['number', 'currency'], true) ? 'numeric' : '' ?>"><?= esc(\App\Services\CatalogData::display($row[$key], $type)) ?></td><?php endforeach ?></tr><?php endforeach ?>
<?php if ($report['rows'] === []): ?><tr><td colspan="<?= count($report['columns']) ?>">Tidak ada data yang sesuai filter.</td></tr><?php endif ?></tbody></table>
<p class="footer">Kerangka SIPROSIR · <?= count($report['rows']) ?> baris <?= ($report['key'] ?? '') === 'ai_analysis' ? 'hasil analisis' : 'data contoh' ?></p>
</body></html>
