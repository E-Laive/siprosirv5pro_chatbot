# Penguatan integritas dan konteks AI — 17 Oktober 2026

## Perubahan

- SQL dinamis yang membaca penjualan diperiksa per scope SELECT dan alias tabel. Setiap cabang OR harus membuktikan filter NORMAL dan periode yang sama dengan rencana. Filter pada subquery lain tidak dianggap melindungi query luar. Detail wajib terhubung ke header yang difilter melalui ID transaksi. Operator boolean ambigu ditolak.
- Periode didukung melalui batas awal inklusif/akhir eksklusif atau YEAR/MONTH yang tepat. Rincian harian diperiksa pada GROUP BY DATE(tanggal). Pemeriksaan ini konservatif: bentuk ekuivalen yang belum dikenal dihentikan dengan kode business_filter_mismatch, business_period_mismatch, atau business_grouping_mismatch. Query gagal tidak mengganti dataset sukses terakhir.
- Perbaikan sintaks maksimal sekali tetap berlaku. Pemeriksaan kompatibilitas kini membandingkan pohon kondisi boolean, JOIN dan ekspresi agregasi lengkap, termasuk operator serta aritmetika. Pemeriksaan tabel, role dan fan-out tetap berlaku.
- Angka laporan baru disimpan sebagai report_facts/last_facts versi 1: key, label baku, nilai numerik dan unit. Ringkasan laporan tidak membaca kembali teks rupiah KPI. Laporan historis tetap mempunyai pembaca kompatibilitas; pesan lama tidak ditulis ulang.
- Persentase dengan dasar nol tidak dipaksakan menjadi 0%/100% pada tren bulanan, perbandingan tahun, dan pertumbuhan member. Metadata periode laporan dibedakan dari bulan fokus untuk follow-up; laporan perbandingan tahun menyebut kedua tahun.
- Penjelasan angka laporan dirender dari fakta server. Teks provider yang mengarang angka atau sebab tidak dipakai. Judul dan metodologi SQL dinamis memakai teks server. Percakapan edukatif dengan angka contoh tetap diperbolehkan. AI online wajib menentukan jalur; tidak ada fallback offline baru.
- Memori terstruktur mempertahankan gaya jawaban eksplisit, indeks delapan laporan terakhir dan slot klarifikasi. Pengambilan riwayat memilih percakapan lama yang relevan ditambah maksimal sepuluh pesan terkini yang memenuhi syarat. Laporan lama dapat diambil berdasarkan periode atau rujukan laporan pertama. Maksimal tiga paket fakta dikirim ke provider; role dan persetujuan endpoint tetap diperiksa.
- Grafik dinamis memakai semua baris hasil query yang sudah dibatasi executor, termasuk NULL sebagai celah. Nilai awal NULL tidak menghilangkan grafik. Batas tersembunyi 15 titik dihapus.
- Tanggung jawab dipisahkan ke SqlPredicate, SqlBusinessPolicy, ReportFacts, ConversationMemory, DynamicReportPresenter dan MeasuredAiProvider. Orchestrator masih besar; pemisahan dilakukan bertahap.
- Telemetri menyimpan jumlah panggilan, durasi provider, kegagalan dan token bila provider mengirim usage. Token tidak tersedia dilaporkan NULL, bukan nol. Audit hanya mencatat angka/status, tanpa prompt, balasan, secret atau dataset.

## Evaluasi yang dapat diulang

```powershell
rtk php vendor/bin/phpunit --no-coverage
rtk node --test tests/frontend/ai-analyst.test.cjs tests/frontend/ai-interactions.test.cjs tests/frontend/theme.test.cjs tests/frontend/ui-model.test.cjs tests/frontend/security.test.cjs
rtk php spark ai:evaluate --live --repeat 2
```

Perintah evaluasi hanya berjalan dengan --live, memakai endpoint localhost:20128/v1 yang disetujui, POS live SELECT, dan riwayat QA terpisah di build/conversation-evaluation. Corpus berada di tests/fixtures/conversation-evaluation.json. Setiap langkah memeriksa jenis respons, kebutuhan query, referensi laporan, jumlah panggilan dan periode bila relevan. Total laporan September/Oktober direkonsiliasi dengan query kontrol terpisah.

Hasil live: build/conversation-evaluation/20261017-120225-1747e4/results.json. Dua putaran, 24/24 langkah lulus, 4/4 kontrol total cocok. Setiap langkah memakai satu panggilan AI; pertanyaan konteks/reformat memakai nol query POS. P95 waktu satu langkah 5734,41 ms, total 43407 token, cakupan usage 24/24. Angka ini sampel lokal pada provider yang dikonfigurasi, bukan jaminan beban produksi.

Verifikasi akhir sesudah perubahan pembanding nol dan periode laporan: PHPUnit 155 tes / 1323 assertions lulus; frontend 26 tes lulus. Corpus live diulang pada build/conversation-evaluation/20261017-121332-75a7e8/results.json: 12/12 langkah dan 2/2 kontrol total lulus. Total tiga putaran live: 36/36 langkah, 6/6 kontrol. Empat belas tes regresi baru mencakup kondisi OR/XOR/alias/subquery, periode, hubungan detail-header, perubahan makna saat repair, fakta desimal, penjelasan palsu, memori panjang, grafik lengkap/NULL, telemetri dan pembanding nol. Lint seluruh PHP yang diubah lulus.

## Batas jaminan

Validator bisnis membuktikan kondisi yang didukung, bukan seluruh makna bahasa alami atau semua rumus SQL arbitrer. Semantik kategori, identitas, definisi metrik khusus dan analisis penyebab tetap membutuhkan template teruji atau evaluasi tambahan. Query ekuivalen yang belum didukung dapat ditolak; tidak ada pelonggaran keamanan otomatis.

Memori ini berupa ringkasan terstruktur dan retrieval terbatas, bukan ingatan tanpa batas atau ringkasan semantik seluruh percakapan. Angka yang belum mempunyai fakta kanonik tidak dipaksakan menjadi ringkasan baru. Pemilihan referensi oleh model tetap diuji melalui corpus.

## Verifikasi browser sesudah login kembali

Percakapan QA `conv-8e9cc2d53fb60347`, berjudul **QA INTEGRITAS · uji browser 17 Oktober**, dipakai terpisah dari riwayat pengguna sebelumnya. Sembilan langkah lulus: sapaan → laporan September harian → pertanyaan periode → penjelasan angka → slogan umum → tombol grafik September → laporan Oktober harian → rujukan September lama → tombol tabel pada laporan September lama. Rename dan reload berhasil. Metadata tersimpan membuktikan hanya dua laporan baru yang menjalankan masing-masing satu query; tujuh langkah lainnya tidak menjalankan query POS. Referensi September tetap sama pada penjelasan, grafik dan tabel walaupun laporan Oktober telah dibuat. Bukti: `build/browser-integrity/metadata-checks.json`.

Grafik/perbesar dan pergantian tipe garis/batang berhasil dioperasikan. SVG September memuat 30 titik. Drawer riwayat ponsel dapat dibuka/ditutup. DOM diukur pada lebar aktual **359, 768 dan 1280 px**; lebar dokumen tidak melebihi viewport dan input berada di dalam layar. Lebar ponsel 359 px adalah hasil aktual override setelah pengaruh skala browser, bukan klaim pengukuran tepat 360 px. Pengaturan tema terang, gelap dan sistem diterapkan lewat UI; atribut tema pada HTML/topbar/sidebar/footer sesuai. Mode sistem dan ukuran browser dikembalikan sesudah pengujian.

Unduhan CSV melalui sesi browser berhasil: `C:/Users/imama/Downloads/laporan-penjualan-harian-september-2026.csv` (1181 byte), 30 baris tanggal September dan nol baris Oktober. Menu CSV/Excel/PDF menunjuk referensi September yang benar. Excel/PDF tidak diunduh ulang pada putaran ini.

Pengambilan screenshot tetap gagal melalui kedua API browser yang didokumentasikan, termasuk sesudah viewport direset. Karena itu, hasil di atas merupakan verifikasi interaksi, DOM, geometri layout, metadata dan file unduhan; pemeriksaan visual berbasis gambar tidak dinyatakan lulus. Konsol browser yang diperiksa tidak mencatat error/warning. Tidak ada perubahan kode aplikasi tambahan pada putaran verifikasi browser ini.
