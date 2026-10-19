<?php
namespace Tests\Feature;

use App\Services\AI\{AiOrchestrator, BusinessClock, ConversationService, PosQueryExecutor, SqlSafetyValidator};
use CodeIgniter\Test\{CIUnitTestCase, DatabaseTestTrait};
use Tests\Support\FakeOnlineProvider;

final class AiSqlRepairTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';
    private FakeOnlineProvider $provider;
    private AiOrchestrator $engine;
    private string $validSql = "SELECT SUM(grand_total) AS omzet FROM tbltransaksipenjualan WHERE COALESCE(source_type,'NORMAL') = 'NORMAL' AND tanggal >= '2026-09-01' AND tanggal < '2026-10-01'";

    protected function setUp(): void
    {
        parent::setUp();
        db_connect()->table('users')->insert(['id' => 1, 'username' => 'repair-test', 'display_name' => 'Repair', 'password_hash' => password_hash('Testing123!', PASSWORD_DEFAULT), 'role' => 'Administrator', 'is_active' => 1]);
        $pos = \Config\Database::connect(config('Database')->tests, false);
        $pos->initialize();
        $pos->query('CREATE TABLE tbltransaksipenjualan (id_transaksi TEXT, tanggal TEXT, grand_total REAL, source_type TEXT)');
        $pos->query("INSERT INTO tbltransaksipenjualan VALUES ('n1','2026-09-01',100,'NORMAL'),('n2','2026-09-30',200,NULL),('other','2026-08-01',90,'NORMAL'),('legacy','2026-09-02',999,'LEGACY_HISTORY')");
        $this->provider = new FakeOnlineProvider();
        $this->engine = new AiOrchestrator(queryExecutor: new PosQueryExecutor(customPosConnection: $pos), providerClient: $this->provider, clock: new BusinessClock('Asia/Jakarta', new \DateTimeImmutable('2026-10-17')));
    }

    private function sqlReply(string $sql): array { return ['parsed_json' => ['title' => 'Omzet September 2026', 'sql' => $sql, 'chart_type' => 'none']]; }
    private function runSqlReplies(array $replies, ?string $uid = null): array
    {
        $this->provider->replies = array_merge([['parsed_json' => ['decision' => 'dynamic']]], $replies);
        return $this->engine->handleMessage('Analisis khusus omzet September 2026', 1, 'Administrator', $uid);
    }
    private function unsupported(): string { return $this->validSql . ' UNION ALL ' . $this->validSql; }

    public function testCapabilitiesAndUnsupportedReasonAreExplicit(): void
    {
        $v = new SqlSafetyValidator();
        $r = $v->validate($this->unsupported(), 'Administrator');
        $this->assertFalse($r['valid']);
        $this->assertTrue($r['retryable']);
        $this->assertSame('unsupported_set_operation', $r['error_code']);
        $this->assertSame(['tbltransaksipenjualan'], $r['tables']);
        $this->assertSame(1, $v->capabilities()['repair']['max_attempts']);
    }

    public function testOneRepairExecutesOnlyReplacementAndKeepsSuccessfulDatasetExportable(): void
    {
        $r = $this->runSqlReplies([$this->sqlReply($this->unsupported()), $this->sqlReply($this->validSql)]);
        $this->assertTrue($r['success'], $r['summary']);
        $this->assertSame(300.0, (float) $r['context']['last_rows'][0]['omzet']);
        $this->assertTrue($r['metadata']['sql_repair_used']);
        $this->assertCount(3, $this->provider->calls); // Routing + generation + one repair.
        $this->assertCount(2, $r['metadata']['sql_planning_attempts']);
        $this->assertCount(1, $r['metadata']['queries']);
        $this->assertTrue($r['metadata']['queries'][0]['success']);
        $this->assertCount(1, $r['context']['last_queries']);
        $this->assertSame(1, db_connect()->table('ai_query_logs')->countAllResults());
        $initial = $this->provider->calls[1][0]['content'];
        $this->assertStringContainsString('KEMAMPUAN VALIDATOR', $initial);
        $this->assertStringContainsString('WITH RECURSIVE', $initial);
        $repairMessages = $this->provider->calls[2];
        $this->assertCount(2, $repairMessages);
        $payload = json_decode($repairMessages[1]['content'], true);
        $this->assertSame('unsupported_set_operation', $payload['rejection']['code']);
        $this->assertSame(1, $payload['max_repair_attempts']);
        foreach (['last_rows','last_kpis','last_queries'] as $key) $this->assertArrayNotHasKey($key, $payload['scope']);
        $stored = (new ConversationService())->getConversation($r['conversation_uid'], 1);
        $this->assertEquals($r['context'], $stored['context']);
    }

    public function testRecursiveCalendarMayBeRewrittenWithoutEnablingRecursiveSql(): void
    {
        $bad = 'WITH RECURSIVE hari AS (SELECT 1 AS hari UNION ALL SELECT hari + 1 FROM hari WHERE hari < 31) ' . $this->validSql;
        $r = $this->runSqlReplies([$this->sqlReply($bad), $this->sqlReply($this->validSql)]);
        $this->assertTrue($r['success'], $r['summary']);
        $this->assertSame('unsupported_recursive_cte', $r['metadata']['sql_planning_attempts'][0]['reason_code']);
        $this->assertStringNotContainsString('RECURSIVE', $r['metadata']['queries'][0]['sql']);
        $this->assertFalse((new SqlSafetyValidator())->validate($bad, 'Administrator')['valid']);
    }

    public function testRepeatedUnsupportedSqlStopsAtOneRepairAndRetainsPreviousSuccess(): void
    {
        $first = $this->runSqlReplies([$this->sqlReply($this->validSql)]);
        $beforeCalls = count($this->provider->calls);
        $r = $this->runSqlReplies([$this->sqlReply($this->unsupported()), $this->sqlReply($this->unsupported()), $this->sqlReply($this->validSql)], $first['conversation_uid']);
        $this->assertFalse($r['success']);
        $this->assertSame('sql_validation_failed', $r['error_code']);
        $this->assertStringContainsString('satu metode alternatif', $r['summary']);
        $this->assertCount($beforeCalls + 3, $this->provider->calls);
        $this->assertCount(1, $this->provider->replies); // A third generation is never consumed.
        $this->assertCount(2, $r['metadata']['sql_planning_attempts']);
        $this->assertEquals($first['context'], $r['context']);
        $this->assertNotContains('kpi', array_column($r['blocks'], 'type'));
    }

    public function testUnsupportedSyntaxCannotHideSecurityViolationAnywhere(): void
    {
        $prefix = 'WITH RECURSIVE hari AS (SELECT 1 AS hari UNION ALL SELECT hari + 1 FROM hari WHERE hari < 31) ';
        $cases = [
            $prefix . 'SELECT username FROM tbluser',
            $this->validSql . ' UNION ALL SELECT total_bayar FROM tblgaji',
            $prefix . 'SELECT id_transaksi FROM other_schema.tbltransaksipenjualan',
            $prefix . 'SELECT password FROM tbltransaksipenjualan',
            $prefix . 'SELECT SLEEP(1) FROM tbltransaksipenjualan',
            $prefix . 'SELECT arbitrary_udf(grand_total) FROM tbltransaksipenjualan',
            $prefix . 'SELECT * FROM tbltransaksipenjualan',
            $prefix . 'SELECT p.id_transaksi FROM tbltransaksipenjualan p, tbluser u',
            $prefix . 'SELECT p.id_transaksi FROM tbltransaksipenjualan p JOIN tbluser u ON 1=1',
            $prefix . 'SELECT SUM(p.grand_total) FROM tbltransaksipenjualan p JOIN tbltransaksipenjualan_detail d ON p.id_transaksi=d.id_transaksi',
            $prefix . 'SELECT id_transaksi FROM tbltransaksipenjualan; DELETE FROM tbltransaksipenjualan',
            $prefix . 'SELECT id_transaksi FROM tbltransaksipenjualan /*! UNION SELECT username FROM tbluser */',
        ];
        $v = new SqlSafetyValidator();
        foreach ($cases as $sql) {
            $validation = $v->validate($sql, 'Kasir');
            $this->assertFalse($validation['valid'], $sql);
            $this->assertFalse($validation['retryable'] ?? false, $sql);
        }
        $r = $this->runSqlReplies([$this->sqlReply($cases[0]), $this->sqlReply($this->validSql)]);
        $this->assertFalse($r['success']);
        $this->assertCount(2, $this->provider->calls);
        $this->assertCount(1, $this->provider->replies);
    }

    public function testRoleDeniedDataDoesNotReceiveRepairEvenWithSetOperations(): void
    {
        $v = new SqlSafetyValidator();
        $r = $v->validate('SELECT id_produk FROM tbldatabarang UNION ALL SELECT id_gaji FROM tblgaji', 'Gudang');
        $this->assertFalse($r['retryable'] ?? false);
        $this->assertSame('sql_security_rejected', $r['error_code']);
    }

    public function testRuntimeAndBusinessFilterFailuresNeverTriggerRepair(): void
    {
        foreach ([str_replace('SUM(grand_total)', 'SUM(total_kotor)', $this->validSql) => 'query_failed', str_replace("COALESCE(source_type,'NORMAL') = 'NORMAL' AND ", '', $this->validSql) => 'business_filter_mismatch'] as $sql => $expected) {
            $before = count($this->provider->calls);
            $r = $this->runSqlReplies([$this->sqlReply($sql), $this->sqlReply($this->validSql)]);
            $this->assertFalse($r['success']);
            $this->assertSame($expected, $r['error_code']);
            $this->assertCount($before + 2, $this->provider->calls);
            $this->assertCount(1, $this->provider->replies);
        }
    }

    public function testChangedPeriodMeasureTableAndNumericFilterAreRejectedBeforeExecution(): void
    {
        $replacements = [str_replace('2026-09-01', '2026-08-01', $this->validSql), str_replace('SUM(grand_total)', 'COUNT(grand_total)', $this->validSql), 'SELECT COUNT(id_produk) AS jumlah FROM tbldatabarang', $this->validSql . ' GROUP BY DATE(tanggal)', $this->validSql . ' AND grand_total > 200'];
        foreach ($replacements as $sql) {
            $r = $this->runSqlReplies([$this->sqlReply($this->unsupported()), $this->sqlReply($sql)]);
            $this->assertFalse($r['success']);
            $this->assertSame('sql_repair_scope_mismatch', $r['error_code']);
            $this->assertSame([], $r['metadata']['queries']);
        }
        $original = $this->validSql . ' AND grand_total > 100';
        $r = $this->runSqlReplies([$this->sqlReply($original . ' UNION ALL ' . $original), $this->sqlReply($this->validSql . ' AND grand_total > 200')]);
        $this->assertSame('sql_repair_scope_mismatch', $r['error_code']);
        $this->assertSame(0, db_connect()->table('ai_query_logs')->countAllResults());
        $grouped = $this->validSql . ' GROUP BY DATE(tanggal)';
        $r = $this->runSqlReplies([$this->sqlReply($grouped . ' UNION ALL ' . $grouped), $this->sqlReply($this->validSql)]);
        $this->assertSame('sql_repair_scope_mismatch', $r['error_code']);
        $this->assertSame([], $r['metadata']['queries']);
    }

    public function testProviderFailureAndMalformedRepairStopWithoutAnotherAttempt(): void
    {
        foreach ([['error' => 'timeout', 'used_remote' => false], ['parsed_json' => null], $this->sqlReply('')] as $reply) {
            $before = count($this->provider->calls);
            $r = $this->runSqlReplies([$this->sqlReply($this->unsupported()), $reply, $this->sqlReply($this->validSql)]);
            $this->assertFalse($r['success']);
            $this->assertContains($r['error_code'], ['provider_unavailable','provider_invalid_response']);
            $this->assertCount($before + 3, $this->provider->calls);
            $this->assertSame([], $r['metadata']['queries']);
            $this->assertCount(2, $r['metadata']['sql_planning_attempts']);
        }
    }

    public function testUnsupportedAndTechnicalQuestionsDoNotAskUserToConfirmCapabilities(): void
    {
        foreach ([['parsed_json' => ['decision' => 'unsupported']], ['parsed_json' => ['decision' => 'clarify', 'question' => 'Apakah database mendukung WITH RECURSIVE?']]] as $reply) {
            $r = $this->runSqlReplies([$this->sqlReply($this->unsupported()), $reply]);
            $this->assertFalse($r['success']);
            $this->assertSame('analysis_not_supported', $r['error_code']);
            $this->assertNotContains('table', array_column($r['blocks'], 'type'));
            $this->assertSame([], $r['metadata']['queries']);
            $this->assertArrayNotHasKey('pending_request', $r['context']);
        }
    }

    public function testBusinessClarificationStillWorksAfterOneRepair(): void
    {
        $r = $this->runSqlReplies([$this->sqlReply($this->unsupported()), ['parsed_json' => ['decision' => 'clarify', 'question' => 'Kategori produk yang dimaksud apa?', 'slot' => 'filter']]]);
        $this->assertSame('clarification', $r['analysis_status']);
        $this->assertSame([], $r['metadata']['queries']);
        $this->assertTrue($r['metadata']['sql_repair_used']);
        $this->assertSame('filter', $r['context']['pending_request']['slot']);
    }

    public function testRoutingDoesNotAskTechnicalCapabilityQuestionsEither(): void
    {
        $this->provider->replies = [['parsed_json' => ['decision' => 'clarify', 'question' => 'Apakah server mendukung CTE?', 'slot' => null]]];
        $r = $this->engine->handleMessage('Analisis omzet September 2026', 1, 'Administrator');
        $this->assertFalse($r['success']);
        $this->assertSame('analysis_not_supported', $r['error_code']);
        $this->assertCount(1, $this->provider->calls);
        $this->assertSame([], $r['metadata']['queries']);
        $this->assertArrayNotHasKey('pending_request', $r['context']);
    }
}
