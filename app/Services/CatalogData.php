<?php

namespace App\Services;

use InvalidArgumentException;

/** Shared, explicitly labelled sample data for the catalog and its exports. */
final class CatalogData
{
    public function report(string $key, string $search = '', string $status = '', string $role = ''): array
    {
        $reports = [
            'produk' => [
                'title' => 'Daftar produk',
                'columns' => ['sku' => ['SKU', 'text'], 'name' => ['Nama produk', 'text'], 'category' => ['Kategori', 'text'], 'stock' => ['Stok', 'number'], 'price' => ['Harga', 'currency'], 'status' => ['Status', 'text']],
                'rows' => [
                    ['sku' => 'PRD-001', 'name' => 'Kopi Arabika 250g', 'category' => 'Minuman', 'stock' => 42, 'price' => 68000, 'status' => 'Aktif'],
                    ['sku' => 'PRD-002', 'name' => 'Susu UHT 1L', 'category' => 'Minuman', 'stock' => 6, 'price' => 21500, 'status' => 'Menipis'],
                    ['sku' => 'PRD-003', 'name' => 'Roti Gandum', 'category' => 'Makanan', 'stock' => 18, 'price' => 17000, 'status' => 'Aktif'],
                    ['sku' => 'PRD-004', 'name' => 'Sirup Vanilla', 'category' => 'Pelengkap', 'stock' => 0, 'price' => 45000, 'status' => 'Nonaktif'],
                ],
            ],
            'pelanggan' => [
                'title' => 'Pelanggan prioritas',
                'columns' => ['name' => ['Pelanggan', 'text'], 'email' => ['Email', 'text'], 'segment' => ['Segmen', 'text'], 'orders' => ['Pesanan', 'number'], 'total' => ['Total belanja', 'currency'], 'status' => ['Status', 'text']],
                'rows' => [
                    ['name' => 'Andini Putri', 'email' => 'andini@contoh.id', 'segment' => 'Retail', 'orders' => 24, 'total' => 8420000, 'status' => 'Aktif'],
                    ['name' => 'Bima Store', 'email' => 'finance@bimastore.id', 'segment' => 'Reseller', 'orders' => 18, 'total' => 12750000, 'status' => 'Prioritas'],
                    ['name' => 'Citra Lestari', 'email' => 'citra@contoh.id', 'segment' => 'Member', 'orders' => 11, 'total' => 4180000, 'status' => 'Perlu follow-up'],
                    ['name' => 'Damar Niaga', 'email' => 'admin@damarniaga.id', 'segment' => 'Grosir', 'orders' => 31, 'total' => 21640000, 'status' => 'Tidak aktif'],
                ],
            ],
            'transaksi' => [
                'title' => 'Ringkasan transaksi',
                'columns' => ['document' => ['Dokumen', 'text'], 'time' => ['Waktu', 'text'], 'customer' => ['Pelanggan', 'text'], 'channel' => ['Channel', 'text'], 'cashier' => ['Kasir', 'text'], 'status' => ['Status', 'text'], 'total' => ['Total', 'currency']],
                'rows' => [
                    ['document' => 'INV-260811-041', 'time' => '11 Agu 2026 09:14', 'customer' => 'Andini Putri', 'channel' => 'Toko', 'cashier' => 'Rani A.', 'status' => 'Lunas', 'total' => 785000],
                    ['document' => 'INV-260811-042', 'time' => '11 Agu 2026 10:02', 'customer' => 'Bima Store', 'channel' => 'Web', 'cashier' => 'Sistem', 'status' => 'Diproses', 'total' => 1240000],
                    ['document' => 'INV-260811-043', 'time' => '11 Agu 2026 11:27', 'customer' => 'Citra Lestari', 'channel' => 'Toko', 'cashier' => 'Farid M.', 'status' => 'Sebagian', 'total' => 460000],
                    ['document' => 'INV-260811-044', 'time' => '11 Agu 2026 13:45', 'customer' => 'Damar Niaga', 'channel' => 'Marketplace', 'cashier' => 'Sistem', 'status' => 'Lunas', 'total' => 840000],
                ],
            ],
            'pengguna' => [
                'title' => 'Manajemen pengguna',
                'columns' => ['name' => ['Pengguna', 'text'], 'username' => ['Username', 'text'], 'role' => ['Peran', 'text'], 'activity' => ['Terakhir aktif', 'text'], 'status' => ['Status', 'text']],
                'rows' => [
                    ['name' => 'Andi Setiawan', 'username' => 'andi.setiawan', 'role' => 'Administrator', 'activity' => 'Baru saja', 'status' => 'Aktif'],
                    ['name' => 'Siti Rahma', 'username' => 'siti.rahma', 'role' => 'Kasir', 'activity' => '8 menit lalu', 'status' => 'Aktif'],
                    ['name' => 'Budi Pratama', 'username' => 'budi.pratama', 'role' => 'Gudang', 'activity' => '2 jam lalu', 'status' => 'Aktif'],
                    ['name' => 'Dewi Wulandari', 'username' => 'dewi.wulandari', 'role' => 'Kasir', 'activity' => '7 hari lalu', 'status' => 'Nonaktif'],
                ],
            ],
        ];
        if (!isset($reports[$key])) {
            throw new InvalidArgumentException('Laporan tidak tersedia.');
        }
        $report = $reports[$key];
        if ($key === 'transaksi') {
            $references = ['POS-1041', 'WEB-2198', 'POS-1043', 'MKT-0821'];
            $notes = ['Member Gold', 'Reseller', 'Member Silver', 'Pelanggan Grosir'];
            foreach ($report['rows'] as $index => &$row) {
                $row['reference'] = $references[$index];
                $row['customer_note'] = $notes[$index];
            }
            unset($row);
        }
        $search = mb_strtolower(trim($search));
        $report['rows'] = array_values(array_filter($report['rows'], static function (array $row) use ($search, $status, $role): bool {
            return ($search === '' || str_contains(mb_strtolower(implode(' ', $row)), $search))
                && ($status === '' || ($row['status'] ?? '') === $status)
                && ($role === '' || ($row['role'] ?? '') === $role);
        }));
        $report['key'] = $key;
        $report['filters'] = array_filter(['Pencarian' => trim($search), 'Status' => $status, 'Peran' => $role], static fn ($value) => $value !== '');
        $report['note'] = 'Data contoh katalog — bukan data operasional.';
        return $report;
    }

    public static function display(mixed $value, string $type): string
    {
        if ($value === null || $value === '') return '—';
        return match ($type) {
            'currency' => 'Rp' . number_format((float) $value, 0, ',', '.'),
            'number' => number_format((float) $value, floor((float) $value) === (float) $value ? 0 : 2, ',', '.'),
            default => (string) $value,
        };
    }
}
