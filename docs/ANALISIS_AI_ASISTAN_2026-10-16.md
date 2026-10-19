# Analisis project AI ASISTAN

> Arsip audit awal sebelum implementasi. Status terkini dan koreksi diagnosis bootstrap tersedia pada [hasil implementasi](HASIL_IMPLEMENTASI_AI_2026-10-16.md).

Tanggal: 16 Oktober 2026 (Asia/Jakarta).
Project yang diperiksa: `D:/DATA/DATA PRODUK/WEB/AI CHATBOT`.

## Kesimpulan

Pemisahan controller, orkestrator, semantic layer, validator SQL, koneksi POS, provider, dan penyimpanan percakapan sudah memberikan struktur yang jelas. Namun, hasil analisis belum layak dianggap selalu akurat: ada query sah yang ditolak, kegagalan query yang berubah menjadi angka nol, dan celah pemeriksaan tabel SQL. Perbaikan batas keamanan SQL serta propagasi kegagalan query menjadi prioritas pertama.

Perubahan kode aplikasi pada pekerjaan ini hanya pada `app/Views/layout/sidebar.php`: sidebar menampilkan satu menu **AI ASISTAN**, tetap mengikuti permission `ai.chat`, dan logo mengarah ke `/asisten-ai`. Dashboard/katalog tidak dirender sebagai menu sidebar. Route modul lama tetap tersedia. Pencarian menu di topbar dan tujuan setelah login belum diubah karena permintaan menyebut sidebar.

## Teknologi dan arsitektur yang ditemukan

| Bagian | Implementasi aktual |
|---|---|
| Backend | PHP CLI 8.3.28; CodeIgniter 4.7.5 dari lockfile dan runtime |
| Frontend | Bootstrap 5.3.7 lokal, Bootstrap Icons, Inter lokal, JavaScript tanpa bundler |
| Navigasi | Shell server + respons JSON fragmen, Fetch/History API, lifecycle workspace/catalog |
| Tema | `theme-init.js`, token CSS, konfigurasi Light/Dark/System per region |
| AI HTTP | `AiChat` untuk percakapan, pesan, status, provider, dan ekspor |
| Analisis | `AiOrchestrator`: intent berbasis pola terlebih dahulu; provider LLM untuk pertanyaan yang tidak cocok dengan pola |
| Aturan bisnis | `BusinessSemanticLayer`: omzet, retur, HPP historis, stok, piutang dan pembelian |
| Schema | `PosSchemaService`: katalog kurasi, domain per role, parser DDL referensi |
| Akses data POS | `SqlSafetyValidator` kemudian `PosQueryExecutor`, grup `posReadonly`; fixture SQLite saat testing/offline |
| Penyimpanan aplikasi | Database default untuk users; service AI mencoba tabel AI, lalu fallback file JSON per akun |
| Ekspor | CSV, PhpSpreadsheet 5.10.0, Dompdf 3.1.6 |

Alur utama: sesi aktif → permission route → percakapan milik akun → resolusi intent/konteks → pemeriksaan domain → validasi SQL → eksekusi POS → perhitungan dan blok respons → penyimpanan riwayat → render teks/KPI/grafik/tabel.

Sebagian besar pertanyaan yang dikenali dijawab oleh mesin deterministik. Mengisi API key tidak berarti seluruh percakapan diproses LLM. Jalur dinamis meminta satu rencana SQL JSON; belum berupa loop tool-calling atau streaming.

## Temuan prioritas

P1 = prioritas tinggi untuk keamanan/keakuratan; P2 = masalah fungsi atau ketahanan; P3 = konsistensi produk/dokumentasi. Status “direproduksi” berarti dijalankan pada probe terisolasi, bukan database POS produksi.

### 1. P1 — Batas izin tabel SQL dapat dilewati lewat FROM dengan koma

Bukti: `app/Services/AI/SqlSafetyValidator.php:413`. Ekstraksi tabel hanya mengikuti `FROM` dan `JOIN`, sehingga tabel kedua setelah koma tidak masuk daftar pemeriksaan.

Probe validator menghasilkan `valid=true` untuk ketiga bentuk berikut:

```sql
-- Administrator: tabel internal tidak terdeteksi
SELECT p.id_transaksi, u.username
FROM tbltransaksipenjualan p, tbluser u

-- Gudang: tabel payroll tidak terdeteksi
SELECT b.id_produk, g.total_bayar
FROM tbldatabarang b, tblgaji g

-- Gudang: schema aplikasi lain tidak terdeteksi
SELECT b.id_produk, u.username
FROM tbldatabarang b, dbkerangka.users u
```

Sebaliknya, `SELECT username FROM tbluser` ditolak. Ini membuktikan celah validator, bukan bahwa data sudah bocor. SQL berbahaya tersebut tidak dieksekusi ke database. Dampak akses aktual bergantung pada output LLM dan hak database akun POS.

Usulan: parser struktur SQL yang memeriksa seluruh table reference dan scope CTE/subquery; sementara, tolak sintaks yang belum didukung. Batasi hak SELECT database pada tabel yang memang diperlukan, dan uji akses silang role/schema.

### 2. P1 — JOIN sah justru ditolak

Bukti: `SqlSafetyValidator.php:446`, fungsi `hasUnboundedJoin()`.

Query dengan `JOIN ... ON ...` normal ditolak sebagai Cartesian product. Direproduksi juga pada SQL internal modul, bukan hanya query buatan probe: drill-down penurunan produk, pertumbuhan member, stok per lokasi, dan laba kotor.

Usulan: validasi relasi pada struktur query; tambahkan kasus JOIN sah dan JOIN tanpa kondisi sebagai pasangan pengujian. Pemeriksaan harus mempertahankan proteksi fan-out tanpa memblokir seluruh penggabungan tabel.

### 3. P1 — Query gagal dapat disajikan sebagai hasil bisnis Rp0

Bukti: `AiOrchestrator.php:850`, terutama default hasil pada baris 869–870; pola serupa muncul pada analyzer lainnya.

Probe menyuntikkan hasil eksekutor `success=false` dan `rows=[]`. Analyzer tetap menghasilkan narasi “0 transaksi”, KPI omzet Rp0 dan tabel rekonsiliasi, tanpa blok peringatan kegagalan. Pada fixture, query stok/laba yang ditolak validator juga tetap menghasilkan narasi nominal nol.

Dampak: pengguna sulit membedakan nilai nol yang sah dengan kegagalan akses/validasi/koneksi. Ekspor dapat membawa hasil yang sama.

Usulan: hentikan perhitungan ketika query wajib gagal. Untuk analisis bertahap, tandai hasil parsial, jelaskan bagian yang gagal, dan jangan menyimpan dataset hasil gagal sebagai konteks analisis sukses.

### 4. P1 — Judul laporan dapat menjadi formula Excel/CSV

Bukti: `app/Services/ReportExporter.php`, metode `excel()` dan `csv()`; `AiOrchestrator.php` mengambil judul dari JSON LLM pada jalur dinamis.

Nilai sel data XLSX sudah memakai tipe eksplisit, tetapi judul A1 memakai `setCellValue()`. Probe judul aman `=1+1` menghasilkan `<f>1+1</f>` pada worksheet XLSX; baris pertama CSV juga tetap `=1+1`. Sanitasi CSV saat ini hanya diterapkan pada isi baris data.

Usulan: perlakukan semua metadata teks sebagai string eksplisit, termasuk judul/catatan/header/filter; terapkan perlindungan formula secara konsisten pada CSV. Tambahkan uji untuk metadata, bukan hanya nama produk dalam dataset.

### 5. P1 — Migration tabel AI tidak tersedia pada source yang diperiksa

Bukti: `app/Database/Migrations` hanya memuat migration users dan security version; `ConversationService.php:49` merujuk kelas `CreateAiAnalystTables` yang tidak ditemukan.

Tabel `ai_conversations`, `ai_messages`, `ai_provider_configs`, dan `ai_query_logs` disebut dalam dokumentasi dan dipakai service, tetapi fresh install tidak memiliki migration pembentuknya. Probe testing beralih ke penyimpanan file. Keberadaan tabel pada database live belum diperiksa.

Usulan: sediakan migration Database A, indeks, foreign key, dan privilege runtime yang tepat. Nyatakan backend penyimpanan yang aktif melalui diagnostik admin. Migration tidak boleh diarahkan ke POS.

### 6. P2 — Penyimpanan percakapan belum atomik dan kegagalan tulis disembunyikan

Bukti: `ConversationService.php:343` dan `:520–542`; `AiProviderClient.php:141`.

Penyimpanan file memakai read → ubah → write; `LOCK_EX` hanya pada write sehingga dua permintaan dapat menimpa hasil satu sama lain. Nilai balik `file_put_contents()` tidak diperiksa. Jalur database menulis pesan user, assistant, dan konteks dalam operasi terpisah tanpa transaksi; penghapusan juga terpisah. Fallback DB ke file tidak memigrasikan percakapan yang sudah ada di DB.

Ini temuan pemeriksaan kode; race condition dan gangguan disk belum diuji secara konkuren.

Usulan: pilih penyimpanan utama yang jelas, transaksi DB untuk satu turn/penghapusan, atau lock seluruh read-modify-write file dengan penggantian file atomik. Gagal menyimpan harus dikembalikan sebagai kegagalan yang terlihat.

### 7. P2 — Waktu relatif dikunci pada Oktober 2026

Bukti: `AiOrchestrator.php:261–262`: `$nowYear = 2026; $nowMonth = 10;`.

“Bulan lalu” selalu mengacu September 2026. Pada tanggal audit kebetulan sesuai, tetapi setelah bulan berganti hasilnya salah. Sebagian default periode lain juga berisi tahun tetap; parser tahun membatasi pola `202[0-9]`.

Usulan: gunakan clock yang dapat diuji, timezone konfigurasi Asia/Jakarta, dan aturan periode konsisten untuk pergantian bulan/tahun. Tes tidak boleh bergantung pada tanggal mesin tertentu.

### 8. P2 — Follow-up grafik/tabel tidak konsisten dengan tombol UI

Bukti: `AiOrchestrator.php:265–276`; `public/assets/js/ai-chat.js` mengirim prompt “Tampilkan grafiknya.” dan “Coba tampilkan dalam tabel saja.”.

Probe dengan konteks berisi `last_rows` menemukan “Tampilkan grafiknya.” menjadi `dynamic_llm_or_clarify`, bukan `reformat_previous`: pola kedua hanya menerima kata `grafik`, tanpa akhiran `nya`. “Ubah menjadi tabel.” tidak mengatur output preference sebagai tabel pada pola pertama. Dalam percakapan fixture, kedua langkah menghasilkan klarifikasi.

Usulan: satu kontrak aksi reformat yang terstruktur untuk tombol; perluas normalisasi bahasa untuk prompt pengguna. Verifikasi bahwa konteks dan dataset sebelumnya tetap digunakan tanpa query ulang.

### 9. P2 — Respons terlambat dapat masuk ke percakapan yang berbeda

Bukti: `public/assets/js/ai-chat.js:597–652`, `:693–716`.

Saat mengirim pesan hanya tombol kirim dinonaktifkan. Pengguna masih dapat membuka percakapan lain atau memilih Percakapan Baru. Respons lama kemudian menimpa `activeUid` dan menambahkan pesan ke stream yang sedang aktif. Input juga dikosongkan sebelum request, tanpa pemulihan ketika request gagal.

Ini temuan pemeriksaan alur async; reproduksi browser belum dilakukan.

Usulan: ikat respons pada UID/generasi request, batalkan atau abaikan respons yang sudah tidak relevan, dan pertahankan pesan gagal sebagai draft dengan aksi coba lagi.

### 10. P2 — Fallback dataset verifikasi aktif secara default

Bukti: `app/Config/AiAnalyst.php`, properti `allowAnalyticalSandboxWhenPosOffline`; `PosQueryExecutor.php:ensureRuntimeConnection()`.

Fallback tidak hanya berlaku ketika koneksi gagal. Koneksi live dengan transaksi penjualan kosong juga dapat dialihkan ke fixture karena pemilihan live memeriksa `COUNT(*) > 0`. Fixture melaporkan `connected=true`, walaupun mode/nama sumber memberikan label verifikasi.

Usulan: pada penggunaan operasional, kegagalan koneksi menjadi status offline, sedangkan dataset simulasi harus dipilih eksplisit. Database valid yang masih kosong harus tetap dianggap sumber live dengan nol transaksi.

### 11. P2 — Rate limit AI dilewati ketika storage limiter bermasalah

Bukti: `app/Controllers/AiChat.php:143–162`.

Exception dari limiter ditelan dan request diteruskan ke orkestrator. Ini berbeda dengan login yang mengembalikan 503 saat limiter tidak tersedia.

Usulan: kembalikan 503/Retry-After serta audit kejadian. Uji dengan storage limiter yang gagal, bukan hanya ketika batas frekuensi tercapai.

### 12. P2 — Kemampuan provider yang ditampilkan belum seluruhnya diterapkan

Bukti: `AiProviderClient.php:45–46`, `:133–134`, payload `chatCompletion()`; jalur dinamis `AiOrchestrator.php:682`.

Flag streaming/tools disimpan, tetapi tidak dipakai pada payload maupun orkestrasi. Status “LLM Aktif” pada UI ditentukan oleh keberadaan API key, bukan hasil health check. Konfigurasi provider bisa dilaporkan sukses walaupun penulisan file gagal. Base URL hanya diperiksa sebagai URL valid; kebijakan skema HTTPS/HTTP lokal belum dibatasi secara eksplisit.

Usulan: tampilkan kemampuan yang benar-benar tersedia, pisahkan status konfigurasi dan koneksi, periksa hasil penyimpanan, serta tentukan kebijakan URL untuk provider remote dan lokal. Jangan menganggap API key terisi sebagai bukti provider berjalan.

### 13. P2 — Grafik fallback memotong nilai negatif menjadi nol

Bukti: `public/assets/js/ai-chat.js:160`, `:173`, `:192` menggunakan `Math.max(0, ...)` pada koordinat/nilai grafik SVG.

Shell tidak memuat ECharts, sehingga fallback SVG adalah jalur penting. Nilai kerugian, saldo negatif, atau perubahan turun dapat digambar sebagai nol. Ini temuan kode; tampilan negatif di browser belum diverifikasi.

Usulan: skala minimum–maksimum yang mencakup nilai negatif, baseline nol yang tepat, dan satuan label sesuai tipe data. Pertahankan tabel sebagai alternatif yang dapat dibaca.

### 14. P3 — Navigasi dan dokumentasi masih mencerminkan starter

Login dan Home tetap menuju Dashboard. Topbar Ctrl+K masih menawarkan katalog. Brand shell, README awal, dan panduan deployment mengacu Kerangka/kerangkaweb.test, sementara konfigurasi URL yang teramati saat render mengarah ke sipv5ai.test. Ini tidak diubah karena perubahan yang diminta hanya sidebar.

Usulan: pada tahap penyesuaian produk berikutnya, tetapkan landing page AI, hasil pencarian menu yang relevan, identitas aplikasi, dan panduan deploy yang sesuai lokasi sebenarnya.

## Hal yang sudah baik dari pemeriksaan kode

- Route AI dilindungi auth dan permission; global CSRF tersedia.
- Principal akun memeriksa ulang status, role, versi keamanan dan timeout sesi.
- Baca/rename/delete percakapan menyertakan UID dan user ID pada jalur akses service.
- Perubahan provider dibatasi Administrator di controller.
- Banyak input berbahaya ditolak: multi-statement, DML/DDL, tabel internal langsung, kolom sensitif dan SELECT * sederhana. Celah sintaks pada temuan 1 tetap perlu diperbaiki.
- API key tidak dikembalikan utuh melalui kontrak konfigurasi normal; secret/runtime diabaikan oleh `.gitignore`.
- Teks markdown di-escape sebelum format HTML; pengujian injeksi script sederhana lulus.
- Modal provider/rename memakai backdrop statis; event UI didelegasikan dan inisialisasi AI terhubung ke lifecycle workspace SPA.
- Katalog schema dibandingkan dengan DDL referensi: tidak ditemukan nama tabel/kolom kurasi yang hilang. Ini tidak membuktikan kesesuaian database live.
- Omzet header dan retur memakai agregasi terpisah; HPP diarahkan pada snapshot detail transaksi. Semantik multi-satuan, diskon faktur, PPN dan histori migrasi tetap memerlukan rekonsiliasi dengan transaksi nyata.
- Renderer ekspor memiliki batas baris/konkurensi; tipe eksplisit sudah digunakan untuk isi sel XLSX.

## Verifikasi aktual dan batasnya

| Pemeriksaan | Hasil |
|---|---|
| Sintaks seluruh PHP app + tests | 140 file diperiksa, lulus |
| Tes frontend theme/ui-model/security/ai-analyst | 11 tes lulus |
| `php spark routes` | Lulus; route AI dan filter terbaca |
| `composer check-platform-reqs` | Lulus |
| Render sidebar dengan CI4 + DOM, Administrator | Tepat 1 link menu, teks AI ASISTAN, tujuan /asisten-ai, tanpa /katalog/ |
| Probe validator SQL | Bypass tabel/schema dan penolakan JOIN sah direproduksi |
| Probe hasil query gagal | Narasi dan KPI Rp0 tanpa warning direproduksi |
| Percakapan 10 langkah pada fixture lokal | 5 langkah memiliki query gagal; 2 langkah reformat menjadi klarifikasi |
| Probe formula ekspor | Formula pada judul XLSX dan CSV direproduksi memakai `=1+1` |
| PHPUnit seluruh suite | Saat audit awal terhambat akses sandbox; pemeriksaan ulang membuktikan bootstrap tersedia. Lihat laporan implementasi untuk hasil suite terbaru. |
| `composer audit --locked` | Tidak selesai: koneksi Packagist gagal; status advisory dependency belum diketahui |
| HTTP sipv5ai.test | Host tidak ter-resolve dari lingkungan tool; tidak ada verifikasi browser/live |

Probe memakai ENVIRONMENT=testing, Database A SQLite in-memory, fixture POS, direktori writable terpisah `build/audit-runtime`, dan provider remote dimatikan pada objek konfigurasi probe. Tidak ada SQL bypass yang dijalankan ke POS; tidak ada migration atau perubahan data operasional. Layanan database/web tidak dinyalakan, dihentikan, atau dikonfigurasi ulang.

Bukti lokal tersimpan di `build/audit-ai-results.jsonl`; runner di `build/audit-ai-probe.php`. Keduanya artefak audit lokal di folder build yang diabaikan Git. Direktori project ini tidak memiliki metadata `.git` yang terdeteksi, sehingga status/diff Git tidak tersedia.

Belum diverifikasi: login/logout browser, visual desktop/mobile dan tema, streaming/provider remote, grant SELECT akun POS, kesesuaian schema live, nilai bisnis terhadap transaksi nyata, ekspor hasil live, backup/restore serta beban konkuren. Lulus lint/frontend tidak berarti keseluruhan AI sudah aman atau akurat.

## Urutan perbaikan yang disarankan

1. Perbaiki parser/validator SQL, kasus JOIN sah, dan penerusan kegagalan query.
2. Amankan seluruh metadata ekspor, lalu tambah uji keamanan yang mereproduksi kasus di atas.
3. Lengkapi migration/storage AI dan uji atomicity, failure handling serta hak database.
4. Perbaiki periode dinamis, follow-up, dan pemisahan request antarpercakapan.
5. Tegaskan mode live/simulasi dan kemampuan provider; selaraskan grafik dan navigasi produk.
6. Pulihkan dependency testing dari lockfile, jalankan suite lengkap, kemudian uji browser serta rekonsiliasi terhadap POS read-only yang benar-benar aktif.

Pekerjaan perbaikan backend di atas belum diimplementasikan pada audit ini. File kode aplikasi yang berubah: `app/Views/layout/sidebar.php`; laporan ini ditambahkan sebagai dokumentasi hasil analisis.
