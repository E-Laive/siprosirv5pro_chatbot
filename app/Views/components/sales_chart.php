<?php
$datasets = [
    '7' => ['labels' => ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'], 'values' => [20, 27, 24, 39, 35, 48, 54]],
    '30' => ['labels' => array_map(static fn ($day) => 'Hari ' . $day, range(1, 30)), 'values' => [20, 27, 24, 39, 35, 48, 54, 32, 28, 41, 38, 45, 51, 49, 22, 31, 34, 36, 42, 52, 55, 29, 25, 37, 33, 44, 53, 56, 43, 50]],
];
$initial = $datasets['7'];
$points = [];
foreach ($initial['values'] as $i => $value) { $points[] = (56 + $i * 90) . ',' . (230 - $value * 3); }
?>
<div data-sales-chart data-datasets="<?= esc(json_encode($datasets), 'attr') ?>">
<label class="chart-period">Periode contoh <select class="form-select compact-select" data-chart-period><option value="7">7 hari</option><option value="30">30 hari</option></select></label>
<p class="demo-note">Omzet contoh dalam juta rupiah. Data simulasi untuk menguji tampilan grafik.</p>
<div class="chart-viewport" tabindex="0" role="region" aria-label="Grafik omzet contoh, gulir jika diperlukan">
<svg class="line-chart" data-chart-svg viewBox="0 0 640 280" role="img" aria-label="Omzet contoh tujuh hari dalam juta rupiah">
<g class="chart-grid"><?php foreach ([0, 20, 40, 60] as $value): ?><line x1="56" y1="<?= 230 - $value * 3 ?>" x2="596" y2="<?= 230 - $value * 3 ?>"/><?php endforeach ?></g>
<g class="chart-labels"><?php foreach ([0, 20, 40, 60] as $value): ?><text x="30" y="<?= 235 - $value * 3 ?>"><?= $value ?></text><?php endforeach ?><?php foreach ($initial['labels'] as $i => $label): ?><text x="<?= 56 + $i * 90 ?>" y="260"><?= esc($label) ?></text><?php endforeach ?></g>
<polyline class="chart-line" points="<?= implode(' ', $points) ?>"/>
<path class="chart-area" data-chart-area d="M<?= implode(' L', $points) ?> L596,230 L56,230 Z"/>
</svg></div>
<details class="chart-data-summary"><summary>Lihat data grafik dalam teks</summary><ul data-chart-data><?php foreach ($initial['values'] as $i => $value): ?><li><?= esc($initial['labels'][$i]) ?>: <?= $value ?> juta rupiah</li><?php endforeach ?></ul></details>
</div>
