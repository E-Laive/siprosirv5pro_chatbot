<?php
namespace App\Services\AI;

/** Additional, conservative business proof for generated SQL; does not replace access validation. */
final class SqlBusinessPolicy
{
    public function assertMatches(string $sql, array $plan): void
    {
        $structure = (new SqlStructure())->inspect($sql);
        $predicate = new SqlPredicate();
        foreach ($structure['scopes'] as $scope) {
            $sales = array_values(array_filter($scope['sources'], static fn ($s) => $s['table'] === 'tbltransaksipenjualan'));
            if (in_array('tbltransaksipenjualan_detail', $scope['tables'], true) && $sales === []) $this->reject('business_filter_mismatch');
            $periodTable=match($plan['entity']??null){'sale_return'=>'tblreturpenjualan','cash_transaction'=>'tbltransaksi_kas','cash_entry'=>'tblbank_ledger',default=>null};
            if($periodTable!==null)foreach($scope['sources'] as $s)if($s['table']===$periodTable){
                $column=preg_quote(strtoupper($s['alias']),'/').'\\.';
                foreach($predicate->branches($predicate->tree($predicate->clause($scope['tokens'],'WHERE'))) as $branch)$this->assertPeriod(array_map(fn($a)=>$predicate->text($a),$branch),$column,$plan);
            }
            if ($sales === []) continue;
            foreach ($scope['sources'] as $detail) if ($detail['table'] === 'tbltransaksipenjualan_detail') $this->assertDetailJoin($scope, $sales, $detail, $predicate);
            $branches = $predicate->branches($predicate->tree($predicate->clause($scope['tokens'], 'WHERE')));
            foreach ($sales as $source) {
                $alias = strtoupper($source['alias']);
                // An unqualified column is only unambiguous with one direct business source.
                $column = count($scope['sources']) === 1 ? '(?:' . preg_quote($alias, '/') . '\.)?' : preg_quote($alias, '/') . '\.';
                foreach ($branches as $branch) {
                    $atoms = array_map(fn ($a) => $predicate->text($a), $branch);
                    $normal = false;
                    foreach ($atoms as $atom) if (preg_match('/^(?:COALESCE\(' . $column . "SOURCE_TYPE,'NORMAL'\)|" . $column . "SOURCE_TYPE)(?:='NORMAL'|IN\('NORMAL'\))$/D", $atom)) $normal = true;
                    if (!$normal) $this->reject('business_filter_mismatch');
                    if (($plan['entity']??null)!=='sale_return' && (!empty($plan['year']) || !empty($plan['date_start']))) $this->assertPeriod($atoms, $column, $plan);
                }
            }
        }
        if (($plan['group_by'] ?? null) === 'day' && in_array('tbltransaksipenjualan', $structure['tables'], true)
            && !(new SqlDailyGrain())->matches($structure['scopes'])) $this->reject('business_grouping_mismatch');
    }

    private function assertDetailJoin(array $scope, array $headers, array $detail, SqlPredicate $p): void
    {
        $tokens = $scope['tokens']; $depth = 0; $conditions = [];
        foreach ($tokens as $i => $t) {
            $v = $t['value'];
            if ($v === '(') $depth++;
            if ($v === ')') $depth--;
            if ($depth !== 0) continue;
            if ($v === 'USING' && count($scope['sources']) === 2 && $p->text(array_slice($tokens, $i + 1, 3)) === '(ID_TRANSAKSI)') return;
            if ($v !== 'ON') continue;
            $body = []; $nested = 0;
            for ($j = $i + 1; $j < count($tokens); $j++) {
                $x = $tokens[$j]['value'];
                if ($nested === 0 && in_array($x, ['JOIN','LEFT','RIGHT','INNER','WHERE','GROUP','ORDER','LIMIT'], true)) break;
                if ($x === '(') $nested++;
                if ($x === ')') $nested--;
                $body[] = $tokens[$j];
            }
            $conditions[] = $body;
        }
        $detailKey = strtoupper($detail['alias']) . '.ID_TRANSAKSI';
        foreach ($conditions as $condition) {
            $proven = true;
            foreach ($p->branches($p->tree($condition)) as $branch) {
                $joined = false;
                foreach ($headers as $header) {
                    $headerKey = strtoupper($header['alias']) . '.ID_TRANSAKSI';
                    foreach ($branch as $atom) if (in_array($p->text($atom), [$detailKey . '=' . $headerKey, $headerKey . '=' . $detailKey], true)) $joined = true;
                }
                if (!$joined) $proven = false;
            }
            if ($proven) return;
        }
        $this->reject('business_filter_mismatch');
    }

    private function assertPeriod(array $atoms, string $column, array $plan): void
    {
        $start = $plan['date_start'] ?? sprintf('%04d-%02d-01', $plan['year'], $plan['month'] ?? 1);
        $date = new \DateTimeImmutable($start);
        $end = $plan['date_end'] ?? $date->modify(!empty($plan['month']) ? '+1 month' : '+1 year')->format('Y-m-d');
        $lower = $upper = $year = $month = false;
        foreach ($atoms as $atom) {
            if (preg_match('/^(?:' . $column . 'TANGGAL|DATE\(' . $column . "TANGGAL\))>='" . preg_quote($start, '/') . "'$/D", $atom)) $lower = true;
            if (preg_match('/^(?:' . $column . 'TANGGAL|DATE\(' . $column . "TANGGAL\))<'" . preg_quote($end, '/') . "'$/D", $atom)) $upper = true;
            if (preg_match('/^YEAR\(' . $column . 'TANGGAL\)=' . (int) ($plan['year'] ?? 0) . '$/D', $atom)) $year = true;
            if (preg_match('/^MONTH\(' . $column . 'TANGGAL\)=' . (int) ($plan['month'] ?? 0) . '$/D', $atom)) $month = true;
        }
        if (!(($lower && $upper) || (empty($plan['date_start']) && $year && (empty($plan['month']) || $month)))) $this->reject('business_period_mismatch');
    }

    private function reject(string $code): never
    {
        $message = match ($code) {
            'business_period_mismatch' => 'Periode query AI belum cocok dengan periode permintaan.',
            'business_grouping_mismatch' => 'Query AI belum mempertahankan rincian per hari yang diminta.',
            default => 'Filter transaksi NORMAL belum terbukti berlaku pada setiap sumber penjualan.',
        };
        throw new AnalysisException($code, $message . ' Analisis dihentikan tanpa angka baru; laporan sebelumnya tetap tersimpan.');
    }
}
