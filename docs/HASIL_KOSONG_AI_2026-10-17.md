# Hasil analisis kosong hanya menampilkan penjelasan

Hasil query sukses yang tidak menemukan transaksi/data ditandai `empty_result` dan `last_empty_result`. Respons hanya memuat penjelasan serta peringatan sumber data jika diperlukan; tabel, grafik, KPI, metodologi, jejak SQL, tombol reformat, ekspor dan saran analisis tidak ditampilkan.

Contoh baru: **Untuk 17 Oktober 2026, tidak ditemukan transaksi penjualan operasional.** Tidak ada kalimat yang menjanjikan rincian tabel harian.

- Berlaku pada respons baru dan saat membaca riwayat bertanda kosong, tanpa menulis ulang pesan lama.
- Permintaan tabel/grafik ulang pada laporan kosong kembali menjadi penjelasan, tanpa query POS baru, termasuk referensi laporan kosong lama setelah laporan lain dibuat.
- Kalender internal perbandingan tetap disimpan untuk menjaga logika periode; kalender kosong tidak menjadi tabel/grafik yang ditampilkan dan tidak dapat diekspor.
- Ekspor kosong ditolak dengan 422 / `empty_result`.
- Transaksi yang benar-benar ada tetapi nominalnya nol tetap merupakan data valid. Filter kosong tidak disimpulkan hanya dari jumlah uang nol.
- Agregasi ringkasan penjualan yang mengembalikan satu baris SQL dengan jumlah transaksi dan retur sama-sama nol juga ditandai kosong. Kegagalan query tetap merupakan error, bukan hasil kosong.

Verifikasi: seluruh suite PHPUnit lulus 159 tes / 1360 assertions sebelum tambahan pemeriksaan referensi laporan kosong lama; frontend lulus 27 tes. Regresi khusus mencakup hasil harian kosong, reload, reformat, ekspor, kalender perbandingan kosong, agregasi tanpa dokumen dan transaksi nyata bernominal nol. Lint PHP dan JavaScript yang diubah lulus.

Sesudah perubahan terakhir, subset konteks/perbandingan/percakapan dijalankan kembali: 47 tes / 428 assertions lulus, termasuk referensi laporan kosong lama. Tidak ada perubahan kode setelah pemeriksaan tersebut.

Provider lokal dan POS live telah diverifikasi dengan contoh 17 Oktober: tujuh pemeriksaan lulus, termasuk respons hanya teks, aksi nonaktif, reformat tanpa query dan pembacaan riwayat. Bukti: `build/empty-report-live-20261017-060328/checks.json`.

Pemeriksaan browser ulang belum berjalan: tab sebelumnya sudah tertutup, pembukaan tab latar tidak berhasil attach dan daftar tab tetap kosong. Verifikasi live di atas melalui backend, bukan klaim lulus browser.
