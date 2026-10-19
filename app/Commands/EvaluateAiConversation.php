<?php
namespace App\Commands;

use App\Services\AI\{AiOrchestrator, AiProviderClient, BusinessClock, ConversationService, PosQueryExecutor};
use CodeIgniter\CLI\{BaseCommand, CLI};

/** Explicit online evaluation. Writes QA conversations locally, never into the user's history. */
final class EvaluateAiConversation extends BaseCommand
{
    protected $group = 'App';
    protected $name = 'ai:evaluate';
    protected $description = 'Evaluasi percakapan dengan provider lokal dan POS SELECT; gunakan --live --repeat 1..3.';
    public function run(array $params)
    {
        if (!CLI::getOption('live')) { CLI::error('Gunakan --live untuk menjalankan panggilan AI dan pembacaan POS secara eksplisit.'); return EXIT_ERROR; }
        $repeat = max(1, min(3, (int) (CLI::getOption('repeat') ?: 1)));
        $directory = ROOTPATH . 'build/conversation-evaluation/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3));
        if (!mkdir($directory, 0700, true)) { CLI::error('Direktori evaluasi tidak tersedia.'); return EXIT_ERROR; }
        $provider = new AiProviderClient();
        if (rtrim($provider->getActiveConfig()['base_url'] ?? '', '/') !== 'http://localhost:20128/v1') { CLI::error('Evaluasi ini hanya mengirim konteks ke provider lokal yang sudah disetujui.'); return EXIT_ERROR; }
        $config = clone config(\Config\AiAnalyst::class); $config->storageDriver = 'file'; $config->allowAnalyticalSandboxWhenPosOffline = false;
        $storage = new ConversationService($config, $directory . '/storage');
        $executor = new PosQueryExecutor(config: $config);
        $status = $executor->connectionStatus();
        if (!$status['connected'] || $status['mode'] !== 'live_pos_db') { CLI::error('POS live tidak tersedia; evaluasi tidak memakai simulasi.'); return EXIT_ERROR; }
        $cases = json_decode(file_get_contents(ROOTPATH . 'tests/fixtures/conversation-evaluation.json'), true, 512, JSON_THROW_ON_ERROR);
        $engine = new AiOrchestrator(conversationService: $storage, queryExecutor: $executor, providerClient: $provider, config: $config);
        $checks = []; $latencies = []; $tokens = []; $controls = [];
        for ($run=1; $run <= $repeat; $run++) {
            $uid = null; $refs = [];
            foreach ($cases as $case) {
                $start = hrtime(true);
                try {
                    $r = $engine->handleMessage($case['question'], 1, 'Administrator', $uid);
                    $uid = $r['conversation_uid']; $metadata = $r['metadata'];
                    $queries = count($metadata['queries'] ?? []);
                    $ok = $r['success'] && ($metadata['response_kind'] ?? '') === $case['kind'] && ($case['queries'] ? $queries > 0 : $queries === 0);
                    if (isset($case['contains'])) $ok = $ok && str_contains($r['summary'], $case['contains']);
                    if (isset($case['ref'])) $ok = $ok && ($metadata['report_ref'] ?? null) === ($refs[$case['ref']] ?? 'missing');
                    if (isset($case['save']) && $r['success']) {
                        $refs[$case['save']] = $metadata['report_ref'] ?? null;
                        $month = $case['save'] === 'september' ? '09' : '10';
                        $end = $month === '09' ? '2026-10-01' : '2026-11-01';
                        $control = $executor->execute("SELECT COALESCE(SUM(grand_total),0) AS total FROM tbltransaksipenjualan WHERE COALESCE(source_type,'NORMAL')='NORMAL' AND tanggal >= ? AND tanggal < ?", ["2026-{$month}-01", $end], 'Administrator', 1, null, 'sales');
                        $value = $r['context']['last_facts']['metrics'][0]['value'] ?? null;
                        $controls[] = ['run' => $run, 'period' => $case['save'], 'matches' => $control['success'] && $value !== null && abs($value - ($control['rows'][0]['total'] ?? INF)) < 0.01];
                    }
                    $callCount = $metadata['provider_metrics']['calls'] ?? 0;
                    $ok = $ok && $callCount === 1;
                    if (($metadata['provider_metrics']['total_tokens'] ?? null) !== null) $tokens[] = $metadata['provider_metrics']['total_tokens'];
                    $checks[] = ['run' => $run, 'case' => $case['id'], 'passed' => $ok, 'kind' => $metadata['response_kind'] ?? null, 'error_code' => $r['error_code'] ?? null, 'queries' => $queries, 'calls' => $callCount];
                } catch (\Throwable $e) { $checks[] = ['run' => $run, 'case' => $case['id'], 'passed' => false, 'exception_type' => get_class($e)]; }
                $latencies[] = round((hrtime(true) - $start) / 1e6, 2);
                CLI::write($run . ':' . $case['id'] . ' ' . (end($checks)['passed'] ? 'PASS' : 'FAIL'));
            }
        }
        sort($latencies);
        $result = ['checks' => $checks, 'controls' => $controls, 'p95_turn_ms' => $latencies[max(0, (int) ceil(count($latencies)*.95)-1)], 'total_tokens' => count($tokens) === count($checks) ? array_sum($tokens) : null, 'token_usage_coverage' => count($tokens) . '/' . count($checks)];
        file_put_contents($directory . '/results.json', json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        CLI::write('Hasil: ' . $directory . '/results.json');
        return array_filter($checks, static fn ($c) => !$c['passed']) || array_filter($controls, static fn ($c) => !$c['matches']) ? EXIT_ERROR : EXIT_SUCCESS;
    }
}
