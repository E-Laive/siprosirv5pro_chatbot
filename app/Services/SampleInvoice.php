<?php

namespace App\Services;

/** A single labelled sample document shared by screen, print and PDF. */
final class SampleInvoice
{
    public function data(): array
    {
        $catalog = new CatalogData();
        $invoice = $catalog->report('transaksi')['rows'][0];
        $quantities = [5, 10, 5, 3];
        $lines = [];
        foreach ($catalog->report('produk')['rows'] as $index => $product) {
            $lines[] = $product + ['quantity' => $quantities[$index], 'subtotal' => $product['price'] * $quantities[$index]];
        }
        $subtotal = array_sum(array_column($lines, 'subtotal'));
        return $invoice + ['lines' => $lines, 'subtotal' => $subtotal, 'service' => 10000, 'paid' => 800000, 'change' => 800000 - $invoice['total']];
    }
}
