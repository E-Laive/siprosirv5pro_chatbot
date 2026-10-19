<?php
namespace App\Services\AI;

/** Narrow, verifiable business contracts. The online router still runs for every turn. */
final class RequestSemantics
{
    public function memberActivity(string $question): ?array
    {
        $q = mb_strtolower($question);
        if (preg_match('/\b(itu\s+tadi|laporan\s+tadi|data\s+tadi|laporan\s+sebelumnya|data\s+sebelumnya|maksud|artinya)\b/u', $q)) return null;
        if (preg_match('/\b(?:member|pelanggan)\s+(?!(?:yang|dengan|berdasarkan|bertransaksi|aktif|hari|pada|bulan|tahun|tanggal|tertinggi|terbanyak|terbesar|top|peringkat|semua)\b)[a-z0-9]/u', $q)) return null;
        if (!preg_match('/\b(member|pelanggan)\b/u', $q)
            || !preg_match('/\b(bertransaksi|transaksi|belanja|pembelian)\b/u', $q)
            || !preg_match('/\b(daftar|siapa|semua|top|peringkat|terbanyak|tertinggi|terbesar)\b|\b(?:member|pelanggan)\s+yang\b/u', $q)) return null;
        // Extra filters and alternate measures belong to the SQL planner, never silently dropped.
        if (preg_match('/\b(retur|bersih|kategori|produk|barang|item|unit|qty|poin|diskon|laba|margin|hpp|piutang|alamat|telepon|hapus|nonaktif|cabang|lokasi|gudang|toko|kelas|grup|tunai|transfer|kredit|pembayaran|minimal|maksimal|di\s+atas|di\s+bawah|lebih\s+dari|kurang\s+dari|per\s*(hari|bulan|tahun)|harian|bulanan|tahunan|bandingkan|peningkatan|pertumbuhan|meningkat|penurunan|terendah|terkecil|paling\s+sedikit)\b|[<>]/u', $q)) return null;
        $rank = (bool) preg_match('/\b(top|peringkat|terbanyak|tertinggi|terbesar)\b/u', $q);
        $amount = (bool) preg_match('/\b(nominal|nilai|belanja|pembelian|rupiah|omzet)\b/u', $q);
        $frequency = (bool) preg_match('/\b(jumlah|frekuensi|sering)\b/u', $q);
        $ambiguous = $rank && ((!$amount && !$frequency) || ($amount && $frequency));
        $limit = $rank ? 1 : 100;
        if (preg_match('/\b(?:top|teratas|peringkat)\s*(\d{1,3})\b/u', $q, $m)) $limit = max(1, min(100, (int) $m[1]));
        return ['intent' => 'member_activity', 'domain' => 'members', 'entity' => 'member',
            'metric' => $amount ? 'invoice_amount' : 'transaction_count', 'dimensions' => ['member'], 'time_grain' => null,
            'group_by' => null, 'rank' => $rank, 'limit' => $limit, 'requires_fresh' => true,
            'output_preference' => 'table'] + ($ambiguous ? ['clarification_question' => 'Terbanyak berdasarkan nominal belanja atau jumlah transaksi?', 'clarification_slot' => 'metric'] : []);
    }

    public function salesPresence(string $question): bool
    {
        return (bool) preg_match('/\b(?:apakah\s+)?(?:sudah\s+)?ada\s+transaksi(?:\s+penjualan|\s+masuk)?\b/iu', $question)
            && !preg_match('/\b(member|pelanggan|supplier|pembelian|retur|kas|bank|gaji|stok|produk|barang|kategori|cabang|lokasi|kasir|tunai|transfer|kredit|minimal|maksimal|nominal|lebih|kurang|di\s+atas|di\s+bawah|itu\s+tadi|laporan\s+tadi|data\s+tadi|maksud|artinya)\b|[<>]/iu', $question);
    }

    public function fresh(string $question): bool
    {
        if (preg_match('/\b(itu\s+tadi|laporan\s+tadi|data\s+tadi|laporan\s+sebelumnya|data\s+sebelumnya|maksud|artinya)\b/iu', $question)) return false;
        return (bool) preg_match('/\b(cek\s+(?:ulang|lagi)|periksa\s+(?:ulang|lagi)|sekarang|terbaru|transaksi\s+masuk)\b/iu', $question)
            || (preg_match('/\b(hari ini|kemarin|bulan ini|bulan lalu|tanggal\s+\d|\d{4}-\d{2}-\d{2})\b/iu', $question) && preg_match('/\b(berapa|laporan|penjualan|omzet|transaksi|belanja)\b/iu', $question));
    }
}
