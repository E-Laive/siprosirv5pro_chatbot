<?php
namespace App\Services\AI;

/** Presentation of validated query results only; no provider, storage or SQL execution. */
final class DynamicReportPresenter
{
    private ConversationScope $dialogue;
    public function __construct() { $this->dialogue = new ConversationScope(new BusinessClock()); }
    public function render(array $parsed, array $res, array $plan, array $context, array $executedQueries, array $metadata, array $planningAttempts = []): array
    {
        $title = 'Hasil Analisis Data POS';
        $explanation = 'Hasil query SELECT yang lolos pemeriksaan akses dan kondisi bisnis yang didukung. Rincian perhitungan tersedia pada SQL laporan; hasil ini tidak membuktikan penyebab perubahan.';
        $rows = $res['rows'];

        $columns = [];
        foreach ($res['columns'] as $colName) {
            $firstVal = '';
            foreach ($rows as $row) if (($row[$colName] ?? null) !== null) { $firstVal = $row[$colName]; break; }
            $isNum = is_numeric($firstVal) && !preg_match('/^(id_|no_|kode|tahun|periode)/i', $colName);
            $isCurr = $isNum && preg_match('/(total|omzet|penjualan|belanja|hpp|harga|laba|saldo|bayar|piutang|potongan|debit|kredit|nominal|gaji|subtotal)/i', $colName) && !preg_match('/(jumlah|transaksi|unit|qty|poin)/i', $colName);
            $columns[] = [
                'key' => $colName,
                'label' => ucwords(str_replace('_', ' ', $colName)),
                'type' => $isCurr ? 'currency' : ($isNum ? 'number' : 'text'),
            ];
        }

        $scopeLabel = !empty($context['year']) && in_array('tbltransaksipenjualan', $res['tables'] ?? [], true) ? ' untuk ' . $this->dialogue->periodLabel($context) : '';
        $summary = "{$title}. Ditemukan {$res['row_count']} baris data{$scopeLabel}.";
        if (count($rows) === 1) {
            $metrics = [];
            foreach ($columns as $column) {
                if (!in_array($column['type'], ['currency', 'number'], true)) continue;
                if (!isset($rows[0][$column['key']]) || !is_numeric($rows[0][$column['key']])) continue;
                $value = (float) $rows[0][$column['key']];
                $metrics[] = $column['label'] . ': **' . ($column['type'] === 'currency' ? $this->rp($value) : number_format($value, floor($value) === $value ? 0 : 2, ',', '.')) . '**';
                if (count($metrics) === 3) break;
            }
            if ($metrics !== []) $summary = "{$title}{$scopeLabel}. " . implode(' · ', $metrics) . '.';
            if (($plan['entity']??null)==='member') {
                $member=$rows[0]['nama']??$rows[0]['nama_member']??null;
                if (is_string($member)&&$member!=='') $summary.=' Member yang memenuhi: **'.$member.'**.';
            }
        }
        $blocks = [['type' => 'text', 'content' => $summary]];

        $labelCol = (string) ($parsed['label_column'] ?? ($res['columns'][0] ?? ''));
        $valCol = (string) ($parsed['value_column'] ?? ($res['columns'][1] ?? ''));
        $chartBlock = null;
        if (($parsed['chart_type'] ?? 'none') !== 'none' && $rows !== [] && array_key_exists($labelCol, $rows[0]) && array_key_exists($valCol, $rows[0])) {
            $chartBlock = [
                'chart_type' => in_array($parsed['chart_type'], ['line', 'bar'], true) ? $parsed['chart_type'] : 'bar',
                'title' => $title,
                'subtitle' => 'Visualisasi dari hasil query SQL dinamis',
                'labels' => array_map(static fn (array $r): string => (string) ($r[$labelCol] ?? ''), $rows),
                'series' => [
                    [
                        'name' => ucwords(str_replace('_', ' ', $valCol)),
                        'data' => array_map(static fn (array $r): ?float => isset($r[$valCol]) && is_numeric($r[$valCol]) ? (float) $r[$valCol] : null, $rows),
                    ],
                ],
            ];
            $blocks[] = ['type' => 'chart'] + $chartBlock;
        }

        $blocks[] = [
            'type' => 'table',
            'title' => $title,
            'columns' => $columns,
            'rows' => $rows,
        ];
        // Presentation is independent from SQL capability: a validated query can answer with text.
        $snapshot=[];
        if(($plan['output_preference']??'auto')==='text'){
            if(count($rows)>1||array_filter($columns,static fn($c)=>$c['type']==='text')){
                $lines=[];
                foreach(array_slice($rows,0,5) as $row){
                    $parts=[];
                    foreach(array_slice($columns,0,8) as $column){
                        $value=$row[$column['key']]??null;
                        $parts[]=$column['label'].': '.($value===null?'—':$this->plain((string)$value));
                    }
                    $lines[]=implode(' · ',$parts);
                }
                if($lines!==[])$summary.="\n\n".implode("\n\n",$lines);
                if(count($rows)>5)$summary.="\n\nRincian lainnya tersedia melalui tombol Tabel.";
            }
            $blocks=[['type'=>'text','content'=>$summary]];
            $snapshot=['columns'=>$columns,'rows'=>$rows];$chartBlock=null;
        }

        $updatedContext = array_merge($context, [
            'domain' => $plan['domain'],
            'intent' => 'dynamic_llm_sql',
            'last_title' => $title,
            'last_rows' => $rows,
            'last_columns' => $columns,
            'last_chart' => $chartBlock,
            'last_queries' => $executedQueries,
        ]);

        return [
            'summary' => $summary,
            'blocks' => $blocks,
            'metadata' => $metadata + ['methodology' => $explanation, 'sql_planning_attempts' => $planningAttempts, 'sql_repair_used' => count($planningAttempts) > 1] + ($snapshot!==[]?['result_snapshot'=>$snapshot]:[]),
            'updated_context' => $updatedContext,
        ];
    }

    private function rp(float $value): string { return ($value < 0 ? '-' : '') . 'Rp' . number_format(abs($value), 0, ',', '.'); }
    private function plain(string $value): string
    {
        return str_replace(['\\','*','_','[',']','`','<','>'],['\\\\','\\*','\\_','\\[','\\]','\\`','&lt;','&gt;'],mb_substr(str_replace(["\r","\n"],' ',$value),0,300));
    }
}
