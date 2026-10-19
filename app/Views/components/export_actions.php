<div class="export-actions" role="group" aria-label="Ekspor <?= esc($title) ?>">
<?php if (service('accessPolicy')->allows('export.' . $dataset)): ?>
<a class="btn btn-soft btn-sm" href="<?= site_url('ekspor/' . $dataset . '/excel') ?>" data-export download><i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Excel</a>
<a class="btn btn-soft btn-sm" href="<?= site_url('ekspor/' . $dataset . '/pdf') ?>" data-export download><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> PDF</a>
<?php endif ?>
</div>
