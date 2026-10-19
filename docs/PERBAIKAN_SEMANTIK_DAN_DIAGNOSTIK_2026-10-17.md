# Perbaikan semantik percakapan dan diagnostik SQL

Tanggal: 17 Oktober 2026. Target: aplikasi lokal `sipv5ai.test`, PHP/CodeIgniter 4 dan renderer `ai-chat.js`.

## Perilaku yang diterapkan

- `tanggal 15 bulan ini` menjadi 15 Oktober 2026 saja. Tanggal tanpa bulan memakai konteks bulan yang tunggal; tanggal tidak valid atau konteks ambigu meminta klarifikasi. Pergantian Desember–Januari dan Februari kabisat diuji.
- `hari ini` merupakan filter periode, bukan permintaan pengelompokan harian. Perpindahan dari laporan penjualan harian ke daftar member melepaskan grain penjualan yang lama.
- Provider online tetap dihubungi. Kandidat rencana menyertakan entitas, metrik, dimensi, grain waktu, peringkat dan kebutuhan pembacaan terbaru. Template member yang teruji tidak memerlukan generasi SQL tambahan.
- Daftar member aktif dan peringkat nominal/frekuensi memakai query berparameter, transaksi NORMAL, COUNT DISTINCT transaksi dan SUM grand_total sebelum retur. Pelanggan anonim dikecualikan dan cakupannya dijelaskan. Nilai seri disertakan; hasil yang terpotong diberi peringatan atau dihentikan jika kelengkapan seri tidak dapat dibuktikan.
- Filter khusus seperti cabang, produk, nominal minimum atau identitas member tidak boleh hilang demi menggunakan template. Permintaan tersebut tetap menuju planner khusus.
- Pertanyaan keberadaan transaksi dan `cek ulang sekarang` membaca POS kembali. Pertanyaan `itu tadi data kapan?` menjelaskan periode dan waktu pengambilan laporan tersimpan tanpa query baru. Pemeriksaan keberadaan transaksi hanya menampilkan teks.
- Hasil kosong tetap hanya penjelasan. Transaksi sah bernominal nol tetap diakui sebagai transaksi.

## Validasi dan koreksi SQL

`SqlDailyGrain` memeriksa tanggal lengkap pada scope hasil, termasuk alias, urutan GROUP BY, DATE_FORMAT tanggal lengkap, dan lineage proyeksi CTE/subquery yang didukung. DAY(tanggal) saja atau agregasi bulanan di scope luar tidak memenuhi kontrak harian. Alias tidak boleh menutupi kolom sumber lain.

Maksimal satu koreksi otomatis total untuk sintaks yang didukung atau grain harian yang belum tepat. Tabel, literal filter, periode, metrik dan dimensi nonwaktu tetap dibandingkan. Kegagalan akses, filter bisnis, koneksi dan eksekusi tidak memicu koreksi otomatis. Validator recursive CTE/UNION tidak dilonggarkan.

## SQL pada kegagalan di mode developer

- SQL yang sudah dihasilkan dicatat di metadata perencanaan dan `sql_diagnostics`, terpisah dari query yang mencapai executor.
- Jejak menampilkan SQL, alasan/kode penolakan dan status `Belum dieksekusi`, `Gagal`, atau `Berhasil`.
- Kegagalan pesan dirender sebagai pesan asisten sehingga jejak langsung dapat dibuka tanpa reload. Draft tetap tersedia. Kesalahan kontrak SQL tidak menawarkan pengulangan identik tanpa batas.
- Konten SQL/alasan dirender dengan textContent, bukan HTML.
- Pada production, query, percobaan perencanaan dan diagnostik SQL dibuang dari respons HTTP, riwayat dan initial context. Konfigurasi lingkungan lokal tetap production; tidak diubah untuk menampilkan SQL.
- Query yang dahulu gagal tanpa disimpan SQL-nya tidak dapat direkonstruksi secara pasti. Riwayat lama tidak ditulis ulang.

## Bukti pengujian

Tes regresi terdapat pada `tests/feature/AiRequestSemanticsTest.php`, suite integritas/repair/percakapan/diagnostik, serta `tests/frontend/ai-interactions.test.cjs`.

Hasil akhir: PHPUnit **174 tes / 1.479 assertion**, frontend **30 tes**, lint 14 berkas PHP terkait dan pemeriksaan sintaks JavaScript lulus. Provider lokal sebenarnya berhasil memperbaiki tiga kandidat QA: UNION, recursive CTE, dan agregasi yang kehilangan grain harian. Setiap skenario memakai tepat satu panggilan koreksi; hasilnya cocok dengan kontrol POS. Kandidat awal sengaja disisipkan oleh harness QA, sehingga ini bukti jalur koreksi, bukan klaim bahwa provider menghasilkan kandidat salah itu secara alami.

Percakapan browser QA `conv-8f15c29cd71377af` bernama **QA SEMANTIK · tanggal dan member**, dibuat terpisah dari percakapan pengguna. Alur yang tersimpan: laporan harian Oktober → daftar member hari ini → nominal member tertinggi → transaksi tanggal 15 → cek transaksi hari ini → penjelasan periode → grafik laporan tanggal 15 melalui referensi laporan lama.

`build/verify-request-semantics-live.php` mencocokkan tujuh turn tersebut dengan kontrol SELECT pada POS. Seluruh 26 pemeriksaan lulus, termasuk angka/frekuensi/coverage member, peringkat, periode tanggal 15, penjelasan tanpa query, reformat tanpa query dan penghilangan SQL production. Contoh tanggal 15: omzet faktur Rp17.785.000 dari 20 transaksi. Pemeriksaan hari ini saat pengujian menghasilkan 20 transaksi. Angka ini adalah snapshot pengujian, bukan nilai tetap aplikasi.

Bukti mesin disimpan di `build/request-semantics-live/checks.json`. Script `build/verify-sql-repair.php` menggunakan kandidat QA yang sengaja salah dan provider lokal sebenarnya untuk koreksi, tanpa mengubah konfigurasi provider atau mengirim baris transaksi.

## Batas verifikasi browser

Alur utama desktop berhasil dijalankan melalui sesi akun yang sudah login. Screenshot gagal diambil oleh alat. Setelah turn lanjutan, alat kembali melaporkan akses browser lokal nonaktif; uji visual ponsel, reload UI akhir dan inspeksi visual jejak developer belum terverifikasi. Perilaku tersebut diuji otomatis, tetapi tidak diklaim lulus visual browser. Riwayat pengguna dan transaksi POS tidak dihapus atau diubah; layanan database/web tidak dioperasikan otomatis.
