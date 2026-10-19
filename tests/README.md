# Verifikasi template

Runtime: PHP 8.3+, PHPUnit 10.5, SQLite in-memory pada ENVIRONMENT=testing.
Tes tidak menggunakan database operasional.

```powershell
php vendor/bin/phpunit --no-coverage
node --test tests/frontend/theme.test.cjs tests/frontend/ui-model.test.cjs tests/frontend/security.test.cjs
```

- AuthenticationTest: setup/login/session, shell dan katalog.
- ExportTest: autentikasi, allowlist laporan/format, filter, tipe cell XLSX,
  keamanan teks formula dan PDF tabel.
- DocumentsTest: route cetak berlogin, allowlist kertas, file PDF dan rekonsiliasi
  subtotal/biaya/total/kembalian; PDF contoh untuk inspeksi tersimpan di build.
- theme.test.cjs: konfigurasi region, observer, preferensi OS dan data rusak.
- ui-model.test.cjs: sorting angka lintas halaman, filter gabungan, page clamp,
  format nominal serta batas tipe/ukuran gambar.

Tes tersebut tidak menggantikan pemeriksaan browser. Uji pencarian menu dengan
keyboard; tambah/edit/validasi/draft/cancel; konfirmasi ketika pindah halaman atau
Back dengan form berubah; sorting/filter/pagination/seleksi; drawer/accordion;
preview/hapus gambar; dark/light/system; dan lebar 360/768/1280 px.
Render PDF dan periksa teks/ukuran halaman. Cetak struk harus diuji pada perangkat
fisik yang digunakan. Catat hasil aktual dan keterbatasannya di build/verification.

- SecurityHardeningTest: setup web tertutup, provisioning tervalidasi/satu admin,
  sesi akun nonaktif/hilang/revoked, timeout, role/permission, CSRF, limiter/fail-closed,
  password panjang/legacy, logout, CSP/header, batas ekspor dan redaksi audit.
- security.test.cjs: draft dipisahkan per akun tanpa menghapus preferensi tema;
  kontrak respons memperbarui CSRF dan menolak redirect lintas origin.
- Fixture authenticated kini membuat user nyata di SQLite dan menyertakan versi/
  timestamp sesi. ID sesi palsu bukan lagi cara melewati autentikasi tes.
