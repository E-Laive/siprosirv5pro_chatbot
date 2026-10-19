# Percakapan alami dan konteks laporan — 17 Oktober 2026

## Perilaku

Satu panggilan pengarah AI sekarang dapat memilih dan langsung menjawab percakapan umum atau penjelasan laporan. Jalur analisis memakai kandidat parser yang tersedia; parser tidak lagi memaksa sapaan dan diskusi umum menjadi permintaan data.

Pilihan pengarah: `general`, `context`, `use_plan`, `dynamic`, `reformat`, dan `clarify`. Jalur umum/konteks tidak mencapai executor SQL. AI online tetap wajib; percakapan umum dapat berhasil ketika POS tidak tersedia atau role tidak memiliki domain data.

- “Halo”, ucapan terima kasih, bantuan menulis dan pengetahuan umum menghasilkan teks.
- “Itu tadi data kapan?” memakai periode transaksi laporan tersimpan dan membedakannya dari waktu pengambilan. Untuk pertanyaan periode/sumber, fakta dirender dari metadata server sehingga teks AI yang keliru tidak menjadi sumber tanggal.
- Penjelasan angka memakai ringkasan agregat yang disetujui. AI tidak boleh mengarang angka atau menganggap dugaan penyebab sebagai hasil pemeriksaan transaksi.
- Pertanyaan tentang penyebab yang membutuhkan data baru tetap menggunakan analisis yang tersedia.
- Sapaan/diskusi tidak mengganti dataset, periode atau permintaan analisis terakhir. Klarifikasi bisnis tetap tersedia setelah sapaan/reload; perintah batal mengakhirinya.
- Rujukan laporan yang belum jelas memiliki keadaan klarifikasi tersendiri. Laporan kosong, data simulasi, cakupan bulan berjalan, dan kegagalan terbaru dibedakan dari hasil sukses baru.
- Tidak ada pencarian internet, streaming atau tindakan eksternal baru. AI diarahkan menyatakan keterbatasan informasi terkini yang tidak dapat diverifikasi.

## Referensi laporan dan tampilan

Laporan baru mendapat `report_ref`, `report_scope` dan `report_request` di metadata pesan. Referensi baru tidak membutuhkan migration. Laporan lama dibaca melalui metadata/blok yang tersedia dan referensi legacy, tanpa menulis ulang pesan historis.

Respons menambahkan `response_kind` dan kemampuan tindakan `actions.table/chart/export`. Percakapan umum dan penjelasan konteks menampilkan teks dengan tindakan salin; tombol tabel/grafik, toolbar ekspor dan saran analisis tidak muncul otomatis. Laporan yang mempunyai dataset/visualisasi tetap menyediakan tindakan yang sesuai.

Endpoint pesan menerima `report_ref` opsional. Ekspor memakai route yang sama dengan query parameter `report_ref` opsional. Referensi selalu dicari dalam percakapan milik akun dan diperiksa menurut role saat digunakan. Pilihan referensi dan format dari tombol pengguna mengalahkan keputusan AI yang bertentangan. Tombol pada laporan lama memakai dataset asalnya, termasuk setelah laporan baru dibuat.

Percakapan dan keadaan analisis dipisahkan. Menjelaskan laporan lama tidak menimpa dataset sukses terakhir; referensi percakapan menentukan laporan untuk reformat/follow-up berikutnya. Menampilkan ulang laporan juga tidak mengonsumsi jawaban klarifikasi bisnis yang sedang tertunda.

## Privasi dan keamanan

Pengarah menerima maksimal tiga laporan terbaru yang boleh diakses role saat ini, masing-masing maksimal tiga KPI/angka agregat utama dan metadata periode/sumber. Angka agregat hanya diaktifkan untuk endpoint yang disetujui: `http://localhost:20128/v1` (trailing slash dinormalisasi). Endpoint lain menerima metadata tanpa metrik agregat.

Baris dataset, identitas member hasil POS, judul/label bebas hasil POS, secret dan SQL log tidak masuk ke ringkasan laporan provider. Label KPI disanitasi menjadi label metrik; nilai angka menggunakan pembacaan format Indonesia. Persentase perbandingan yang tersedia dihitung backend.

Riwayat umum dari kedua pihak disertakan secara terbatas. Laporan assistant mentah dan jawaban penjelasan laporan tidak diputar ulang sebagai riwayat. Jawaban umum yang pernah menerima konteks agregat diberi penanda; riwayat tersebut disaring ketika endpoint provider atau role berubah. Halaman awal, endpoint baca percakapan, prompt, reformat dan ekspor memeriksa kembali akses laporan. Data laporan lama yang tidak sesuai role disamarkan sebelum dikirim ke browser.

Diskusi edukatif SQL dapat dijawab sebagai teks; operasi POS terlarang tetap diblokir. Validator read-only, filter NORMAL, fan-out protection, kepemilikan percakapan, CSRF, rate limiter dan batas satu perbaikan sintaks tetap digunakan.

## Hasil verifikasi

- PHPUnit: **141 tes, 1.241 assertion**, lulus dengan database testing dan writable terisolasi.
- Termasuk **13 tes percakapan baru, 93 assertion**: alur campuran, nol query untuk teks/konteks, satu routing call, periode vs waktu pengambilan, dua laporan, ekspor referensi lama, konflik referensi AI/tombol, role berubah, endpoint agregat berubah, riwayat aman, hasil kosong, klarifikasi/reload dan edukasi SQL.
- Frontend Node: **26 tes lulus**, termasuk tindakan percakapan yang ringkas, pengiriman referensi laporan lama, toolbar ekspor/saran tersembunyi saat percakapan, retry, draft, escaping dan regresi grafik/tema.
- Lint delapan berkas PHP yang berubah/ditambahkan dan pemeriksaan sintaks JavaScript: lulus.
- **Sembilan pemeriksaan integrasi provider/POS live lulus**: halo → laporan September → periode → penjelasan angka → terima kasih → promosi toko → grafik → laporan Agustus → tabel laporan September melalui referensi lama.
- Seluruh sembilan langkah tersebut memakai satu panggilan provider per pesan. Hanya dua langkah laporan menjalankan query POS; pertanyaan konteks dan reformat memakai data tersimpan.
- Contoh live: laporan September menyebut omzet Rp462.244.000, periode September 2026, serta waktu pengambilan 17 Oktober 2026. Pertanyaan periode dan penjelasan hasil tidak menjalankan query baru.

Bukti: `build/natural-conversation/live-checks.json`. Harness: `build/verify-natural-conversation.php`. Riwayat QA berada di folder file terpisah `build/natural-conversation/storage`; riwayat pengguna tidak ditulis dalam pengujian integrasi.

## Batas pemeriksaan

Uji browser desktop/ponsel belum dapat dijalankan: alat mengembalikan `Local browser use is disabled in Work settings`. Tes DOM/frontend tidak dianggap sebagai pengganti pemeriksaan visual browser. Tidak ada layanan database/web yang dinyalakan/dihentikan dan tidak ada penulisan transaksi POS.
