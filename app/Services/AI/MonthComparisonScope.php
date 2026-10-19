<?php

namespace App\Services\AI;

/** A comparison has two ordered periods; a single inherited month cannot represent it. */
final class MonthComparisonScope
{
    private const MONTHS = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];

    public function __construct(private BusinessClock $clock) {}

    /** Coverage is computed by the server for AI-supplied periods, never trusted from generated dates. */
    public function fromPeriods(array $baseline,array $target): array
    {
        if ($baseline===$target) throw new AnalysisException('provider_invalid_plan','Periode dasar dan pembanding harus berbeda.');
        foreach([$baseline,$target] as $period) if(sprintf('%04d-%02d',$period['year'],$period['month'])>$this->clock->now()->format('Y-m')) throw new AnalysisException('provider_invalid_plan','Periode pembanding belum berlangsung.');
        return $this->coverage(['baseline'=>$baseline,'target'=>$target,'alignment'=>'day_of_month','metric'=>'omzet_faktur']);
    }

    public function restore(array $context): array
    {
        if (!empty($context['comparison']) || !in_array($context['intent'] ?? '', ['month_comparison', 'month_comparison_daily'], true)) return $context;
        // Older fields reversed the first/second month. Recover from the actual request instead.
        $plan = $this->resolve((string) ($context['last_request'] ?? ''), array_diff_key($context, ['intent' => true]));
        if (!empty($plan['comparison'])) return array_replace($context, array_intersect_key($plan, array_flip(['comparison', 'year', 'month', 'comparison_year', 'comparison_month'])));
        return $context;
    }

    public function resolve(string $question, array $context): ?array
    {
        $q = mb_strtolower(trim($question));
        $daily = (bool) preg_match('/\b(per\s*hari|harian)\b/u', $q);
        if (($context['intent'] ?? '') === 'month_comparison_daily' && !preg_match('/\b(total\s+saja|bulanan|per\s*bulan|ringkasan)\b/u', $q)) $daily = true;
        $followup = preg_match('/^(?:(?:tolong|coba)\s+)?(?:(?:pisahkan|rincikan|uraikan|detailkan|pecah|pecahkan)\b|per\s*hari\b|harian\b)/u', $q)
            && $daily && !preg_match('/\b(member|pelanggan|produk|barang|kategori|retur|laba|stok|supplier)\b/u', $q);
        $comparing = (bool) preg_match('/\b(bandingkan|perbandingan|komparasi|vs|versus|dibanding(?:kan)?|terhadap)\b/u', $q);
        if (!$comparing && !$followup) return null;
        if (preg_match('/\b(stok|persediaan)\b/u', $q)) return null;

        preg_match_all('/\b(' . implode('|', self::MONTHS) . '|bulan\s+(?:ini|lalu|kemarin|sebelumnya))(?:\s+(?:tahun\s+)?((?:19|20|21)\d{2}))?\b/u', $q, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        preg_match_all('/\b(?:19|20|21)\d{2}\b/u', $q, $years);
        $uniqueYears = array_unique($years[0]);
        $periods = [];
        foreach ($matches as $m) {
            $token = $m[1][0];
            if (str_starts_with($token, 'bulan ')) {
                $date = $this->clock->now()->modify('first day of this month');
                if ($token === 'bulan sebelumnya' && !empty($context['year']) && !empty($context['month'])) $date = $date->setDate((int) $context['year'], (int) $context['month'], 1);
                if ($token !== 'bulan ini') $date = $date->modify('-1 month');
                $periods[] = ['year' => (int) $date->format('Y'), 'month' => (int) $date->format('n')];
            } else {
                $year = !empty($m[2][0]) ? (int) $m[2][0] : (count($uniqueYears) === 1 ? (int) reset($uniqueYears) : (int) ($context['year'] ?? $this->clock->now()->format('Y')));
                if (count($uniqueYears) > 1 && empty($m[2][0])) return $this->clarify($daily, 'Sebutkan tahun untuk masing-masing bulan yang dibandingkan.');
                $periods[] = ['year' => $year, 'month' => array_search($token, self::MONTHS, true) + 1];
            }
        }
        if (count($periods) > 2) return $this->clarify($daily, 'Dua bulan mana yang ingin dibandingkan? Sebutkan bulan dan tahunnya.');
        if (count($periods) === 1 && preg_match('/\bbulan\s+sebelumnya\b/u', $q)) {
            $target = ['year' => (int) ($context['year'] ?? $this->clock->now()->format('Y')), 'month' => (int) ($context['month'] ?? $this->clock->now()->format('n'))];
            $periods = [$target, $periods[0]];
        }
        if (count($periods) === 0 && $followup && in_array($context['intent'] ?? '', ['month_comparison', 'month_comparison_daily'], true)) {
            $comparison = $context['comparison'] ?? null;
            if (!$comparison) return $this->clarify(true, 'Bulan dan tahun mana yang ingin dibandingkan per hari?');
        } elseif (count($periods) === 2) {
            [$first, $second] = $periods;
            if ($first === $second) return $this->clarify($daily, 'Kedua periode sama. Sebutkan dua bulan atau tahun yang berbeda.');
            // "X terhadap/dibanding Y" means X is the target; neutral pairs are chronological.
            $explicit = preg_match('/\b(terhadap|dibanding(?:kan)?)\b/u', $q);
            if ($explicit) { $baseline = $second; $target = $first; }
            else {
                usort($periods, static fn ($a, $b) => [$a['year'], $a['month']] <=> [$b['year'], $b['month']]);
                [$baseline, $target] = $periods;
            }
            $comparison = ['baseline' => $baseline, 'target' => $target, 'alignment' => 'day_of_month', 'metric' => 'omzet_faktur'];
        } elseif ($matches !== [] || $followup || preg_match('/\bbulan\b/u', $q)) {
            return $this->clarify($daily, 'Dua bulan mana yang ingin dibandingkan? Sebutkan bulan dan tahunnya.');
        } elseif ($daily) return $this->clarify(true, 'Dua bulan mana yang ingin dibandingkan per hari? Sebutkan bulan dan tahunnya.');
        else return null; // Annual comparison and other existing intents remain separate.

        foreach (['baseline', 'target'] as $key) {
            $period = $comparison[$key];
            if (sprintf('%04d-%02d', $period['year'], $period['month']) > $this->clock->now()->format('Y-m')) {
                return $this->clarify($daily, 'Salah satu bulan belum berlangsung. Sebutkan dua periode yang sudah memiliki cakupan data untuk dibandingkan.');
            }
        }
        $comparison = $this->coverage($comparison);
        $plan = ['intent' => $daily ? 'month_comparison_daily' : 'month_comparison', 'domain' => 'sales',
            'year' => $comparison['target']['year'], 'month' => $comparison['target']['month'],
            'comparison_year' => $comparison['baseline']['year'], 'comparison_month' => $comparison['baseline']['month'],
            'comparison' => $comparison, 'group_by' => $daily ? 'day' : null, 'category' => null, 'date_start' => null, 'date_end' => null, 'period_known' => true];
        if (preg_match('/\b(retur|bersih|laba|margin|hpp|kategori|produk|barang|member|pelanggan|supplier|diskon|potongan|kotor|cabang|lokasi|gudang|tunai|kredit|legacy_history|migrasi|tanggal|mingguan|rata-rata|jumlah\s+transaksi)\b|\b\d{4}-\d{2}-\d{2}\b|\b\d{1,2}\s+(?:' . implode('|', self::MONTHS) . ')\b/u', $q)) {
            $plan['clarification_question'] = 'Perbandingan ini menghitung omzet faktur penjualan NORMAL. Apakah metrik tersebut yang dimaksud, atau sebutkan metrik dan filter lain yang diperlukan?';
            $plan['clarification_slot'] = 'metric';
        }
        return $plan;
    }

    private function clarify(bool $daily, string $message): array
    {
        return ['intent' => $daily ? 'month_comparison_daily' : 'month_comparison', 'domain' => 'sales', 'group_by' => $daily ? 'day' : null, 'clarification_question' => $message, 'clarification_slot' => 'period'];
    }

    private function coverage(array $comparison): array
    {
        $now = $this->clock->now();
        $days = [];
        $current = false;
        foreach (['baseline', 'target'] as $key) {
            $period = $comparison[$key];
            $start = $now->setDate($period['year'], $period['month'], 1)->setTime(0, 0);
            $days[$key] = (int) $start->format('t');
            if ($start->format('Y-m') === $now->format('Y-m')) $current = true;
            $comparison[$key]['start'] = $start->format('Y-m-d');
            $comparison[$key]['end'] = $start->modify('+1 month')->format('Y-m-d');
        }
        $cutoff = $current ? min((int) $now->format('j'), ...array_values($days)) : null;
        foreach (['baseline', 'target'] as $key) {
            if ($cutoff !== null) $comparison[$key]['end'] = $now->setDate($comparison[$key]['year'], $comparison[$key]['month'], $cutoff)->setTime(0, 0)->modify('+1 day')->format('Y-m-d');
        }
        $comparison['coverage'] = $current ? 'matched_days' : 'full_month';
        $comparison['cutoff_day'] = $cutoff;
        $comparison['partial_today'] = $current && $cutoff === (int) $now->format('j');
        return $comparison;
    }
}
