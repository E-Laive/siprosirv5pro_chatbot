<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/** Safe, public-facing copy shared by real errors and the authenticated gallery. */
class ErrorPages extends BaseConfig
{
    public array $pages = [
        400 => ['label' => 'Permintaan tidak valid', 'category' => 'Permintaan', 'icon' => 'bi-file-earmark-x', 'title' => 'Permintaan belum dapat diproses', 'description' => 'Ada informasi yang tidak sesuai pada permintaan Anda. Periksa kembali isian atau alamat halaman sebelum melanjutkan.', 'next' => 'Buka halaman tujuan dari menu utama, lalu periksa kembali informasi yang Anda kirim.'],
        401 => ['label' => 'Perlu login', 'category' => 'Autentikasi', 'icon' => 'bi-person-lock', 'title' => 'Silakan masuk terlebih dahulu', 'description' => 'Halaman ini memerlukan akun yang sudah masuk. Gunakan akun Anda untuk melanjutkan dengan aman.', 'next' => 'Masuk melalui halaman login. Jika belum memiliki akun, hubungi administrator.'],
        403 => ['label' => 'Akses ditolak', 'category' => 'Hak akses', 'icon' => 'bi-shield-lock', 'title' => 'Akses tidak tersedia', 'description' => 'Akun Anda tidak memiliki izin untuk membuka halaman ini. Anda tetap dapat mengakses menu yang tersedia untuk akun Anda.', 'next' => 'Hubungi administrator jika halaman ini diperlukan untuk pekerjaan Anda.'],
        404 => ['label' => 'Tidak ditemukan', 'category' => 'Navigasi', 'icon' => 'bi-search', 'title' => 'Halaman tidak ditemukan', 'description' => 'Halaman yang Anda cari mungkin sudah dipindahkan, alamatnya berubah, atau belum tersedia.', 'next' => 'Periksa kembali alamat halaman atau temukan halaman tujuan melalui menu utama.'],
        419 => ['label' => 'Sesi formulir berakhir', 'category' => 'Sesi formulir', 'icon' => 'bi-hourglass-split', 'title' => 'Sesi formulir telah berakhir', 'description' => 'Permintaan dari formulir ini tidak dapat diverifikasi. Buka kembali formulir sebelum mengirim ulang.', 'next' => 'Salin isian yang masih terlihat sebelum memuat ulang. Masuk kembali jika aplikasi meminta login.'],
        429 => ['label' => 'Terlalu banyak permintaan', 'category' => 'Batas permintaan', 'icon' => 'bi-speedometer2', 'title' => 'Tunggu sebelum mencoba kembali', 'description' => 'Terlalu banyak permintaan dikirim dalam waktu berdekatan. Aplikasi membatasi permintaan untuk menjaga layanan tetap stabil.', 'next' => 'Tunggu beberapa saat, kemudian coba kembali. Hindari menekan tombol berulang kali.'],
        500 => ['label' => 'Kendala sistem', 'category' => 'Sistem', 'icon' => 'bi-hdd-network', 'title' => 'Ada kendala pada sistem', 'description' => 'Permintaan Anda belum dapat diselesaikan karena kendala internal. Silakan coba kembali beberapa saat lagi.', 'next' => 'Jika masalah berulang, sampaikan kode 500 dan langkah yang Anda lakukan kepada administrator.'],
        503 => ['label' => 'Layanan belum tersedia', 'category' => 'Layanan', 'icon' => 'bi-tools', 'title' => 'Layanan sementara belum tersedia', 'description' => 'Layanan sedang sibuk atau memerlukan pemeliharaan. Silakan kembali beberapa saat lagi.', 'next' => 'Tunggu hingga layanan tersedia. Hubungi administrator jika kendala berlangsung lama.'],
    ];

    public function find(mixed $code): ?array
    {
        if (!is_string($code) && !is_int($code)) return null;
        $key = (string) $code;
        if (!preg_match('/^[45][0-9]{2}$/D', $key)) return null;
        return isset($this->pages[(int) $key]) ? ['code' => (int) $key] + $this->pages[(int) $key] : null;
    }
}
