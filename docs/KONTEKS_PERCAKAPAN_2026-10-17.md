# Konteks percakapan AI ASISTAN — 17 Oktober 2026

## Perilaku yang diperbaiki

- Pertanyaan lanjutan tanpa periode baru memakai periode percakapan aktif. Periode eksplisit terbaru mengalahkan periode lama.
- `bulan lalu` dan `bulan kemarin` mengacu ke clock bisnis Asia/Jakarta; `bulan sebelumnya` mengacu ke periode aktif. Pergantian Desember–Januari ditangani dengan tanggal kalender.
- Jawaban singkat seperti `Kalau bulan lalu?` melanjutkan permintaan terakhir. Untuk member yang telah diperjelas, identitas yang sudah dipilih tetap digunakan.
- Konteks mendukung bulan/tahun, hari ini/kemarin, minggu ini/lalu, tanggal lengkap dan rentang tanggal lengkap. Batas akhir query eksklusif; tanggal tidak valid meminta klarifikasi tanpa query.
- Klarifikasi disimpan dalam `pending_request`: permintaan, slot yang kurang, dan scope yang sudah diketahui. Nama/ID dan periode dapat dilengkapi dalam beberapa turn, termasuk setelah reload. Mengganti topik meninggalkan permintaan yang belum selesai.
- Permintaan yang telah diperjelas dinormalisasi memakai identitas terbaru, sehingga provider tidak kembali menanyakan nama awal yang ambigu. Pertanyaan baru dengan metrik/filter tambahan tetap diperiksa AI.
- Riwayat dinamis lama yang belum menyimpan bulan dapat memulihkan periode dari batas tanggal query terakhir yang berhasil dan tidak ambigu, tanpa mengubah pesan historis.
- Pembelian oleh member diarahkan sebagai belanja member pada transaksi penjualan toko. Pencocokan nama/ID menggunakan parameter SQL. Nama ganda atau nama yang belum ditemukan meminta identitas; hasil lookup gagal tidak diubah menjadi belanja Rp0.
- Laporan penjualan per hari memiliki template teruji dan tabel terbuka saat diminta. Mengganti bulan mempertahankan rincian harian. Ringkasan menyebut periode, omzet faktur, jumlah transaksi dan hari bertransaksi.
- Hasil kosong menyebut periode laporan. Belanja member yang benar-benar tidak memiliki transaksi dibedakan dari identitas yang belum jelas dan query yang gagal.
- Schema dan aturan bisnis memakai filter operasional `NORMAL` yang sama. Query dinamis yang tidak menyertakan predicate NORMAL yang didukung atau mencampur `LEGACY_HISTORY` dihentikan dengan `business_filter_mismatch`.
- AI online tetap wajib pada setiap analisis, klarifikasi dan reformat. Gangguan provider tidak membuat jawaban/perhitungan offline dan tidak menghapus konteks sukses atau klarifikasi yang sedang menunggu jawaban.

## Kontrak dan privasi

Route tetap sama. Respons klarifikasi memiliki `success: true`, `analysis_status: clarification`, dan metadata intent `clarification_needed`; ini bukan dataset analisis baru.

Provider menerima pertanyaan, riwayat permintaan terbatas, pertanyaan klarifikasi yang aman, serta scope terstruktur. Dataset, KPI, hasil laporan assistant, SQL log, secret dan saran member hasil lookup POS tidak dikirim kembali ke provider. Identitas dan angka bisnis hasil query tetap diproses lokal.

Pertanyaan standar tentang member/periode menggunakan kalimat klarifikasi Bahasa Indonesia yang konsisten. Saat menunggu klarifikasi, jawaban tidak menawarkan reformat tabel/grafik dan saran lanjutan disembunyikan supaya fokus tetap pada jawaban pengguna.

Konteks terikat UID percakapan dan pemilik. Chat baru tidak mewarisi identitas member dari chat lain. Pesan historis tidak ditulis ulang; perubahan berlaku untuk pemrosesan pesan baru.

## Validasi otomatis

- PHPUnit: **105 tes, 860 assertion**, seluruhnya lulus dengan database testing dan writable terisolasi.
- Di antaranya **19 tes konteks baru, 132 assertion**: alur harian tiga turn, periode kosong, belanja member dan akses Kasir, nama ganda, nama tidak ditemukan, jawaban singkat, klarifikasi bertahap, reload, pergantian topik, batas tahun, tanggal lengkap/rentang, tanggal tidak valid, perubahan periode saat klarifikasi, provider offline, privasi lookup member, filter query AI, dan kompatibilitas konteks lama.
- Frontend Node: **17 tes lulus**, termasuk fokus klarifikasi, reformat, respons terlambat, draft/retry, escaping, tema dan grafik.
- Lint **153 berkas PHP lulus**; berkas yang berubah terakhir dan script integrasi juga diperiksa ulang dan lulus.

## Validasi provider dan POS aktif

Script `build/verify-conversation-context.php` memakai provider aktif `http://localhost:20128/v1`, riwayat QA file terisolasi, dan akses POS SELECT saja. Bukti terstruktur berada di `build/conversation-context/live-checks.json`.

**Sepuluh pemeriksaan integrasi lulus**, termasuk daftar member melalui Text-to-SQL, member tidak ditemukan → jawaban ID → analisis, dan pemulihan scope dari salinan konteks percakapan asli `conv-90fea3971128920b`. Percakapan asli tidak ditulis dalam pengujian kompatibilitas tersebut.

Hasil yang telah dicocokkan:

| Skenario | Hasil |
| --- | --- |
| Penjualan harian Oktober 2026 | Omzet faktur Rp531.000; 42 transaksi; 6 hari bertransaksi |
| Belanja Pelanggan Uji X2 Kredit, follow-up tanpa bulan | Mengikuti Oktober 2026; Rp42.000 dari 11 transaksi; cocok dengan query kontrol |
| Penjualan bulan kemarin | September 2026; hasil kosong dinyatakan dengan periode yang jelas |
| Member tanpa periode pada chat baru | Klarifikasi tanpa query; jawaban `Oktober 2026` melanjutkan permintaan |
| Daftar member yang pernah bertransaksi Oktober 2026 | Text-to-SQL dinamis berhasil; 2 baris member, tidak salah diarahkan ke belanja satu member |
| Identitas `TidakAda`, lalu jawaban `X2-MEMBER-001` | Meminta klarifikasi, kemudian Rp42.000 dari 11 transaksi tanpa terikat nama lama |
| Konteks percakapan lama yang sebelumnya kehilangan bulan | Memulihkan September 2026 dari query terakhir dan menerapkannya pada pertanyaan belanja member |

Provider adalah gateway lokal yang dikonfigurasi. Validasi ini membuktikan provider AI aktif dapat dihubungi, bukan lokasi/upstream cloud di balik gateway.

## Browser

Memakai akun yang sudah login pada `http://sipv5ai.test`, tanpa mengganti kredensial. Percakapan QA terpisah: `conv-8d4e8c900557a52e`.

Alur yang diuji: laporan harian → member yang belum jelas → reload → jawaban nama lengkap → perubahan periode → jawaban ID → follow-up pendek dengan member yang sama → member tidak ditemukan → klarifikasi Bahasa Indonesia → jawaban ID.

Uji browser menemukan masalah tambahan selama pengerjaan: klarifikasi provider bercampur bahasa, rujukan member kembali ke nama awal yang belum lengkap, dan provider mengulang klarifikasi ID yang sudah dijawab. Semuanya diperbaiki dan diberi tes regresi. Pesan QA sebelum perbaikannya tetap berada di riwayat sebagai jejak pengujian.

Pengujian akhir dari chat baru (`conv-1ba6c713b58f6c20`, **QA KONTEKS · hasil akhir**) lulus: pertanyaan belanja member tanpa periode → klarifikasi → jawaban `Oktober 2026` → Rp42.000 / 11 transaksi → `Kalau bulan kemarin?` → September 2026 kosong → `Kalau bulan ini?` → kembali ke Oktober dan member yang sama. Judul, pesan, dan konteks tetap tersimpan setelah reload. Bukti: `build/conversation-context/browser-final.png`.

## Berkas utama

- `app/Services/AI/ConversationScope.php`: resolusi periode, rujukan permintaan, dan konteks provider yang aman.
- `app/Services/AI/AiOrchestrator.php`: routing member, slot klarifikasi, laporan harian, belanja member, dan validasi filter query dinamis.
- `app/Services/AI/ConversationService.php`: riwayat pemahaman percakapan tanpa hasil laporan bisnis.
- `app/Services/AI/PosSchemaService.php` dan `BusinessSemanticLayer.php`: konsistensi domain dan filter.
- `public/assets/js/ai-chat.js`: fokus saat klarifikasi.
- `tests/feature/AiConversationContextTest.php`: regresi konteks.
