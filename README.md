# AI ASISTAN

Asisten analisis POS berbasis CodeIgniter 4, Bootstrap, Inter, dan JavaScript lokal.
Sidebar, halaman awal setelah login, serta pencarian menu berfokus pada AI ASISTAN.
Jawaban menampilkan ringkasan dan maksimal tiga angka utama; tabel, grafik, metodologi,
dan SQL tersedia dalam detail. Setiap pertanyaan wajib mendapat keputusan valid
dari provider AI yang dikonfigurasi admin. Tidak ada jawaban analisis offline.
AI memeriksa maksud dan periode; query serta perhitungan angka tetap diperiksa server.

## Menjalankan

PHP 8.3+ 64-bit dan Composer 2 diperlukan. Salin konfigurasi dari .env.example,
isi credential aplikasi dan koneksi POS yang terpisah, lalu jalankan:

~~~powershell
composer install
php spark migrate
php spark ai:import-history
~~~

Migration hanya dijalankan pada database aplikasi. Perintah impor dapat diulang,
mempertahankan UID/pemilik/pesan/konteks, membuat cadangan file lama, dan tidak
menghapus sumbernya. Bila instalasi baru belum memiliki admin, gunakan
php spark app:provision-admin secara interaktif. Tidak ada akun/password bawaan.
Arahkan document root web ke public/. Konfigurasi pengembangan lokal saat verifikasi
menggunakan http://sipv5ai.test; produksi sebaiknya memakai HTTPS dan cookie secure.

## Penyimpanan dan sumber data

- ai.storageDriver = database adalah default. Empat tabel AI berada pada database
  aplikasi, terpisah dari database POS. Satu turn dan penghapusan memakai transaksi.
- ai.storageDriver = file adalah pilihan eksplisit. File memakai lock sepanjang
  read-modify-write dan penggantian atomik. Gangguan database tidak memindahkan mode.
- Konfigurasi provider aktif berada pada writable/ai_storage/provider_config.json.
  File ini rahasia dan tidak boleh dipublikasikan. Metadata database tidak
  menimpa konfigurasi aktif. Pengaturan hanya tersedia untuk Administrator.
- Provider remote wajib HTTPS. HTTP hanya untuk localhost, 127.0.0.1, atau [::1].
  Status dikonfigurasi berbeda dari status berhasil dihubungi. Alur masih sinkron.
- POS memakai grup posReadonly dengan akun SELECT terbatas pada tabel bisnis.
  Koneksi gagal ditampilkan offline. Database kosong tidak diganti dengan fixture.
- Fixture hanya tersedia pada ENVIRONMENT=testing. Konfigurasi lama
  ai.allowAnalyticalSandboxWhenPosOffline tidak dapat mengaktifkan simulasi
  pada aplikasi operasional. POS gagal tetap offline, tanpa data pengganti.
- Waktu bisnis memakai ai.timezone = Asia/Jakarta. Jangan menyalakan/mematikan
  layanan database/web melalui alur aplikasi atau skrip verifikasi.

Setiap pertanyaan mengirim pertanyaan, kandidat rencana dan konteks periode
ke provider terpilih. Pertanyaan bebas juga dapat mengirim metadata skema serta
riwayat pertanyaan pengguna. Dataset, KPI dan jawaban bisnis lama tidak dikirim
sebagai riwayat assistant. Pilih provider yang memang diizinkan menerima informasi tersebut.
Query hasil provider tetap harus melalui pembatasan role, validator SELECT,
proteksi tabel/schema/kolom sensitif, LIMIT, dan timeout sebelum dieksekusi.

## Endpoint dan pengujian

Route utama /asisten-ai; kontrak pesan tetap dipakai untuk aksi
reformat dengan action=reformat dan format=table/chart/both, tanpa query POS ulang.
Reformat tetap membutuhkan keputusan baru dari provider AI. Riwayat dan ekspor
hasil yang sudah tersimpan dapat dibuka tanpa membuat analisis baru.
Provider tidak dikonfigurasi, gagal dihubungi, atau memberi respons rusak menghentikan
analisis sebelum query baru dijalankan. Respons sukses menyertakan
ai_mode=online_required, provider_used=true dan planner_model.

Respons menyertakan analysis_status dan error_code untuk membedakan kegagalan,
penolakan, dan hasil sukses. Ekspor CSV/XLSX/PDF menjaga batas baris dan konkurensi.

~~~powershell
php vendor/phpunit/phpunit/phpunit --no-coverage
node --test tests/frontend/ai-analyst.test.cjs tests/frontend/ai-interactions.test.cjs tests/frontend/security.test.cjs tests/frontend/theme.test.cjs tests/frontend/ui-model.test.cjs
composer check-platform-reqs
composer audit --locked
~~~

Bootstrap PHPUnit menggunakan database testing dan direktori build/test-runtime-*
yang terisolasi. Tes tidak boleh diarahkan ke database POS atau storage chat nyata.
Lihat [perbaikan konteks percakapan dan pengujian](docs/KONTEKS_PERCAKAPAN_2026-10-17.md),
[kebijakan AI wajib dan hasil pengujian](docs/AI_ONLINE_WAJIB_2026-10-17.md),
[hasil implementasi dan batas verifikasi](docs/HASIL_IMPLEMENTASI_AI_2026-10-16.md),
[audit awal](docs/ANALISIS_AI_ASISTAN_2026-10-16.md), serta
[panduan keamanan deployment](deploy/SECURITY.md).
