<?php
namespace App\Services\AI;

/** Owned report snapshots stay local; only allowlisted metadata and aggregate facts enter prompts. */
final class ReportContext
{
    public function __construct(private ?PosSchemaService $schema = null) { $this->schema ??= new PosSchemaService(); }

    public function kind(array $message): string
    {
        $meta = $message['metadata'] ?? [];
        if (!empty($meta['response_kind'])) return $meta['response_kind'];
        if (($meta['analysis_status'] ?? '') === 'error') return 'error';
        if (in_array($meta['analysis_status'] ?? '', ['refused','clarification'], true)) return $meta['analysis_status'];
        if (in_array($meta['intent'] ?? '', ['security_refusal','rbac_denied'], true)) return 'refused';
        if (($meta['intent'] ?? '') === 'clarification_needed') return 'clarification';
        foreach ($message['blocks'] ?? [] as $block) if (in_array($block['type'] ?? '', ['table','chart','kpi'], true)) return 'analysis';
        return !empty($meta['queries']) ? 'analysis' : 'conversation';
    }

    public function allowed(array $context, string $role): bool
    {
        return in_array($context['domain'] ?? '', $this->schema->allowedDomainsForRole($role), true);
    }

    public function snapshots(array $conversation, string $role): array
    {
        $reports = [];
        $root = $conversation['context'] ?? [];
        $lastQuestion = '';
        foreach ($conversation['messages'] ?? [] as $message) {
            if (($message['role'] ?? '') === 'user') { $lastQuestion = $message['content'] ?? ''; continue; }
            if (($message['role'] ?? '') !== 'assistant' || $this->kind($message) !== 'analysis') continue;
            $meta = $message['metadata'] ?? [];
            if (($meta['intent'] ?? '') === 'reformat_previous') continue;
            if (array_filter($meta['queries'] ?? [], static fn ($q) => empty($q['success']))) continue;
            $scope = $meta['report_scope'] ?? $this->legacyScope($meta);
            $ref = $meta['report_ref'] ?? 'legacy-' . ($message['id'] ?? count($reports));
            $context = array_replace(['domain' => $meta['domain'] ?? 'sales', 'intent' => $meta['intent'] ?? '', 'last_columns' => [], 'last_rows' => [], 'last_chart' => null, 'last_kpis' => [], 'last_queries' => $meta['queries'] ?? [], 'source_mode' => $meta['source_mode'] ?? 'unknown', 'last_title' => 'Laporan tersimpan'], $scope);
            $context['last_request'] = $meta['report_request'] ?? $lastQuestion;
            if (isset($meta['report_scope'])) $context['last_report_period'] = $meta['report_scope'];
            if (isset($meta['report_facts'])) $context['last_facts'] = $meta['report_facts'];
            if (isset($meta['semantic_plan'])) $context['semantic_plan']=$meta['semantic_plan'];
            if(array_key_exists('member_identity',$meta))$context['member_identity']=$meta['member_identity'];
            if(isset($meta['result_facts']))$context['last_facts']=$meta['result_facts'];
            if(isset($meta['result_snapshot'])){
                $context['last_rows']=$meta['result_snapshot']['rows']??[];$context['last_columns']=$meta['result_snapshot']['columns']??[];
            }
            if(($meta['intent']??'')==='data_lookup'){
                $context['lookup_count']=$meta['lookup_count']??0;$context['lookup_partial']=!empty($meta['lookup_partial']);
                $context['last_rows']=$meta['lookup_result']['rows']??[];$context['last_columns']=$meta['lookup_result']['columns']??[];
                $context['last_empty_explanation']='Tidak ditemukan data master yang sesuai pencarian.';
            }
            $context['last_empty_result'] = !empty($meta['empty_result']);
            foreach ($message['blocks'] ?? [] as $block) {
                if (($block['type'] ?? '') === 'table') { $context['last_rows'] = $block['rows'] ?? []; $context['last_columns'] = $block['columns'] ?? []; $context['last_title'] = $block['title'] ?? $context['last_title']; }
                if (($block['type'] ?? '') === 'chart') $context['last_chart'] = array_diff_key($block, ['type' => true]);
                if (($block['type'] ?? '') === 'kpi') $context['last_kpis'] = $block['items'] ?? [];
                if (($block['type'] ?? '') === 'insight') $context['last_notes'] = implode(' ', array_filter($block['items'] ?? [], 'is_string'));
                if (($block['type'] ?? '') === 'warning') $context['last_warnings'][] = $block;
            }
            $identity=MemberIdentity::fromContext($context);
            if($identity){$context['member_identity']=$identity;$context['member_ref']=$identity['ref'];}
            $reports[$ref] = ['ref' => $ref, 'context' => $context, 'generated_at' => $meta['generated_at'] ?? $message['created_at'] ?? null, 'empty' => $meta['empty_result'] ?? $context['last_rows'] === []];
        }
        // Preserve compatibility with stored contexts that predate report metadata.
        if (!empty($root['last_columns']) || !empty($root['last_chart'])) {
            $ref = $root['last_report_ref'] ?? (array_key_last($reports) ?? 'legacy-active');
            if (!array_filter($root['last_queries'] ?? [], static fn ($q) => empty($q['success']))) {
                $reports[$ref] = ['ref' => $ref, 'context' => $root, 'generated_at' => $reports[$ref]['generated_at'] ?? null, 'empty' => $reports[$ref]['empty'] ?? (($root['last_rows'] ?? []) === [])];
                $reports[$ref]['context']['last_empty_result'] = $reports[$ref]['empty'];
            }
        }
        return array_filter($reports, fn ($report) => $this->allowed($report['context'], $role));
    }

    public function select(array $conversation, string $role, ?string $ref = null): ?array
    {
        $reports = $this->snapshots($conversation, $role);
        $ref ??= $conversation['context']['dialogue']['report_ref'] ?? $conversation['context']['last_report_ref'] ?? null;
        return $ref !== null ? ($reports[$ref] ?? null) : ($reports === [] ? null : end($reports));
    }

    /** At most three fact packets, with older reports retrieved by ordinal or explicit period. */
    public function relevant(array $conversation, string $role, string $question, ?string $activeRef = null): array
    {
        $all = $this->snapshots($conversation, $role); $selected = [];
        if ($all === []) return [];
        if (preg_match('/\b(laporan|data|analisis)\s+(?:yang\s+)?(?:pertama|paling awal)\b/iu', $question)) { $ref = array_key_first($all); $selected[$ref] = $all[$ref]; }
        $period = (new ConversationScope(new BusinessClock()))->explicitPeriod($question, $conversation['context'] ?? []);
        if ($period) foreach ($all as $ref => $report) {
            $c = $report['context'];
            if (($c['year'] ?? null) === $period['year'] && (empty($period['month']) || ($c['month'] ?? null) === $period['month'])) $selected[$ref] = $report;
        }
        if ($activeRef !== null && isset($all[$activeRef])) $selected[$activeRef] ??= $all[$activeRef];
        foreach (array_reverse($all, true) as $ref => $report) $selected[$ref] ??= $report;
        return array_values(array_slice($selected, 0, 3, true));
    }

    public function scope(array $context): array
    {
        $scope = array_intersect_key($context, array_flip(['year','month','comparison_year','comparison_month','comparison','date_start','date_end','group_by','category','source_filter','entity','metric','dimensions','time_grain','rank','limit','filters','ranking','sort_direction','member_ref']));
        return array_replace($scope, $context['last_report_period'] ?? []);
    }

    public function facts(array $report, bool $aggregates): array
    {
        $c = $report['context'];
        $scope = $this->scope($c);
        if (isset($scope['category'])) $scope['category'] = mb_substr((string) $scope['category'], 0, 200);
        $clock = new BusinessClock();
        $period = new ConversationScope($clock);
        $label = $period->periodLabel($scope);
        if(($c['intent']??'')==='data_lookup')$label='data master saat pemeriksaan (tanpa periode transaksi)';
        if (!empty($scope['comparison'])) $label = $period->periodLabel($scope['comparison']['baseline']) . ' dan ' . $period->periodLabel($scope['comparison']['target']);
        elseif (!empty($scope['comparison_year']) && $scope['comparison_year'] !== ($scope['year'] ?? null)) $label = $period->periodLabel(['year' => $scope['year']]) . ' dan ' . $period->periodLabel(['year' => $scope['comparison_year']]);
        elseif (!empty($scope['comparison_month'])) $label = $period->periodLabel(['year' => $scope['year'], 'month' => $scope['comparison_month']]) . ' dan ' . $period->periodLabel($scope);
        $facts = ['report_ref' => $report['ref'], 'domain' => $c['domain'], 'intent' => $c['intent'] ?? '', 'period' => $scope, 'period_label' => $label,
            'generated_at' => $report['generated_at'], 'timezone' => 'Asia/Jakarta', 'source_mode' => $c['source_mode'] ?? 'unknown', 'empty' => $report['empty'], 'source_filter' => $c['source_filter'] ?? 'normal'];
        if($identity=MemberIdentity::fromContext($c))$facts['member_reference']=['ref'=>$identity['ref']];
        if ($aggregates) {
            $metrics = [];
            if (isset($c['last_facts'])) $metrics = array_slice($c['last_facts']['metrics'] ?? [], 0, 3);
            // Compatibility only: historical reports predate the numeric fact contract.
            foreach (isset($c['last_facts']) ? [] : array_slice($c['last_kpis'] ?? [], 0, 3) as $i => $item) {
                $value = $this->numeric($item['value'] ?? null);
                if ($value === null) continue;
                $label = 'Indikator ' . ($i + 1);
                if (!empty($c['comparison'])) $label = ['Total periode dasar','Total periode pembanding','Selisih nominal'][$i];
                else foreach (['omzet' => 'Omzet faktur','penjualan' => 'Penjualan','retur' => 'Retur','laba' => 'Laba','transaksi' => 'Jumlah transaksi','stok' => 'Stok','piutang' => 'Piutang'] as $word => $name) if (str_contains(mb_strtolower($item['label'] ?? ''), $word)) { $label = $name; break; }
                $metrics[] = ['label' => $label, 'value' => $value, 'unit' => str_contains((string) ($item['value'] ?? ''), 'Rp') ? 'IDR' : 'number'];
            }
            if (!isset($c['last_facts']) && $metrics === [] && in_array($c['intent'] ?? '', ['sales_daily','member_spend'], true)) {
                $key = $c['intent'] === 'sales_daily' ? 'omzet_faktur' : 'total_belanja';
                $metrics[] = ['label' => 'Omzet faktur', 'value' => (float) array_sum(array_column($c['last_rows'] ?? [], $key)), 'unit' => 'IDR'];
            }
            $facts['metrics'] = $metrics;
            if (!empty($c['comparison']) && count($metrics) === 3 && $metrics[0]['value'] != 0) $facts['metrics'][2]['percentage_change'] = round(($metrics[1]['value'] - $metrics[0]['value']) / $metrics[0]['value'] * 100, 2);
        }
        return $facts;
    }

    private function numeric(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) return (float) $value;
        if (is_string($value) && is_numeric($value) && !preg_match('/^[+\-]?\d{1,3}(?:\.\d{3})+$/D', $value)) return (float) $value;
        if (!is_string($value) || !preg_match('/^[+\-]?(?:Rp\s*)?[\d.,]+(?:\s*(?:%|transaksi|item))?$/u', trim($value))) return null;
        return (float) str_replace(',', '.', str_replace(['Rp',' ','.','%','transaksi','item'], '', trim($value)));
    }

    public function actions(array $context): array
    {
        if (!empty($context['last_empty_result'])) return ['table' => false, 'chart' => false, 'export' => false];
        return ['table' => !empty($context['last_columns']), 'chart' => is_array($context['last_chart'] ?? null), 'export' => !empty($context['last_columns']) && !empty($context['last_rows'])];
    }

    public function emptyExplanation(array $context): string
    {
        if(($context['intent']??'')==='data_lookup')return $context['last_empty_explanation']??'Tidak ditemukan data master yang sesuai pencarian.';
        $scope = $this->scope($context);
        $period = new ConversationScope(new BusinessClock());
        $label = !empty($scope['comparison']) ? $period->periodLabel($scope['comparison']['baseline']) . ' dan ' . $period->periodLabel($scope['comparison']['target']) : $period->periodLabel($scope);
        if (empty($scope['comparison']) && !empty($scope['comparison_year']) && $scope['comparison_year'] !== ($scope['year'] ?? null)) $label = $period->periodLabel(['year' => $scope['year']]) . ' dan ' . $period->periodLabel(['year' => $scope['comparison_year']]);
        if (in_array($context['intent']??'',['member_spend','member_transactions'],true) && !empty($context['focus_member']['nama'])) return 'Untuk **' . $label . '**, member **' . $context['focus_member']['nama'] . '** tidak memiliki transaksi penjualan operasional.';
        if(($context['entity']??null)==='sale_return')return 'Untuk **'.$label.'**, tidak ditemukan retur penjualan sah sesuai periode dan filter laporan.';
        if(in_array($context['entity']??null,['cash_transaction','cash_entry'],true))return 'Untuk **'.$label.'**, tidak ditemukan transaksi '.($context['entity']==='cash_transaction'?'kas operasional manual FINAL':'kas/bank yang sudah diposting').' sesuai periode dan filter. Transfer internal dikecualikan.';
        if (!empty($context['filters'])) return 'Untuk **'.$label.'**, tidak ditemukan data yang memenuhi seluruh filter laporan. Transaksi lain pada periode tersebut mungkin tetap tersedia.';
        return 'Untuk **' . $label . '**, tidak ditemukan ' . (in_array($context['domain'] ?? '', ['sales','members'], true) ? 'transaksi penjualan operasional.' : 'data sesuai periode dan filter laporan.');
    }

    public function forClient(array $conversation, string $role): array
    {
        $reports = $this->snapshots($conversation, $role);
        foreach ($conversation['messages'] ?? [] as $i => $message) {
            if (($message['role'] ?? '') !== 'assistant') continue;
            $kind = $this->kind($message);
            $meta = $message['metadata'] ?? [];
            $deniedConversationContext = $kind === 'conversation' && !empty($meta['aggregate_context_attached']) && array_diff($meta['context_domains'] ?? [], $this->schema->allowedDomainsForRole($role));
            if ($deniedConversationContext || (in_array($kind, ['analysis','report_context'], true) && !$this->allowed(['domain' => $meta['domain'] ?? 'sales'], $role))) {
                $message['content'] = 'Laporan ini tidak tersedia untuk peran akun Anda saat ini.';
                $message['blocks'] = [['type' => 'warning', 'content' => $message['content']]];
                $message['metadata'] = ['intent' => 'rbac_denied', 'analysis_status' => 'refused'];
                $kind = 'refused';
            }
            $ref = $meta['report_ref'] ?? 'legacy-' . ($message['id'] ?? '');
            if ($kind === 'analysis' && (!empty($meta['empty_result']) || !empty($reports[$ref]['context']['last_empty_result']))) {
                $message['content'] = $this->emptyExplanation($reports[$ref]['context'] ?? ['domain' => $meta['domain'] ?? 'sales']);
                $message['blocks'] = array_merge([['type' => 'text', 'content' => $message['content']]], array_values(array_filter($message['blocks'] ?? [], static fn ($b) => ($b['type'] ?? '') === 'warning')));
                $message['metadata']['empty_result'] = true;
            }
            $message['metadata']['response_kind'] = $kind;
            $message['metadata']['actions'] = $kind === 'analysis' && isset($reports[$ref]) ? $this->actions($reports[$ref]['context']) : ['table' => false, 'chart' => false, 'export' => false];
            if (isset($reports[$ref]) && $kind === 'analysis') $message['metadata']['report_ref'] = $ref;
            $conversation['messages'][$i] = $message;
        }
        if ((isset($conversation['context']['domain']) || !empty($conversation['context']['last_columns']) || !empty($conversation['context']['last_rows'])) && !$this->allowed($conversation['context'], $role)) $conversation['context'] = [];
        $activeRef = $conversation['context']['last_report_ref'] ?? null;
        if ($activeRef !== null && isset($reports[$activeRef])) $conversation['context']['last_empty_result'] = $reports[$activeRef]['empty'];
        return (new AiClientDiagnostics())->present($conversation);
    }

    private function legacyScope(array $meta): array
    {
        $sql = str_replace('`', '', $meta['queries'][0]['sql'] ?? '');
        $scope = [];
        if (preg_match('/(?:YEAR\(\s*(?:\w+\s*\.\s*)?tanggal\s*\)\s*=\s*|\x27)((?:19|20|21)\d{2})(?:-(\d{2}))?/i', $sql, $m)) {
            $scope['year'] = (int) $m[1]; if (!empty($m[2])) $scope['month'] = (int) $m[2];
        }
        return $scope;
    }
}
