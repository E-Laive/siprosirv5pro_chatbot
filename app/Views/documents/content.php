<?php $money = static fn ($amount) => \App\Services\CatalogData::display($amount, 'currency'); $thermal = $format !== 'a4'; ?>
<article class="kit-document <?= $thermal ? 'kit-receipt' : 'kit-invoice' ?>" data-paper="<?= esc($format) ?>">
    <header><h2>SIPROSIR</h2><p>Toko Contoh · Jl. Usaha No. 21</p><p class="document-sample">DOKUMEN CONTOH — BUKAN TRANSAKSI AKTUAL</p></header>
    <h3><?= $thermal ? 'Struk Penjualan' : 'Invoice Penjualan' ?></h3>
    <div class="document-meta"><p><strong><?= esc($invoice['document']) ?></strong><br><?= esc($invoice['time']) ?></p><p>Pelanggan: <?= esc($invoice['customer']) ?><br>Kasir: <?= esc($invoice['cashier']) ?></p></div>
    <?php if ($thermal): ?>
        <div class="receipt-items"><?php foreach ($invoice['lines'] as $line): ?><div class="receipt-line"><strong><?= esc($line['name']) ?></strong><table><tr><td><?= $line['quantity'] ?> × <?= esc($money($line['price'])) ?></td><td class="amount"><?= esc($money($line['subtotal'])) ?></td></tr></table></div><?php endforeach ?></div>
    <?php else: ?>
        <table class="document-items"><thead><tr><th scope="col">Produk</th><th scope="col" class="amount">Jumlah</th><th scope="col" class="amount">Harga</th><th scope="col" class="amount">Subtotal</th></tr></thead><tbody><?php foreach ($invoice['lines'] as $line): ?><tr><td><?= esc($line['name']) ?><small><?= esc($line['sku']) ?></small></td><td class="amount"><?= $line['quantity'] ?> pcs</td><td class="amount"><?= esc($money($line['price'])) ?></td><td class="amount"><?= esc($money($line['subtotal'])) ?></td></tr><?php endforeach ?></tbody></table>
    <?php endif ?>
    <table class="document-totals"><tbody><?php foreach (['subtotal' => 'Subtotal', 'service' => 'Biaya layanan', 'total' => 'Total', 'paid' => 'Tunai', 'change' => 'Kembalian'] as $key => $label): ?><tr class="<?= $key === 'total' ? 'grand-total' : '' ?>"><th scope="row"><?= $label ?></th><td class="amount"><?= esc($money($invoice[$key])) ?></td></tr><?php endforeach ?></tbody></table>
    <footer><p>Terima kasih atas kunjungan Anda.</p><p>Semua nilai digunakan untuk demonstrasi template.</p></footer>
</article>
