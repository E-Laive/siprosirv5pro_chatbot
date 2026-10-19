# Perbaikan SQL otomatis terbatas — 17 Oktober 2026

## Perilaku

AI menerima kemampuan validator sebelum menyusun SQL: SELECT satu statement pada tabel bisnis yang diizinkan, agregasi, CASE WHEN, JOIN ON/USING, CTE nonrekursif, subquery dan batas baris. Larangan seperti WITH RECURSIVE, UNION, wildcard, akses lintas schema, variabel sesi dan DML/DDL disebutkan sejak awal. AI diarahkan meminta klarifikasi hanya untuk informasi bisnis, bukan dukungan teknis aplikasi/database.

Jika kandidat SQL ditolak karena keterbatasan grammar yang dikenali, aplikasi boleh meminta **satu perbaikan otomatis** di dalam request pesan yang sama. Pemicu saat ini adalah CTE rekursif, operasi himpunan UNION/INTERSECT/EXCEPT, dan SELECT tanpa sumber tabel di dalam query yang tetap mempunyai sumber bisnis yang sah.

Sebelum memberikan izin perbaikan, seluruh token query diperiksa secara konservatif. Sintaks yang tidak didukung tidak boleh menyembunyikan akses tabel/kolom terlarang, pelanggaran role, lintas schema, fungsi tidak diizinkan, wildcard, comma join, multi-statement, komentar eksekusi atau fan-out header/detail. Jika pemeriksaan ini tidak lolos, tidak ada panggilan perbaikan.

## Batas dan penjagaan

- Maksimal dua generasi SQL: kandidat awal dan satu pengganti. Dengan pemeriksaan routing yang sudah ada, maksimum tiga panggilan AI pada alur dinamis ini. `maxToolIterations` tidak memperbesar batas perbaikan.
- Tidak ada perbaikan SQL untuk gangguan koneksi POS, kegagalan eksekusi, gangguan provider/protokol atau ketidakcocokan filter transaksi NORMAL.
- Prompt perbaikan memakai kontrak sistem/schema yang sama, permintaan dan scope terstruktur, kandidat SQL yang ditolak, serta kode/alasan terkontrol. Riwayat percakapan tidak diputar ulang pada panggilan perbaikan. Dataset, KPI, hasil laporan, secret, dan error koneksi mentah tidak disertakan.
- Query pengganti wajib lolos validator lagi. Pemeriksaan tambahan membandingkan tabel bisnis, literal string, predicate numerik sederhana, agregasi dan GROUP BY. Penggantian periode/metrik/pengelompokan yang terdeteksi dihentikan sebelum eksekusi.
- Pemeriksaan kesesuaian tersebut konservatif, bukan pembuktian kesetaraan SQL secara umum. Alternatif yang tidak dapat dibuktikan sesuai oleh pemeriksaan ini dapat ditolak walaupun secara matematis setara.
- Hanya SQL pengganti yang valid dijalankan pada POS. Kandidat gagal yang akhirnya berhasil diperbaiki dicatat sebagai percobaan perencanaan, sehingga tidak merusak status dataset sukses maupun kelayakan ekspornya.
- Jika pengganti masih gagal, pengguna diberi tahu bahwa satu metode alternatif sudah dicoba dan proses berhenti. Dataset/konteks sukses sebelumnya tetap disimpan.
- Pertanyaan AI tentang dukungan teknis diblokir pada routing maupun penyusunan/perbaikan SQL. Klarifikasi bisnis tetap berfungsi.
- Untuk penolakan metode yang final, UI menampilkan alasannya dan mempertahankan draft untuk diedit, tanpa tombol pengiriman ulang pertanyaan yang sama. Gangguan sementara tetap menyediakan coba lagi.

## Kontrak dan audit

Route dan bentuk blok respons tetap digunakan. Validator menambahkan `error_code`, `retryable` dan `repair_hint` pada penolakan keterbatasan yang dikenali. Metadata respons menambahkan `sql_planning_attempts` dan, pada hasil dinamis sukses/klarifikasi, `sql_repair_used`.

Kode `analysis_not_supported` menandai belum tersedianya metode; `sql_repair_scope_mismatch` menandai perubahan scope yang terdeteksi. `sql_validation_failed`, `provider_unavailable`, `provider_invalid_response`, `business_filter_mismatch`, `pos_unavailable` dan `query_failed` tetap dipertahankan.

Audit mencatat permintaan perbaikan dan keputusan validasi pengganti melalui `ai.sql.repair.*`, dengan identitas pemilik/percakapan dan kode alasan. Percobaan perencanaan hanya menyimpan status dan kode alasan; SQL hasil eksekusi tetap memakai log query yang tersedia.

## Verifikasi

- PHPUnit: **128 tes, 1.148 assertion**, lulus dengan database testing dan writable terisolasi.
- Termasuk **12 tes regresi perbaikan SQL, 125 assertion**: batas satu perbaikan, CTE rekursif/UNION, penolakan role/kolom/schema/fungsi yang tersembunyi, larangan retry runtime/provider, perubahan tabel/periode/filter/metrik/GROUP BY, privasi prompt, konteks tetap, dan klarifikasi bisnis/teknis.
- Frontend Node: **23 tes lulus**, termasuk tidak menawarkan retry identik untuk penolakan metode final, draft tetap tersedia, serta retry gangguan sementara.
- Lint sembilan berkas PHP dan pemeriksaan sintaks JavaScript lulus.
- Alur dinamis biasa dengan pertanyaan master merek yang jelas berhasil memakai provider lokal aktif dan POS live. Pertanyaan awal yang hanya menyebut “master” menghasilkan klarifikasi bisnis; pertanyaan uji diperjelas agar membedakan master merek dari merek pada master produk.
- Dua uji integrasi perbaikan lulus: kandidat awal UNION dan CTE rekursif sengaja disuntikkan oleh harness QA lokal, lalu **masing-masing diperbaiki melalui satu panggilan ke provider aktif**. SQL hasilnya lolos validator dan total September cocok dengan SELECT kontrol POS. Ini pengujian dengan kandidat awal tersimulasi, bukan klaim bahwa provider aktif menghasilkan kandidat salah tersebut.
- Bukti: `build/sql-repair/live-checks.json`, `build/live-verification/provider-check.json`; harness: `build/verify-sql-repair.php` dan `build/verify-provider-sql.php`.

Tidak ada penulisan transaksi POS, perubahan konfigurasi provider produksi atau layanan yang dinyalakan/dihentikan. Riwayat pengguna tidak ditulis dalam uji integrasi; alur pesan QA biasa memakai folder file terpisah. Perubahan kecil perilaku tombol diverifikasi dengan tes DOM otomatis; pemeriksaan browser visual tidak dijalankan pada pekerjaan ini.
