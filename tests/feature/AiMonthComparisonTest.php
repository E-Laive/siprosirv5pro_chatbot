<?php

namespace Tests\Feature;

use App\Services\AI\{AiOrchestrator, BusinessClock, ConversationService, MonthComparisonScope, PosQueryExecutor};
use App\Services\ReportExporter;
use CodeIgniter\Test\{CIUnitTestCase, DatabaseTestTrait};
use Tests\Support\FakeOnlineProvider;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class AiMonthComparisonTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';
    private FakeOnlineProvider $provider;
    private AiOrchestrator $engine;
    private \CodeIgniter\Database\BaseConnection $pos;

    protected function setUp(): void
    {
        parent::setUp();
        db_connect()->table('users')->insert(['id' => 1, 'username' => 'comparison-test', 'display_name' => 'Comparison', 'password_hash' => password_hash('Testing123!', PASSWORD_DEFAULT), 'role' => 'Administrator', 'is_active' => 1]);
        $this->pos = \Config\Database::connect(config('Database')->tests, false);
        $this->pos->initialize();
        $this->pos->query('CREATE TABLE tbltransaksipenjualan (id_transaksi TEXT, tanggal TEXT, grand_total REAL, source_type TEXT)');
        $this->pos->query("INSERT INTO tbltransaksipenjualan VALUES ('a1','2026-08-01 23:59:59',300000000,NULL),('a31','2026-08-31',203560500,'NORMAL'),('s1','2026-09-01',300000000,'NORMAL'),('s30','2026-09-30',162244000,'NORMAL'),('exclude','2026-09-02',999999999,'LEGACY_HISTORY'),('o1','2026-10-01',100,'NORMAL'),('o18','2026-10-18',999,'NORMAL'),('f29','2024-02-29',29,'NORMAL')");
        $this->provider = new FakeOnlineProvider();
        $this->engine = $this->engineAt('2026-10-17');
    }

    private function engineAt(string $date): AiOrchestrator
    {
        return new AiOrchestrator(queryExecutor: new PosQueryExecutor(customPosConnection: $this->pos), providerClient: $this->provider, clock: new BusinessClock('Asia/Jakarta', new \DateTimeImmutable($date, new \DateTimeZone('Asia/Jakarta'))));
    }

    public function testExactThreeTurnRegressionAndReloadKeepDailyPair(): void
    {
        $uid = null;
        $expected = null;
        foreach (['bandingkan penjualan bulan agustus dan september di tahun 2026. perbandinganya perhari ya berdasarkan nominal', 'pisahkan datanya perhari', 'pisahkan datanya perhari'] as $q) {
            $r = $this->engine->handleMessage($q, 1, 'Administrator', $uid);
            $this->assertTrue($r['success'], $r['summary']);
            $uid = $r['conversation_uid'];
            $this->assertSame('month_comparison_daily', $r['metadata']['intent']);
            $this->assertSame('table', $r['metadata']['presentation']);
            $this->assertCount(1, $r['metadata']['queries']);
            $this->assertTrue($r['metadata']['queries'][0]['success']);
            $this->assertCount(31, $r['context']['last_rows']);
            $this->assertSame(503560500.0, array_sum(array_column($r['context']['last_rows'], 'nominal_dasar')));
            $this->assertSame(462244000.0, array_sum(array_column($r['context']['last_rows'], 'nominal_pembanding')));
            $this->assertStringContainsString('-8,20%', $r['summary']);
            $this->assertStringContainsString('penurunan', $r['summary']);
            $this->assertCount(3, $r['context']['last_kpis']);
            $this->assertSame(0.0, $r['context']['last_rows'][1]['nominal_pembanding']);
            $this->assertNull($r['context']['last_rows'][30]['nominal_pembanding']);
            $this->assertNull($r['context']['last_rows'][30]['selisih']);
            $this->assertNull($r['context']['last_rows'][1]['perubahan_persen']);
            $this->assertCount(2, $r['context']['last_chart']['series']);
            $this->assertCount(31, $r['context']['last_chart']['series'][1]['data']);
            $this->assertNull($r['context']['last_chart']['series'][1]['data'][30]);
            if ($expected !== null) $this->assertEquals($expected, $r['context']['last_rows']);
            $expected = $r['context']['last_rows'];
            $stored = (new ConversationService())->getConversation($uid, 1);
            $this->assertEquals($r['context'], $stored['context']);
            $this->engine = $this->engineAt('2026-10-17');
        }
    }

    public function testNeutralAndExplicitDirectionAndCrossYearAreDistinct(): void
    {
        $cases = [
            ['Bandingkan September dan Agustus 2026 perhari', [2026, 8], [2026, 9]],
            ['Bandingkan Agustus terhadap September 2026 perhari', [2026, 9], [2026, 8]],
            ['Bandingkan Desember 2025 dan Januari 2026 per hari', [2025, 12], [2026, 1]],
            ['Bandingkan Januari 2026 dibanding Desember 2025', [2025, 12], [2026, 1]],
            ['Bandingkan Agustus 2025 dan Agustus 2026 perhari', [2025, 8], [2026, 8]],
        ];
        foreach ($cases as [$q, $a, $b]) {
            $plan = $this->engine->resolveIntentAndPlan($q, []);
            $this->assertSame($a, [$plan['comparison_year'], $plan['comparison_month']], $q);
            $this->assertSame($b, [$plan['year'], $plan['month']], $q);
        }
        $r = $this->engine->handleMessage('Bandingkan Agustus terhadap September 2026 perhari', 1, 'Administrator');
        $this->assertStringContainsString('+8,94%', $r['summary']);
    }

    public function testLegacyReversedScopeRecoversWithoutRewritingHistory(): void
    {
        $service = new ConversationService();
        $c = $service->createConversation(1);
        $legacy = ['intent' => 'month_comparison', 'domain' => 'sales', 'year' => 2026, 'month' => 8, 'comparison_month' => 9, 'comparison_year' => 2026, 'last_request' => 'bandingkan penjualan Agustus dan September 2026 perhari'];
        $service->appendTurn((int) $c['id'], $legacy['last_request'], 'Laporan lama tetap disimpan', [], [], $legacy, null, 1, $c['conversation_uid']);
        $r = $this->engine->handleMessage('pisahkan datanya perhari', 1, 'Administrator', $c['conversation_uid']);
        $this->assertTrue($r['success']);
        $this->assertSame(8, $r['context']['comparison_month']);
        $this->assertSame(9, $r['context']['month']);
        $this->assertSame(4, (new ConversationService())->getConversation($c['conversation_uid'], 1)['message_count']);
    }

    public function testRunningMonthUsesMatchedDaysAndReformatPreservesWarnings(): void
    {
        $r = $this->engine->handleMessage('Bandingkan September dan Oktober 2026 perhari', 1, 'Administrator');
        $this->assertTrue($r['success'], $r['summary']);
        $this->assertCount(17, $r['context']['last_rows']);
        $this->assertSame(17, $r['context']['comparison']['cutoff_day']);
        $this->assertSame(100.0, array_sum(array_column($r['context']['last_rows'], 'nominal_pembanding')));
        $this->assertSame(300000000.0, array_sum(array_column($r['context']['last_rows'], 'nominal_dasar')));
        $this->assertContains('warning', array_column($r['blocks'], 'type'));
        $count = db_connect()->table('ai_query_logs')->countAllResults();
        foreach (['grafikkan', 'Tampilkan grafiknya.', 'Ubah menjadi tabel.'] as $q) {
            $again = $this->engine->handleMessage($q, 1, 'Administrator', $r['conversation_uid']);
            $this->assertTrue($again['success'], $again['summary']);
            $this->assertSame('reformat_previous', $again['metadata']['intent']);
            $this->assertSame([], $again['metadata']['queries']);
            $this->assertContains('warning', array_column($again['blocks'], 'type'));
            $this->assertEquals($r['context']['last_rows'], $again['context']['last_rows']);
        }
        $this->assertSame($count, db_connect()->table('ai_query_logs')->countAllResults());
    }

    public function testLeapCalendarAndEmptySuccessfulQueries(): void
    {
        foreach (['2024' => 29, '2025' => 28] as $year => $days) {
            $r = $this->engine->handleMessage("Bandingkan Januari dan Februari {$year} perhari", 1, 'Administrator');
            $this->assertTrue($r['success'], $r['summary']);
            $this->assertCount(31, $r['context']['last_rows']);
            $this->assertNull($r['context']['last_rows'][$days]['nominal_pembanding']);
            $this->assertSame($days === 29 ? 29.0 : 0.0, $r['context']['last_rows'][$days - 1]['nominal_pembanding']);
            $this->assertNull($r['context']['last_rows'][0]['perubahan_persen']);
            if ($days === 28) {
                $this->assertTrue($r['metadata']['empty_result']);
                $this->assertSame(['text'], array_column($r['blocks'], 'type'));
                $this->assertSame(['table' => false, 'chart' => false, 'export' => false], $r['metadata']['actions']);
            }
        }
        $r = $this->engineAt('2026-03-31')->resolveIntentAndPlan('Bandingkan Februari dan Maret 2026 perhari', []);
        $this->assertSame(28, $r['comparison']['cutoff_day']);
        $this->assertFalse($r['comparison']['partial_today']);
    }

    public function testIncompleteSameFutureAndAmbiguousYearsAskBeforeQuery(): void
    {
        foreach (['Bandingkan Agustus perhari', 'Bandingkan Agustus 2026 dan Agustus 2026 perhari', 'Bandingkan Desember dan Januari tahun 2025 dan 2026 perhari', 'Bandingkan Oktober dan November 2026 perhari', 'Bandingkan Agustus dan September 2026 di lokasi A perhari'] as $q) {
            $r = $this->engine->handleMessage($q, 1, 'Administrator');
            $this->assertSame('clarification', $r['analysis_status'], $q . ': ' . $r['summary']);
            $this->assertSame([], $r['metadata']['queries']);
        }
        $r = $this->engine->handleMessage('Bandingkan perhari', 1, 'Administrator');
        $this->assertSame('clarification', $r['analysis_status']);
    }

    public function testClarificationAcceptsCompletePairAndDoesNotReuseOldMonthNames(): void
    {
        $r = $this->engine->handleMessage('Bandingkan Agustus perhari', 1, 'Administrator');
        $next = $this->engine->handleMessage('Agustus dan September 2026', 1, 'Administrator', $r['conversation_uid']);
        $this->assertTrue($next['success'], $next['summary']);
        $this->assertSame('month_comparison_daily', $next['metadata']['intent']);
        $this->assertSame(8, $next['context']['comparison_month']);
        $this->assertSame(9, $next['context']['month']);
        $this->assertArrayNotHasKey('pending_request', $next['context']);
        $payload = json_decode(end($this->provider->calls)[1]['content'], true);
        $this->assertArrayNotHasKey('last_rows', $payload['period_context']);
        $this->assertArrayNotHasKey('last_kpis', $payload['period_context']);
    }

    public function testOnlineFailureMismatchAndQueryFailurePreserveSuccessfulDataset(): void
    {
        $first = $this->engine->handleMessage('Bandingkan Agustus dan September 2026 perhari', 1, 'Administrator');
        foreach ([['used_remote' => false, 'error' => 'timeout'], ['parsed_json' => ['decision' => 'dynamic']]] as $reply) {
            $this->provider->replies = [$reply];
            $r = $this->engine->handleMessage('pisahkan datanya perhari', 1, 'Administrator', $first['conversation_uid']);
            $this->assertFalse($r['success']);
            $this->assertContains($r['error_code'], ['provider_unavailable', 'provider_plan_mismatch']);
            $this->assertEquals($first['context'], $r['context']);
            $this->assertSame([], $r['metadata']['queries']);
        }
        $this->pos->query('DROP TABLE tbltransaksipenjualan'); // Isolated in-memory test database only.
        $r = $this->engine->handleMessage('pisahkan datanya perhari', 1, 'Administrator', $first['conversation_uid']);
        $this->assertFalse($r['success']);
        $this->assertSame('query_failed', $r['error_code']);
        $this->assertSame('execution', $r['metadata']['queries'][0]['failure_stage']);
        $this->assertEquals($first['context'], $r['context']);
        $this->assertNotContains('kpi', array_column($r['blocks'], 'type'));
    }

    public function testMetricConfirmationResolvesOnceAndNewComparisonRetainsGrouping(): void
    {
        $r = $this->engine->handleMessage('Bandingkan laba Agustus dan September 2026 perhari', 1, 'Administrator');
        $this->assertSame('clarification', $r['analysis_status']);
        $this->assertSame('metric', $r['context']['pending_request']['slot']);
        $next = $this->engine->handleMessage('omzet faktur', 1, 'Administrator', $r['conversation_uid']);
        $this->assertTrue($next['success'], $next['summary']);
        $this->assertSame('month_comparison_daily', $next['metadata']['intent']);
        $this->assertStringContainsString('-8,20%', $next['summary']);
        $this->assertArrayNotHasKey('pending_request', $next['context']);
        $changed = $this->engine->handleMessage('Bandingkan Juli dan Agustus 2026', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame('month_comparison_daily', $changed['metadata']['intent']);
        $this->assertSame(7, $changed['context']['comparison_month']);
        $this->assertSame(8, $changed['context']['month']);
        $total = $this->engine->handleMessage('Bandingkan Agustus dan September 2026 total saja', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame('month_comparison', $total['metadata']['intent']);
        $this->assertCount(2, $total['context']['last_rows']);
    }

    public function testSqlValidationFailureIsNotReportedAsOffline(): void
    {
        $bad = ['parsed_json' => ['title' => 'Uji', 'sql' => 'SELECT id_transaksi FROM tbltransaksipenjualan UNION ALL SELECT id_transaksi FROM tbltransaksipenjualan', 'explanation' => 'Uji', 'chart_type' => 'none']];
        $this->provider->replies = [['parsed_json' => ['decision' => 'dynamic']], $bad, $bad];
        $r = $this->engine->handleMessage('Analisis khusus transaksi September 2026', 1, 'Administrator');
        $this->assertFalse($r['success']);
        $this->assertSame('sql_validation_failed', $r['error_code']);
        $this->assertSame('validation', $r['metadata']['queries'][0]['failure_stage']);
    }

    public function testNullAmountsRemainBlankInExcelCsvAndDashInPdfView(): void
    {
        $report = ['key' => 'ai_analysis', 'title' => 'Perbandingan', 'note' => '— = tanggal tidak ada', 'columns' => ['amount' => ['Nominal', 'currency'], 'pct' => ['Perubahan (%)', 'number']], 'rows' => [['amount' => null, 'pct' => null], ['amount' => 0, 'pct' => 0], ['amount' => -10, 'pct' => -8.2]]];
        $exporter = new ReportExporter();
        $file = WRITEPATH . 'comparison.xlsx';
        file_put_contents($file, $exporter->excel($report));
        $book = IOFactory::load($file);
        try {
            $this->assertContains($book->getActiveSheet()->getCell('A6')->getValue(), ['', null]);
            $this->assertSame(0, $book->getActiveSheet()->getCell('A7')->getValue());
        } finally { $book->disconnectWorksheets(); unlink($file); }
        $csv = $exporter->csv($report);
        $this->assertStringContainsString("\n,\n0,0\n", $csv);
        $html = view('exports/table', ['report' => $report, 'filterLabel' => '']);
        $this->assertStringContainsString('>—</td>', $html);
        $this->assertStringContainsString('>Rp0</td>', $html);
        $this->assertStringContainsString('>-8,20</td>', $html);
        $this->assertStringNotContainsString('baris data contoh', $html);
        $this->assertStringStartsWith('%PDF-', $exporter->pdf($report));
    }
}
