<?php

namespace App\Services\AI;

/** Resolves conversational references locally; never stores or invents business results. */
final class ConversationScope
{
    private const MONTHS = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];

    public function __construct(private BusinessClock $clock) {}

    /** Recover only unambiguous date bounds from successful queries saved by the older dynamic path. */
    public function restoreLegacyPeriod(array $context): array
    {
        if (isset($context['last_request']) || !empty($context['month']) || ($context['intent'] ?? '') !== 'dynamic_llm_sql' || count($context['last_queries'] ?? []) !== 1) return $context;
        $query = $context['last_queries'][0];
        if (empty($query['success'])) return $context;
        $sql = str_replace('`', '', (string) ($query['sql'] ?? ''));
        if (!preg_match('/\btbltransaksipenjualan\b/i', $sql)) return $context;
        if (preg_match_all('/\btanggal\s*>=\s*\x27(\d{4}-\d{2}-\d{2})\x27/i', $sql, $starts) !== 1
            || preg_match_all('/\btanggal\s*<\s*\x27(\d{4}-\d{2}-\d{2})\x27/i', $sql, $ends) !== 1) return $context;
        $start = \DateTimeImmutable::createFromFormat('!Y-m-d', $starts[1][0], new \DateTimeZone('Asia/Jakarta'));
        $end = \DateTimeImmutable::createFromFormat('!Y-m-d', $ends[1][0], new \DateTimeZone('Asia/Jakarta'));
        if (!$start || !$end || $start->format('Y-m-d') !== $starts[1][0] || $end->format('Y-m-d') !== $ends[1][0] || $end <= $start) return $context;
        $context['year'] = (int) $start->format('Y');
        if ($start->format('d') === '01' && $start->modify('+1 month') == $end) $context['month'] = (int) $start->format('n');
        elseif ($start->format('m-d') !== '01-01' || $start->modify('+1 year') != $end) {
            $context['date_start'] = $start->format('Y-m-d');
            $context['date_end'] = $end->format('Y-m-d');
        }
        if (preg_match('/GROUP\s+BY\s+DATE\(\s*(?:\w+\s*\.\s*)?tanggal\s*\)/i', $sql)) $context['group_by'] = 'day';
        return $context;
    }

    public function effectiveQuestion(string $question, array $context): string
    {
        $pending = $context['pending_request']['question'] ?? null;
        if (!is_string($pending) || $pending === '') {
            if (!empty($context['last_request']) && preg_match('/^(?:tolong\s+)?(?:cek\s+(?:ulang|lagi)|periksa\s+(?:ulang|lagi))(?:\s+(?:sekarang|datanya|transaksinya|hari ini))*[.!?]*$/iu', trim($question))) return $context['last_request'] . "\nPemeriksaan POS baru: " . $question;
            // A bare period is a change to the last successful request, not a new analysis topic.
            if (!empty($context['last_request']) && preg_match('/^(?:(?:kalau|bagaimana dengan|untuk|yang|dan)\s+)?(?:bulan|tahun|januari|februari|maret|april|mei|juni|juli|agustus|september|oktober|november|desember)\b[^\n]*$/iu', trim($question))
                && !preg_match('/\b(member|produk|stok|supplier|laba|penjualan|kategori)\b/iu', $question)) {
                $request = ($context['intent'] ?? '') === 'member_spend' && !empty($context['member_query'])
                    ? 'Total belanja member ' . $context['member_query'] : mb_substr($context['last_request'], 0, 2000);
                return $request . "\nPeriode baru: " . $question;
            }
            return $question;
        }
        if (($context['pending_request']['slot'] ?? '') === 'metric' && !empty($context['pending_request']['scope']['comparison'])
            && preg_match('/^(?:ya|iya|benar|betul|setuju|pakai\s+omzet\s+faktur|omzet\s+faktur)[.!?]*$/iu', trim($question))) {
            $comparison = $context['pending_request']['scope']['comparison'];
            $label = static fn ($p) => self::MONTHS[$p['month'] - 1] . ' ' . $p['year'];
            return 'Bandingkan omzet faktur ' . $label($comparison['target']) . ' terhadap ' . $label($comparison['baseline'])
                . (($context['pending_request']['scope']['group_by'] ?? '') === 'day' ? ' per hari' : '');
        }
        if (($context['pending_request']['slot'] ?? '') === 'period' && preg_match('/\b(bandingkan|perbandingan|komparasi|terhadap)\b/iu', $pending)
            && preg_match_all('/\b(?:' . implode('|', self::MONTHS) . '|bulan\s+(?:ini|lalu|kemarin|sebelumnya))\b/iu', $question) === 2) {
            return 'Bandingkan penjualan ' . $question . (preg_match('/\b(per\s*hari|harian)\b/iu', $pending) ? ' per hari' : '');
        }
        // A new subject cancels an unfinished clarification. Short slot answers retain the request.
        if (preg_match('/\b(batal|abaikan|ganti topik|pertanyaan baru|stok|persediaan|produk|barang|supplier|hutang|utang|gaji|karyawan|laba|omzet|penjualan|piutang|retur|kas|bank|saldo)\b/iu', $question)
            || (preg_match('/\b(pembelian|belanja|membeli|transaksi|laporan|analisis|bandingkan)\b/iu', $question) && preg_match('/\b(member|pelanggan)\b/iu', $question))) return $question;
        return $pending . "\nJawaban klarifikasi: " . $question;
    }

    /** null means no explicit period. A supplied year deliberately clears an inherited month. */
    public function explicitPeriod(string $question, array $context): ?array
    {
        $q = mb_strtolower($question);
        $now = $this->clock->now()->modify('first day of this month');
        $today = $this->clock->now()->setTime(0, 0);
        $q = preg_replace('/\btanggal\s+(\d{1,2})\s+bulan\s+(' . implode('|', self::MONTHS) . ')\b/u', 'tanggal $1 $2', $q);
        // A numbered day is a date filter, never a request to aggregate the whole month.
        if (preg_match('/\btanggal\s+(\d{1,2})(?:\s+(?:di\s+)?bulan\s+(ini|lalu|kemarin|sebelumnya))?\b/u', $q, $day)
            && !preg_match('/\btanggal\s+\d{1,2}\s+(?:' . implode('|', self::MONTHS) . ')\b/u', $q)) {
            if (preg_match('/\btanggal\s+\d{1,2}\s*(?:sampai|hingga|s\.d\.|-)\s*\d/u', $q)) return null;
            $base = null;
            if (!empty($day[2])) {
                $base = $now;
                if ($day[2] === 'sebelumnya') {
                    if (!empty($context['month'])) $base = $now->setDate((int) $context['year'], (int) $context['month'], 1);
                    elseif (!empty($context['date_start'])) $base = (new \DateTimeImmutable($context['date_start'], $today->getTimezone()))->modify('first day of this month');
                }
                if ($day[2] !== 'ini') $base = $base->modify('-1 month');
            } elseif (!empty($context['comparison'])) return null;
            elseif (!empty($context['year']) && !empty($context['month'])) {
                $base = $now->setDate((int) $context['year'], (int) $context['month'], 1);
            } elseif (!empty($context['date_start']) && !empty($context['date_end'])
                && substr($context['date_start'], 0, 7) === substr((new \DateTimeImmutable($context['date_end']))->modify('-1 day')->format('Y-m-d'), 0, 7)) {
                $base = new \DateTimeImmutable($context['date_start'], $today->getTimezone());
            }
            if (!$base || !checkdate((int) $base->format('n'), (int) $day[1], (int) $base->format('Y'))) return null;
            $date = $base->setDate((int) $base->format('Y'), (int) $base->format('n'), (int) $day[1])->setTime(0, 0);
            return ['year' => (int) $date->format('Y'), 'month' => null, 'date_start' => $date->format('Y-m-d'), 'date_end' => $date->modify('+1 day')->format('Y-m-d')];
        }
        $q = preg_replace_callback('/\b(\d{1,2})\s+(' . implode('|', self::MONTHS) . ')(?:\s+((?:19|20|21)\d{2}))?\b/u', static function ($m) use ($context, $today) {
            return sprintf('%04d-%02d-%02d', (int) ($m[3] ?? $context['year'] ?? $today->format('Y')), array_search($m[2], self::MONTHS, true) + 1, (int) $m[1]);
        }, $q);
        $window = null;
        if (preg_match('/\bhari ini\b/u', $q)) $window = [$today, $today->modify('+1 day')];
        elseif (preg_match('/\bkemarin\b/u', $q) && !preg_match('/\b(bulan|tahun)\s+kemarin\b/u', $q)) $window = [$today->modify('-1 day'), $today];
        elseif (preg_match('/\bminggu\s+(ini|lalu|kemarin)\b/u', $q, $w)) {
            $start = $today->modify('monday this week');
            if ($w[1] !== 'ini') $start = $start->modify('-1 week');
            $window = [$start, $start->modify('+1 week')];
        } elseif (preg_match_all('/\b(\d{4}-\d{2}-\d{2})\b/u', $q, $dates)) {
            if (count($dates[1]) > 2 || preg_match('/(?<![\d-])\b\d{1,2}\s*(?:sampai|hingga|s\.d\.|-)\s*\d{4}-/u', $q)) return null;
            $valid = [];
            foreach ($dates[1] as $date) {
                $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, $today->getTimezone());
                if (!$d || $d->format('Y-m-d') !== $date) return null;
                $valid[] = $d;
            }
            if (end($valid) < $valid[0]) return null;
            $window = [$valid[0], end($valid)->modify('+1 day')];
        }
        if ($window) return ['year' => (int) $window[0]->format('Y'), 'month' => null, 'date_start' => $window[0]->format('Y-m-d'), 'date_end' => $window[1]->format('Y-m-d')];
        if (preg_match('/\bbulan\s+(ini|lalu|kemarin|sebelumnya)\b/u', $q, $m)) {
            $base = $now;
            if ($m[1] === 'sebelumnya' && !empty($context['year']) && !empty($context['month'])) {
                $base = $now->setDate((int) $context['year'], (int) $context['month'], 1);
            } elseif ($m[1] === 'sebelumnya' && !empty($context['date_start'])) {
                $base = (new \DateTimeImmutable($context['date_start'], $now->getTimezone()))->modify('first day of this month');
            }
            if ($m[1] !== 'ini') $base = $base->modify('-1 month');
            return ['year' => (int) $base->format('Y'), 'month' => (int) $base->format('n'), 'date_start' => null, 'date_end' => null];
        }
        $year = preg_match('/\b((?:19|20|21)\d{2})\b/u', $q, $y) ? (int) $y[1] : null;
        if (preg_match('/\btahun\s+(ini|lalu|kemarin)\b/u', $q, $y)) $year = (int) $now->format('Y') - ($y[1] === 'ini' ? 0 : 1);
        foreach (self::MONTHS as $i => $name) {
            if (preg_match('/\b' . $name . '\b/u', $q)) return ['year' => $year ?? (int) ($context['year'] ?? $now->format('Y')), 'month' => $i + 1, 'date_start' => null, 'date_end' => null];
        }
        return $year === null ? null : ['year' => $year, 'month' => null, 'date_start' => null, 'date_end' => null];
    }

    public function periodLabel(array $scope): string
    {
        if (!empty($scope['date_start']) && !empty($scope['date_end'])) {
            $start = new \DateTimeImmutable($scope['date_start']);
            $end = (new \DateTimeImmutable($scope['date_end']))->modify('-1 day');
            $label = static fn ($d) => $d->format('j') . ' ' . ucfirst(self::MONTHS[(int) $d->format('n') - 1]) . ' ' . $d->format('Y');
            return $label($start) . ($start == $end ? '' : ' sampai ' . $label($end));
        }
        $year = $scope['year'] ?? null;
        if (!$year) return 'periode yang belum ditentukan';
        $month = $scope['month'] ?? null;
        return $month ? ucfirst(self::MONTHS[(int) $month - 1]) . ' ' . $year : 'tahun ' . $year;
    }

    public function hasDateExpression(string $question): bool
    {
        return (bool) preg_match('/\b(?:tanggal\s+\d{1,2}|\d{4}-\d{2}-\d{2}|\d{1,2}\s+(?:' . implode('|', self::MONTHS) . '))\b/iu', $question);
    }

    public function promptContext(array $context): array
    {
        $safe = array_intersect_key($context, array_flip(['intent', 'domain', 'year', 'month', 'date_start', 'date_end', 'comparison', 'comparison_year', 'comparison_month', 'category', 'group_by', 'member_query', 'last_request', 'source_filter', 'last_report_ref', 'entity', 'metric', 'dimensions', 'time_grain', 'rank', 'limit','filters','ranking','sort_direction']));
        if($identity=MemberIdentity::fromContext($context))$safe['member_reference']=['ref'=>$identity['ref']];
        if (!empty($context['pending_request'])) {
            // Member suggestions come from POS and must remain local, outside provider prompts.
            $safe['pending_request'] = array_intersect_key($context['pending_request'], array_flip(['question', 'slot', 'scope']));
        }
        if (!empty($context['pending_report_reference'])) $safe['pending_report_reference'] = $context['pending_report_reference'];
        return $safe;
    }

    public function memberQuery(string $question): ?string
    {
        if (!preg_match('/\b(?:member|pelanggan)\s+(.+?)(?=\s+(?:berapa|bulan|tahun|hari|kemarin|minggu|tanggal|pada|selama|per\s*hari|yang|di\s+bulan)\b|[?,.!\n]|$)/iu', $question, $m)) return null;
        $name = trim($m[1], " \t\"'");
        if (preg_match('/^(ini|itu|tersebut|nya|mana|siapa|berapa|semua|yang|bulan|tahun|aktif|pernah)\b/iu', $name)) return null;
        return $name !== '' ? mb_substr($name, 0, 150) : null;
    }
}
