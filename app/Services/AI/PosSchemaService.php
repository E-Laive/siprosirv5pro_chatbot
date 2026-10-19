<?php

namespace App\Services\AI;

use Config\AiAnalyst;

/**
 * Schema Catalog & Intelligence Service untuk Database POS (dbsiprosir_online_v5_x2).
 * Menggabungkan definisi kurasi domain bisnis, parser DDL referensi, dan introspeksi
 * serta penegakan hak akses level tabel/kolom.
 */
final class PosSchemaService
{
    private AiAnalyst $config;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $parsedDdlCache = null;

    public function __construct(?AiAnalyst $config = null)
    {
        $this->config = $config ?? config(AiAnalyst::class);
    }

    /**
     * Katalog tabel bisnis POS yang dikurasi beserta relasi, filter status/hapus, dan aturan agregasinya.
     *
     * @return array<string, array<string, mixed>>
     */
    public function catalog(): array
    {
        return [
            'tbltransaksipenjualan' => [
                'domain' => 'sales',
                'description' => 'Header transaksi penjualan POS (kasir/faktur penjualan).',
                'primary_key' => ['id_transaksi'],
                'has_soft_delete' => false,
                'status_filter' => "COALESCE(source_type, 'NORMAL') = 'NORMAL'",
                'columns' => [
                    'id_transaksi' => 'ID unik transaksi penjualan (PK)',
                    'no_faktur' => 'Nomor faktur/struk penjualan',
                    'tanggal' => 'Waktu transaksi penjualan (datetime)',
                    'id_member' => 'ID pelanggan/member (relasi ke tbldata_member.id_member, kosong/NULL jika non-member)',
                    'customer' => 'Nama pelanggan pada struk',
                    'id_admin' => 'ID/username kasir atau admin pembuat transaksi',
                    'total_kotor' => 'Total nilai penjualan kotor sebelum potongan faktur dan PPN (Rp)',
                    'potongan' => 'Potongan/diskon tingkat faktur (Rp)',
                    'potongan_poin' => 'Potongan dari penukaran poin member',
                    'ppn' => 'Nilai Pajak Pertambahan Nilai (Rp)',
                    'grand_total' => 'Total bersih tagihan faktur setelah potongan dan PPN (Rp)',
                    'bayar' => 'Jumlah uang yang dibayarkan pelanggan (Rp)',
                    'kembalian' => 'Uang kembalian ke pelanggan (Rp)',
                    'mode_pembayaran' => 'Metode bayar utama (TUNAI, TRANSFER, QRIS, PIUTANG, dll.)',
                    'id_bank' => 'Kode akun kas/bank penerima (CASH, KAS_LACI, atau kode bank di tbldata_bank)',
                    'id_shift' => 'ID shift kasir (relasi ke tblkas_shift.id_shift)',
                    'source_type' => "Asal transaksi: 'NORMAL' (operasional POS) atau 'LEGACY_HISTORY' (data migrasi)",
                ],
                'relations' => [
                    'tbltransaksipenjualan_detail' => 'tbltransaksipenjualan.id_transaksi = tbltransaksipenjualan_detail.id_transaksi (1:N)',
                    'tbltransaksipenjualan_bank' => 'tbltransaksipenjualan.id_transaksi = tbltransaksipenjualan_bank.id_transaksi (1:N)',
                    'tbldata_member' => 'tbltransaksipenjualan.id_member = tbldata_member.id_member (N:1)',
                    'tblreturpenjualan' => 'tbltransaksipenjualan.id_transaksi = tblreturpenjualan.id_transaksi (1:N)',
                    'tblpiutangpenjualan' => 'tbltransaksipenjualan.id_transaksi = tblpiutangpenjualan.id_transaksi (1:1)',
                ],
                'aggregation_notes' => 'JANGAN melakukan SUM(grand_total) atau SUM(total_kotor) bersamaan dengan JOIN langsung ke tbltransaksipenjualan_detail karena akan melipatgandakan nilai faktur sebanyak jumlah item.',
            ],
            'tbltransaksipenjualan_detail' => [
                'domain' => 'sales',
                'description' => 'Rincian baris produk yang terjual pada setiap transaksi penjualan.',
                'primary_key' => ['id_detail'],
                'has_soft_delete' => false,
                'status_filter' => 'terjual = 1',
                'columns' => [
                    'id_detail' => 'ID unik baris detail penjualan (PK)',
                    'id_transaksi' => 'ID transaksi induk (FK ke tbltransaksipenjualan.id_transaksi)',
                    'id_produk' => 'Kode produk (FK ke tbldatabarang.id_produk)',
                    'nama_produk' => 'Snapshot nama produk saat transaksi',
                    'kategori' => 'Snapshot kategori produk saat transaksi',
                    'satuan' => 'Satuan jual pada baris transaksi (mis. PCS, BOX, DUS)',
                    'isi_satuan' => 'Konversi jumlah satuan dasar per satuan jual (kuantitas dasar keluar stok = jumlah * isi_satuan)',
                    'harga_beli' => 'Harga pokok/beli historis yang tercatat saat penjualan berlangsung (Rp)',
                    'harga_jual' => 'Harga jual satuan sebelum diskon item (Rp)',
                    'jumlah' => 'Kuantitas terjual dalam satuan transaksi',
                    'diskon_item' => 'Nominal diskon per baris item (Rp)',
                    'total_subtotal' => 'Subtotal baris item bersih setelah diskon_item (Rp)',
                    'poin' => 'Poin member yang dihasilkan dari item ini',
                    'status' => 'Tipe harga penjualan (mis. Retail, Grosir, Member)',
                    'terjual' => 'Indikator baris transaksi sah/terjual (1 = terjual aktif)',
                    'id_lokasi' => 'Kode lokasi/gudang pengeluaran stok (default TOKO)',
                ],
                'relations' => [
                    'tbltransaksipenjualan' => 'tbltransaksipenjualan_detail.id_transaksi = tbltransaksipenjualan.id_transaksi (N:1)',
                    'tbldatabarang' => 'tbltransaksipenjualan_detail.id_produk = tbldatabarang.id_produk (N:1)',
                ],
                'aggregation_notes' => 'Gunakan SUM(total_subtotal) untuk omzet level produk/kategori. Gunakan SUM(jumlah * isi_satuan) untuk total volume satuan dasar. Estimasi HPP historis = SUM(jumlah * harga_beli).',
            ],
            'tbltransaksipenjualan_bank' => [
                'domain' => 'sales',
                'description' => 'Pencatatan pembayaran non-tunai/bank pada transaksi penjualan.',
                'primary_key' => ['id_detail'],
                'has_soft_delete' => false,
                'status_filter' => '',
                'columns' => [
                    'id_detail' => 'ID baris pembayaran bank (PK)',
                    'id_transaksi' => 'ID transaksi penjualan',
                    'id_bank' => 'Kode bank penerima (FK ke tbldata_bank.id_bank)',
                    'nominal' => 'Nominal pembayaran via bank tersebut (Rp)',
                    'no_ref' => 'Nomor referensi pembayaran non-tunai',
                    'tanggal' => 'Waktu pembayaran',
                ],
                'relations' => [
                    'tbltransaksipenjualan' => 'tbltransaksipenjualan_bank.id_transaksi = tbltransaksipenjualan.id_transaksi',
                    'tbldata_bank' => 'tbltransaksipenjualan_bank.id_bank = tbldata_bank.id_bank',
                ],
            ],
            'tblreturpenjualan' => [
                'domain' => 'returns_receivables',
                'description' => 'Header transaksi retur penjualan dari pelanggan.',
                'primary_key' => ['id_retur'],
                'has_soft_delete' => true,
                'status_filter' => "COALESCE(hapus, 0) = 0 AND UPPER(COALESCE(status, '')) IN ('FINAL', 'SELESAI', 'SUKSES')",
                'columns' => [
                    'id_retur' => 'Nomor dokumen retur penjualan (PK)',
                    'id_transaksi' => 'Nomor transaksi penjualan asal',
                    'tanggal' => 'Tanggal retur penjualan',
                    'tipe_retur' => 'Tipe penyelesaian retur (TUNAI, POTONG_PIUTANG, dll.)',
                    'total_retur' => 'Total nilai barang yang diretur (Rp)',
                    'potong_piutang' => 'Nilai retur yang memotong saldo piutang pelanggan (Rp)',
                    'potongan_retur' => 'Biaya/potongan administrasi retur (Rp)',
                    'dana_kembali' => 'Uang tunai/transfer yang dikembalikan ke pelanggan (Rp)',
                    'status' => "Status dokumen retur ('DRAFT', 'FINAL', 'SELESAI', 'SUKSES')",
                    'id_bank' => 'Akun kas/bank pengembalian dana',
                    'id_admin' => 'Petugas yang memproses retur',
                    'hapus' => 'Flag hapus (0 = aktif, 1 = dihapus)',
                ],
                'relations' => [
                    'tbltransaksipenjualan' => 'tblreturpenjualan.id_transaksi = tbltransaksipenjualan.id_transaksi',
                    'tblreturpenjualan_detail' => 'tblreturpenjualan.id_retur = tblreturpenjualan_detail.id_retur',
                ],
            ],
            'tblreturpenjualan_detail' => [
                'domain' => 'returns_receivables',
                'description' => 'Detail produk yang diretur oleh pelanggan.',
                'primary_key' => ['id_retur', 'id_transaksi', 'id_produk', 'satuan', 'status'],
                'has_soft_delete' => false,
                'status_filter' => "UPPER(COALESCE(status, '')) = 'FINAL'",
                'columns' => [
                    'id_detail' => 'ID unik baris retur penjualan',
                    'id_retur' => 'ID dokumen retur induk',
                    'id_transaksi' => 'ID transaksi penjualan asal',
                    'id_produk' => 'Kode produk yang diretur',
                    'nama_produk' => 'Nama produk yang diretur',
                    'satuan' => 'Satuan barang retur',
                    'isi_satuan' => 'Isi konversi satuan dasar',
                    'jumlah' => 'Kuantitas retur dalam satuan transaksi',
                    'qty_dasar' => 'Kuantitas retur dalam satuan dasar (jumlah * isi_satuan)',
                    'harga_beli' => 'Harga beli produk saat retur',
                    'harga_jual' => 'Harga jual satuan yang diretur',
                    'diskon_item' => 'Diskon item pada saat penjualan',
                    'subtotal_retur' => 'Total nilai retur untuk baris produk ini (Rp)',
                    'id_lokasi' => 'Lokasi pengembalian stok',
                    'kondisi_barang' => 'Kondisi fisik barang (BAIK / RUSAK)',
                    'aksi_stok' => 'Tindak lanjut stok (KEMBALI_STOK / BUANG)',
                    'status' => 'Status baris retur (FINAL)',
                ],
                'relations' => [
                    'tblreturpenjualan' => 'tblreturpenjualan_detail.id_retur = tblreturpenjualan.id_retur',
                    'tbldatabarang' => 'tblreturpenjualan_detail.id_produk = tbldatabarang.id_produk',
                ],
            ],
            'tblpiutangpenjualan' => [
                'domain' => 'returns_receivables',
                'description' => 'Daftar piutang penjualan pelanggan yang belum/sudah lunas.',
                'primary_key' => ['id_piutang'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_piutang' => 'Nomor dokumen piutang penjualan (PK)',
                    'id_transaksi' => 'ID transaksi penjualan terkait',
                    'id_member' => 'ID member/pelanggan debitur (FK ke tbldata_member.id_member)',
                    'total' => 'Total nilai piutang awal (Rp)',
                    'dibayar' => 'Akumulasi pembayaran piutang yang sudah diterima (Rp)',
                    'sisa' => 'Sisa tagihan piutang yang belum dibayar (total - dibayar) (Rp)',
                    'status' => "Status pelunasan piutang: 'Belum' atau 'Lunas'",
                    'tanggal' => 'Tanggal timbulnya piutang',
                    'jatuh_tempo' => 'Tanggal jatuh tempo piutang (date)',
                    'terakhir_bayar' => 'Waktu pembayaran cicilan terakhir',
                    'id_admin' => 'Admin pencatat',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tbldata_member' => 'tblpiutangpenjualan.id_member = tbldata_member.id_member',
                    'tbltransaksipenjualan' => 'tblpiutangpenjualan.id_transaksi = tbltransaksipenjualan.id_transaksi',
                    'tblpiutangpenjualan_log' => 'tblpiutangpenjualan.id_piutang = tblpiutangpenjualan_log.id_piutang',
                ],
            ],
            'tblpiutangpenjualan_log' => [
                'domain' => 'returns_receivables',
                'description' => 'Riwayat pembayaran/cicilan piutang penjualan dari pelanggan.',
                'primary_key' => ['id_detail'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_detail' => 'ID pembayaran piutang (PK)',
                    'id_piutang' => 'ID dokumen piutang induk',
                    'jumlah' => 'Nominal pembayaran cicilan (Rp)',
                    'tanggal' => 'Waktu pembayaran',
                    'mode_pembayaran' => 'Metode bayar (TUNAI, TRANSFER, dll.)',
                    'id_bank' => 'Kode kas/bank penerima',
                    'no_ref' => 'Nomor referensi bukti bayar',
                    'keterangan' => 'Catatan pembayaran',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tblpiutangpenjualan' => 'tblpiutangpenjualan_log.id_piutang = tblpiutangpenjualan.id_piutang',
                ],
            ],
            'tbldatabarang' => [
                'domain' => 'products_inventory',
                'description' => 'Master data barang/produk beserta harga pokok, harga jual saat ini, dan batas stok minimum.',
                'primary_key' => ['id_produk'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_produk' => 'Kode unik produk/SKU (PK)',
                    'nama' => 'Nama lengkap produk',
                    'kategori' => 'Nama kategori produk (relasi ke tblkategori.kategori)',
                    'merek' => 'Merek produk (relasi ke tblmerek.merek)',
                    'id_rak' => 'Kode rak penyimpanan (relasi ke tblrak.id_rak)',
                    'id_sub' => 'Kode sub-rak',
                    'harga_beli' => 'Harga beli/pokok produk saat ini per satuan dasar (Rp)',
                    'harga_jual' => 'Harga jual retail saat ini per satuan dasar (Rp)',
                    'markup_mode' => "Mode markup harga ('NORMAL', '%', '+')",
                    'markup_value' => 'Nilai markup',
                    'min_stok' => 'Batas stok minimum untuk peringatan restock',
                    'kode' => 'Kode barcode / PLU produk',
                    'keterangan' => 'Deskripsi tambahan produk',
                    'id_group' => 'Grup produk',
                    'hapus' => 'Flag hapus (0 = produk aktif, 1 = dihapus)',
                ],
                'relations' => [
                    'tbldatabarang_stok' => 'tbldatabarang.id_produk = tbldatabarang_stok.id_produk (1:N per lokasi)',
                    'tblharga_satuan' => 'tbldatabarang.id_produk = tblharga_satuan.id_produk (1:N)',
                    'tblkategori' => 'tbldatabarang.kategori = tblkategori.kategori',
                    'tblmerek' => 'tbldatabarang.merek = tblmerek.merek',
                ],
            ],
            'tbldatabarang_stok' => [
                'domain' => 'products_inventory',
                'description' => 'Saldo kuantitas stok produk aktual berdasarkan lokasi (TOKO atau gudang).',
                'primary_key' => ['id_produk', 'id_lokasi'],
                'has_soft_delete' => false,
                'status_filter' => '',
                'columns' => [
                    'id_produk' => 'Kode produk (FK ke tbldatabarang.id_produk)',
                    'id_lokasi' => 'Kode lokasi penyimpanan (FK ke tbldata_gudang.id_gudang, mis. TOKO, GDG-01)',
                    'stok' => 'Jumlah stok tersedia saat ini dalam satuan dasar (decimal)',
                ],
                'relations' => [
                    'tbldatabarang' => 'tbldatabarang_stok.id_produk = tbldatabarang.id_produk',
                    'tbldata_gudang' => 'tbldatabarang_stok.id_lokasi = tbldata_gudang.id_gudang',
                ],
            ],
            'tbldata_gudang' => [
                'domain' => 'products_inventory',
                'description' => 'Master lokasi penyimpanan (Toko utama maupun cabang/gudang).',
                'primary_key' => ['id_gudang'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_gudang' => 'Kode lokasi/gudang (PK, mis. TOKO, GUDANG)',
                    'nama' => 'Nama lokasi/gudang',
                    'lokasi' => 'Alamat/deskripsi lokasi fisik',
                    'keterangan' => 'Catatan gudang',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tbldatabarang_stok' => 'tbldata_gudang.id_gudang = tbldatabarang_stok.id_lokasi',
                ],
            ],
            'tblharga_satuan' => [
                'domain' => 'products_inventory',
                'description' => 'Konversi multi-satuan produk (mis. PCS, PACK, DUS) beserta isi konversi dan harga jualnya.',
                'primary_key' => ['id_produk', 'nama_satuan'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_produk' => 'Kode produk',
                    'nama_satuan' => 'Nama satuan (PCS, BOX, DUS, LUSIN, dll.)',
                    'barcode' => 'Barcode khusus satuan tersebut',
                    'isi' => 'Jumlah satuan dasar di dalam satuan ini (decimal)',
                    'harga_jual' => 'Harga jual per satuan ini (Rp)',
                    'poin' => 'Poin member untuk satuan ini',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tbldatabarang' => 'tblharga_satuan.id_produk = tbldatabarang.id_produk',
                ],
            ],
            'tblharga_grosir' => [
                'domain' => 'products_inventory',
                'description' => 'Daftar harga grosir bertingkat berdasarkan kuantitas pembelian minimum.',
                'primary_key' => ['id_produk', 'min'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_produk' => 'Kode produk',
                    'nama_grosir' => 'Label tingkatan grosir',
                    'min' => 'Kuantitas minimum untuk mendapatkan harga grosir',
                    'harga_jual' => 'Harga jual satuan grosir (Rp)',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tbldatabarang' => 'tblharga_grosir.id_produk = tbldatabarang.id_produk',
                ],
            ],
            'tblharga_member' => [
                'domain' => 'products_inventory',
                'description' => 'Harga khusus produk berdasarkan kelas member.',
                'primary_key' => ['id_produk', 'id_kelas'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_produk' => 'Kode produk',
                    'id_kelas' => 'ID kelas member (FK ke tblmember_setting.id_kelas)',
                    'harga_jual' => 'Harga jual khusus kelas member (Rp)',
                    'harga_diskon' => 'Diskon khusus kelas member (Rp)',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tbldatabarang' => 'tblharga_member.id_produk = tbldatabarang.id_produk',
                    'tblmember_setting' => 'tblharga_member.id_kelas = tblmember_setting.id_kelas',
                ],
            ],
            'tblharga_diskon' => [
                'domain' => 'products_inventory',
                'description' => 'Program diskon periode terbatas per produk dan satuan.',
                'primary_key' => ['id_produk', 'dari', 'sampai', 'satuan'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0 AND status = 1',
                'columns' => [
                    'id_produk' => 'Kode produk',
                    'diskon' => 'Nilai diskon',
                    'dari' => 'Tanggal mulai promo (date)',
                    'sampai' => 'Tanggal berakhir promo (date)',
                    'satuan' => 'Satuan yang berlaku',
                    'min' => 'Kuantitas minimum pembelian',
                    'status' => 'Status aktif promo (1 = aktif)',
                    'mode_diskon' => 'Tipe perhitungan diskon',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tbldatabarang' => 'tblharga_diskon.id_produk = tbldatabarang.id_produk',
                ],
            ],
            'tbldatabarang_expired' => [
                'domain' => 'products_inventory',
                'description' => 'Pencatatan stok per batch dan tanggal kedaluwarsa (expired date).',
                'primary_key' => ['sync_uid'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_produk' => 'Kode produk',
                    'kode_batch' => 'Nomor batch produksi',
                    'id_lokasi' => 'Lokasi penyimpanan batch',
                    'tanggal_masuk' => 'Tanggal barang masuk',
                    'tanggal_expired' => 'Tanggal kedaluwarsa (date)',
                    'jumlah_awal' => 'Kuantitas awal batch',
                    'jumlah' => 'Sisa kuantitas batch saat ini',
                    'keterangan' => 'Catatan batch',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tbldatabarang' => 'tbldatabarang_expired.id_produk = tbldatabarang.id_produk',
                ],
            ],
            'tblstok' => [
                'domain' => 'products_inventory',
                'description' => 'Kartu stok / log mutasi keluar-masuk barang secara kronologis.',
                'primary_key' => ['id_log'],
                'has_soft_delete' => false,
                'status_filter' => '',
                'columns' => [
                    'id_log' => 'ID log mutasi stok (PK)',
                    'source_type' => 'Sumber mutasi (SALE, PURCHASE, SALE_RETURN, PURCHASE_RETURN, PURCHASE_ORDER_RECEIPT, STOCK_OPNAME, STOCK_TRANSFER)',
                    'source_id' => 'ID detail sumber mutasi',
                    'no_referensi' => 'Nomor dokumen referensi transaksi',
                    'posting_role' => 'Peran posting (MAIN, TRANSFER_OUT, TRANSFER_IN, OPENING)',
                    'id_produk' => 'Kode produk',
                    'id_target' => 'Kode lokasi/gudang',
                    'penambahan' => 'Jumlah stok masuk dalam satuan dasar',
                    'pengurangan' => 'Jumlah stok keluar dalam satuan dasar',
                    'stok_akhir' => 'Saldo stok lokasi setelah mutasi',
                    'tanggal' => 'Waktu mutasi stok',
                    'id_admin' => 'User pelaku transaksi',
                    'keterangan' => 'Keterangan mutasi',
                ],
                'relations' => [
                    'tbldatabarang' => 'tblstok.id_produk = tbldatabarang.id_produk',
                    'tbldata_gudang' => 'tblstok.id_target = tbldata_gudang.id_gudang',
                ],
            ],
            'tblstok_opname' => [
                'domain' => 'products_inventory',
                'description' => 'Catatan penyesuaian stok fisik (Stock Opname) di gudang/toko.',
                'primary_key' => ['id_opname'],
                'has_soft_delete' => false,
                'status_filter' => 'status = 1',
                'columns' => [
                    'id_opname' => 'Nomor dokumen opname (PK)',
                    'tanggal' => 'Waktu opname',
                    'id_produk' => 'Kode produk',
                    'id_gudang' => 'Kode lokasi/gudang',
                    'stok_sistem' => 'Stok menurut catatan sistem saat opname',
                    'stok_fisik' => 'Stok hasil hitung fisik',
                    'selisih' => 'Selisih kuantitas (stok_fisik - stok_sistem)',
                    'keterangan' => 'Alasan selisih',
                    'id_admin' => 'Petugas opname',
                    'status' => 'Status finalisasi opname (1 = final)',
                ],
                'relations' => [
                    'tbldatabarang' => 'tblstok_opname.id_produk = tbldatabarang.id_produk',
                ],
            ],
            'tbltransferbarang' => [
                'domain' => 'products_inventory',
                'description' => 'Transaksi pemindahan/mutasi stok barang antar lokasi (Toko ke Gudang atau sebaliknya).',
                'primary_key' => ['id_transfer', 'id_produk', 'dari', 'ke'],
                'has_soft_delete' => false,
                'status_filter' => '',
                'columns' => [
                    'id_transfer' => 'Nomor dokumen transfer barang',
                    'id_produk' => 'Kode produk',
                    'jumlah' => 'Jumlah dalam satuan dasar',
                    'qty_input' => 'Jumlah dalam satuan input',
                    'satuan' => 'Satuan input',
                    'isi' => 'Isi konversi satuan',
                    'jenis_dari' => 'Tipe lokasi asal (TOKO, GUDANG, LAINNYA)',
                    'dari' => 'Kode lokasi asal',
                    'jenis_ke' => 'Tipe lokasi tujuan (TOKO, GUDANG, LAINNYA)',
                    'ke' => 'Kode lokasi tujuan',
                    'tanggal' => 'Waktu transfer',
                    'keterangan' => 'Catatan transfer',
                ],
                'relations' => [
                    'tbldatabarang' => 'tbltransferbarang.id_produk = tbldatabarang.id_produk',
                ],
            ],
            'tblkategori' => [
                'domain' => 'products_inventory',
                'description' => 'Master kategori barang.',
                'primary_key' => ['kategori'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'kategori' => 'Nama kategori (PK)',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [],
            ],
            'tblmerek' => [
                'domain' => 'products_inventory',
                'description' => 'Master merek barang.',
                'primary_key' => ['merek'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'merek' => 'Nama merek (PK)',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [],
            ],
            'tblsatuan' => [
                'domain' => 'products_inventory',
                'description' => 'Master nama satuan kemasan.',
                'primary_key' => ['satuan'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'satuan' => 'Nama satuan (PK)',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [],
            ],
            'tblrak' => [
                'domain' => 'products_inventory',
                'description' => 'Master rak display/gudang.',
                'primary_key' => ['id_rak'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_rak' => 'Kode rak (PK)',
                    'nama_rak' => 'Nama rak',
                    'keterangan' => 'Keterangan lokasi rak',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [],
            ],
            'tbldata_member' => [
                'domain' => 'members',
                'description' => 'Master data member/pelanggan terdaftar beserta kelas dan saldo poin.',
                'primary_key' => ['id_member'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_member' => 'Kode unik member (PK)',
                    'kelas_member' => 'ID kelas member (FK ke tblmember_setting.id_kelas)',
                    'nama' => 'Nama lengkap member/pelanggan',
                    'alamat' => 'Alamat pelanggan',
                    'no_hp' => 'Nomor telepon/WhatsApp',
                    'email' => 'Alamat email',
                    'status' => 'Status keanggotaan (1 = aktif, 0 = nonaktif)',
                    'masa_berlaku' => 'Tanggal berakhir keanggotaan',
                    'poin' => 'Saldo poin loyalitas saat ini',
                    'created_at' => 'Tanggal registrasi member',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tblmember_setting' => 'tbldata_member.kelas_member = tblmember_setting.id_kelas',
                    'tbltransaksipenjualan' => 'tbldata_member.id_member = tbltransaksipenjualan.id_member',
                    'tblmember_poin_log' => 'tbldata_member.id_member = tblmember_poin_log.id_member',
                ],
            ],
            'tblmember_setting' => [
                'domain' => 'members',
                'description' => 'Pengaturan tingkatan/kelas member dan diskon globalnya.',
                'primary_key' => ['id_kelas'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_kelas' => 'ID kelas member (PK)',
                    'nama_kelas' => 'Nama kelas member (mis. Silver, Gold, Reseller)',
                    'diskon_global_persen' => 'Persentase diskon global kelas member (%)',
                    'min_belanja' => 'Minimum nilai belanja untuk diskon (Rp)',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [],
            ],
            'tblmember_poin_log' => [
                'domain' => 'members',
                'description' => 'Riwayat perolehan dan penukaran poin loyalitas member.',
                'primary_key' => ['id_poin'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_poin' => 'ID log poin (PK)',
                    'id_member' => 'Kode member',
                    'id_transaksi' => 'Nomor transaksi terkait',
                    'jenis' => 'Jenis mutasi poin (SALDO_AWAL, PENYESUAIAN, TRANSAKSI, REDEEM)',
                    'masuk' => 'Jumlah poin bertambah',
                    'keluar' => 'Jumlah poin berkurang',
                    'saldo_akhir' => 'Saldo poin setelah mutasi',
                    'tanggal' => 'Waktu mutasi poin',
                    'keterangan' => 'Catatan mutasi poin',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tbldata_member' => 'tblmember_poin_log.id_member = tbldata_member.id_member',
                ],
            ],
            'tbltransaksipembelian' => [
                'domain' => 'purchasing_payables',
                'description' => 'Header transaksi pembelian/kulakan barang dari supplier.',
                'primary_key' => ['id_pembelian'],
                'has_soft_delete' => true,
                'status_filter' => "COALESCE(hapus, 0) = 0 AND status = 'Sukses'",
                'columns' => [
                    'id_pembelian' => 'Nomor dokumen pembelian (PK)',
                    'id_supiller' => 'Kode supplier (FK ke tbldata_supiller.id_supiller)',
                    'status' => "Status pembelian: 'Sukses', 'Proses', atau 'Batal'",
                    'total' => 'Total nilai bersih pembelian (Rp)',
                    'bayar' => 'Jumlah yang dibayarkan saat pembelian (Rp)',
                    'id_faktur' => 'Nomor faktur dari supplier',
                    'id_po' => 'Nomor Purchase Order terkait (jika berasal dari PO)',
                    'masuk_ke' => 'Kode lokasi/gudang penerima barang',
                    'tambahan' => 'Biaya tambahan/ongkir pembelian (Rp)',
                    'potongan' => 'Potongan/diskon faktur pembelian (Rp)',
                    'mode_pembayaran' => 'Metode bayar (TUNAI, TRANSFER, HUTANG/KREDIT)',
                    'id_bank' => 'Akun kas/bank pembayaran',
                    'tanggal' => 'Tanggal transaksi pembelian',
                    'id_admin' => 'Petugas penerima/input',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tbldata_supiller' => 'tbltransaksipembelian.id_supiller = tbldata_supiller.id_supiller',
                    'tbltransaksipembelian_detail' => 'tbltransaksipembelian.id_pembelian = tbltransaksipembelian_detail.id_pembelian',
                    'tblpiutangpembelian' => 'tbltransaksipembelian.id_pembelian = tblpiutangpembelian.id_pembelian',
                ],
            ],
            'tbltransaksipembelian_detail' => [
                'domain' => 'purchasing_payables',
                'description' => 'Rincian item barang yang dibeli pada transaksi pembelian.',
                'primary_key' => ['id_produk', 'satuan', 'id_pembelian'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_detail' => 'ID baris detail pembelian',
                    'id_pembelian' => 'Nomor dokumen pembelian induk',
                    'id_produk' => 'Kode produk',
                    'nama' => 'Nama produk saat pembelian',
                    'kategori' => 'Kategori produk',
                    'satuan' => 'Satuan beli',
                    'isi' => 'Isi konversi satuan dasar',
                    'jumlah' => 'Kuantitas beli dalam satuan beli',
                    'harga' => 'Harga beli per satuan beli (Rp)',
                    'diskon' => 'Diskon item pembelian (Rp)',
                    'subtotal' => 'Subtotal baris pembelian bersih (Rp)',
                    'masuk_ke' => 'Kode gudang penerima',
                    'id_supiller' => 'Kode supplier',
                    'tanggal' => 'Tanggal pembelian',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tbltransaksipembelian' => 'tbltransaksipembelian_detail.id_pembelian = tbltransaksipembelian.id_pembelian',
                    'tbldatabarang' => 'tbltransaksipembelian_detail.id_produk = tbldatabarang.id_produk',
                ],
            ],
            'tbldata_supiller' => [
                'domain' => 'purchasing_payables',
                'description' => 'Master data pemasok / supplier barang.',
                'primary_key' => ['id_supiller'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_supiller' => 'Kode unik supplier (PK)',
                    'nama' => 'Nama perusahaan/toko supplier',
                    'alamat' => 'Alamat supplier',
                    'kota' => 'Kota supplier',
                    'provinsi' => 'Provinsi supplier',
                    'telepon' => 'Nomor telepon supplier',
                    'email' => 'Email supplier',
                    'kontak' => 'Nama kontak person (salesman)',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tbltransaksipembelian' => 'tbldata_supiller.id_supiller = tbltransaksipembelian.id_supiller',
                    'tblpiutangpembelian' => 'tbldata_supiller.id_supiller = tblpiutangpembelian.id_supiller',
                ],
            ],
            'tblpurchase_order' => [
                'domain' => 'purchasing_payables',
                'description' => 'Header dokumen pesanan pembelian (Purchase Order / PO) ke supplier.',
                'primary_key' => ['id_po'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_po' => 'Nomor PO (PK)',
                    'tanggal' => 'Tanggal terbit PO',
                    'tanggal_estimasi' => 'Estimasi tanggal barang tiba',
                    'id_supiller' => 'Kode supplier',
                    'masuk_ke' => 'Lokasi gudang tujuan',
                    'subtotal' => 'Subtotal pesanan (Rp)',
                    'tambahan' => 'Biaya tambahan (Rp)',
                    'potongan' => 'Potongan harga PO (Rp)',
                    'total' => 'Total nilai PO (Rp)',
                    'status' => 'Status PO (DRAFT, DIKIRIM, DITERIMA, SELESAI, BATAL)',
                    'id_pembelian' => 'ID pembelian realisasi',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tbldata_supiller' => 'tblpurchase_order.id_supiller = tbldata_supiller.id_supiller',
                    'tblpurchase_order_item' => 'tblpurchase_order.id_po = tblpurchase_order_item.id_po',
                ],
            ],
            'tblpurchase_order_item' => [
                'domain' => 'purchasing_payables',
                'description' => 'Item produk yang dipesan di dalam dokumen Purchase Order.',
                'primary_key' => ['id_po_item'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_po_item' => 'ID item PO (PK)',
                    'id_po' => 'Nomor PO induk',
                    'id_produk' => 'Kode produk',
                    'nama' => 'Nama produk',
                    'kategori' => 'Kategori produk',
                    'satuan' => 'Satuan pesan',
                    'isi' => 'Isi konversi satuan dasar',
                    'qty_order' => 'Jumlah yang dipesan',
                    'harga' => 'Harga satuan PO (Rp)',
                    'diskon' => 'Diskon item (Rp)',
                    'subtotal' => 'Subtotal item PO (Rp)',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tblpurchase_order' => 'tblpurchase_order_item.id_po = tblpurchase_order.id_po',
                ],
            ],
            'tblreturpembelian' => [
                'domain' => 'purchasing_payables',
                'description' => 'Header retur pembelian barang kembali ke supplier.',
                'primary_key' => ['id_retur'],
                'has_soft_delete' => true,
                'status_filter' => "COALESCE(hapus, 0) = 0 AND UPPER(COALESCE(status, '')) IN ('FINAL', 'SELESAI', 'SUKSES')",
                'columns' => [
                    'id_retur' => 'Nomor retur pembelian (PK)',
                    'id_pembelian' => 'Nomor pembelian asal',
                    'tanggal' => 'Tanggal retur pembelian',
                    'tipe_retur' => 'Tipe kompensasi (KEMBALI_DANA, POTONG_HUTANG)',
                    'total_retur' => 'Total nilai retur pembelian (Rp)',
                    'potong_hutang' => 'Nominal pemotong hutang supplier (Rp)',
                    'dana_kembali' => 'Nominal dana tunai/bank yang dikembalikan supplier (Rp)',
                    'status' => 'Status retur (FINAL)',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tbltransaksipembelian' => 'tblreturpembelian.id_pembelian = tbltransaksipembelian.id_pembelian',
                    'tblreturpembelian_detail' => 'tblreturpembelian.id_retur = tblreturpembelian_detail.id_retur',
                ],
            ],
            'tblreturpembelian_detail' => [
                'domain' => 'purchasing_payables',
                'description' => 'Detail barang yang diretur ke supplier.',
                'primary_key' => ['id_detail'],
                'has_soft_delete' => true,
                'status_filter' => "COALESCE(hapus, 0) = 0 AND UPPER(COALESCE(status, '')) = 'FINAL'",
                'columns' => [
                    'id_detail' => 'ID detail retur beli (PK)',
                    'id_retur' => 'Nomor retur pembelian induk',
                    'id_pembelian' => 'Nomor pembelian asal',
                    'id_produk' => 'Kode produk',
                    'nama_produk' => 'Nama produk',
                    'satuan' => 'Satuan retur',
                    'isi_satuan' => 'Isi satuan dasar',
                    'jumlah' => 'Jumlah satuan retur',
                    'qty_dasar' => 'Jumlah satuan dasar',
                    'harga_beli' => 'Harga beli satuan (Rp)',
                    'subtotal_retur' => 'Total nilai baris retur pembelian (Rp)',
                    'id_lokasi' => 'Lokasi gudang asal barang retur',
                    'status' => 'Status baris (FINAL)',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tblreturpembelian' => 'tblreturpembelian_detail.id_retur = tblreturpembelian.id_retur',
                ],
            ],
            'tblpiutangpembelian' => [
                'domain' => 'purchasing_payables',
                'description' => 'Catatan kewajiban/hutang dagang toko kepada supplier atas pembelian kredit (catatan: nama tabel historis adalah tblpiutangpembelian namun bermakna Hutang Pembelian/Accounts Payable).',
                'primary_key' => ['id_piutang'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_piutang' => 'ID dokumen hutang pembelian (PK)',
                    'id_pembelian' => 'Nomor transaksi pembelian terkait',
                    'id_supiller' => 'Kode supplier kreditur (FK ke tbldata_supiller.id_supiller)',
                    'total' => 'Total nilai hutang awal ke supplier (Rp)',
                    'dibayar' => 'Jumlah yang sudah dibayar ke supplier (Rp)',
                    'status' => "Status pelunasan: 'Belum' atau 'Lunas'",
                    'tanggal' => 'Tanggal timbulnya hutang',
                    'jatuh_tempo' => 'Tanggal jatuh tempo pembayaran ke supplier',
                    'terakhir_bayar' => 'Waktu pembayaran terakhir',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tbldata_supiller' => 'tblpiutangpembelian.id_supiller = tbldata_supiller.id_supiller',
                    'tbltransaksipembelian' => 'tblpiutangpembelian.id_pembelian = tbltransaksipembelian.id_pembelian',
                    'tblpiutangpembelian_log' => 'tblpiutangpembelian.id_piutang = tblpiutangpembelian_log.id_piutang',
                ],
            ],
            'tblpiutangpembelian_log' => [
                'domain' => 'purchasing_payables',
                'description' => 'Riwayat pembayaran cicilan hutang dagang kepada supplier.',
                'primary_key' => ['id_detail'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_detail' => 'ID log bayar hutang (PK)',
                    'id_piutang' => 'ID dokumen hutang pembelian induk',
                    'jumlah' => 'Nominal yang dibayarkan (Rp)',
                    'tanggal' => 'Waktu pembayaran',
                    'mode_pembayaran' => 'Metode pembayaran',
                    'id_pembayaran' => 'Nomor referensi bukti bayar',
                    'id_bank' => 'Akun kas/bank sumber dana',
                    'keterangan' => 'Catatan pembayaran',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tblpiutangpembelian' => 'tblpiutangpembelian_log.id_piutang = tblpiutangpembelian.id_piutang',
                ],
            ],
            'tbldata_bank' => [
                'domain' => 'cash_finance',
                'description' => 'Master akun Kas (CASH/KAS_LACI) dan Rekening Bank beserta saldo akhirnya.',
                'primary_key' => ['id_bank'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0 AND COALESCE(status, 1) = 1',
                'columns' => [
                    'id_bank' => 'Kode akun kas/bank (PK, mis. CASH, KAS_LACI, BCA, BRI)',
                    'nama_bank' => 'Nama kas atau bank',
                    'no_rekening' => 'Nomor rekening bank',
                    'atas_nama' => 'Nama pemilik rekening',
                    'jenis' => 'Jenis akun (KAS / BANK)',
                    'saldo' => 'Saldo aktual saat ini (Rp)',
                    'biaya_admin' => 'Biaya admin standar (Rp)',
                    'status' => 'Status aktif akun (1 = aktif)',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tblbank_ledger' => 'tbldata_bank.id_bank = tblbank_ledger.id_bank',
                ],
            ],
            'tblbank_ledger' => [
                'domain' => 'cash_finance',
                'description' => 'Buku besar (ledger) mutasi debit/kredit seluruh akun kas dan rekening bank.',
                'primary_key' => ['id_ledger'],
                'has_soft_delete' => false,
                'status_filter' => '',
                'columns' => [
                    'id_ledger' => 'ID baris ledger (PK)',
                    'id_bank' => 'Kode akun kas/bank (FK ke tbldata_bank.id_bank)',
                    'tanggal' => 'Waktu pencatatan mutasi',
                    'no_referensi' => 'Nomor dokumen referensi sumber',
                    'source_type' => 'Tipe sumber mutasi (SALE, PURCHASE, RECEIVABLE_PAYMENT, PAYABLE_PAYMENT, SALARY, SALE_RETURN, PURCHASE_RETURN, CASH_TRANSACTION, REVERSAL)',
                    'source_id' => 'ID dokumen sumber',
                    'posting_role' => 'Peran posting (MAIN, DP, TRANSFER_OUT, TRANSFER_IN, REVERSAL)',
                    'jenis' => 'Jenis transaksi kas/bank',
                    'kategori' => 'Kategori arus kas',
                    'id_shift' => 'ID shift kasir terkait',
                    'keterangan' => 'Uraian mutasi',
                    'debit' => 'Nominal uang masuk / debit (Rp)',
                    'kredit' => 'Nominal uang keluar / kredit (Rp)',
                    'saldo_akhir' => 'Saldo akun kas/bank setelah mutasi ini (Rp)',
                    'id_admin' => 'User pencatat',
                ],
                'relations' => [
                    'tbldata_bank' => 'tblbank_ledger.id_bank = tbldata_bank.id_bank',
                ],
            ],
            'tblkas_shift' => [
                'domain' => 'cash_finance',
                'description' => 'Rekapitulasi sesi shift kasir (modal awal, penjualan tunai, kas masuk/keluar, saldo fisik, dan selisih kas).',
                'primary_key' => ['id_shift'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_shift' => 'Kode shift kasir (PK)',
                    'tanggal_buka' => 'Waktu buka shift',
                    'tanggal_tutup' => 'Waktu tutup shift',
                    'id_admin' => 'Kasir penanggung jawab shift',
                    'modal_awal' => 'Modal kas awal di laci (Rp)',
                    'total_penjualan_tunai' => 'Total penjualan tunai selama shift (Rp)',
                    'total_kas_masuk' => 'Total kas masuk operasional (Rp)',
                    'total_kas_keluar' => 'Total pengeluaran kas operasional (Rp)',
                    'total_setor' => 'Total setoran kas (Rp)',
                    'saldo_sistem' => 'Saldo kas seharusnya menurut sistem (Rp)',
                    'saldo_fisik' => 'Saldo uang tunai hasil hitung fisik saat tutup shift (Rp)',
                    'selisih' => 'Selisih kas (saldo_fisik - saldo_sistem) (Rp)',
                    'status' => "Status shift ('AKTIF' atau 'TUTUP')",
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tblkas_mutasi' => 'tblkas_shift.id_shift = tblkas_mutasi.id_shift',
                ],
            ],
            'tblkas_mutasi' => [
                'domain' => 'cash_finance',
                'description' => 'Rincian mutasi kas operasional di dalam suatu shift kasir.',
                'primary_key' => ['id_mutasi'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_mutasi' => 'ID mutasi kas (PK)',
                    'id_shift' => 'Kode shift kasir',
                    'tanggal' => 'Waktu mutasi',
                    'jenis' => 'Jenis mutasi (MASUK / KELUAR)',
                    'kategori' => 'Kategori kas',
                    'no_referensi' => 'Nomor referensi',
                    'keterangan' => 'Uraian mutasi kas',
                    'debit' => 'Kas masuk (Rp)',
                    'kredit' => 'Kas keluar (Rp)',
                    'saldo_akhir' => 'Saldo kas setelah mutasi (Rp)',
                    'id_bank' => 'Kode kas/bank',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tblkas_shift' => 'tblkas_mutasi.id_shift = tblkas_shift.id_shift',
                ],
            ],
            'tbltransaksi_kas' => [
                'domain' => 'cash_finance',
                'description' => 'Transaksi kas masuk, biaya/pengeluaran operasional, dan transfer antar kas/bank.',
                'primary_key' => ['id_transaksi_kas'],
                'has_soft_delete' => true,
                'status_filter' => "COALESCE(hapus, 0) = 0 AND status = 'FINAL'",
                'columns' => [
                    'id_transaksi_kas' => 'Nomor dokumen transaksi kas (PK)',
                    'tanggal' => 'Waktu transaksi kas',
                    'jenis' => "Jenis transaksi: 'MASUK', 'KELUAR', atau 'TRANSFER'",
                    'id_bank_asal' => 'Akun kas/bank sumber',
                    'id_bank_tujuan' => 'Akun kas/bank tujuan (khusus TRANSFER)',
                    'nominal' => 'Nominal transaksi kas (Rp)',
                    'id_kategori' => 'Kode kategori kas',
                    'kategori' => 'Nama kategori pemasukan/pengeluaran kas',
                    'id_shift' => 'ID shift kasir',
                    'keterangan' => 'Keterangan detail transaksi kas',
                    'status' => 'Status dokumen (FINAL)',
                    'id_admin' => 'User pembuat',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tblkas_kategori' => 'tbltransaksi_kas.id_kategori = tblkas_kategori.id_kategori',
                ],
            ],
            'tblkas_kategori' => [
                'domain' => 'cash_finance',
                'description' => 'Master kategori arus kas masuk dan biaya operasional keluar.',
                'primary_key' => ['id_kategori'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_kategori' => 'Kode kategori kas (PK)',
                    'nama_kategori' => 'Nama kategori pemasukan/biaya',
                    'tipe' => 'Tipe kategori (MASUK / KELUAR)',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [],
            ],
            'tblkaryawan' => [
                'domain' => 'hr_payroll',
                'description' => 'Master data karyawan toko beserta jabatan dan gaji pokok.',
                'primary_key' => ['id_karyawan'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0 AND COALESCE(status, 1) = 1',
                'columns' => [
                    'id_karyawan' => 'Kode karyawan (PK)',
                    'nama' => 'Nama lengkap karyawan',
                    'jabatan' => 'Jabatan karyawan',
                    'telepon' => 'Nomor telepon',
                    'tanggal_masuk' => 'Tanggal mulai bekerja',
                    'gaji_pokok' => 'Gaji pokok (Rp)',
                    'status' => 'Status aktif bekerja (1 = aktif)',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tblgaji' => 'tblkaryawan.id_karyawan = tblgaji.id_karyawan',
                ],
            ],
            'tblgaji' => [
                'domain' => 'hr_payroll',
                'description' => 'Header penggajian karyawan per periode.',
                'primary_key' => ['id_gaji'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_gaji' => 'Nomor slip gaji (PK)',
                    'id_karyawan' => 'Kode karyawan (FK ke tblkaryawan.id_karyawan)',
                    'periode_awal' => 'Tanggal awal periode gaji',
                    'periode_akhir' => 'Tanggal akhir periode gaji',
                    'tanggal' => 'Tanggal pembayaran gaji',
                    'total_penghasilan' => 'Total penghasilan kotor (Rp)',
                    'total_potongan' => 'Total potongan gaji (Rp)',
                    'total_bayar' => 'Total gaji bersih dibayarkan / take home pay (Rp)',
                    'id_bank' => 'Akun kas/bank pembayar gaji',
                    'status' => "Status slip gaji ('DRAFT' atau 'DIBAYAR')",
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tblkaryawan' => 'tblgaji.id_karyawan = tblkaryawan.id_karyawan',
                    'tblgaji_detail' => 'tblgaji.id_gaji = tblgaji_detail.id_gaji',
                ],
            ],
            'tblgaji_detail' => [
                'domain' => 'hr_payroll',
                'description' => 'Rincian komponen penghasilan dan potongan pada slip gaji karyawan.',
                'primary_key' => ['id_detail'],
                'has_soft_delete' => true,
                'status_filter' => 'COALESCE(hapus, 0) = 0',
                'columns' => [
                    'id_detail' => 'ID baris detail gaji (PK)',
                    'id_gaji' => 'Nomor slip gaji induk',
                    'id_komponen' => 'Kode komponen gaji',
                    'nama_komponen' => 'Nama komponen (Gaji Pokok, Lembur, Bonus, Potongan)',
                    'tipe' => 'Tipe komponen (PENGHASILAN / POTONGAN)',
                    'qty' => 'Kuantitas (hari/jam)',
                    'tarif' => 'Tarif per satuan (Rp)',
                    'subtotal' => 'Subtotal komponen (Rp)',
                    'nominal' => 'Nominal akhir komponen (Rp)',
                    'hapus' => 'Flag hapus (0 = aktif)',
                ],
                'relations' => [
                    'tblgaji' => 'tblgaji_detail.id_gaji = tblgaji.id_gaji',
                ],
            ],
        ];
    }

    /**
     * Daftar domain bisnis yang diizinkan untuk peran pengguna saat ini.
     *
     * @return list<string>
     */
    public function allowedDomainsForRole(?string $role = null): array
    {
        $role ??= (string) session('auth_role');
        return $this->config->roleDomains[$role] ?? [];
    }

    /**
     * Daftar nama tabel yang diizinkan untuk peran pengguna tertentu.
     *
     * @return list<string>
     */
    public function allowedTablesForRole(?string $role = null): array
    {
        $allowedDomains = $this->allowedDomainsForRole($role);
        $tables = [];
        foreach ($this->catalog() as $tableName => $meta) {
            if (in_array($meta['domain'], $allowedDomains, true) && !$this->isTableForbidden($tableName)) {
                $tables[] = $tableName;
            }
        }
        return $tables;
    }

    /** Single-master lookups share one compiler; relationship reports still use validated SQL. */
    public function lookupCatalog(?string $role = null): array
    {
        $tables=['tbldata_member','tbldatabarang','tbldata_supiller','tbldata_gudang','tblkategori','tblmerek','tblsatuan','tblrak','tbldata_bank','tblkas_kategori','tblkaryawan'];
        $catalog=array_intersect_key($this->catalog(),array_flip($tables));
        if($role!==null)$catalog=array_intersect_key($catalog,array_flip($this->allowedTablesForRole($role)));
        foreach($catalog as $table=>&$meta){
            if($this->isTableForbidden($table)){unset($catalog[$table]);continue;}
            $meta['columns']=array_filter($meta['columns'],fn($description,$column)=>!$this->isColumnForbidden($column),ARRAY_FILTER_USE_BOTH);
        }
        unset($meta);
        return $catalog;
    }

    public function isTableForbidden(string $table): bool
    {
        $normalized = strtolower(trim($table, " `\t\n\r\0\x0B"));
        if (str_starts_with($normalized, 'tblsync_')) {
            return true;
        }
        return in_array($normalized, array_map('strtolower', $this->config->forbiddenTables), true);
    }

    public function isColumnForbidden(string $column): bool
    {
        $normalized = strtolower(trim($column, " `\t\n\r\0\x0B"));
        if (str_contains($normalized, '.')) {
            $parts = explode('.', $normalized);
            $normalized = (string) end($parts);
        }
        return in_array($normalized, array_map('strtolower', $this->config->forbiddenColumns), true);
    }

    public function isTableAllowedForRole(string $table, ?string $role = null): bool
    {
        $normalized = strtolower(trim($table, " `\t\n\r\0\x0B"));
        if ($this->isTableForbidden($normalized)) {
            return false;
        }
        return in_array($normalized, $this->allowedTablesForRole($role), true);
    }

    /**
     * Memilih metadata schema yang relevan berdasarkan domain atau kata kunci pertanyaan,
     * sehingga prompt ke LLM tetap hemat token dan akurat.
     *
     * @param list<string> $domains
     * @return array<string, array<string, mixed>>
     */
    public function selectRelevantSchema(array $domains, ?string $role = null): array
    {
        $allowedDomains = $this->allowedDomainsForRole($role);
        $targetDomains = array_values(array_intersect($domains ?: $allowedDomains, $allowedDomains));
        if ($targetDomains === []) {
            $targetDomains = array_slice($allowedDomains, 0, 3);
        }

        $selected = [];
        foreach ($this->catalog() as $table => $meta) {
            if (in_array($meta['domain'], $targetDomains, true)) {
                $selected[$table] = $meta;
            }
        }
        return $selected;
    }

    /**
     * Mem-parsing file referensi DDL SQL (dbsiprosir_online_v5_x2_struktur.sql) untuk verifikasi struktur kolom aktual.
     *
     * @return array<string, array{columns: list<string>, primary_key: list<string>}>
     */
    public function parseReferenceDdl(): array
    {
        if ($this->parsedDdlCache !== null) {
            return $this->parsedDdlCache;
        }

        $path = $this->config->schemaSqlPath;
        if (!is_file($path)) {
            return $this->parsedDdlCache = [];
        }

        $sql = (string) file_get_contents($path);
        $tables = [];
        if (preg_match_all('/CREATE\s+TABLE\s+`([^`]+)`\s*\((.*?)\)\s*ENGINE/si', $sql, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tableName = strtolower($match[1]);
                $body = $match[2];
                $columns = [];
                $primaryKey = [];
                foreach (preg_split('/\r?\n/', $body) as $line) {
                    $line = trim($line);
                    if (preg_match('/^`([^`]+)`\s+/i', $line, $colMatch)) {
                        $columns[] = strtolower($colMatch[1]);
                    } elseif (preg_match('/^PRIMARY\s+KEY\s*\(([^)]+)\)/i', $line, $pkMatch)) {
                        if (preg_match_all('/`([^`]+)`/', $pkMatch[1], $pkCols)) {
                            $primaryKey = array_map('strtolower', $pkCols[1]);
                        }
                    }
                }
                $tables[$tableName] = [
                    'columns' => $columns,
                    'primary_key' => $primaryKey,
                ];
            }
        }

        return $this->parsedDdlCache = $tables;
    }

    /**
     * Menghasilkan ringkasan teks schema yang ringkas untuk konteks prompt LLM.
     *
     * @param list<string> $domains
     */
    public function buildSchemaPromptContext(array $domains, ?string $role = null): string
    {
        $schema = $this->selectRelevantSchema($domains, $role);
        $lines = [];
        foreach ($schema as $tableName => $meta) {
            $cols = [];
            foreach ($meta['columns'] as $colName => $desc) {
                $cols[] = "{$colName} ({$desc})";
            }
            $filterInfo = $meta['status_filter'] !== '' ? " | Filter Wajib: {$meta['status_filter']}" : ' | Tanpa kolom hapus';
            $lines[] = "- Tabel `{$tableName}` [{$meta['domain']}]: {$meta['description']}{$filterInfo}\n  Kolom: " . implode('; ', $cols);
            if (!empty($meta['aggregation_notes'])) {
                $lines[] = "  Catatan Agregasi: {$meta['aggregation_notes']}";
            }
        }
        return implode("\n", $lines);
    }
}
