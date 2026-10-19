<?php
namespace App\Services\AI;

/** Numeric facts originate in calculations, never in formatted UI text. */
final class ReportFacts
{
    private const LABELS = [
        'matched_records'=>'Jumlah data master yang cocok','displayed_records'=>'Jumlah data master yang ditampilkan',
        'return_documents'=>'Jumlah retur penjualan sah','cash_documents'=>'Jumlah dokumen/mutasi kas','cash_inflow'=>'Kas masuk','cash_outflow'=>'Kas keluar','cash_difference'=>'Selisih kas masuk terhadap keluar',
        'ranked_products'=>'Jumlah produk dalam peringkat','product_units'=>'Unit dasar produk peringkat pertama','product_amount'=>'Nominal item produk peringkat pertama','product_frequency'=>'Frekuensi nota produk peringkat pertama',
        'net_sales' => 'Penjualan bersih setelah retur', 'invoice_sales' => 'Omzet faktur sebelum retur',
        'gross_sales' => 'Penjualan kotor', 'returns' => 'Retur penjualan sah', 'monthly_average' => 'Rata-rata omzet bulanan',
        'baseline' => 'Total periode dasar', 'target' => 'Total periode pembanding', 'difference' => 'Selisih pembanding terhadap dasar',
        'growth' => 'Perubahan terhadap dasar', 'transactions' => 'Jumlah transaksi', 'member_spend' => 'Total belanja member sebelum retur',
        'transaction_daily_average' => 'Rata-rata transaksi per hari aktif', 'sales_active_average' => 'Rata-rata omzet per hari aktif',
        'sales_calendar_average' => 'Rata-rata omzet per hari kalender', 'average_receipt' => 'Rata-rata nilai struk',
        'top_product_sales' => 'Omzet produk teratas', 'net_gross_profit' => 'Laba kotor setelah retur',
        'gross_profit' => 'Laba kotor sebelum retur', 'net_hpp' => 'HPP historis bersih', 'return_margin' => 'Koreksi margin retur',
        'low_stock_count' => 'Jumlah produk dengan stok menipis', 'restock_cost' => 'Estimasi biaya restock minimum',
        'stock_hpp' => 'Nilai stok berdasarkan HPP', 'stock_sale' => 'Potensi nilai jual stok', 'purchases' => 'Total pembelian sah',
        'receivables' => 'Sisa piutang aktif', 'overdue_receivables' => 'Piutang lewat jatuh tempo', 'cash_balance' => 'Saldo kas dan bank',
        'displayed_members' => 'Jumlah member dalam hasil yang ditampilkan', 'max_member_spend' => 'Nominal belanja member tertinggi sebelum retur', 'max_member_transactions' => 'Jumlah transaksi member tertinggi',
    ];

    public static function metric(string $key, float|int|null $value, string $unit = 'IDR'): array
    {
        return ['key' => $key, 'label' => self::LABELS[$key] ?? 'Indikator numerik', 'value' => $value, 'unit' => $unit];
    }

    public function capture(array $context): array
    {
        $metrics = [];
        if(($context['intent']??'')==='data_lookup')$metrics[] = self::metric(!empty($context['lookup_partial'])?'displayed_records':'matched_records',(int)($context['lookup_count']??0),'number');
        foreach ($context['last_kpis'] ?? [] as $item) if (isset($item['fact'])) $metrics[] = $item['fact'];
        if (($context['intent'] ?? '') === 'member_activity' && !empty($context['last_rows'])) {
            $metrics = [self::metric('displayed_members', count($context['last_rows']), 'number'),
                self::metric('max_member_spend', max(array_column($context['last_rows'], 'total_belanja'))),
                self::metric('max_member_transactions', max(array_column($context['last_rows'], 'jumlah_transaksi')), 'number')];
        }
        if ($metrics === [] && in_array($context['intent'] ?? '', ['sales_daily','member_spend'], true)) {
            $key = $context['intent'] === 'sales_daily' ? 'omzet_faktur' : 'total_belanja';
            $metrics[] = self::metric($context['intent'] === 'sales_daily' ? 'invoice_sales' : 'member_spend', array_sum(array_column($context['last_rows'] ?? [], $key)));
            $metrics[] = self::metric('transactions', array_sum(array_column($context['last_rows'] ?? [], 'jumlah_transaksi')), 'number');
        }
        if ($metrics === [] && ($context['intent'] ?? '') === 'dynamic_llm_sql' && count($context['last_rows'] ?? []) === 1) {
            foreach ($context['last_columns'] ?? [] as $column) {
                $value = $context['last_rows'][0][$column['key']] ?? null;
                if (!in_array($column['type'], ['number','currency'], true) || !is_numeric($value)) continue;
                $metrics[] = ['key' => 'result_' . count($metrics), 'label' => 'Indikator ' . (count($metrics) + 1), 'value' => (float) $value, 'unit' => $column['type'] === 'currency' ? 'IDR' : 'number'];
            }
        }
        return ['version' => 1, 'metrics' => array_slice($metrics, 0, 3)];
    }

    public function explain(array $facts, string $question): string
    {
        $reply = 'Laporan ini mencakup **' . $facts['period_label'] . '**.';
        if ($facts['empty']) return $reply . ' Tidak ditemukan transaksi sesuai periode dan filter laporan.';
        $metrics = $facts['metrics'] ?? [];
        foreach ($metrics as $metric) {
            $v = $metric['value'];
            $display = $v === null ? 'tidak dapat dihitung' : ($metric['unit'] === 'IDR' ? 'Rp' : '') . number_format((float) $v, $metric['unit'] === 'percent' || floor((float) $v) !== (float) $v ? 2 : 0, ',', '.') . ($metric['unit'] === 'percent' ? '%' : '');
            $reply .= ' ' . $metric['label'] . ': **' . $display . '**.';
        }
        if (!empty($facts['period']['comparison']) && count($metrics) === 3) {
            $base = $metrics[0]['value']; $delta = $metrics[2]['value'];
            $reply .= $base == 0 ? ' Persentase perubahan tidak dihitung karena nilai dasar nol.' : ' Perubahannya **' . number_format($delta / $base * 100, 2, ',', '.') . '%**, dihitung dari selisih dibagi nilai dasar.';
        }
        if ($facts['empty']) $reply .= ' Tidak ditemukan transaksi sesuai cakupan laporan.';
        if ($metrics === []) $reply .= ' Rincian tersedia pada laporan tersimpan; ringkasan angka terverifikasi belum tersedia untuk laporan ini.';
        if (preg_match('/\b(kenapa|mengapa|penyebab|sebab|turun|naik)\b/iu', $question)) $reply .= ' Angka ini belum membuktikan penyebab perubahan. Pemeriksaan produk, transaksi, atau retur diperlukan untuk menilainya.';
        return $reply;
    }
}
