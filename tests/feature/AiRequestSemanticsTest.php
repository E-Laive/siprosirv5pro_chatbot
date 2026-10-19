<?php
namespace Tests\Feature;

use App\Services\AI\{AiClientDiagnostics, AiOrchestrator, AnalysisException, BusinessClock, ConversationScope, ConversationService, PosQueryExecutor, SqlBusinessPolicy};
use CodeIgniter\Test\{CIUnitTestCase, DatabaseTestTrait};
use Tests\Support\FakeOnlineProvider;

final class AiRequestSemanticsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';
    private FakeOnlineProvider $provider;
    private AiOrchestrator $engine;
    private \CodeIgniter\Database\BaseConnection $pos;
    private const WHERE = "COALESCE(p.source_type,'NORMAL')='NORMAL' AND p.tanggal >= '2026-10-01' AND p.tanggal < '2026-11-01'";

    protected function setUp(): void
    {
        parent::setUp();
        db_connect()->table('users')->insert(['id' => 1, 'username' => 'semantics', 'display_name' => 'QA', 'password_hash' => password_hash('Testing123!', PASSWORD_DEFAULT), 'role' => 'Administrator', 'is_active' => 1]);
        $this->pos = \Config\Database::connect(config('Database')->tests, false); $this->pos->initialize();
        $this->pos->query('CREATE TABLE tbltransaksipenjualan (id_transaksi TEXT, id_member TEXT, tanggal TEXT, grand_total REAL, total_kotor REAL, source_type TEXT)');
        $this->pos->query('CREATE TABLE tbldata_member (id_member TEXT, nama TEXT, hapus INTEGER)');
        $this->pos->query("INSERT INTO tbldata_member VALUES ('A','Private Alpha',0),('B','Private Beta',0),('C','Private Gamma',0),('D','Deleted',1),('Z','Zero',0)");
        $this->pos->query("INSERT INTO tbltransaksipenjualan VALUES ('a','A','2026-10-17 09:00:00',200,200,'NORMAL'),('b1','B','2026-10-17 10:00:00',40,40,'NORMAL'),('b2','B','2026-10-17 11:00:00',60,60,NULL),('c','C','2026-10-17 12:00:00',200,200,'NORMAL'),('d','D','2026-10-17',999,999,'NORMAL'),('anon','','2026-10-17',9999,9999,'NORMAL'),('legacy','B','2026-10-17',9999,9999,'LEGACY_HISTORY'),('day15','A','2026-10-15',15,15,'NORMAL'),('zero','Z','2026-10-16',0,0,'NORMAL')");
        $this->provider = new FakeOnlineProvider(); $this->provider->baseUrlOverride = 'http://localhost:20128/v1';
        $this->engine = new AiOrchestrator(queryExecutor: new PosQueryExecutor(customPosConnection: $this->pos), providerClient: $this->provider, clock: new BusinessClock('Asia/Jakarta', new \DateTimeImmutable('2026-10-17')));
    }

    public function testRelativeNumberedDayNeverExpandsToTheWholeMonth(): void
    {
        $r = $this->engine->handleMessage('transaksi tanggal 15 bulan ini', 1, 'Administrator');
        $this->assertTrue($r['success'], $r['summary']);
        $this->assertSame('2026-10-15', $r['context']['date_start']);
        $this->assertSame('2026-10-16', $r['context']['date_end']);
        $this->assertEquals(15, $r['context']['last_rows'][0]['omzet_faktur']);
        $again = $this->engine->handleMessage('transaksi tanggal 16', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame('2026-10-16', $again['context']['date_start']);
        $this->assertFalse($again['metadata']['empty_result']); // Real zero-valued receipt.
        $invalid = $this->engine->handleMessage('transaksi tanggal 31 bulan lalu', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame('clarification', $invalid['analysis_status']);
        $this->assertSame([], $invalid['metadata']['queries']);
    }

    public function testDayResolutionHandlesYearRolloverLeapYearAndMissingMonth(): void
    {
        $scope = new ConversationScope(new BusinessClock('Asia/Jakarta', new \DateTimeImmutable('2027-01-17')));
        $this->assertSame('2026-12-31', $scope->explicitPeriod('tanggal 31 bulan lalu', [])['date_start']);
        $this->assertSame('2024-02-29', $scope->explicitPeriod('tanggal 29', ['year'=>2024,'month'=>2])['date_start']);
        $this->assertNull($scope->explicitPeriod('tanggal 29', ['year'=>2025,'month'=>2]));
        $this->assertNull($scope->explicitPeriod('tanggal 15', []));
        $this->assertSame('2026-10-15', $scope->explicitPeriod('tanggal 15 bulan Oktober 2026', [])['date_start']);
        $this->assertNull($scope->explicitPeriod('tanggal 1 sampai 5 bulan ini', []));
    }

    public function testMemberVariantsClearSalesGrainAndUseTheSameBoundedQuery(): void
    {
        $first = $this->engine->handleMessage('Laporan penjualan Oktober 2026 per hari', 1, 'Administrator');
        foreach (['daftar member yang memiliki transaksi pada hari ini', 'daftar member yang bertransaksi pada hari ini'] as $question) {
            $r = $this->engine->handleMessage($question, 1, 'Administrator', $first['conversation_uid']);
            $this->assertTrue($r['success'], $r['summary']);
            $this->assertSame('member_activity', $r['metadata']['intent']);
            $this->assertNull($r['context']['group_by']);
            $this->assertCount(3, $r['context']['last_rows']);
            $this->assertEquals(500, array_sum(array_column($r['context']['last_rows'], 'total_belanja')));
            $this->assertCount(1, $r['metadata']['queries']);
            $this->assertSame(1, $r['metadata']['provider_metrics']['calls']);
            $this->assertStringNotContainsString('Private Alpha', json_encode(end($this->provider->calls)));
        }
    }

    public function testNominalRankingIncludesTiesAndFrequencyRankingUsesCounts(): void
    {
        $r = $this->engine->handleMessage('member yang bertransaksi dengan nominal terbanyak hari ini', 1, 'Administrator');
        $this->assertTrue($r['success'], $r['summary']);
        $this->assertEquals(['A','C'], array_column($r['context']['last_rows'], 'id_member'));
        $this->assertStringContainsString('nilai seri', $r['summary']);
        $r = $this->engine->handleMessage('siapa member dengan jumlah transaksi terbanyak hari ini', 1, 'Administrator');
        $this->assertEquals(['B'], array_column($r['context']['last_rows'], 'id_member'));
        $this->assertSame('transaction_count', $r['metadata']['metric']);
        $top = $this->engine->handleMessage('top 2 member berdasarkan nominal transaksi hari ini', 1, 'Administrator');
        $this->assertEquals(['A','C'], array_column($top['context']['last_rows'], 'id_member'));
        $ambiguous = $this->engine->handleMessage('member yang bertransaksi terbanyak hari ini', 1, 'Administrator');
        $this->assertSame('clarification', $ambiguous['analysis_status']);
        $this->assertSame([], $ambiguous['metadata']['queries']);
        $answer = $this->engine->handleMessage('nominal belanja', 1, 'Administrator', $ambiguous['conversation_uid']);
        $this->assertEquals(['A','C'], array_column($answer['context']['last_rows'], 'id_member'));
    }

    public function testFreshPresenceQueriesSeeNewTransactionsWhileContextDoesNotQuery(): void
    {
        $this->pos->query('DELETE FROM tbltransaksipenjualan'); // Isolated SQLite only.
        $first = $this->engine->handleMessage('hari ini apakah ada transaksi penjualan?', 1, 'Administrator');
        $this->assertTrue($first['metadata']['empty_result']);
        $this->pos->query("INSERT INTO tbltransaksipenjualan VALUES ('new','A','2026-10-17',20,20,'NORMAL')");
        $this->provider->replies = []; // Online analysis plan explicitly requests fresh data.
        $fresh = $this->engine->handleMessage('hari ini apakah ada transaksi penjualan?', 1, 'Administrator', $first['conversation_uid']);
        $this->assertFalse($fresh['metadata']['empty_result']);
        $this->assertCount(1, $fresh['metadata']['queries']);
        $this->assertSame(['text'], array_column($fresh['blocks'], 'type'));
        $this->assertFalse((bool) array_filter($fresh['metadata']['actions']));
        $this->provider->replies = [['parsed_json' => ['decision'=>'context','reply'=>'Periode laporan.','report_ref'=>$fresh['metadata']['report_ref'],'context_subject'=>'period']]];
        $context = $this->engine->handleMessage('itu tadi data kapan?', 1, 'Administrator', $fresh['conversation_uid']);
        $this->assertSame([], $context['metadata']['queries']);
        $this->assertStringContainsString('17 Oktober 2026', $context['summary']);
        $this->provider->replies = [];
        $refresh = $this->engine->handleMessage('cek ulang sekarang', 1, 'Administrator', $fresh['conversation_uid']);
        $this->assertCount(1, $refresh['metadata']['queries']);
    }

    public function testFullDateAliasesOrderAndLineageAreAcceptedButMonthlyOuterScopeIsRejected(): void
    {
        $plan = ['year'=>2026, 'month'=>10, 'group_by'=>'day'];
        $valid = [
            'SELECT DATE(p.tanggal) AS hari,SUM(p.grand_total) AS total FROM tbltransaksipenjualan p WHERE '.self::WHERE.' GROUP BY hari',
            'SELECT p.id_member,DATE(p.tanggal) AS hari,SUM(p.grand_total) AS total FROM tbltransaksipenjualan p WHERE '.self::WHERE.' GROUP BY p.id_member,DATE(p.tanggal)',
            "SELECT DATE_FORMAT(p.tanggal,'%Y-%m-%d') AS hari,SUM(p.grand_total) AS total FROM tbltransaksipenjualan p WHERE ".self::WHERE.' GROUP BY hari',
            'WITH s AS (SELECT p.tanggal,p.grand_total FROM tbltransaksipenjualan p WHERE '.self::WHERE.') SELECT DATE(s.tanggal) AS hari,SUM(s.grand_total) AS total FROM s GROUP BY hari',
            'SELECT s.hari,s.total FROM (SELECT DATE(p.tanggal) AS hari,SUM(p.grand_total) AS total FROM tbltransaksipenjualan p WHERE '.self::WHERE.' GROUP BY hari) s',
        ];
        foreach ($valid as $sql) { (new SqlBusinessPolicy())->assertMatches($sql,$plan); $this->assertTrue(true); }
        foreach ([
            'SELECT DATE(p.tanggal) AS id_member,SUM(p.grand_total) FROM tbltransaksipenjualan p WHERE '.self::WHERE.' GROUP BY id_member',
            'SELECT DAY(p.tanggal) AS hari,SUM(p.grand_total) FROM tbltransaksipenjualan p WHERE '.self::WHERE.' GROUP BY hari',
            'SELECT p.id_member,SUM(p.grand_total) FROM tbltransaksipenjualan p WHERE '.self::WHERE.' GROUP BY p.id_member',
            'WITH s AS (SELECT DATE(p.tanggal) AS hari,SUM(p.grand_total) AS total FROM tbltransaksipenjualan p WHERE '.self::WHERE.' GROUP BY hari) SELECT MONTH(s.hari) AS bulan,SUM(s.total) FROM s GROUP BY bulan',
        ] as $sql) {
            try { (new SqlBusinessPolicy())->assertMatches($sql,$plan); $this->fail('Lost date grain accepted'); }
            catch (AnalysisException $e) { $this->assertSame('business_grouping_mismatch',$e->errorCode); }
        }
    }

    public function testDailySemanticRepairUsesTheSameSingleRetryBudgetAndSavesRejectedSql(): void
    {
        $bad = 'SELECT MONTH(p.tanggal) AS hari,SUM(p.grand_total) AS total FROM tbltransaksipenjualan p WHERE '.self::WHERE.' GROUP BY MONTH(p.tanggal)';
        $good = str_replace('MONTH(', 'DATE(', $bad);
        $this->provider->replies = [['parsed_json'=>['decision'=>'dynamic']],['parsed_json'=>['sql'=>$bad]],['parsed_json'=>['sql'=>$good]]];
        $r = $this->engine->handleMessage('Analisis khusus omzet Oktober 2026 per hari',1,'Administrator');
        $this->assertTrue($r['success'],$r['summary']);
        $this->assertSame(3,$r['metadata']['provider_metrics']['calls']);
        $this->assertCount(1,$r['metadata']['queries']);
        $this->assertSame($bad,$r['metadata']['sql_diagnostics'][0]['sql']);
        $this->assertFalse($r['metadata']['sql_diagnostics'][0]['executed']);
        $this->provider->replies = [['parsed_json'=>['decision'=>'dynamic']],['parsed_json'=>['sql'=>$bad]],['parsed_json'=>['sql'=>$bad]],['parsed_json'=>['sql'=>$good]]];
        $failed = $this->engine->handleMessage('Analisis khusus omzet Oktober 2026 per hari',1,'Administrator',$r['conversation_uid']);
        $this->assertFalse($failed['success']);
        $this->assertSame('business_grouping_mismatch',$failed['error_code']);
        $this->assertSame(3,$failed['metadata']['provider_metrics']['calls']);
        $this->assertCount(1,$this->provider->replies);
        $this->assertCount(2,$failed['metadata']['sql_diagnostics']);
        $this->assertSame([],$failed['metadata']['queries']);
        $this->assertEquals($r['context'],$failed['context']);
        $stored = (new ConversationService())->getConversation($r['conversation_uid'],1);
        $this->assertSame($bad,end($stored['messages'])['metadata']['sql_diagnostics'][0]['sql']);
        $this->assertStringNotContainsString($bad,json_encode((new AiClientDiagnostics())->present($failed,true)));
        $this->assertSame($failed,(new AiClientDiagnostics())->present($failed,false));
    }

    public function testProviderAndQueryFailuresCannotBecomeEmptyOrSuccessfulMemberReports(): void
    {
        $this->provider->replies = [['error'=>'offline','used_remote'=>false]];
        $r = $this->engine->handleMessage('daftar member yang bertransaksi hari ini',1,'Administrator');
        $this->assertSame('provider_unavailable',$r['error_code']);
        $this->assertSame([],$r['metadata']['queries']);
        $this->pos->query('DROP TABLE tbldata_member');
        $r = $this->engine->handleMessage('daftar member yang bertransaksi hari ini',1,'Administrator');
        $this->assertSame('query_failed',$r['error_code']);
        $this->assertArrayNotHasKey('empty_result',$r['metadata']);
        $this->assertCount(1,$r['metadata']['queries']);
    }

    public function testEmptyMemberReportsAndRealZeroSpendingAreDistinctAndReformatDoesNotQuery(): void
    {
        $empty = $this->engine->handleMessage('daftar member yang bertransaksi tanggal 14 bulan ini',1,'Administrator');
        $this->assertTrue($empty['success'],$empty['summary']);
        $this->assertTrue($empty['metadata']['empty_result']);
        $this->assertSame(['text'],array_column($empty['blocks'],'type'));
        $zero = $this->engine->handleMessage('member yang bertransaksi dengan nominal terbanyak tanggal 16 bulan ini',1,'Administrator');
        $this->assertTrue($zero['success'],$zero['summary']);
        $this->assertFalse($zero['metadata']['empty_result']);
        $this->assertEquals(0,$zero['context']['last_rows'][0]['total_belanja']);
        $again = $this->engine->handleMessage('Tampilkan grafiknya',1,'Administrator',$zero['conversation_uid'],'chart',$zero['metadata']['report_ref']);
        $this->assertTrue($again['success'],$again['summary']);
        $this->assertSame([],$again['metadata']['queries']);
        $this->assertEquals($zero['context']['last_rows'],$again['context']['last_rows']);
        $loaded = (new ConversationService())->getConversation($zero['conversation_uid'],1);
        $this->assertSame('invoice_amount',$loaded['context']['metric']);
    }

    public function testUnsupportedExtraFiltersNeverUseTheMemberTemplate(): void
    {
        foreach (['daftar member yang bertransaksi hari ini dengan nominal di atas 100000','daftar member yang bertransaksi hari ini di cabang A','daftar member yang bertransaksi per hari bulan ini','daftar transaksi member B hari ini'] as $question) {
            $plan = $this->engine->resolveIntentAndPlan($question,['domain'=>'sales','group_by'=>'day']);
            $this->assertSame('dynamic_llm_or_clarify',$plan['intent']);
            $this->assertSame('members',$plan['domain']);
        }
    }

    public function testPresenceNeverDropsExtraFiltersOrExecutesCachedReportQuestions(): void
    {
        $semantics = new \App\Services\AI\RequestSemantics();
        foreach (['apakah ada transaksi hari ini di cabang A','apakah ada transaksi nominal di atas 100000 hari ini','itu tadi ada transaksi pada tanggal berapa?'] as $question) $this->assertFalse($semantics->salesPresence($question));
        $this->assertNull($semantics->memberActivity('itu tadi daftar member yang bertransaksi pada hari apa?'));
        $this->provider->replies = [];
        $result = $this->engine->handleMessage('hari ini apakah ada transaksi penjualan?',1,'Administrator');
        $this->assertCount(1,$result['metadata']['queries']);
        $this->assertStringContainsString('pemeriksaan POS terbaru',$result['summary']);
    }

    public function testMissingDailyDimensionCanBeRepairedWithoutChangingMeasuresOrFilters(): void
    {
        $bad = 'SELECT SUM(p.grand_total) AS total FROM tbltransaksipenjualan p WHERE '.self::WHERE;
        $good = 'SELECT DATE(p.tanggal) AS hari,SUM(p.grand_total) AS total FROM tbltransaksipenjualan p WHERE '.self::WHERE.' GROUP BY DATE(p.tanggal)';
        $this->provider->replies = [['parsed_json'=>['decision'=>'dynamic']],['parsed_json'=>['sql'=>$bad]],['parsed_json'=>['sql'=>$good]]];
        $r = $this->engine->handleMessage('Analisis khusus omzet Oktober 2026 per hari',1,'Administrator');
        $this->assertTrue($r['success'],$r['summary']);
        $this->assertCount(3,$r['context']['last_rows']);
        $this->assertSame(3,$r['metadata']['provider_metrics']['calls']);
        $this->assertCount(1,$r['metadata']['queries']);
    }

    public function testSyntaxRepairFollowedByWrongGrainNeverGetsAThirdGeneration(): void
    {
        $monthly = 'SELECT MONTH(p.tanggal) AS hari,SUM(p.grand_total) AS total FROM tbltransaksipenjualan p WHERE '.self::WHERE.' GROUP BY MONTH(p.tanggal)';
        $this->provider->replies = [['parsed_json'=>['decision'=>'dynamic']],['parsed_json'=>['sql'=>$monthly.' UNION ALL '.$monthly]],['parsed_json'=>['sql'=>$monthly]],['parsed_json'=>['sql'=>str_replace('MONTH(','DATE(',$monthly)]]];
        $r = $this->engine->handleMessage('Analisis khusus omzet Oktober 2026 per hari',1,'Administrator');
        $this->assertFalse($r['success']);
        $this->assertSame('business_grouping_mismatch',$r['error_code']);
        $this->assertSame(3,$r['metadata']['provider_metrics']['calls']);
        $this->assertCount(1,$this->provider->replies);
        $this->assertSame([],$r['metadata']['queries']);
    }
}
