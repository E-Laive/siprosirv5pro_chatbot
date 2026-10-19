# Grafik ringkas dan fleksibel — 17 Oktober 2026

## Perubahan

Grafik pada pesan memakai area gambar 260 px di desktop/tablet dan 200 px di ponsel. SVG menyesuaikan ukuran area melalui ResizeObserver; tinggi tidak lagi mengikuti pembesaran lebar kartu dan lebar minimum 500 px dihapus.

- Label bulan dipadatkan (`2026-01` menjadi `Jan`). Grafik lintas tahun tetap menunjukkan tahun singkat. Jumlah label sumbu menyesuaikan ruang; titik/baris data tidak dikurangi.
- Tombol **Perbesar** membuka modal Bootstrap dengan dataset yang sama dan tipe grafik yang sedang dipilih, tanpa request analisis atau query baru.
- Desktop menyediakan pilihan **Layar penuh**; ponsel otomatis memakai modal layar penuh. Backdrop tetap statis dan tersedia tombol Tutup/Escape, fokus kembali ke tombol pembuka.
- Nilai lengkap tampil ketika titik/batang diarahkan atau disentuh. Area grafik mendukung tombol panah, Home dan End. Nilai asli dan tanggal lengkap tersedia pada keterangan detail meskipun sumbu dipadatkan.
- Nilai negatif, nol dan nilai kosong dibedakan. Data kosong tidak digambar sebagai angka nol; garis terputus pada nilai yang hilang. Dataset tidak diubah.
- Observer modal dibersihkan saat ditutup dan observer grafik dibersihkan saat berpindah percakapan.

Komponen tetap menggunakan SVG, Bootstrap 5.3.7, Inter dan token tema aplikasi. Tidak ada dependency baru atau perubahan alur backend/POS. Cabang ECharts opsional yang tidak dipakai instalasi ini dihilangkan agar seluruh tampilan memakai renderer SVG yang sama.

## Berkas

- `public/assets/js/ai-chat.js`: renderer responsif, label, detail sentuh/keyboard dan modal.
- `public/assets/css/ai-chat.css`: ukuran ringkas, pembatas lebar dan ukuran modal.
- `app/Views/pages/ai_chat.php`: markup modal Bootstrap.
- `tests/frontend/ai-interactions.test.cjs`: regresi label padat, resize, nilai hilang, detail angka, pembesaran, fokus dan tanpa request.

## Verifikasi

- **21 tes frontend lulus**. Empat skenario grafik baru menguji titik lengkap dengan label yang dikurangi, perubahan lebar canvas, keyboard/sentuh, data kosong/nol/negatif, modal, pergantian tipe, pengembalian fokus dan tidak ada request baru saat memperbesar.
- **105 tes PHPUnit, 860 assertion lulus**, menggunakan database dan writable testing terisolasi.
- `node --check public/assets/js/ai-chat.js` dan lint PHP view lulus.
- Sebelum akses terputus, browser pada sesi login yang ada menampilkan grafik tersimpan beserta tombol Perbesar yang baru. Setelah itu tool melaporkan `Local browser use is disabled in Work settings`.

**Batas verifikasi:** pemeriksaan visual akhir pada lebar 360/768/1280 px, tema terang/gelap, dan interaksi modal Bootstrap nyata belum selesai karena akses browser nonaktif. Tes DOM otomatis tidak dianggap pengganti pemeriksaan visual tersebut. Tidak ada screenshot hasil akhir yang diklaim sebagai bukti browser.
