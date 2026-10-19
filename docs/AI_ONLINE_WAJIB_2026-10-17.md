# AI wajib terhubung sebelum analisis

Tanggal: 17 Oktober 2026, Asia/Jakarta.

Setiap pertanyaan analisis dan aksi reformat sekarang memerlukan keputusan baru
yang valid dari provider AI. Parser pola hanya mengusulkan kandidat rencana;
AI harus menyetujuinya, meminta SQL khusus, atau memberikan klarifikasi.
Keamanan SQL, hak akses, dan perhitungan angka tetap dijalankan server.
Penolakan keamanan/RBAC merupakan pesan kebijakan, bukan analisis bisnis offline.
Riwayat dan ekspor dataset sukses yang telah tersimpan tetap dapat dibuka.

Tanpa konfigurasi provider: provider_not_configured. Gangguan koneksi/HTTP:
provider_unavailable. Respons rusak atau keputusan tidak dikenal:
provider_invalid_response. Ketiganya menghentikan analisis sebelum query baru,
tanpa KPI/nol buatan. Konteks sukses sebelumnya dipertahankan dan draft dapat
dicoba kembali. Tidak ada flag produksi untuk melewati keputusan AI.
Fixture POS dibatasi pada ENVIRONMENT=testing. Flag simulasi lama diabaikan
pada runtime operasional. Kegagalan POS tidak mengganti data dengan fixture.

Konfigurasi aktif tidak diganti: http://localhost:20128/v1, gateway yang sudah
dikonfigurasi pengguna. Istilah online di implementasi ini berarti provider
benar-benar memberi respons valid untuk permintaan tersebut, bukan label status
lama atau tersedianya API key. Endpoint loopback masih diizinkan untuk gateway.
Pemeriksaan ini tidak membuktikan jenis model/upstream gateway atau menjamin
bahwa gateway tidak memiliki fallback model lokal di dalam layanannya sendiri.
Untuk menjamin cloud-only, kebijakan upstream gateway juga harus membatasi model
lokal; itu berada di luar project ini dan tidak diubah.

Permintaan review mengirim pertanyaan, tanggal bisnis, kandidat intent/periode,
dan konteks periode. Dataset/KPI/query logs/secret tidak dikirim ke review.
Jalur SQL khusus mengirim skema kurasi dan riwayat pertanyaan pengguna, tanpa
riwayat jawaban assistant yang berisi laporan bisnis. Query AI tetap divalidasi.
Perubahan ini menambah satu panggilan AI pada analisis standar/reformat dan dapat
menambah latensi/biaya provider; SQL khusus memerlukan panggilan berikutnya.

Pengujian:
- PHPUnit: 86 tes, 728 assertion lulus. Provider fake eksplisit hanya pada tests;
  aplikasi produksi tidak memiliki bypass online.
- Frontend Node: 16 tes lulus.
- Uji live terisolasi: provider aktif → analisis POS live sukses; reformat online
  tanpa query POS ulang; provider port1 yang sengaja gagal → tidak ada query/KPI
  baru dan dataset sebelumnya utuh. Provider aktif tidak dimatikan/diganti.
- Flag simulasi true dengan grup POS tidak tersedia pada objek uji production
  tetap menghasilkan offline, bukan fixture.
- Browser akun yang sudah login: pertanyaan member bertransaksi Oktober 2026
  diarahkan oleh AI ke SQL khusus, menghasilkan tabel 2 member; reformat sukses.
  Sebelumnya pola lokal menjawab pertanyaan tersebut sebagai ringkasan omzet.
- Uji ini menemukan operator >= yang terpisah menjadi > = saat SQL dinormalisasi.
  Lexer diperbaiki untuk >=, <=, <>, !=, <=>, && dan ||; regresi rentang tanggal
  serta pengujian ulang query live lulus.

Bukti lokal: build/online-required/checks.json dan screenshot browser.jpg.
Percakapan browser diberi judul QA AI ONLINE · Member Oktober sebagai bukti.
Data POS, credential, akun dan layanan sistem tidak diubah.
