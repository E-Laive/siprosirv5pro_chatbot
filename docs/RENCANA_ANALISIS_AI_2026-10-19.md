# Rencana analisis yang disusun AI

AI online kini menyusun rencana bisnis lengkap sebelum aplikasi menjalankan analisis. Perubahan ini mengatasi hilangnya filter nominal dan konteks pada percakapan member, tanpa menambah pola kata pada parser sebagai penentu eksekusi.

## Kontrak dan eksekusi

Pengarah percakapan mengembalikan `decision: analysis` dengan rencana versi 1: tujuan analisis, domain, permintaan yang sudah disambungkan, periode, entitas, metrik, dimensi, grain waktu, filter, peringkat dan preferensi penyajian. Kandidat parser lama hanya menjadi referensi bagi AI. Jawaban umum, konteks, klarifikasi dan reformat tetap berada pada panggilan pengarah yang sama.

`AiAnalysisPlan` memeriksa tipe, field yang diizinkan, tanggal, operator, kolom bisnis, metrik dan kompatibilitas template. Persetujuan `use_plan` atau `dynamic` tanpa rencana lengkap tidak dapat menjalankan analisis baru. Rencana yang tidak lengkap ditolak; aplikasi tidak beralih ke penafsiran offline. Nama keputusan lama masih dapat diterima jika membawa kontrak lengkap. Aksi reformat terstruktur tetap memakai laporan yang dipilih.

Rencana lengkap disimpan pada metadata laporan dan konteks JSON. Pertanyaan lanjutan dapat mengganti batas nominal sambil mempertahankan member, total belanja dan periode. Laporan lama tetap memiliki referensi serta rencananya sendiri. Percakapan umum, klarifikasi dan kegagalan tidak mengganti dataset sukses terakhir.

Representasi bulan dan rentang tanggal yang setara dinormalisasi. Oktober dengan 1 Oktober sampai 1 November eksklusif adalah periode yang sama; rentang yang bertentangan dengan bulan ditolak. Cakupan perbandingan bulan tetap dihitung server dari pasangan periode dan clock bisnis.

## Filter dan angka

Filter agregat total belanja atau frekuensi diterapkan melalui HAVING berparameter, setelah pengelompokan member. Filter per baris menggunakan WHERE. Operator dan kolom berasal dari kontrak yang diperiksa, sedangkan nilainya diikat sebagai parameter. Daftar serta peringkat member tetap mencakup member aktif tanpa pelanggan anonim, transaksi NORMAL, dan nominal faktur sebelum retur.

`SqlPlanContract` memeriksa filter pada setiap cabang kondisi, metrik, pengelompokan member, dan arah peringkat untuk SQL khusus. Filter agregat tidak dapat diganti menjadi batas per transaksi. Menghilangkan filter, membalik operator atau menggandakan metrik tidak menghasilkan laporan sukses. Representasi SQL yang belum dapat dibuktikan ditolak secara konservatif; kontrak ini tidak melonggarkan validator keamanan SQL.

Peringkat menyertakan nilai seri. Jika batas hasil membuat kelengkapan seri tidak dapat dibuktikan, aplikasi meminta penyempitan cakupan. Hasil kosong menjelaskan bahwa tidak ada data yang memenuhi filter, bukan menyatakan seluruh periode tidak memiliki transaksi. Hasil satu member menyebut nama dan nominalnya dalam ringkasan.

SQL yang sudah dihasilkan beserta alasan penolakan tetap tersedia pada diagnostik developer. Respons production tetap membersihkan jejak SQL. Provider online, kepemilikan percakapan, pemeriksaan role, transaksi NORMAL, perlindungan fan-out dan batas satu koreksi SQL tetap dipertahankan.

## Verifikasi

Suite `AiStructuredPlanTest` mencakup otoritas rencana AI atas kandidat parser, filter dan batas nominal, frekuensi, urutan binding, peringkat naik/turun, hasil kosong/nol, input rusak, hak akses, pelestarian konteks, kontrak SQL, dan normalisasi periode. `FakeOnlineProvider` memiliki adapter eksplisit untuk fixture lama; adapter tersebut hanya terdapat di direktori testing, tidak tersedia pada jalur produksi.

Harness `build/verify-ai-plan-live.php` menggunakan provider lokal yang telah disetujui dan penyimpanan QA file terpisah. Alur melebihi Rp17 juta, di atas Rp5 juta, dan melampaui lima juta rupiah mempertahankan Oktober 2026, masing-masing memakai satu panggilan AI, serta cocok dengan kontrol POS SELECT. Penjelasan periode dan reformat tidak menambah query POS. Hasil lengkap berada di `build/ai-plan-live-20261019-085135/checks.json`.

Percakapan browser QA terpisah bernama **QA RENCANA AI · filter nominal**, UID `conv-869a1a797bbca3e5`, menunjukkan satu member di atas Rp17 juta dan sembilan member di atas Rp5 juta. Reload mempertahankan hasil dan follow-up dengan ungkapan berbeda menghasilkan cakupan yang sama. Percakapan asli `conv-ceeae239f3081ca7` tidak diubah. Angka pengujian merupakan snapshot tanggal 19 Oktober 2026.

Uji percakapan campuran tersedia melalui `build/verify-natural-conversation.php`. Sembilan langkah lulus melalui provider nyata: sapaan, laporan September, periode, arti angka, terima kasih, percakapan umum, grafik, laporan Agustus baru, dan tabel dari referensi September lama. Pemeriksaan pergantian bulan mewajibkan query baru, bulan Agustus, ringkasan yang sesuai, dan referensi baru; status sukses saja tidak cukup. Instruksi pengarah tidak lagi mewajibkan reformat hanya karena kandidat parser membawa intent reformat lama.

Regresi akhir lulus **188 tes PHPUnit / 1.626 assertion** dengan database testing dan writable terisolasi, serta **30 tes frontend Node**. Harness filter nominal lulus **24 pemeriksaan live**. Satu pengulangan percakapan campuran sempat mengalami `provider_unavailable` pada sapaan pertama; aplikasi tidak menjalankan query atau memberi jawaban offline. Delapan langkah sesudahnya lulus, dan pengulangan lengkap berikutnya lulus sembilan langkah. Bukti kegagalan disimpan di `build/natural-conversation/live-checks-20261019-provider-start-unavailable.json`; bukti terbaru di `build/natural-conversation/live-checks.json` dan salinan bertanggal.

Setelah pengguna login kembali, pengulangan browser sesudah perubahan terakhir lulus pada percakapan QA yang sama: laporan September Rp462.244.000 / 30 hari, grafik September, laporan Agustus baru Rp503.560.500 / 31 hari dengan referensi berbeda, tabel dari tombol laporan September lama, reload, dan pertanyaan periode. Jawaban konteks terakhir menyebut September 2026 serta waktu pengambilan 19 Oktober 2026 pukul 16:39:33 Asia/Jakarta tanpa tombol tabel/grafik. Enam pemeriksaan tercatat di `build/ai-plan-browser-checks-20261019.json`, dengan bukti layar di `build/ai-plan-browser-context-20261019.jpg`. Percakapan asli tetap tidak diubah.

Lebar 360 px belum dapat dikonfirmasi karena viewport aktual alat berbeda dari ukuran yang diminta; pemeriksaan browser terakhir menggunakan viewport aktual 1552 × 1319. Tidak ada penulisan transaksi POS atau pengoperasian layanan database/web dalam pekerjaan ini.

AI masih dapat salah menafsirkan bahasa. Pemeriksaan backend membuktikan syarat yang tercatat pada rencana; pemeriksaan itu bukan pembuktian sempurna bahwa setiap maksud tersirat manusia telah ditangkap. Informasi bisnis yang ambigu tetap harus diklarifikasi.

## Perbaikan retur produk dan kas

Audit percakapan `conv-2bad0d17bac169c7` menemukan pertanyaan retur dijawab dengan 45 penjualan biasa; follow-up lalu mengulang angka penjualan. Percakapan `conv-110e33fa5fea8b6e` menunjukkan penolakan rencana untuk retur, jumlah unit produk, dan permintaan penjualan baru. Perbaikan berikut diterapkan pada 19 Oktober 2026. Isi kedua percakapan asli tetap disimpan tanpa perubahan.

AI tetap memilih rencana lebih dahulu. Kontrak menambahkan entitas dan metrik retur, unit produk, transaksi kas manual, serta ledger bank. Guard hanya menolak kehilangan objek bisnis yang jelas; guard tidak membuat analisis offline. Permintaan ketersediaan retur tidak boleh memakai `sales_presence` atau penjelasan jumlah penjualan lama. Permintaan arus kas juga tidak boleh diganti dengan posisi saldo saat ini.

- **Retur:** `sales_returns_presence` memeriksa keberadaan dengan jawaban teks. `sales_returns` menyediakan rincian. Keduanya memakai tanggal retur, status FINAL/SELESAI/SUKSES, tidak dihapus, dan penjualan asal NORMAL. Pada pemeriksaan live 19 Oktober 2026 tidak ditemukan retur sah hari itu; hasil ini cocok dengan kontrol POS. Fixture berisi membuktikan kasus tersedia, status draft/hapus, penjualan historis, dan batas tanggal.
- **Produk:** peringkat mendukung unit dasar (`jumlah * isi_satuan`), nominal item, dan frekuensi nota. Metrik menentukan pengurutan; batas N menyertakan nilai seri. Satu SKU tetap satu kelompok walaupun nama/kategori historis berubah. Label representatif diambil dari snapshot teks historis; label itu tidak diklaim sebagai nama master terbaru.
- **Kas:** `cash_transactions` memakai dokumen manual FINAL yang tidak dihapus, dengan arah MASUK/KELUAR/BOTH. `cash_flow` memakai ledger kas/bank yang sudah diposting, mengecualikan peran TRANSFER_IN/TRANSFER_OUT. Laporan satu arah hanya menyampaikan angka arah tersebut, bukan mengklaim arah lain bernilai nol. Cakupan manual tidak mencakup penerimaan penjualan atau pembayaran pembelian otomatis. Arus ledger bukan laba atau saldo akhir. Saldo historis belum didukung template saldo saat ini.

Compiler baru dan SQL dinamis tetap melewati validator SQL serta pemeriksaan sumber, periode, metrik dan kondisi wajib. Filter tambahan atau dimensi di luar kemampuan compiler tidak boleh diam-diam diabaikan; jalur SQL khusus tetap harus membuktikannya. Representasi tanggal eksplisit dengan tahun null dapat dinormalisasi dari tanggal awal yang valid, sedangkan tahun yang bertentangan ditolak. Penyajian `text` didukung untuk pemeriksaan keberadaan. Kondisi tetap compiler tidak perlu dikarang ulang sebagai kolom filter oleh AI.

`OperationalReports` menghormati batas baris yang dikonfigurasi, maksimum 500. Hasil rincian yang melampaui batas atau nilai seri yang belum terbukti lengkap ditolak tanpa menyimpan dataset terpotong. Jumlah dan nominal rincian retur/kas diperiksa terhadap agregat; perubahan data di antara pengambilan menghasilkan `source_changed`, mempertahankan laporan sukses sebelumnya. Query yang sudah dijalankan tetap tercatat pada diagnostik kegagalan.

Diagnostik developer menyimpan path field, aturan penolakan, dan bentuk rencana yang dibatasi. Teks permintaan, nilai filter dan kredensial tidak disalin ke bentuk diagnostik. Panel tertutup memakai komponen detail yang sudah ada dan `textContent`; API production membersihkan diagnostik rencana maupun SQL. Tidak ada tambahan dependensi, perubahan tema, atau pelonggaran keamanan SQL.

Regresi akhir lulus **208 tes PHPUnit / 1.744 assertion**, termasuk 20 tes operasional baru, serta **32 tes frontend**. Cakupan baru meliputi sumber retur versus penjualan, kelanjutan klarifikasi jumlah unit, topik baru setelah kegagalan, kas satu arah, transfer/draft/hapus, perubahan data, batas baris, ekspor referensi lama, kepemilikan dan perubahan role. Penolakan cakupan dan batas baris mempertahankan draft yang bisa diedit tanpa menawarkan pengulangan identik; kegagalan sementara tetap dapat dicoba kembali.

Harness `build/verify-operational-live.php` menjalankan ulang 12 langkah melalui provider lokal yang disetujui dan mencocokkan angka dengan kontrol POS SELECT-only. Pengulangan akhir lulus 44 pemeriksaan di `build/operational-live-20261019-121041/checks.json`, termasuk retur September yang berisi satu dokumen Rp24.000. Bukti awal 11 langkah yang lulus tetap ada di `build/operational-live-20261019-115432/checks.json` (40 pemeriksaan); setiap pengulangan memiliki folder bukti sendiri. Riwayat CLI QA memakai penyimpanan file terisolasi, tanpa menulis transaksi POS atau mengoperasikan layanan database/web.

Browser desktop lulus sembilan pemeriksaan: retur hanya teks, follow-up retur, klarifikasi metrik, jawaban singkat jumlah, penjualan setelah pergantian topik, grafik produk lama dengan referensi tepat, pengeluaran kas sesuai cakupan, reload, dan rincian retur September yang berisi satu dokumen Rp24.000. Percakapan QA `conv-4a574037723134ff` bernama **QA OPERASIONAL · retur unit kas**. Bukti ada di `build/operational-browser-checks-20261019.json`, `build/operational-browser-units-20261019.jpg`, `build/operational-browser-cash-compact-20261019.jpg`, dan `build/operational-browser-return-filled-20261019.jpg`. Viewport aktual 1552 × 1319; pengujian ponsel 360 px tetap belum terverifikasi. Diagnostik penolakan baru dan penyembunyian pada produksi diperiksa otomatis, bukan melalui pemaksaan kegagalan provider di browser live.

## Pengaturan reasoning 9Router

Administrator dapat memilih Otomatis, Nonaktif (jika didukung), Rendah, Sedang, atau Tinggi di modal Pengaturan Provider. Otomatis tidak mengirim parameter reasoning, sehingga perilaku 9Router yang sudah ada tetap berlaku. Pilihan eksplisit diperiksa lewat metadata `/v1/models` untuk model yang persis sama; nama alias tidak dipakai untuk menebak kemampuan. Dukungan yang tidak dinyatakan tidak dianggap tersedia. Nonaktif juga membutuhkan pernyataan bahwa model dapat menonaktifkan thinking.

Konfigurasi reasoning disimpan atomik pada file server yang sama dengan konfigurasi aktif. Metadata database tetap tidak menjadi sumber konfigurasi. Cache kemampuan berlaku sepuluh menit dan terikat endpoint, model, serta kredensial; perubahan konfigurasi memerlukan pemeriksaan baru. Tidak ada migration atau perubahan hak akses. Pemeriksaan role Administrator dan CSRF tetap berlaku.

Pilihan eksplisit mengirim `reasoning_effort` melalui Chat Completions dan menghilangkan parameter sampling temperature untuk menghindari penolakan model reasoning. Permintaan tetap sinkron dengan `stream:false`. Teks reasoning terpisah dan blok thinking di awal respons tidak digunakan sebagai JSON rencana, jawaban akhir, riwayat, atau log. Hanya statistik numerik yang boleh dicatat. Token reasoning yang tidak dilaporkan provider bernilai null, bukan nol.

Respons hanya reasoning, jawaban kosong, dan jawaban terpotong menghasilkan `provider_output_incomplete`; kemampuan tidak tersedia atau tidak mendukung level menghasilkan `provider_capabilities_unavailable` atau `provider_reasoning_unsupported`. Tidak ada pengulangan otomatis tanpa batas atau fallback offline. Kegagalan yang memerlukan perubahan konfigurasi mempertahankan draft tanpa menawarkan pengulangan identik. Modal tetap terbuka pada kegagalan simpan dan mencegah pengiriman ganda.

Integrasi nyata `build/verify-reasoning-live.php` lulus enam pemeriksaan pada `build/reasoning-live-20261019-132036/checks.json`: aplikasi mengirim medium ke 9Router, mendapat HTTP 200, dan jawaban perhitungan sesuai kontrol. Alias `reques-normal` mengembalikan model `gpt-5.6-luna` pada snapshot ini. Ini membuktikan penerimaan parameter oleh 9Router; payload yang diteruskan ke upstream tidak diinspeksi, dan jumlah token reasoning tidak dilaporkan. Jangan menganggapnya sebagai bukti jumlah token atau level internal model.

`build/verify-reasoning-workflow-live.php --activate` lulus dua belas pemeriksaan pada `build/reasoning-workflow-live-20261019-132422/checks.json`: percakapan umum, pencarian member yang cocok dengan kontrol SELECT, konteks tanpa query POS, dan laporan penjualan baru. Riwayat pengujian disimpan terpisah. Setelah alur lulus, konfigurasi aktif aplikasi diatur medium, maksimum 8192 token, timeout 90 detik. Verifikasi memakai konfigurasi aktif default lulus empat pemeriksaan di `build/reasoning-active-20261019.json`.

Sebelas tes reasoning mencakup persistensi, cache, pergantian model, peran/CSRF, respons terpotong, privasi thinking dan kredensial, metrik yang tidak tersedia, serta render kontrol khusus Administrator. Tiga tes frontend tambahan memeriksa draft, pengiriman pengaturan sekali saja, serta pemulihan dari penolakan/kegagalan koneksi. Lint PHP dan JavaScript lulus. Browser lokal pada sesi penyelesaian dilaporkan nonaktif; tampilan desktop/ponsel, tema, dan interaksi modal langsung belum diverifikasi untuk perubahan reasoning ini.

Regresi akhir: **230 tes PHPUnit / 1905 assertion** dan **35 tes frontend** lulus. Pengujian backend menggunakan SQLite testing dan direktori writable terisolasi. Tidak ada perubahan transaksi POS atau pengoperasian layanan database/web.

## Rincian transaksi dan konteks member

Audit `conv-71dcd058cda3b401` menemukan rencana AI sudah meminta `time_grain:day`, tetapi fungsi lama `member_spend` tetap menjalankan satu agregat seluruh periode dan menandainya sukses. Permintaan daftar transaksi juga dijawab ringkasan. Dua follow-up terakhir tidak membawa `member_query`; validator menolak, tetapi path diagnostiknya masih menunjuk ranking. Riwayat asli tidak ditulis ulang.

`MemberSalesReport` menggantikan fungsi lama: `member_spend` mendukung total serta agregat per hari/bulan/tahun, sedangkan `member_transactions` menghasilkan baris nota dengan ID, nomor faktur, waktu transaksi dan nominal. Bentuk dimensi, grain, metrik dan visualisasi diperiksa sebelum eksekusi. Kondisi atau filter yang tidak didukung compiler tetap melalui SQL dinamis tervalidasi. Tidak ada penambahan kata kunci untuk menentukan hasil bisnis secara offline.

Identitas member dipulihkan di backend melalui nama/ID yang diberikan pengguna atau `member_ref` opaque dari laporan milik akun. Pencarian master yang tunggal dapat menjadi referensi; pencarian baru yang kosong atau ambigu tidak mempertahankan pilihan lama. Referensi member yang sama tetap stabil. Snapshot lama dibaca tanpa migration, dan laporan terdahulu yang masih diizinkan dapat dipilih dengan referensinya sendiri. Role dan kepemilikan tetap diperiksa sebelum konteks digunakan.

Petunjuk identitas/klarifikasi lama dari kandidat parser dihilangkan ketika konteks memiliki referensi member yang sudah dipilih. AI tetap menentukan makna pesan terbaru. Tahun baru tidak mewarisi bulan lama secara diam-diam. Klarifikasi yang dibutuhkan akibat perubahan kecocokan member mempertahankan dataset serta rencana sukses sebelumnya.

Query rincian memakai parameter dan transaksi NORMAL. Jumlah/nominal rincian dicocokkan dengan query kontrol sebelum hasil dianggap sukses. ID nota harus unik, baris harian menggunakan tanggal lengkap, dan jumlah rincian tidak boleh terpotong oleh batas baris. Data kosong hanya menampilkan teks. Nilai negatif dan nol yang benar-benar ada tetap dipertahankan; hari tanpa transaksi tidak diberi nilai buatan. Grafik harian menyimpan seluruh titik hasil, dan reformat/ekspor memakai snapshot laporan yang dipilih tanpa query POS ulang.

Untuk SQL dinamis pada member terpilih, AI memakai literal `__selected_member__`; backend menggantinya secara struktural dengan identitas lokal lalu memvalidasi ulang. Setiap cabang kondisi penjualan wajib membatasi member yang benar. Daftar nota tidak dapat diganti oleh SUM/GROUP BY; grain bulan/tahun juga diperiksa. Perbaikan SQL tetap maksimal sekali. Prompt perbaikan memakai SQL dari AI yang masih berisi placeholder, sehingga ID hasil POS tidak dikirim ke provider. Query identitas yang sudah dijalankan tetap tercatat ketika perencanaan berikutnya gagal.

Enam belas tes member baru menguji daftar versus agregat, referensi setelah lookup/reload, konteks dua laporan, klarifikasi, role/kepemilikan, ekspor, provider/query gagal, batas hasil, sumber berubah, SQL perbaikan, privasi prompt, Desember–Januari, Februari kabisat, frekuensi transaksi serta nilai negatif/nol. Fixture provider lama diperbarui menjadi rencana lengkap hanya dalam kode testing; produksi tetap mensyaratkan keputusan AI online. Lint PHP/JavaScript dan 35 tes frontend lulus.

Harness `build/verify-member-reports-live.php` menggunakan provider aktif dengan reasoning medium, penyimpanan QA terpisah, dan kontrol POS SELECT-only. Tujuh permintaan percakapan, reformat laporan lama, reload, serta pemulihan konteks riwayat lama lulus **38 pemeriksaan** di `build/member-reports-live-20261019-134603/checks.json`. Snapshot kontrol Oktober 2026: **10 transaksi, Rp6.073.000, tujuh tanggal transaksi**. Seluruh tahun kalender 2026: **111 transaksi, Rp105.248.000**. Bukti awal kegagalan klarifikasi tahunan tetap tersimpan pada folder `build/member-reports-live-20261019-134355`.

Replay yang lulus disalin ke percakapan aplikasi baru **QA · Transaksi member per hari**, UID `conv-508a96e19544f911`, berisi 16 pesan dan tujuh baris harian. Percakapan asli tetap disimpan. Browser lokal pada sesi implementasi dilaporkan nonaktif, sehingga render grafik/tema desktop dan ponsel secara langsung belum diverifikasi. Tidak ada penulisan transaksi POS atau pengoperasian layanan database/web.

Regresi akhir tahap member lulus **246 tes PHPUnit / 2027 assertion** dengan SQLite testing dan writable terisolasi, serta **35 tes frontend**.
