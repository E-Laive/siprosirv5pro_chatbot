<?php

namespace App\Services\AI;

use CodeIgniter\Database\BaseConnection;
use Config\AiAnalyst;
use Throwable;

/**
 * Eksekutor Query Read-Only untuk Database POS Eksternal (Database B — posReadonly).
 *
 * Setiap query WAJIB melewati SqlSafetyValidator terlebih dahulu.
 * Mendukung:
 * 1. Koneksi MariaDB/MySQL POS aktual (posReadonly).
 * 2. Kegagalan koneksi live ditampilkan sebagai offline, tanpa perpindahan otomatis.
 * 3. Fixture SQLite3 / PDO SQLite hanya untuk testing.
 */
final class PosQueryExecutor
{
    private AiAnalyst $config;
    private SqlSafetyValidator $validator;
    private ?BaseConnection $posConnection = null;
    private ?\PDO $pdoFallback = null;
    private string $connectionMode = 'uninitialized';
    private ?string $lastConnectionError = null;

    public function __construct(
        ?SqlSafetyValidator $validator = null,
        ?AiAnalyst $config = null,
        ?BaseConnection $customPosConnection = null
    ) {
        $this->config = $config ?? config(AiAnalyst::class);
        $this->validator = $validator ?? new SqlSafetyValidator(null, $this->config);
        if ($customPosConnection !== null) {
            $this->posConnection = $customPosConnection;
            $this->connectionMode = 'custom_connection';
        }
    }

    /**
     * Memeriksa status koneksi ke database POS eksternal.
     *
     * @return array{connected: bool, mode: string, database: string, message: string}
     */
    public function connectionStatus(): array
    {
        try {
            $this->ensureRuntimeConnection();
            return [
                'connected' => true,
                'mode' => $this->connectionMode,
                'database' => $this->connectionMode === 'live_pos_db'
                    ? (string) (config('Database')->posReadonly['database'] ?? 'dbsiprosir_online_v5_x2')
                    : 'dbsiprosir_online_v5_x2 (Verification Mode)',
                'message' => $this->connectionMode === 'live_pos_db'
                    ? 'Terhubung langsung ke database POS MariaDB (Read-Only).'
                    : 'Aktif menggunakan dataset verifikasi skema POS (konfigurasi database.posReadonly.* di .env untuk koneksi POS live).',
            ];
        } catch (Throwable $e) {
            return [
                'connected' => false,
                'mode' => 'offline',
                'database' => (string) (config('Database')->posReadonly['database'] ?? 'dbsiprosir_online_v5_x2'),
                'message' => 'Koneksi database POS belum tersedia. Periksa konfigurasi melalui Administrator.',
            ];
        }
    }

    /**
     * Memvalidasi dan mengeksekusi query SELECT read-only ke database POS.
     *
     * @param list<mixed> $bindings
     * @return array{
     *   success: bool,
     *   sql: string,
     *   rows: list<array<string, mixed>>,
     *   columns: list<string>,
     *   row_count: int,
     *   duration_ms: int,
     *   source_mode: string,
     *   error: ?string,
     *   tables: list<string>
     * }
     */
    public function execute(
        string $rawSql,
        array $bindings = [],
        ?string $role = null,
        ?int $userId = null,
        ?int $conversationId = null,
        ?string $domain = null
    ): array {
        $role ??= (string) (session('auth_role') ?: 'Administrator');
        $userId ??= (int) (session('auth_user_id') ?: 0);

        $validation = $this->validator->validate($rawSql, $role);
        if (!$validation['valid']) {
            $this->logQuery($conversationId, $userId, $role, $domain, $rawSql, $bindings, 0, 0, 'blocked', $validation['error']);
            return [
                'success' => false,
                'sql' => $rawSql,
                'rows' => [],
                'columns' => [],
                'row_count' => 0,
                'duration_ms' => 0,
                'source_mode' => $this->connectionMode,
                'error' => $validation['error'],
                'failure_stage' => 'validation',
                'tables' => $validation['tables'],
            ];
        }

        $safeSql = $validation['sql'];
        $startedAt = hrtime(true);
        $failureStage = 'connection';

        try {
            $this->ensureRuntimeConnection();
            $failureStage = 'execution';

            if ($this->posConnection !== null) {
                $adaptedSql = $this->adaptSqlForDriver($safeSql, $this->posConnection->DBDriver);
                $query = $this->posConnection->query($adaptedSql, $bindings);
                if (!is_object($query) || !method_exists($query, 'getResultArray')) {
                    throw new \RuntimeException((string) ($this->posConnection->error()['message'] ?? 'Eksekusi query gagal mengembalikan dataset.'));
                }
                /** @var list<array<string, mixed>> $rows */
                $rows = $query->getResultArray();
                $columns = $rows !== [] ? array_keys($rows[0]) : (method_exists($query, 'getFieldNames') ? $query->getFieldNames() : []);
            } elseif ($this->pdoFallback !== null) {
                $adaptedSql = $this->adaptSqlForDriver($safeSql, 'SQLite3');
                $stmt = $this->pdoFallback->prepare($adaptedSql);
                $stmt->execute(array_values($bindings));
                /** @var list<array<string, mixed>> $rows */
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                $columns = $rows !== [] ? array_keys($rows[0]) : [];
            } else {
                throw new \RuntimeException('Tidak ada driver database yang tersedia.');
            }

            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            $this->logQuery($conversationId, $userId, $role, $domain, $safeSql, $bindings, count($rows), $durationMs, 'success', null);

            return [
                'success' => true,
                'sql' => $safeSql,
                'rows' => $rows,
                'columns' => array_values($columns),
                'row_count' => count($rows),
                'duration_ms' => $durationMs,
                'source_mode' => $this->connectionMode,
                'error' => null,
                'tables' => $validation['tables'],
            ];
        } catch (Throwable $e) {
            $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);
            $safeError = 'Query belum dapat dijalankan pada database POS. Periksa kesesuaian filter atau koneksi database.';
            $this->logQuery($conversationId, $userId, $role, $domain, $safeSql, $bindings, 0, $durationMs, 'error', $e->getMessage());

            return [
                'success' => false,
                'sql' => $safeSql,
                'rows' => [],
                'columns' => [],
                'row_count' => 0,
                'duration_ms' => $durationMs,
                'source_mode' => $this->connectionMode,
                'error' => $safeError,
                'failure_stage' => $failureStage,
                'tables' => $validation['tables'],
            ];
        }
    }

    private function ensureRuntimeConnection(): void
    {
        if ($this->posConnection !== null || $this->pdoFallback !== null) {
            return;
        }

        // 1. Coba koneksi ke Database B (posReadonly) jika bukan environment testing murni
        if (ENVIRONMENT !== 'testing') {
            try {
                $db = \Config\Database::connect($this->config->posDatabaseGroup, false);
                $db->initialize();
                if ($db->connID) {
                        if (stripos($db->DBDriver, 'MySQL') !== false) {
                            if (!$db->query('SET SESSION TRANSACTION READ ONLY')) throw new \RuntimeException('Sesi POS read-only tidak dapat diaktifkan.');
                            $timeoutSec = max(1, $this->config->queryTimeoutSeconds);
                            if (!$db->query('SET SESSION max_statement_time = ' . $timeoutSec)) throw new \RuntimeException('Batas waktu query POS tidak dapat diaktifkan.');
                        }
                        $this->connectionMode = 'live_pos_db';
                        $this->posConnection = $db;
                        return;
                }
            } catch (Throwable $e) {
                $this->lastConnectionError = $e->getMessage();
            }
        }

        // 2. Fallback ke SQLite Verification Fixture (CI4 SQLite3 atau PDO sqlite::memory:)
        if (ENVIRONMENT === 'testing') {
            if (extension_loaded('sqlite3')) {
                try {
                    $this->posConnection = $this->buildSqliteVerificationFixture();
                    $this->connectionMode = 'pos_verification_fixture';
                    return;
                } catch (Throwable $e) {
                    $this->lastConnectionError = $e->getMessage();
                }
            }

            if (extension_loaded('pdo_sqlite')) {
                $this->pdoFallback = $this->buildPdoSqliteFixture();
                $this->connectionMode = 'pos_verification_fixture';
                return;
            }
        }

        throw new \RuntimeException($this->lastConnectionError ?? 'Database POS read-only tidak dapat dihubungi.');
    }

    /**
     * Menyesuaikan fungsi tanggal spesifik MariaDB ke SQLite ketika berjalan pada fixture pengujian SQLite.
     */
    private function adaptSqlForDriver(string $sql, string $driver): string
    {
        if (stripos($driver, 'SQLite') === false) {
            return $sql;
        }

        $adapted = $sql;
        $adapted = preg_replace('/\bYEAR\s*\(\s*([^)]+)\s*\)/i', "CAST(strftime('%Y', $1) AS INTEGER)", $adapted) ?? $adapted;
        $adapted = preg_replace('/\bMONTH\s*\(\s*([^)]+)\s*\)/i', "CAST(strftime('%m', $1) AS INTEGER)", $adapted) ?? $adapted;
        $adapted = preg_replace('/\bDATE_FORMAT\s*\(\s*([^,]+?)\s*,\s*\'%Y-%m\'\s*\)/i', "strftime('%Y-%m', $1)", $adapted) ?? $adapted;
        $adapted = preg_replace('/\bDATE_FORMAT\s*\(\s*([^,]+?)\s*,\s*\'%Y-%m-%d\'\s*\)/i', "strftime('%Y-%m-%d', $1)", $adapted) ?? $adapted;
        $adapted = preg_replace('/\bCURDATE\s*\(\s*\)/i', "date('now')", $adapted) ?? $adapted;
        return $adapted;
    }

    private function buildSqliteVerificationFixture(): BaseConnection
    {
        $db = \Config\Database::connect([
            'DSN' => '',
            'hostname' => '127.0.0.1',
            'username' => '',
            'password' => '',
            'database' => ':memory:',
            'DBDriver' => 'SQLite3',
            'DBPrefix' => '',
            'pConnect' => false,
            'DBDebug' => false,
            'charset' => 'utf8',
            'DBCollat' => '',
            'foreignKeys' => true,
        ], false);
        $db->initialize();
        $this->createAndSeedTables(static fn (string $sql) => $db->query($sql));
        return $db;
    }

    private function buildPdoSqliteFixture(): \PDO
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->createAndSeedTables(static fn (string $sql) => $pdo->exec($sql));
        return $pdo;
    }

    private function createAndSeedTables(callable $exec): void
    {
        $exec('CREATE TABLE tbldata_gudang (id_gudang TEXT PRIMARY KEY, nama TEXT, lokasi TEXT, keterangan TEXT, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tblkategori (kategori TEXT PRIMARY KEY, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tblmerek (merek TEXT PRIMARY KEY, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tbldatabarang (id_produk TEXT PRIMARY KEY, nama TEXT, kategori TEXT, merek TEXT, id_rak TEXT, id_sub TEXT, harga_beli NUMERIC DEFAULT 0, harga_jual NUMERIC DEFAULT 0, min_stok INTEGER DEFAULT 0, kode TEXT, keterangan TEXT, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tbldatabarang_stok (id_produk TEXT, id_lokasi TEXT, stok NUMERIC DEFAULT 0, PRIMARY KEY (id_produk, id_lokasi))');
        $exec('CREATE TABLE tblharga_satuan (id_produk TEXT, nama_satuan TEXT, barcode TEXT, isi NUMERIC DEFAULT 1, harga_jual NUMERIC DEFAULT 0, poin INTEGER DEFAULT 0, hapus INTEGER DEFAULT 0, PRIMARY KEY (id_produk, nama_satuan))');
        $exec('CREATE TABLE tblmember_setting (id_kelas INTEGER PRIMARY KEY, nama_kelas TEXT, diskon_global_persen NUMERIC DEFAULT 0, min_belanja NUMERIC DEFAULT 0, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tbldata_member (id_member TEXT PRIMARY KEY, kelas_member INTEGER DEFAULT 0, nama TEXT, alamat TEXT, no_hp TEXT, email TEXT, status INTEGER DEFAULT 1, poin INTEGER DEFAULT 0, created_at TEXT, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tbltransaksipenjualan (id_transaksi TEXT PRIMARY KEY, no_faktur TEXT, tanggal TEXT, id_member TEXT, customer TEXT, id_admin TEXT, total_kotor NUMERIC DEFAULT 0, potongan NUMERIC DEFAULT 0, potongan_poin INTEGER DEFAULT 0, ppn NUMERIC DEFAULT 0, grand_total NUMERIC DEFAULT 0, bayar NUMERIC DEFAULT 0, kembalian NUMERIC DEFAULT 0, mode_pembayaran TEXT DEFAULT "TUNAI", id_bank TEXT DEFAULT "CASH", id_shift TEXT, source_type TEXT DEFAULT "NORMAL")');
        $exec('CREATE TABLE tbltransaksipenjualan_detail (id_detail INTEGER PRIMARY KEY AUTOINCREMENT, id_transaksi TEXT, id_produk TEXT, nama_produk TEXT, kategori TEXT, satuan TEXT, isi_satuan NUMERIC DEFAULT 1, harga_beli NUMERIC DEFAULT 0, harga_jual NUMERIC DEFAULT 0, jumlah NUMERIC DEFAULT 0, diskon_item NUMERIC DEFAULT 0, total_subtotal NUMERIC DEFAULT 0, poin INTEGER DEFAULT 0, status TEXT DEFAULT "Retail", terjual INTEGER DEFAULT 1, id_lokasi TEXT DEFAULT "TOKO")');
        $exec('CREATE TABLE tblreturpenjualan (id_retur TEXT PRIMARY KEY, id_transaksi TEXT, tanggal TEXT, tipe_retur TEXT, total_retur NUMERIC DEFAULT 0, potong_piutang NUMERIC DEFAULT 0, potongan_retur NUMERIC DEFAULT 0, dana_kembali NUMERIC DEFAULT 0, status TEXT DEFAULT "FINAL", id_bank TEXT, id_shift TEXT, id_admin TEXT, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tblreturpenjualan_detail (id_detail INTEGER PRIMARY KEY AUTOINCREMENT, id_retur TEXT, id_transaksi TEXT, id_produk TEXT, nama_produk TEXT, satuan TEXT, isi_satuan NUMERIC DEFAULT 1, jumlah NUMERIC DEFAULT 0, qty_dasar NUMERIC DEFAULT 0, harga_beli NUMERIC DEFAULT 0, harga_jual NUMERIC DEFAULT 0, diskon_item NUMERIC DEFAULT 0, subtotal_retur NUMERIC DEFAULT 0, id_lokasi TEXT DEFAULT "TOKO", status TEXT DEFAULT "FINAL")');
        $exec('CREATE TABLE tblpiutangpenjualan (id_piutang TEXT PRIMARY KEY, id_transaksi TEXT, id_member TEXT, total NUMERIC DEFAULT 0, dibayar NUMERIC DEFAULT 0, sisa NUMERIC DEFAULT 0, status TEXT DEFAULT "Belum", tanggal TEXT, jatuh_tempo TEXT, terakhir_bayar TEXT, id_admin TEXT, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tbldata_supiller (id_supiller TEXT PRIMARY KEY, nama TEXT, alamat TEXT, kota TEXT, provinsi TEXT, telepon TEXT, email TEXT, kontak TEXT, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tbltransaksipembelian (id_pembelian TEXT PRIMARY KEY, id_supiller TEXT, status TEXT DEFAULT "Sukses", total NUMERIC DEFAULT 0, bayar NUMERIC DEFAULT 0, id_faktur TEXT, id_po TEXT, masuk_ke TEXT, tambahan NUMERIC DEFAULT 0, potongan NUMERIC DEFAULT 0, mode_pembayaran TEXT, id_bank TEXT, tanggal TEXT, id_admin TEXT, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tbltransaksipembelian_detail (id_detail INTEGER PRIMARY KEY AUTOINCREMENT, id_pembelian TEXT, id_produk TEXT, nama TEXT, kategori TEXT, satuan TEXT, isi NUMERIC DEFAULT 1, jumlah NUMERIC DEFAULT 0, harga NUMERIC DEFAULT 0, diskon NUMERIC DEFAULT 0, subtotal NUMERIC DEFAULT 0, masuk_ke TEXT, id_supiller TEXT, tanggal TEXT, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tblreturpembelian (id_retur TEXT PRIMARY KEY, id_pembelian TEXT, tanggal TEXT, tipe_retur TEXT, total_retur NUMERIC DEFAULT 0, potong_hutang NUMERIC DEFAULT 0, dana_kembali NUMERIC DEFAULT 0, status TEXT DEFAULT "FINAL", hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tblpiutangpembelian (id_piutang TEXT PRIMARY KEY, id_pembelian TEXT, id_supiller TEXT, total NUMERIC DEFAULT 0, dibayar NUMERIC DEFAULT 0, status TEXT DEFAULT "Belum", tanggal TEXT, jatuh_tempo TEXT, terakhir_bayar TEXT, id_admin TEXT, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tbldata_bank (id_bank TEXT PRIMARY KEY, nama_bank TEXT, no_rekening TEXT, atas_nama TEXT, jenis TEXT, saldo NUMERIC DEFAULT 0, biaya_admin NUMERIC DEFAULT 0, status INTEGER DEFAULT 1, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tblbank_ledger (id_ledger INTEGER PRIMARY KEY AUTOINCREMENT, id_bank TEXT, tanggal TEXT, no_referensi TEXT, source_type TEXT, source_id TEXT, posting_role TEXT, jenis TEXT, kategori TEXT, id_shift TEXT, keterangan TEXT, debit NUMERIC DEFAULT 0, kredit NUMERIC DEFAULT 0, saldo_akhir NUMERIC DEFAULT 0, id_admin TEXT)');
        $exec('CREATE TABLE tblkas_shift (id_shift TEXT PRIMARY KEY, tanggal_buka TEXT, tanggal_tutup TEXT, id_admin TEXT, modal_awal NUMERIC DEFAULT 0, total_penjualan_tunai NUMERIC DEFAULT 0, total_kas_masuk NUMERIC DEFAULT 0, total_kas_keluar NUMERIC DEFAULT 0, total_setor NUMERIC DEFAULT 0, saldo_sistem NUMERIC DEFAULT 0, saldo_fisik NUMERIC DEFAULT 0, selisih NUMERIC DEFAULT 0, status TEXT DEFAULT "TUTUP", hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tbltransaksi_kas (id_transaksi_kas TEXT PRIMARY KEY, tanggal TEXT, jenis TEXT, id_bank_asal TEXT, id_bank_tujuan TEXT, nominal NUMERIC DEFAULT 0, id_kategori TEXT, kategori TEXT, id_shift TEXT, keterangan TEXT, status TEXT DEFAULT "FINAL", id_admin TEXT, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tblkaryawan (id_karyawan TEXT PRIMARY KEY, nama TEXT, jabatan TEXT, telepon TEXT, tanggal_masuk TEXT, gaji_pokok NUMERIC DEFAULT 0, status INTEGER DEFAULT 1, hapus INTEGER DEFAULT 0)');
        $exec('CREATE TABLE tblgaji (id_gaji TEXT PRIMARY KEY, id_karyawan TEXT, periode_awal TEXT, periode_akhir TEXT, tanggal TEXT, total_penghasilan NUMERIC DEFAULT 0, total_potongan NUMERIC DEFAULT 0, total_bayar NUMERIC DEFAULT 0, id_bank TEXT, status TEXT DEFAULT "DIBAYAR", hapus INTEGER DEFAULT 0)');

        // Seed Gudang
        $exec("INSERT INTO tbldata_gudang VALUES ('TOKO','Toko Utama Surakarta','Jl. Slamet Riyadi No. 88','Area display dan kasir utama',0),('GDG-PUSAT','Gudang Pusat Palur','Kawasan Industri Palur','Penyimpanan stok grosir',0),('CAB-SOLOBARU','Cabang Solo Baru','Ruko Solo Baru Sektor 1','Toko cabang selatan',0)");

        // Seed Produk
        $products = [
            ['BRG-001', 'Beras Ramos Super 5kg', 'Sembako', 'Ramos', 62000, 72000, 25, '8991001'],
            ['BRG-002', 'Minyak Goreng Sawit 2L', 'Sembako', 'Filma', 31000, 36500, 30, '8991002'],
            ['BRG-003', 'Kopi Arabika Gayo 250g', 'Minuman', 'Nusantara', 52000, 68000, 15, '8991003'],
            ['BRG-004', 'Susu UHT Full Cream 1L', 'Minuman', 'Ultra', 16500, 21500, 20, '8991004'],
            ['BRG-005', 'Roti Gandum Utuh', 'Makanan', 'SariRoti', 12500, 17000, 12, '8991005'],
            ['BRG-006', 'Mie Instan Goreng Karton (40 pcs)', 'Makanan', 'Indomie', 104000, 118000, 18, '8991006'],
            ['BRG-007', 'Gula Pasir Kristal 1kg', 'Sembako', 'Gulaku', 15200, 18000, 40, '8991007'],
            ['BRG-008', 'Sabun Cuci Piring Jeruk 750ml', 'Kebutuhan Rumah', 'Sunlight', 13000, 16500, 20, '8991008'],
            ['BRG-009', 'Biskuit Kaleng Assorted 650g', 'Makanan', 'KhongGuan', 48000, 59000, 10, '8991009'],
            ['BRG-010', 'Teh Celup Melati Box 50s', 'Minuman', 'Sariwangi', 9500, 13000, 25, '8991010'],
        ];
        foreach ($products as [$id, $nama, $kat, $mrk, $hb, $hj, $min, $kd]) {
            $exec("INSERT INTO tbldatabarang (id_produk,nama,kategori,merek,harga_beli,harga_jual,min_stok,kode,hapus) VALUES ('{$id}','{$nama}','{$kat}','{$mrk}',{$hb},{$hj},{$min},'{$kd}',0)");
        }

        // Seed Stok Lokasi
        $exec("INSERT INTO tbldatabarang_stok VALUES ('BRG-001','TOKO',45),('BRG-001','GDG-PUSAT',120),('BRG-002','TOKO',12),('BRG-002','GDG-PUSAT',80),('BRG-003','TOKO',8),('BRG-004','TOKO',6),('BRG-005','TOKO',22),('BRG-006','TOKO',14),('BRG-006','GDG-PUSAT',65),('BRG-007','TOKO',19),('BRG-008','TOKO',34),('BRG-009','TOKO',4),('BRG-010','CAB-SOLOBARU',50)");

        // Seed Kelas Member & Member
        $exec("INSERT INTO tblmember_setting VALUES (1,'Silver',1.5,200000,0),(2,'Gold',3.0,500000,0),(3,'Mitra Grosir',5.0,1500000,0)");
        $exec("INSERT INTO tbldata_member VALUES ('MBR-001',3,'Toko Berkah Jaya','Laweyan, Surakarta','08123456001','berkah@contoh.id',1,420,'2024-02-10 09:00:00',0),('MBR-002',2,'Andini Putri','Banjarsari, Surakarta','08123456002','andini@contoh.id',1,215,'2024-05-14 10:00:00',0),('MBR-003',3,'Bima Store','Kartasura, Sukoharjo','08123456003','bima@contoh.id',1,580,'2024-06-01 11:00:00',0),('MBR-004',1,'Citra Lestari','Jebres, Surakarta','08123456004','citra@contoh.id',1,95,'2025-01-12 08:30:00',0),('MBR-005',2,'Damar Niaga','Colomadu, Karanganyar','08123456005','damar@contoh.id',1,310,'2025-02-19 14:20:00',0),('MBR-006',1,'Warung Bu Ratna','Pasar Kliwon, Surakarta','08123456006','ratna@contoh.id',1,160,'2025-03-04 16:10:00',0)");

        $sales2025 = [
            ['01', 'MBR-001', 'BRG-001', 120, 'BRG-006', 60],
            ['02', 'MBR-002', 'BRG-001', 130, 'BRG-002', 110],
            ['03', 'MBR-003', 'BRG-006', 95,  'BRG-009', 80],
            ['04', 'MBR-001', 'BRG-001', 150, 'BRG-003', 70],
            ['05', 'MBR-004', 'BRG-002', 140, 'BRG-004', 120],
            ['06', 'MBR-005', 'BRG-006', 115, 'BRG-009', 90],
            ['07', 'MBR-002', 'BRG-001', 110, 'BRG-005', 85],
            ['08', 'MBR-003', 'BRG-006', 125, 'BRG-003', 90],
            ['09', 'MBR-006', 'BRG-001', 135, 'BRG-002', 115],
            ['10', 'MBR-001', 'BRG-006', 140, 'BRG-007', 160],
            ['11', 'MBR-005', 'BRG-001', 155, 'BRG-003', 100],
            ['12', 'MBR-003', 'BRG-009', 150, 'BRG-006', 145],
        ];
        $sales2026 = [
            ['01', 'MBR-001', 'BRG-001', 165, 'BRG-006', 85],
            ['02', 'MBR-003', 'BRG-001', 175, 'BRG-002', 140],
            ['03', 'MBR-002', 'BRG-006', 135, 'BRG-009', 120],
            ['04', 'MBR-005', 'BRG-001', 190, 'BRG-003', 95],
            ['05', 'MBR-001', 'BRG-002', 180, 'BRG-004', 150],
            ['06', 'MBR-003', 'BRG-006', 170, 'BRG-009', 140],
            ['07', 'MBR-004', 'BRG-006', 85,  'BRG-009', 45],
            ['08', 'MBR-005', 'BRG-001', 200, 'BRG-006', 155],
            ['09', 'MBR-006', 'BRG-001', 160, 'BRG-003', 80],
            ['10', 'MBR-001', 'BRG-001', 140, 'BRG-002', 110],
        ];

        $pMap = [];
        foreach ($products as $p) {
            $pMap[$p[0]] = ['nama' => $p[1], 'kategori' => $p[2], 'hb' => $p[4], 'hj' => $p[5]];
        }
        $mMap = ['MBR-001' => 'Toko Berkah Jaya', 'MBR-002' => 'Andini Putri', 'MBR-003' => 'Bima Store', 'MBR-004' => 'Citra Lestari', 'MBR-005' => 'Damar Niaga', 'MBR-006' => 'Warung Bu Ratna'];

        $seedSale = static function (string $year, array $entry, int $seq) use ($exec, $pMap, $mMap): void {
            [$month, $memberId, $prodA, $qtyA, $prodB, $qtyB] = $entry;
            $trxId = "TRX-{$year}{$month}-00{$seq}";
            $faktur = "INV/{$year}/{$month}/00{$seq}";
            $date = "{$year}-{$month}-15 10:30:00";
            $pA = $pMap[$prodA];
            $pB = $pMap[$prodB];
            $subA = $qtyA * $pA['hj'];
            $subB = $qtyB * $pB['hj'];
            $gross = $subA + $subB;
            $discount = 50000;
            $grand = $gross - $discount;
            $mode = ($seq % 3 === 0) ? 'TRANSFER' : 'TUNAI';
            $bank = ($seq % 3 === 0) ? 'BCA' : 'CASH';
            $cust = $mMap[$memberId] ?? 'Umum';

            $exec("INSERT INTO tbltransaksipenjualan VALUES ('{$trxId}','{$faktur}','{$date}','{$memberId}','{$cust}','kasir.utama',{$gross},{$discount},0,0,{$grand},{$grand},0,'{$mode}','{$bank}','SHF-{$year}{$month}','NORMAL')");
            $exec("INSERT INTO tbltransaksipenjualan_detail (id_transaksi,id_produk,nama_produk,kategori,satuan,isi_satuan,harga_beli,harga_jual,jumlah,diskon_item,total_subtotal,poin,status,terjual,id_lokasi) VALUES ('{$trxId}','{$prodA}','{$pA['nama']}','{$pA['kategori']}','PCS',1,{$pA['hb']},{$pA['hj']},{$qtyA},0,{$subA},10,'Retail',1,'TOKO'),('{$trxId}','{$prodB}','{$pB['nama']}','{$pB['kategori']}','PCS',1,{$pB['hb']},{$pB['hj']},{$qtyB},0,{$subB},10,'Retail',1,'TOKO')");
        };

        foreach ($sales2025 as $i => $e) {
            $seedSale('2025', $e, $i + 1);
        }
        foreach ($sales2026 as $i => $e) {
            $seedSale('2026', $e, $i + 1);
        }

        // Retur Penjualan
        $exec("INSERT INTO tblreturpenjualan VALUES ('RTR-202607-01','TRX-202607-007','2026-07-18 14:00:00','TUNAI',236000,0,0,236000,'FINAL','CASH','SHF-202607','kasir.utama',0),('RTR-202609-01','TRX-202609-009','2026-09-20 11:15:00','TUNAI',360000,0,0,360000,'FINAL','CASH','SHF-202609','kasir.utama',0)");
        $exec("INSERT INTO tblreturpenjualan_detail (id_retur,id_transaksi,id_produk,nama_produk,satuan,isi_satuan,jumlah,qty_dasar,harga_beli,harga_jual,diskon_item,subtotal_retur,id_lokasi,status) VALUES ('RTR-202607-01','TRX-202607-007','BRG-006','Mie Instan Goreng Karton (40 pcs)','PCS',1,2,2,104000,118000,0,236000,'TOKO','FINAL'),('RTR-202609-01','TRX-202609-009','BRG-001','Beras Ramos Super 5kg','PCS',1,5,5,62000,72000,0,360000,'TOKO','FINAL')");

        // Piutang Penjualan
        $exec("INSERT INTO tblpiutangpenjualan VALUES ('PTG-2026-001','TRX-202608-008','MBR-005',6500000,2500000,4000000,'Belum','2026-08-15 10:30:00','2026-09-15','2026-09-01 09:00:00','kasir.utama',0),('PTG-2026-002','TRX-202609-009','MBR-006',4200000,1200000,3000000,'Belum','2026-09-15 10:30:00','2026-10-25','2026-09-28 14:00:00','kasir.utama',0),('PTG-2026-003','TRX-202606-006','MBR-003',5000000,5000000,0,'Lunas','2026-06-15 10:30:00','2026-07-15','2026-07-10 11:00:00','kasir.utama',0)");

        // Supplier & Pembelian
        $exec("INSERT INTO tbldata_supiller VALUES ('SUP-001','PT Pangan Makmur Sejahtera','Jl. Raya Solo-Sragen KM 9','Karanganyar','Jawa Tengah','0271-654321','order@panganmakmur.co.id','Hendra',0),('SUP-002','CV Tirta Distribusi Nusantara','Kawasan Pergudangan Pedaringan','Surakarta','Jawa Tengah','0271-778899','sales@tirtadistribusi.id','Wahyu',0),('SUP-003','PT Boga Niaga Utama','Jl. Industri Raya No. 12','Sukoharjo','Jawa Tengah','0271-556677','admin@boganiaga.id','Lina',0)");
        $exec("INSERT INTO tbltransaksipembelian VALUES ('PBL-202608-01','SUP-001','Sukses',28500000,28500000,'FKT-PM-881','','GDG-PUSAT',0,500000,'TRANSFER','BCA','2026-08-05 09:00:00','gudang.utama',0),('PBL-202609-01','SUP-001','Sukses',31200000,20000000,'FKT-PM-912','','GDG-PUSAT',0,300000,'KREDIT','BCA','2026-09-08 10:15:00','gudang.utama',0),('PBL-202609-02','SUP-002','Sukses',19400000,19400000,'FKT-TD-404','','TOKO',0,200000,'TRANSFER','BRI','2026-09-14 13:40:00','gudang.utama',0),('PBL-202610-01','SUP-003','Sukses',24600000,15000000,'FKT-BN-109','','GDG-PUSAT',0,0,'KREDIT','BCA','2026-10-04 11:00:00','gudang.utama',0)");
        $exec("INSERT INTO tblpiutangpembelian VALUES ('HTG-2026-01','PBL-202609-01','SUP-001',31200000,20000000,'Belum','2026-09-08 10:15:00','2026-10-08','2026-09-20 10:00:00','admin',0),('HTG-2026-02','PBL-202610-01','SUP-003',24600000,15000000,'Belum','2026-10-04 11:00:00','2026-11-04','2026-10-04 11:00:00','admin',0)");
        $exec("INSERT INTO tbldata_bank VALUES ('CASH','Kas Tunai Utama','-','Toko SIPROSIR','KAS',42500000,0,1,0),('BCA','Bank BCA Operasional','015-8899001','PT SIPROSIR RITEL','BANK',168400000,2500,1,0),('BRI','Bank BRI Mitra','0092-01-002341','PT SIPROSIR RITEL','BANK',64200000,2500,1,0)");
    }

    /**
     * @param list<mixed> $bindings
     */
    private function logQuery(
        ?int $conversationId,
        int $userId,
        string $role,
        ?string $domain,
        string $sql,
        array $bindings,
        int $rowCount,
        int $durationMs,
        string $status,
        ?string $errorMessage
    ): void {
        try {
            service('auditLogger')->record('ai.query.' . $status, [
                'user_id' => $userId,
                'resource' => $domain ?? 'pos_query',
                'rows' => $rowCount,
            ]);
        } catch (Throwable) {
        }

        try {
            $appDb = db_connect();
            if ($appDb->tableExists('ai_query_logs')) {
                $appDb->table('ai_query_logs')->insert([
                    'conversation_id' => $this->config->storageDriver === 'database' ? $conversationId : null,
                    'user_id' => $userId,
                    'user_role' => $role,
                    'domain' => $domain,
                    'sql_query' => mb_substr($sql, 0, 4000),
                    'bindings_json' => json_encode($bindings, JSON_UNESCAPED_UNICODE),
                    'row_count' => $rowCount,
                    'duration_ms' => $durationMs,
                    'status' => $status,
                    'error_message' => $errorMessage !== null ? mb_substr($errorMessage, 0, 490) : null,
                    'created_at' => (new BusinessClock($this->config->timezone))->now()->format('Y-m-d H:i:s'),
                ]);
            }
        } catch (Throwable) {
        }
    }
}
