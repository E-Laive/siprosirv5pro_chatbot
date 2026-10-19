# Perbandingan penjualan bulanan per hari — 17 Oktober 2026

## Perilaku yang diperbaiki

- Permintaan dua bulan dengan `per hari` menggunakan intent `month_comparison_daily`. Pengelompokan benar-benar menentukan eksekusi, bukan hanya petunjuk untuk AI.
- Pasangan netral diurutkan kronologis: bulan lebih awal sebagai dasar, bulan berikutnya sebagai pembanding. `X terhadap/dibanding Y` memakai X sebagai pembanding dan Y sebagai dasar. Tahun masing-masing bulan dipertahankan.
- Scope JSON `comparison` menyimpan baseline/target, metrik omzet faktur, penyelarasan tanggal, batas awal/akhir eksklusif, cakupan, cutoff dan status hari berjalan. Field tahun/bulan lama tetap tersedia untuk kompatibilitas. Tidak ada migration.
- Follow-up `pisahkan datanya perhari` mempertahankan pasangan periode dan metrik. Penggantian pasangan bulan mempertahankan rincian harian sampai pengguna meminta total saja.
- Scope lama yang terbalik dipulihkan dari permintaan perbandingan terakhir yang jelas. Riwayat asli tidak ditulis ulang. Scope yang belum jelas meminta dua bulan dan tahunnya.
- Periode yang belum berlangsung tidak diisi angka nol. Pasangan ambigu, periode sama, dan metrik/filter di luar template meminta klarifikasi. Jawaban lengkap atas klarifikasi periode menggantikan nama bulan lama; persetujuan memakai omzet faktur menyelesaikan klarifikasi metrik.
- Provider online tetap wajib pada analisis, klarifikasi dan reformat. Penolakan rencana harian oleh provider tidak dialihkan diam-diam menjadi SQL kalender buatan AI.

## Perhitungan dan tampilan

Satu SELECT berparameter menjumlahkan `grand_total` dan menghitung transaksi per `DATE(tanggal)` pada dua rentang bulan, hanya `COALESCE(source_type, 'NORMAL') = 'NORMAL'`. Kalender disusun di PHP. Validator, pembatas SQL, dan hak baca POS tetap berlaku; tidak ada UNION atau CTE rekursif untuk membuat tanggal.

Untuk bulan selesai, seluruh tanggal masing-masing bulan dipakai. Tanggal 31 September menjadi null, ditampilkan sebagai `—` dan celah grafik; tanggal valid tanpa transaksi menjadi nol hanya setelah query berhasil. Selisih adalah pembanding dikurangi dasar. Persentase dengan dasar nol tidak dihitung.

Untuk bulan berjalan, kedua rentang dibatasi tanggal setara yang valid pada kedua bulan. Peringatan data hari berjalan tetap terlihat, termasuk saat reformat. Jika jumlah hari bulan selesai berbeda, catatan menjelaskan bahwa total memakai seluruh tanggal masing-masing bulan.

Jawaban memiliki tiga KPI, tabel harian langsung terbuka saat diminta, dan dua seri grafik ringkas dengan seluruh titik kalender. Tabel/chart tetap memakai implementasi yang ada. Reformat memakai dataset tersimpan tanpa SELECT ulang. Nilai null tidak berubah menjadi Rp0 pada tabel/PDF atau menjadi angka nol pada Excel/CSV. Catatan cakupan ikut ekspor; persentase pecahan tidak dibulatkan menjadi bilangan bulat pada PDF.

## Kegagalan dan kompatibilitas

Kode baru `sql_validation_failed` membedakan penolakan SQL; `pos_unavailable` menunjukkan kegagalan penyiapan koneksi; `query_failed` menunjukkan kegagalan eksekusi. Metadata query menambahkan `failure_stage`. `provider_plan_mismatch` menghentikan perbandingan ketika provider tidak menyetujui template. Kegagalan tidak mengganti dataset/konteks sukses sebelumnya. Route dan bentuk blok respons tetap digunakan.

## Hasil verifikasi

- PHPUnit: **116 tes, 1.023 assertion**, lulus; database testing dan writable terisolasi.
- Termasuk **11 tes perbandingan baru, 163 assertion**: replay tiga pesan, arah/tahun, pemulihan scope lama, reload, bulan berjalan, reformat tanpa query, kabisat, bulan kosong, klarifikasi, filter lokasi tidak diabaikan, provider gagal, query gagal, validasi SQL dan ekspor null.
- Frontend Node: **22 tes lulus**, termasuk tabel kosong vs nol, dua seri berisi 31 tanggal, celah grafik, detail tertutup/terbuka, grafik ringkas/perbesar, respons terlambat dan tema.
- Lint sembilan berkas PHP yang berubah/ditambahkan dan script integrasi: lulus.
- **15 pemeriksaan integrasi live lulus** memakai provider lokal yang dikonfigurasi dan SELECT POS. Riwayat QA disimpan terpisah di `build/daily-comparison/storage`; percakapan pengguna `conv-01dea4bd0cd1fd39` hanya dibaca.
- Bukti integrasi: `build/daily-comparison/live-checks.json`; script: `build/verify-daily-comparison.php`.

| Periode live | Transaksi NORMAL | Omzet faktur |
| --- | ---: | ---: |
| Agustus 2026 | 454 | Rp503.560.500 |
| September 2026 | 456 | Rp462.244.000 |

Jumlah nominal harian cocok dengan dua query kontrol total bulanan yang terpisah. September terhadap Agustus **turun Rp41.316.500 atau 8,20%**. Ketiga pesan, reformat tanpa query, salinan scope lama, kegagalan koneksi yang disengaja, dan klarifikasi periode/metrik lulus dengan provider aktif.

## Batas verifikasi

Uji browser visual desktop/ponsel belum dapat dijalankan pada sesi ini: alat mengembalikan `Local browser use is disabled in Work settings`. Tes frontend otomatis tidak dianggap sebagai pengganti pemeriksaan visual browser. Status: **regresi otomatis dan integrasi provider/POS lulus; pemeriksaan browser visual belum terverifikasi**.

Tidak ada penulisan transaksi POS, perubahan hak akses, atau layanan database/web yang dinyalakan/dihentikan dalam pekerjaan ini.
