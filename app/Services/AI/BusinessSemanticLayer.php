<?php

namespace App\Services\AI;

/**
 * Business Semantic Layer untuk Database POS SIPROSIR V5.
 * Menyediakan definisi metrik bisnis terstandar, rumus SQL yang bebas dari jebakan
 * duplikasi JOIN (fan-out), serta penjelasan metodologi yang dapat diaudit.
 */
final class BusinessSemanticLayer
{
    /**
     * Katalog definisi metrik bisnis resmi beserta ekspresi SQL dan catatan auditnya.
     *
     * @return array<string, array<string, string>>
     */
    public function metrics(): array
    {
        return [
            'penjualan_kotor' => [
                'label' => 'Penjualan Kotor (Gross Sales)',
                'domain' => 'sales',
                'formula' => 'SUM(p.total_kotor) dari tbltransaksipenjualan p',
                'rule' => "Menjumlahkan nilai penjualan sebelum diskon faktur (`potongan`) dan `ppn` pada tabel header `tbltransaksipenjualan`. Default memfilter transaksi operasional (`COALESCE(p.source_type, 'NORMAL') = 'NORMAL'`).",
            ],
            'omzet_faktur' => [
                'label' => 'Omzet Penjualan Faktur (Grand Total)',
                'domain' => 'sales',
                'formula' => 'SUM(p.grand_total) dari tbltransaksipenjualan p',
                'rule' => 'Menjumlahkan nilai tagihan akhir faktur (`total_kotor - potongan + ppn`) pada `tbltransaksipenjualan` tanpa melakukan JOIN langsung ke tabel detail agar tidak terjadi duplikasi baris.',
            ],
            'retur_penjualan_sah' => [
                'label' => 'Total Retur Penjualan Sah',
                'domain' => 'returns_receivables',
                'formula' => "SUM(r.total_retur) dari tblreturpenjualan r WHERE COALESCE(r.hapus,0)=0 AND UPPER(COALESCE(r.status,'')) IN ('FINAL','SELESAI','SUKSES')",
                'rule' => 'Hanya menghitung dokumen retur penjualan yang berstatus FINAL/SELESAI/SUKSES dan tidak dihapus (`hapus = 0`). Retur berstatus DRAFT tidak mengurangi penjualan bersih.',
            ],
            'penjualan_bersih' => [
                'label' => 'Penjualan Bersih (Net Sales)',
                'domain' => 'sales',
                'formula' => 'SUM(p.grand_total) - COALESCE(SUM(r.total_retur), 0)',
                'rule' => 'Omzet faktur (`grand_total`) dikurangi total retur penjualan sah (`status IN (FINAL, SELESAI, SUKSES)` dan `hapus = 0`) yang diagregasi secara terpisah per periode.',
            ],
            'estimasi_laba_kotor' => [
                'label' => 'Estimasi Laba Kotor (Gross Profit)',
                'domain' => 'sales',
                'formula' => 'SUM(d.total_subtotal - (d.jumlah * d.harga_beli)) - COALESCE(koreksi_laba_retur, 0)',
                'rule' => 'Dihitung dari `tbltransaksipenjualan_detail d` (`d.terjual = 1`) menggunakan `d.harga_beli` historis saat transaksi terjadi (bukan `tbldatabarang.harga_beli` saat ini). Dikurangi margin barang yang diretur pada `tblreturpenjualan_detail` (`status = FINAL`).',
            ],
            'margin_kotor_persen' => [
                'label' => 'Margin Laba Kotor (%)',
                'domain' => 'sales',
                'formula' => '(Estimasi Laba Kotor / Total Penjualan Item Bersih) * 100',
                'rule' => 'Persentase laba kotor terhadap total nilai penjualan bersih item.',
            ],
            'rata_rata_nilai_transaksi' => [
                'label' => 'Rata-rata Nilai Transaksi (Average Basket Size / AOV)',
                'domain' => 'sales',
                'formula' => 'SUM(p.grand_total) / NULLIF(COUNT(DISTINCT p.id_transaksi), 0)',
                'rule' => 'Total omzet faktur dibagi jumlah dokumen transaksi unik pada periode tersebut.',
            ],
            'rata_rata_penjualan_harian' => [
                'label' => 'Rata-rata Penjualan Harian',
                'domain' => 'sales',
                'formula' => 'SUM(p.grand_total) / NULLIF(COUNT(DISTINCT DATE(p.tanggal)), 0)',
                'rule' => 'Total omzet dibagi jumlah hari aktif transaksi (atau jumlah hari kalender pada periode yang dianalisis).',
            ],
            'nilai_persediaan_lokasi' => [
                'label' => 'Nilai Persediaan Berdasarkan Lokasi',
                'domain' => 'products_inventory',
                'formula' => 'SUM(s.stok * b.harga_beli) dari tbldatabarang_stok s JOIN tbldatabarang b',
                'rule' => 'Mengalikan saldo stok satuan dasar (`tbldatabarang_stok.stok`) di setiap `id_lokasi` dengan harga pokok master produk aktif (`tbldatabarang.harga_beli`, `COALESCE(b.hapus,0)=0`).',
            ],
            'stok_menipis' => [
                'label' => 'Produk dengan Stok Menipis / Di Bawah Minimum',
                'domain' => 'products_inventory',
                'formula' => 'Total stok produk <= tbldatabarang.min_stok (dengan COALESCE(b.hapus,0)=0)',
                'rule' => 'Membandingkan akumulasi stok produk di `tbldatabarang_stok` terhadap ambang `min_stok` pada `tbldatabarang`.',
            ],
            'piutang_belum_lunas' => [
                'label' => 'Saldo Piutang Penjualan Belum Lunas',
                'domain' => 'returns_receivables',
                'formula' => "SUM(pp.total - pp.dibayar) dari tblpiutangpenjualan pp WHERE COALESCE(pp.hapus,0)=0 AND pp.status='Belum'",
                'rule' => 'Menjumlahkan sisa tagihan piutang pelanggan (`total - dibayar` atau kolom virtual `sisa`) yang masih berstatus `Belum` dan tidak dihapus.',
            ],
            'pembelian_bersih_supplier' => [
                'label' => 'Total Pembelian Sah per Supplier',
                'domain' => 'purchasing_payables',
                'formula' => "SUM(pb.total) dari tbltransaksipembelian pb WHERE COALESCE(pb.hapus,0)=0 AND pb.status='Sukses'",
                'rule' => 'Hanya menghitung transaksi pembelian dengan `status = Sukses` dan `hapus = 0`. Dikurangi retur pembelian berstatus `FINAL` jika menghitung pembelian bersih.',
            ],
            'hutang_supplier_belum_lunas' => [
                'label' => 'Saldo Hutang Pembelian ke Supplier',
                'domain' => 'purchasing_payables',
                'formula' => "SUM(hp.total - hp.dibayar) dari tblpiutangpembelian hp WHERE COALESCE(hp.hapus,0)=0 AND hp.status='Belum'",
                'rule' => 'Tabel `tblpiutangpembelian` menyimpan kewajiban/hutang dagang toko kepada supplier atas pembelian kredit.',
            ],
        ];
    }

    /**
     * Mengembalikan konteks aturan bisnis dalam bentuk teks ringkas untuk AI Planner / LLM.
     */
    public function buildSemanticPromptContext(): string
    {
        $lines = [
            'ATURAN SEMANTIK BISNIS SIPROSIR POS V5 (WAJIB DIIKUTI):',
            "1. Tabel `tbltransaksipenjualan` dan `tbltransaksipenjualan_detail` TIDAK memiliki kolom `hapus`. Filter baris detail penjualan yang sah menggunakan `d.terjual = 1` dan `COALESCE(p.source_type, 'NORMAL') = 'NORMAL'`.",
            '2. JANGAN PERNAH menghitung `SUM(p.grand_total)` atau `SUM(p.total_kotor)` dalam satu query yang melakukan JOIN langsung ke `tbltransaksipenjualan_detail` atau `tblreturpenjualan` karena akan melipatgandakan nilai faktur (Cartesian/fan-out trap). Gunakan agregasi terpisah atau subquery.',
            '3. Penjualan Kotor = `SUM(p.total_kotor)`. Omzet Faktur = `SUM(p.grand_total)`. Penjualan Bersih = `SUM(p.grand_total)` dikurangi Retur Penjualan Sah (`tblreturpenjualan` dengan `COALESCE(hapus,0)=0` dan `UPPER(status) IN (\'FINAL\',\'SELESAI\',\'SUKSES\')`).',
            '4. Estimasi Laba Kotor dihitung dari `tbltransaksipenjualan_detail` (`terjual = 1`) menggunakan `harga_beli` historis pada baris detail: `SUM(d.total_subtotal - (d.jumlah * d.harga_beli))`, dan wajib dikurangi koreksi laba dari `tblreturpenjualan_detail` (`status = \'FINAL\'`) bila memperhitungkan retur.',
            '5. Kuantitas penjualan dalam satuan dasar adalah `d.jumlah * d.isi_satuan` karena produk dapat dijual dalam berbagai kemasan (`tblharga_satuan`).',
            '6. Saldo stok per lokasi berada di `tbldatabarang_stok` (`id_produk`, `id_lokasi`, `stok`). Nilai stok per lokasi = `SUM(s.stok * b.harga_beli)` dengan `COALESCE(b.hapus,0)=0`.',
            "7. Piutang pelanggan yang belum lunas berada di `tblpiutangpenjualan` (`COALESCE(hapus,0)=0 AND status='Belum'`), sisa tagihan = `total - dibayar` (kolom `sisa`).",
            "8. Pembelian sah ke supplier berada di `tbltransaksipembelian` (`COALESCE(hapus,0)=0 AND status='Sukses'`). Kewajiban hutang ke supplier berada di `tblpiutangpembelian` (`COALESCE(hapus,0)=0 AND status='Belum'`).",
        ];

        return implode("\n", $lines);
    }

    /**
     * Mendeteksi domain bisnis yang relevan dari teks pertanyaan bahasa alami pengguna.
     *
     * @return list<string>
     */
    public function detectDomains(string $question, array $activeContext = []): array
    {
        $q = mb_strtolower($question, 'UTF-8');
        $domains = [];

        if (preg_match('/\b(penjualan|omzet|jual|faktur|kasir|transaksi|laba|margin|profit|untung|rug|diskon|promo|kategori|tren|turun|naik|banding)\b/u', $q)) {
            $domains[] = 'sales';
        }
        if (preg_match('/\b(retur|kembalian barang|piutang|tagihan pelanggan|jatuh tempo|belum lunas|kredit pelanggan|laba|bersih)\b/u', $q)) {
            $domains[] = 'returns_receivables';
        }
        if (preg_match('/\b(produk|barang|stok|persediaan|gudang|lokasi|menipis|habis|opname|expired|kedaluwarsa|satuan|merek|rak|transfer)\b/u', $q)) {
            $domains[] = 'products_inventory';
        }
        if (preg_match('/\b(member|pelanggan|poin|loyalitas|belanja member|kelas member)\b/u', $q)) {
            $domains[] = 'members';
        }
        if (preg_match('/\b(pembelian|kulakan|supplier|supiller|pemasok|po\b|purchase order|hutang|utang)\b/u', $q) && !preg_match('/\b(member|pelanggan)\b/u', $q)) {
            $domains[] = 'purchasing_payables';
        }
        if (preg_match('/\b(member|pelanggan)\b/u', $q) && preg_match('/\b(pembelian|belanja|transaksi)\b/u', $q)) $domains[] = 'sales';
        if (preg_match('/\b(kas|bank|saldo|ledger|shift|setor|arus kas|mutasi kas|pengeluaran|biaya)\b/u', $q)) {
            $domains[] = 'cash_finance';
        }
        if (preg_match('/\b(gaji|karyawan|payroll|upah|lembur|pegawai)\b/u', $q)) {
            $domains[] = 'hr_payroll';
        }

        if ($domains === [] && !empty($activeContext['domain'])) {
            $domains[] = (string) $activeContext['domain'];
        }

        if ($domains === []) {
            $domains = ['sales', 'products_inventory'];
        }

        return array_values(array_unique($domains));
    }
}
