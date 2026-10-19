<?php
namespace Tests\Feature;

use App\Services\AI\{AiOrchestrator, AnalysisException, BusinessClock, ConversationMemory, ConversationService, DynamicReportPresenter, MeasuredAiProvider, PosQueryExecutor, ReportContext, ReportFacts, SqlBusinessPolicy, SqlSafetyValidator};
use CodeIgniter\Test\{CIUnitTestCase, DatabaseTestTrait};
use Tests\Support\FakeOnlineProvider;

final class AiBusinessIntegrityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';
    private const FILTER = "COALESCE(source_type,'NORMAL')='NORMAL' AND tanggal >= '2026-09-01' AND tanggal < '2026-10-01'";
    private const PLAN = ['year' => 2026, 'month' => 9];
    private FakeOnlineProvider $provider;
    private AiOrchestrator $engine;
    private \CodeIgniter\Database\BaseConnection $pos;
    protected function setUp(): void
    {
        parent::setUp();
        db_connect()->table('users')->insert(['id' => 1, 'username' => 'integrity-test', 'display_name' => 'Integrity', 'password_hash' => password_hash('Testing123!', PASSWORD_DEFAULT), 'role' => 'Administrator', 'is_active' => 1]);
        $pos = \Config\Database::connect(config('Database')->tests, false); $pos->initialize(); $this->pos = $pos;
        $pos->query('CREATE TABLE tbltransaksipenjualan (id_transaksi TEXT, tanggal TEXT, grand_total REAL, total_kotor REAL, source_type TEXT)');
        $pos->query("INSERT INTO tbltransaksipenjualan VALUES ('n','2026-09-01',123.45,150,'NORMAL'),('l','2026-09-02',999,999,'LEGACY_HISTORY'),('o','2026-08-01',500,500,'NORMAL')");
        $this->provider = new FakeOnlineProvider(); $this->provider->baseUrlOverride = 'http://localhost:20128/v1';
        $this->engine = new AiOrchestrator(queryExecutor: new PosQueryExecutor(customPosConnection: $pos), providerClient: $this->provider, clock: new BusinessClock('Asia/Jakarta', new \DateTimeImmutable('2026-10-17')));
    }

    public function testEveryBooleanBranchMustProveTheNormalFilterAndExactPeriod(): void
    {
        $bad = [
            'SELECT SUM(grand_total) FROM tbltransaksipenjualan WHERE ' . self::FILTER . ' OR 1=1',
            'SELECT SUM(grand_total) FROM tbltransaksipenjualan WHERE ' . self::FILTER . ' AND 1=1 || grand_total>0',
            "SELECT SUM(grand_total) FROM tbltransaksipenjualan WHERE source_type='NORMAL' AND 1=1 XOR 1=1 AND tanggal >= '2026-09-01' AND tanggal < '2026-10-01'",
            "SELECT SUM(grand_total) FROM tbltransaksipenjualan WHERE (source_type='NORMAL' OR source_type='LEGACY_HISTORY') AND tanggal >= '2026-09-01' AND tanggal < '2026-10-01'",
            "SELECT SUM(grand_total) FROM tbltransaksipenjualan WHERE source_type='NORMAL' AND tanggal >= '2026-08-01' AND tanggal < '2026-10-01'",
            "SELECT SUM(p.grand_total) FROM tbltransaksipenjualan p WHERE EXISTS(SELECT q.id_transaksi FROM tbltransaksipenjualan q WHERE q.source_type='NORMAL' AND q.tanggal >= '2026-09-01' AND q.tanggal < '2026-10-01')",
            "SELECT SUM(p.grand_total) FROM tbltransaksipenjualan p JOIN tbltransaksipenjualan q ON p.id_transaksi=q.id_transaksi WHERE q.source_type='NORMAL' AND q.tanggal >= '2026-09-01' AND q.tanggal < '2026-10-01'",
        ];
        foreach ($bad as $sql) {
            try { (new SqlBusinessPolicy())->assertMatches($sql, self::PLAN); $this->fail('Unproven scope was accepted'); }
            catch (AnalysisException $e) { $this->assertContains($e->errorCode, ['business_filter_mismatch','business_period_mismatch']); }
        }
        $good = [
            'SELECT SUM(grand_total) FROM tbltransaksipenjualan WHERE (' . self::FILTER . ') AND (grand_total>10 OR grand_total<0)',
            "SELECT SUM(p.grand_total) FROM tbltransaksipenjualan p WHERE p.source_type IN ('NORMAL') AND YEAR(p.tanggal)=2026 AND MONTH(p.tanggal)=9",
            'WITH scoped AS (SELECT grand_total FROM tbltransaksipenjualan WHERE ' . self::FILTER . ') SELECT SUM(grand_total) FROM scoped',
            'SELECT SUM(grand_total) FROM (SELECT grand_total FROM tbltransaksipenjualan WHERE ' . self::FILTER . ') s',
        ];
        foreach ($good as $sql) { (new SqlBusinessPolicy())->assertMatches($sql, self::PLAN); $this->assertTrue(true); }
    }

    public function testDetailRowsMustBeLinkedToTheFilteredHeader(): void
    {
        $sql = "SELECT SUM(d.total_subtotal) FROM tbltransaksipenjualan_detail d JOIN tbltransaksipenjualan p ON d.id_transaksi=p.id_transaksi WHERE p.source_type='NORMAL' AND p.tanggal >= '2026-09-01' AND p.tanggal < '2026-10-01'";
        (new SqlBusinessPolicy())->assertMatches($sql, self::PLAN);
        $this->assertTrue(true);
        foreach (['1=1','d.id_transaksi=p.id_transaksi OR 1=1'] as $join) {
            try { (new SqlBusinessPolicy())->assertMatches(str_replace('d.id_transaksi=p.id_transaksi', $join, $sql), self::PLAN); $this->fail('Unlinked details were accepted'); }
            catch (AnalysisException $e) { $this->assertSame('business_filter_mismatch', $e->errorCode); }
        }
    }

    public function testRepairCannotChangeBooleanMeaningStringOperatorsOrAggregateArithmetic(): void
    {
        $base = 'SELECT SUM(grand_total) FROM tbltransaksipenjualan WHERE ' . self::FILTER;
        $v = new SqlSafetyValidator(); $tables = ['tbltransaksipenjualan'];
        foreach ([str_replace(' AND tanggal <', ' OR tanggal <', $base), str_replace("tanggal < '", "tanggal > '", $base), str_replace('SUM(grand_total)', 'SUM(grand_total*2)', $base)] as $replacement) $this->assertFalse($v->isCompatibleRepair($base . ' UNION ALL ' . $base, $replacement, $tables, $tables));
        $this->assertTrue($v->isCompatibleRepair($base . ' UNION ALL ' . $base, $base, $tables, $tables));
        $joined = "SELECT SUM(p.grand_total) FROM tbltransaksipenjualan p JOIN tbltransaksipenjualan q ON p.id_member=q.id_member WHERE p.source_type='NORMAL' AND q.source_type='NORMAL'";
        $this->assertFalse($v->isCompatibleRepair($joined . ' UNION ALL ' . $joined, str_replace('SUM(p.grand_total)', 'SUM(q.grand_total)', $joined), $tables, $tables));
    }

    public function testInvalidBusinessScopeNeverExecutesOrReplacesLastSuccessfulDataset(): void
    {
        $first = $this->engine->handleMessage('Laporan penjualan September 2026 perhari', 1, 'Administrator');
        foreach ([' OR 1=1', ''] as $suffix) {
            $sql = 'SELECT SUM(grand_total) AS omzet FROM tbltransaksipenjualan WHERE ' . self::FILTER . $suffix;
            if ($suffix === '') $sql = str_replace('2026-09-01', '2026-08-01', $sql);
            $this->provider->replies = [['parsed_json' => ['decision' => 'dynamic']], ['parsed_json' => ['sql' => $sql]]];
            $r = $this->engine->handleMessage('Analisis khusus omzet September 2026', 1, 'Administrator', $first['conversation_uid']);
            $this->assertFalse($r['success']);
            $this->assertSame([], $r['metadata']['queries']);
            $this->assertEquals($first['context']['last_rows'], $r['context']['last_rows']);
            $this->assertSame(2, $r['metadata']['provider_metrics']['calls']);
        }
    }

    public function testDailyGroupingCannotSilentlyBecomeMonthly(): void
    {
        $this->expectException(AnalysisException::class);
        (new SqlBusinessPolicy())->assertMatches('SELECT MONTH(tanggal),SUM(grand_total) FROM tbltransaksipenjualan WHERE ' . self::FILTER . ' GROUP BY MONTH(tanggal)', self::PLAN + ['group_by' => 'day']);
    }

    public function testFactsKeepRawDecimalsEvenWhenDisplayKpisAreChanged(): void
    {
        $r = $this->engine->handleMessage('Laporan penjualan September 2026 perhari', 1, 'Administrator');
        $stored = (new ConversationService())->getConversation($r['conversation_uid'], 1);
        $snapshot = (new ReportContext())->select($stored, 'Administrator');
        $snapshot['context']['last_kpis'] = [['label' => 'Omzet', 'value' => 'Rp999.999.999']];
        $facts = (new ReportContext())->facts($snapshot, true);
        $this->assertSame(123.45, $facts['metrics'][0]['value']);
        $this->assertSame('invoice_sales', $facts['metrics'][0]['key']);
        $this->assertEquals($r['context']['last_facts'], $stored['messages'][1]['metadata']['report_facts']);
        $this->assertArrayNotHasKey('metrics', (new ReportContext())->facts($snapshot, false));
    }

    public function testProviderCannotInventNumbersOrCausesInReportExplanation(): void
    {
        $r = $this->engine->handleMessage('Laporan penjualan September 2026 perhari', 1, 'Administrator');
        foreach (['context','general'] as $decision) {
            $this->provider->replies = [['parsed_json' => ['decision' => $decision, 'reply' => 'Omzet Rp999999 turun karena kasir mencuri.', 'report_ref' => $r['metadata']['report_ref'], 'context_subject' => 'meaning']]];
            $reply = $this->engine->handleMessage('Jelaskan arti angka dan penyebab turun tadi', 1, 'Administrator', $r['conversation_uid']);
            $this->assertStringNotContainsString('999999', $reply['summary']);
            $this->assertStringNotContainsString('mencuri', $reply['summary']);
            $this->assertStringContainsString('Rp123,45', $reply['summary']);
            $this->assertStringContainsString('belum membuktikan penyebab', $reply['summary']);
            $this->assertSame([], $reply['metadata']['queries']);
            $this->assertSame(1, $reply['metadata']['provider_metrics']['calls']);
        }
    }

    public function testOldReportAndWritingPreferenceSurviveLongConversationAndReload(): void
    {
        $first = $this->engine->handleMessage('Laporan penjualan September 2026 perhari', 1, 'Administrator');
        $this->provider->replies = [['parsed_json' => ['decision' => 'general', 'reply' => 'Slogan: Belanja nyaman setiap hari.']]];
        $this->engine->handleMessage('Buat slogan, jawab ringkas', 1, 'Administrator', $first['conversation_uid']);
        for ($i=0; $i<12; $i++) {
            $this->provider->replies = [['parsed_json' => ['decision' => 'general', 'reply' => 'Sama-sama.']]];
            $this->engine->handleMessage('terima kasih', 1, 'Administrator', $first['conversation_uid']);
        }
        for ($i=0; $i<4; $i++) $this->engine->handleMessage('Laporan penjualan Oktober 2026 perhari', 1, 'Administrator', $first['conversation_uid']);
        $this->provider->replies = [['parsed_json' => ['decision' => 'context', 'reply' => 'Data lama', 'report_ref' => $first['metadata']['report_ref'], 'context_subject' => 'period']]];
        $reply = $this->engine->handleMessage('Laporan pertama itu data kapan?', 1, 'Administrator', $first['conversation_uid']);
        $this->assertStringContainsString('September 2026', $reply['summary']);
        $payload = json_decode(end($this->provider->calls)[1]['content'], true);
        $this->assertSame($first['metadata']['report_ref'], $payload['reports'][0]['report_ref']);
        $this->assertCount(3, $payload['reports']);
        $this->assertSame('concise', $payload['period_context']['conversation_memory']['response_style']);
        $this->provider->replies = [['parsed_json' => ['decision' => 'general', 'reply' => 'Belanja nyaman.']]];
        $this->engine->handleMessage('Singkatkan slogan tadi', 1, 'Administrator', $first['conversation_uid']);
        $payload = json_decode(end($this->provider->calls)[1]['content'], true);
        $this->assertStringContainsString('Belanja nyaman setiap hari', json_encode($payload['recent_dialogue']));
        $this->assertLessThanOrEqual(12, count($payload['recent_dialogue']));
        $memory = (new ConversationMemory())->forPrompt($payload['period_context']['conversation_memory'], ['products_inventory']);
        $this->assertSame([], $memory['reports']);
        $this->assertStringContainsString('Memori:', (new ConversationService())->getConversation($first['conversation_uid'], 1)['summary_text']);
    }

    public function testDynamicChartKeepsEveryDayAndNullGap(): void
    {
        $rows = [];
        for ($day=1; $day<=31; $day++) $rows[] = ['tanggal' => '2026-08-' . sprintf('%02d',$day), 'omzet' => $day===20 ? null : $day-10];
        $result = (new DynamicReportPresenter())->render(['chart_type' => 'line','label_column' => 'tanggal','value_column' => 'omzet'], ['rows' => $rows, 'columns' => ['tanggal','omzet'], 'row_count' => 31, 'tables' => []], ['domain' => 'sales'], [], [], []);
        $chart = $result['updated_context']['last_chart'];
        $this->assertCount(31, $chart['labels']); $this->assertCount(31, $chart['series'][0]['data']);
        $this->assertNull($chart['series'][0]['data'][19]); $this->assertSame(-9.0, $chart['series'][0]['data'][0]);
        $this->assertSame('2026-08-31', end($chart['labels']));
    }

    public function testMetricsDistinguishMissingTokenUsageAndCountFailedCalls(): void
    {
        $provider = new MeasuredAiProvider($this->provider);
        $this->provider->replies = [['usage' => ['total_tokens' => 40]], ['used_remote' => false,'error' => 'timeout']];
        $provider->chatCompletion([]); $this->assertSame(40, $provider->metrics()['total_tokens']);
        $provider->chatCompletion([]); $m = $provider->metrics();
        $this->assertSame(2, $m['calls']); $this->assertSame(1, $m['failed_calls']); $this->assertNull($m['total_tokens']);
        $this->assertGreaterThanOrEqual(0, $m['duration_ms']);
        $provider->reset(); $this->assertSame(0, $provider->metrics()['calls']);
    }

    public function testEducationalExampleNumbersRemainGeneralConversation(): void
    {
        $r = $this->engine->handleMessage('Laporan penjualan September 2026 perhari', 1, 'Administrator');
        $this->provider->replies = [['parsed_json' => ['decision' => 'general', 'reply' => 'Contoh edukatif: laba Rp100 dari penjualan Rp1000 berarti margin 10%.']]];
        $reply = $this->engine->handleMessage('Apa itu margin laba? Berikan contoh sederhana.', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame('conversation', $reply['metadata']['response_kind']);
        $this->assertStringContainsString('margin 10%', $reply['summary']);
        $this->assertSame([], $reply['metadata']['queries']);
    }

    public function testDynamicNullFirstPointAndUntrustedTitleDoNotProduceFalseClaims(): void
    {
        $rows = [['tanggal'=>'2026-09-01','omzet'=>null],['tanggal'=>'2026-09-02','omzet'=>12.5]];
        $r = (new DynamicReportPresenter())->render(['title'=>'Omzet Rp999999 karena kasir mencuri','explanation'=>'Turun karena kasir mencuri','chart_type'=>'line','label_column'=>'tanggal','value_column'=>'omzet'], ['rows'=>$rows,'row_count'=>2,'columns'=>['tanggal','omzet'],'tables'=>[]], ['domain'=>'sales'], [], [], []);
        $this->assertNotNull($r['updated_context']['last_chart']);
        $this->assertNull($r['updated_context']['last_chart']['series'][0]['data'][0]);
        $this->assertSame('currency', $r['updated_context']['last_columns'][1]['type']);
        $this->assertStringNotContainsString('999999', json_encode($r));
        $this->assertStringNotContainsString('mencuri', json_encode($r));
    }

    public function testYearComparisonZeroBaselineHasNoFakePercentageAndKeepsBothReportYears(): void
    {
        $r = $this->engine->handleMessage('Bandingkan penjualan 2025 dan 2026', 1, 'Administrator');
        $this->assertTrue($r['success'], $r['summary']);
        $this->assertStringContainsString('dasar pembanding nol', $r['summary']);
        $this->assertNull($r['context']['last_facts']['metrics'][2]['value']);
        $report = (new ReportContext())->select((new ConversationService())->getConversation($r['conversation_uid'],1), 'Administrator');
        $facts = (new ReportContext())->facts($report, true);
        $this->assertSame('tahun 2025 dan tahun 2026', $facts['period_label']);
        $this->assertNull($facts['period']['month']);
    }

    public function testMemberGrowthFromZeroIsUndefinedInsteadOfOneHundredPercent(): void
    {
        $pos = $this->pos;
        $pos->query('CREATE TABLE tbldata_member (id_member TEXT, nama TEXT, kelas_member TEXT, hapus INTEGER)');
        $pos->query('CREATE TABLE tblmember_setting (id_kelas TEXT, nama_kelas TEXT)');
        $pos->query("INSERT INTO tbldata_member VALUES ('M1','Private member','K1',0)");
        $pos->query('ALTER TABLE tbltransaksipenjualan ADD COLUMN id_member TEXT');
        $pos->query("UPDATE tbltransaksipenjualan SET id_member='M1'");
        $engine = new AiOrchestrator(queryExecutor: new PosQueryExecutor(customPosConnection: $pos), providerClient: $this->provider, clock: new BusinessClock('Asia/Jakarta',new \DateTimeImmutable('2026-10-17')));
        $r = $engine->handleMessage('Tampilkan 10 member dengan peningkatan belanja tertinggi dari 2025 ke 2026',1,'Administrator');
        $this->assertTrue($r['success'],$r['summary']);
        $this->assertSame('Dasar pembanding nol',$r['context']['last_rows'][0]['pertumbuhan']);
        $this->assertNull($r['context']['last_facts']['metrics'][1]['value']);
        $this->assertStringNotContainsString('Private member',json_encode($r['context']['last_facts']));
    }
}
