# Deployment keamanan kerangka

Scope aplikasi: kerangkaweb.test / dbkerangka. Katalog/form/gambar tetap simulasi; pengerasan ini tidak menghubungkan POS atau tenant operasional.

## Instalasi baru

1. Gunakan document root public/ dengan PHP >=8.3 sesuai lockfile. Pasang dependency dari composer.lock.
2. Siapkan database dan credential migration yang dapat DDL. Jalankan migration melalui akun deployment tersebut, terpisah dari akun aplikasi. Jangan beri hak DDL/global pada koneksi web.
3. Salin .env.example ke .env, isi credential aplikasi acak dan encryption.key acak. Batasi ACL file hanya operator, identitas runtime, SYSTEM dan administrator. Jangan commit atau menaruh .env di public/.
4. Jalankan `php spark app:provision-admin` dari terminal interaktif pada server. Password diminta tersembunyi, tidak diterima melalui argumen. CLI menolak jika user sudah ada. Setup web GET/POST selalu ditutup.
5. Aktifkan HTTPS dengan sertifikat sesuai domain. Redirect HTTP; cookie Secure + HttpOnly + SameSite=Lax. Jangan memakai sertifikat wildcard top-level *.test karena verifikasi hostname dapat menolaknya.
6. Jalankan `php spark app:security-check` lalu verifikasi TLS, redirect, header dan listing melalui HTTP. Command memeriksa konfigurasi dan koneksi; tidak membuktikan Nginx sudah reload atau TLS benar.

Migration pada instalasi ini menambah users.security_version dengan default 1; data akun dipertahankan. Koneksi web memakai kerangka_app pada localhost/127.0.0.1 dengan SELECT, INSERT, UPDATE hanya pada dbkerangka.users. Akun migrasi/admin database tetap terpisah.

## Sesi dan permission

- Idle timeout 30 menit, absolute timeout 8 jam, diverifikasi server.
- Principal dibaca ulang; user hilang/nonaktif atau security_version tidak cocok ditolak.
- UserModel menaikkan versi ketika password/role/is_active berubah melalui model. Bulk security update harus dilakukan per user.
- `php spark app:revoke-sessions ID` mencabut seluruh sesi satu akun tanpa mengganti password.
- Administrator: seluruh permission. Kasir: katalog, dokumen, ekspor produk/pelanggan/transaksi. Gudang: katalog dan ekspor produk. Pengguna/ekspor pengguna hanya Administrator; role tak dikenal ditolak.
- Cakupan outlet/tenant belum dibangun. Jika ditambahkan, wajib scope pada query/service dan uji akses silang; permission peran saja tidak cukup.

## Baseline deployment Nginx

- public/ sebagai root; autoindex off; hanya /index.php yang diteruskan ke FastCGI; PHP lain dan dotfiles ditolak.
- TLS 1.2/1.3, HTTP redirect 308, HSTS tanpa includeSubDomains agar tidak memengaruhi domain lain.
- CSP script self, object none, base/form/frame self. style unsafe-inline dipertahankan untuk Bootstrap dan SVG/CSS demo; ini pengecualian khusus style, bukan script.
- Nginx mengatur header dasar untuk static/error. AppHeaders mengatur CSP/header/no-store untuk respons aplikasi.
- Batas body vhost 2 MB; contoh gambar hanya preview lokal, tanpa endpoint upload.

Pada mesin ini sertifikat lokal khusus CN/SAN kerangkaweb.test berada di direktori SSL Devaron dan dipercaya pada CurrentUser Windows. Masa berlaku sampai 15 Oktober 2027; private key dibatasi ACL dan tidak disertakan dalam source/backup proyek. Sertifikat ini untuk pengembangan lokal; deployment internet memerlukan sertifikat CA publik dan pengelolaan pembaruan otomatis.

Devaron menghasilkan managed-sites.conf otomatis. Perubahan manual vhost dapat ditimpa saat generator dijalankan. Setelah regenerasi jalankan `powershell -File deploy/Apply-KerangkaVhost.ps1 -Reload` dari terminal Administrator. Script mencadangkan file, mengganti hanya block kerangka, membandingkan vhost lain, dan menjalankan nginx -t; kegagalan validasi memulihkan file lama. Template deploy/nginx/kerangkaweb.conf memuat kebijakan target; sesuaikan path/pool/sertifikat pada host berbeda. Jangan mengganti vhost proyek lain. Script ini tidak dijadwalkan otomatis.

## Limit, log, dan scaling

- Rate limiter file atomik menggunakan flock dan key HMAC. Login: IP 5/60 detik dan akun 15/900 detik. Penyimpanan rusak/tidak tersedia menolak login dengan 503, bukan melewati limit.
- Ekspor: user 10/60 detik; maksimal 5.000 baris XLSX / 1.000 baris PDF; dua slot rendering per server. Sesi ditutup sebelum rendering berat.
- File limiter/slot di writable/security; provisioning memakai writable/setup.lock. Direktori writable berada di luar public/.
- Audit event memakai notice (aktif pada logger produksi), context allowlist; password/token/hash tidak dicatat. Atur rotasi, retensi, ACL dan monitoring log sesuai lingkungan.
- Model tabel saat ini memproses empat baris contoh di klien. Modul operasional berdata besar perlu pagination/filter/sort allowlist server dan limit query sebelum materialisasi ekspor.
- Untuk multi-instance, pindahkan limiter/lock/provisioning ke storage koordinasi bersama dan evaluasi sesi bersama, antrean serta streaming. Implementasi file sekarang ditujukan untuk satu host.

## Pengujian

```text
php vendor/bin/phpunit --no-coverage
node --test tests/frontend/theme.test.cjs tests/frontend/ui-model.test.cjs tests/frontend/security.test.cjs
php spark app:security-check
```

Uji tambahan yang perlu dilakukan pada deployment aktual: HTTPS/hostname/trust, HTTP redirect, directory listing, cookie flags, login/logout lintas tab, sesi revoked, permission tiap peran, semua halaman katalog/modal/form/chart/ekspor, CSP console, serta printer fisik.

Cadangan source sebelum pengerasan ada di build/backups/before-security-hardening-20261015.zip. Konfigurasi server lama dicadangkan terpisah. Jangan melakukan rollback source tanpa memperhitungkan migration/.env/permission yang kini lebih ketat.
