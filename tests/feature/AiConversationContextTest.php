<?php

namespace Tests\Feature;

use App\Services\AI\{AiOrchestrator, BusinessClock, ConversationService, PosQueryExecutor, PosSchemaService, BusinessSemanticLayer};
use CodeIgniter\Test\{CIUnitTestCase, DatabaseTestTrait};
use Tests\Support\FakeOnlineProvider;

final class AiConversationContextTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';
    private FakeOnlineProvider $provider;
    private AiOrchestrator $engine;

    protected function setUp(): void
    {
        parent::setUp();
        db_connect()->table('users')->insert(['id' => 1, 'username' => 'context-test', 'display_name' => 'Context Test', 'password_hash' => password_hash('Testing123!', PASSWORD_DEFAULT), 'role' => 'Administrator', 'is_active' => 1]);
        $db = \Config\Database::connect(config('Database')->tests, false);
        $db->initialize();
        $db->query('CREATE TABLE tbltransaksipenjualan (id_transaksi TEXT, tanggal TEXT, grand_total REAL, total_kotor REAL, source_type TEXT, id_member TEXT)');
        $db->query('CREATE TABLE tbldata_member (id_member TEXT, nama TEXT, hapus INTEGER)');
        $db->query("INSERT INTO tbldata_member VALUES ('M1','Member Alpha',0),('M2','Member Kembar',0),('M3','Member Kembar',0),('M4','Member Dihapus',1)");
        $db->query("INSERT INTO tbltransaksipenjualan VALUES ('o1','2026-10-01',100,110,'NORMAL','M1'),('o2','2026-10-02',300,310,NULL,'M1'),('s1','2026-09-01',70,80,'NORMAL','M1'),('legacy','2026-10-01',999,999,'LEGACY_HISTORY','M1'),('dec','2026-12-01',50,50,'NORMAL','M1')");
        $this->provider = new FakeOnlineProvider();
        $this->engine = new AiOrchestrator(queryExecutor: new PosQueryExecutor(customPosConnection: $db), providerClient: $this->provider, clock: new BusinessClock('Asia/Jakarta', new \DateTimeImmutable('2026-10-17')));
    }

    public function testDailyReportRetainsGroupingAndChangesOnlyRequestedPeriod(): void
    {
        $first = $this->engine->handleMessage('lapran penjualan bulan ini, pisahkan perhari', 1, 'Administrator');
        $this->assertTrue($first['success']);
        $this->assertSame('sales_daily', $first['metadata']['intent']);
        $this->assertSame('table', $first['metadata']['presentation']);
        $this->assertCount(2, $first['context']['last_rows']);
        $this->assertStringContainsString('Rp400', $first['summary']);
        $next = $this->engine->handleMessage('penjualan bulan kemarin', 1, 'Administrator', $first['conversation_uid']);
        $this->assertTrue($next['success']);
        $this->assertSame(9, $next['context']['month']);
        $this->assertSame('day', $next['context']['group_by']);
        $this->assertStringContainsString('September 2026', $next['summary']);
        $this->assertSame(70.0, (float) $next['context']['last_rows'][0]['omzet_faktur']);
        $again = $this->engine->handleMessage('laporan penjualan bulan kemarin, pisahkan perhari', 1, 'Administrator', $first['conversation_uid']);
        $this->assertEquals($next['context']['last_rows'], $again['context']['last_rows']);
        foreach ([$first, $next, $again] as $r) $this->assertStringNotContainsString('LEGACY_HISTORY', $r['metadata']['queries'][0]['sql']);
    }

    public function testEmptyDailyReportNamesPeriodAndKeepsDatasetAvailable(): void
    {
        $r = $this->engine->handleMessage('laporan penjualan Agustus 2026 per hari', 1, 'Administrator');
        $this->assertTrue($r['success']);
        $this->assertStringContainsString('Agustus 2026', $r['summary']);
        $this->assertStringContainsString('tidak ditemukan transaksi', $r['summary']);
        $this->assertSame([], $r['context']['last_rows']);
        $next = $this->engine->handleMessage('Tampilkan grafiknya.', 1, 'Administrator', $r['conversation_uid']);
        $this->assertTrue($next['success']);
        $this->assertSame([], $next['metadata']['queries']);
    }

    public function testMemberPurchaseInheritsMonthAndNeverRoutesToSupplierEvenForCashier(): void
    {
        $r = $this->engine->handleMessage('laporan penjualan bulan ini perhari', 1, 'Administrator');
        $member = $this->engine->handleMessage('Pembelian member Member Alpha berapa?', 1, 'Kasir', $r['conversation_uid']);
        $this->assertTrue($member['success'], $member['summary']);
        $this->assertSame('member_spend', $member['metadata']['intent']);
        $this->assertSame(10, $member['context']['month']);
        $this->assertSame(400.0, $member['context']['last_rows'][0]['total_belanja']);
        $this->assertStringContainsString('Oktober 2026', $member['summary']);
        $this->assertCount(2, $member['metadata']['queries']);
        $next = $this->engine->handleMessage('Kalau belanjanya bulan kemarin?', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame(70.0, $next['context']['last_rows'][0]['total_belanja']);
        $this->assertSame('Member Alpha', $next['context']['focus_member']['nama']);
        $this->assertNotEmpty($next['context']['member_identity']['ref']);
    }

    public function testDuplicateMemberAsksForIdentityAndConsumesReplyAfterReload(): void
    {
        $r = $this->engine->handleMessage('Pembelian member Member Kembar bulan ini berapa?', 1, 'Administrator');
        $this->assertSame('clarification', $r['analysis_status']);
        $this->assertStringContainsString('M2', $r['summary']);
        $this->assertStringContainsString('M3', $r['summary']);
        $this->assertSame('member', $r['context']['pending_request']['slot']);
        $this->assertNotContains('kpi', array_column($r['blocks'], 'type'));
        $stored = (new ConversationService())->getConversation($r['conversation_uid'], 1);
        $this->assertEquals($r['context'], $stored['context']);
        $next = $this->engine->handleMessage('M2', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame('member_spend', $next['metadata']['intent']);
        $this->assertSame('M2', $next['context']['focus_member']['id_member']);
        $this->assertSame([], $next['context']['last_rows']);
        $this->assertSame(10, $next['context']['month']);
        $this->assertArrayNotHasKey('pending_request', $next['context']);
        $this->assertStringContainsString('tidak memiliki transaksi', $next['summary']);
    }

    public function testUnknownMemberDoesNotInventZeroAndPreservesPriorResult(): void
    {
        $first = $this->engine->handleMessage('Penjualan bulan ini perhari', 1, 'Administrator');
        $r = $this->engine->handleMessage('Pembelian member TidakAda berapa?', 1, 'Administrator', $first['conversation_uid']);
        $this->assertSame('clarification', $r['analysis_status']);
        $this->assertStringNotContainsString('Rp0', $r['summary']);
        $this->assertEquals($first['context']['last_rows'], $r['context']['last_rows']);
        $next = $this->engine->handleMessage('Member Alpha', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame('Member Alpha', $next['context']['last_rows'][0]['nama']);
        $this->assertSame(400.0, $next['context']['last_rows'][0]['total_belanja']);
        $payload = json_decode(end($this->provider->calls)[1]['content'], true);
        $this->assertSame('Member Alpha', $payload['latest_user_message']);
        $this->assertStringNotContainsString('TidakAda', $payload['question']);
        $this->assertStringContainsString('Member Alpha', $payload['question']);
        $followup = $this->engine->handleMessage('Kalau bulan lalu?', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame('member_spend', $followup['metadata']['intent']);
        $this->assertSame(70.0, $followup['context']['last_rows'][0]['total_belanja']);
    }

    public function testMissingPeriodAsksOnceThenUsesShortAnswer(): void
    {
        $r = $this->engine->handleMessage('Pembelian member Member Alpha berapa?', 1, 'Administrator');
        $this->assertSame('clarification', $r['analysis_status']);
        $this->assertSame([], $r['metadata']['queries']);
        $next = $this->engine->handleMessage('bulan lalu', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame('member_spend', $next['metadata']['intent']);
        $this->assertSame(9, $next['context']['month']);
        $this->assertSame(70.0, $next['context']['last_rows'][0]['total_belanja']);
    }

    public function testExplicitYearClearsInheritedMonthAndNewChatHasNoMemberReference(): void
    {
        $r = $this->engine->handleMessage('Pembelian member Member Alpha bulan ini berapa?', 1, 'Administrator');
        $next = $this->engine->handleMessage('Berapa belanjanya tahun 2026?', 1, 'Administrator', $r['conversation_uid']);
        $this->assertTrue($next['success'],json_encode($next['metadata']['plan_diagnostics']??$next['summary']));
        $this->assertNull($next['context']['month']);
        $this->assertSame(520.0, $next['context']['last_rows'][0]['total_belanja']);
        $fresh = $this->engine->handleMessage('Berapa belanjanya?', 1, 'Administrator');
        $this->assertSame('clarification', $fresh['analysis_status']);
        $this->assertSame([], $fresh['metadata']['queries']);
    }

    public function testNewTopicCancelsPendingRequestAndPreviousMonthUsesActivePeriod(): void
    {
        $r = $this->engine->handleMessage('Pembelian member Member Kembar bulan ini berapa?', 1, 'Administrator');
        $next = $this->engine->handleMessage('Laporan penjualan September 2026 perhari', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame('sales_daily', $next['metadata']['intent']);
        $this->assertArrayNotHasKey('pending_request', $next['context']);
        $previous = $this->engine->handleMessage('Penjualan bulan sebelumnya', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame(8, $previous['context']['month']);
        $january = new AiOrchestrator(providerClient: $this->provider, clock: new BusinessClock('Asia/Jakarta', new \DateTimeImmutable('2027-01-02')));
        $plan = $january->resolveIntentAndPlan('Penjualan bulan kemarin perhari', []);
        $this->assertSame(12, $plan['month']);
        $this->assertSame(2026, $plan['year']);
        $pending = $this->engine->handleMessage('Pembelian member Member Kembar bulan ini berapa?', 1, 'Administrator');
        $changed = $this->engine->handleMessage('Pembelian member Member Alpha bulan lalu berapa?', 1, 'Administrator', $pending['conversation_uid']);
        $this->assertSame('member_spend', $changed['metadata']['intent']);
        $this->assertSame(70.0, $changed['context']['last_rows'][0]['total_belanja']);
    }

    public function testOnlineAndDynamicClarificationsKeepConversationWithoutSendingReports(): void
    {
        $first = $this->engine->handleMessage('Penjualan bulan ini perhari', 1, 'Administrator');
        $this->provider->replies = [['parsed_json' => ['decision' => 'dynamic']], ['parsed_json' => ['decision' => 'clarify', 'question' => 'Kategori produk yang dimaksud apa?']]];
        $r = $this->engine->handleMessage('Analisis kategori yang sesuai', 1, 'Administrator', $first['conversation_uid']);
        $this->assertSame('clarification', $r['analysis_status']);
        $this->assertSame([], $r['metadata']['queries']);
        $this->assertEquals($first['context']['last_rows'], $r['context']['last_rows']);
        $messages = (new ConversationService())->getUnderstandingMessages((int) (new ConversationService())->getConversation($r['conversation_uid'], 1)['id'], 1, $r['conversation_uid']);
        $text = json_encode($messages);
        $this->assertStringContainsString('Kategori produk', $text);
        $this->assertStringNotContainsString('Rp400', $text);
        $this->assertStringNotContainsString('SELECT', $text);
    }

    public function testSchemaAndSemanticsAgreeOnNormalTransactions(): void
    {
        $schema = new PosSchemaService();
        $this->assertSame("COALESCE(source_type, 'NORMAL') = 'NORMAL'", $schema->catalog()['tbltransaksipenjualan']['status_filter']);
        $domains = (new BusinessSemanticLayer())->detectDomains('Pembelian member Member Alpha berapa?');
        $this->assertContains('sales', $domains);
        $this->assertContains('members', $domains);
        $this->assertNotContains('purchasing_payables', $domains);
    }

    public function testMemberListIsNotMistakenForSingleMemberSpending(): void
    {
        foreach (['Member yang pernah melakukan transaksi di bulan Oktober 2026', 'Berapa jumlah member bulan ini?', 'Daftar pembelian member Member Alpha bulan ini', 'Berapa diskon member Member Alpha bulan ini?', 'Grafik belanja member Member Alpha per bulan tahun 2026'] as $q) {
            $plan = $this->engine->resolveIntentAndPlan($q, []);
            $this->assertNotSame('member_spend', $plan['intent']);
            $this->assertNotSame('purchasing_payables', $plan['domain']);
        }
    }

    public function testBarePeriodFollowupKeepsMemberAndOfflinePreservesPendingAnswer(): void
    {
        $first = $this->engine->handleMessage('Pembelian member Member Alpha bulan ini berapa?', 1, 'Administrator');
        $next = $this->engine->handleMessage('Kalau bulan lalu?', 1, 'Administrator', $first['conversation_uid']);
        $this->assertSame('member_spend', $next['metadata']['intent']);
        $this->assertSame(70.0, $next['context']['last_rows'][0]['total_belanja']);
        $ambiguous = $this->engine->handleMessage('Pembelian member Member Kembar berapa?', 1, 'Administrator', $first['conversation_uid']);
        $this->provider->replies = [['used_remote' => false, 'error' => 'offline']];
        $failed = $this->engine->handleMessage('M2', 1, 'Administrator', $first['conversation_uid']);
        $this->assertFalse($failed['success']);
        $this->assertSame('provider_unavailable', $failed['error_code']);
        $this->assertEquals($ambiguous['context'], $failed['context']);
        $this->assertSame([], $failed['metadata']['queries']);
    }

    public function testPosMemberSuggestionsAreNotSentBackToProvider(): void
    {
        $r = $this->engine->handleMessage('Pembelian member Member Kembar bulan ini berapa?', 1, 'Administrator');
        $this->engine->handleMessage('M2', 1, 'Administrator', $r['conversation_uid']);
        $prompt = $this->provider->calls[1][1]['content'];
        $this->assertStringNotContainsString('M3', $prompt);
        $payload = json_decode($prompt, true);
        $this->assertArrayNotHasKey('clarification', $payload['period_context']['pending_request']);
        $this->assertArrayNotHasKey('last_rows', $payload['period_context']);
    }

    public function testMemberAndPeriodCanBeClarifiedInSeparateTurns(): void
    {
        $r = $this->engine->handleMessage('Pembelian member berapa?', 1, 'Administrator');
        $this->assertSame('member', $r['context']['pending_request']['slot']);
        $r = $this->engine->handleMessage('M1', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame('period', $r['context']['pending_request']['slot']);
        $r = $this->engine->handleMessage('bulan ini', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame('member_spend', $r['metadata']['intent']);
        $this->assertSame('M1', $r['context']['last_rows'][0]['id_member']);
        $this->assertSame(400.0, $r['context']['last_rows'][0]['total_belanja']);
    }

    public function testOnlineMemberClarificationUsesItsSlotWhenAnswerIsAnId(): void
    {
        $this->provider->replies = [['parsed_json' => ['decision' => 'clarify', 'question' => 'Cho ID hoac ten day du?', 'slot' => 'member']]];
        $r = $this->engine->handleMessage('Pembelian member Alpha bulan ini berapa?', 1, 'Administrator');
        $this->assertSame('member', $r['context']['pending_request']['slot']);
        $this->assertSame('Member yang dimaksud siapa? Sebutkan nama lengkap atau ID member.', $r['summary']);
        $r = $this->engine->handleMessage('M1', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame(400.0, $r['context']['last_rows'][0]['total_belanja']);
    }

    public function testPeriodChangeDuringMemberClarificationIsNotLost(): void
    {
        $r = $this->engine->handleMessage('Pembelian member Member Kembar bulan ini berapa?', 1, 'Administrator');
        $r = $this->engine->handleMessage('September 2026', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame('clarification', $r['analysis_status']);
        $r = $this->engine->handleMessage('M2', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame('member_spend', $r['metadata']['intent']);
        $this->assertSame(9, $r['context']['month']);
    }

    public function testDateWindowPersistsAndExplicitMonthReplacesIt(): void
    {
        $r = $this->engine->handleMessage('Penjualan tanggal 2026-10-01 per hari', 1, 'Administrator');
        $this->assertTrue($r['success']);
        $this->assertSame('2026-10-01', $r['context']['date_start']);
        $this->assertSame('2026-10-02', $r['context']['date_end']);
        $this->assertStringContainsString('1 Oktober 2026', $r['summary']);
        $this->assertStringContainsString('Rp100', $r['summary']);
        $next = $this->engine->handleMessage('Pembelian member Member Alpha berapa?', 1, 'Administrator', $r['conversation_uid']);
        $this->assertSame(100.0, $next['context']['last_rows'][0]['total_belanja']);
        $next = $this->engine->handleMessage('Kalau bulan ini?', 1, 'Administrator', $r['conversation_uid']);
        $this->assertNull($next['context']['date_start']);
        $this->assertSame(400.0, $next['context']['last_rows'][0]['total_belanja']);
        $today = $this->engine->resolveIntentAndPlan('Pembelian member Member Alpha hari ini berapa?', []);
        $this->assertSame('2026-10-17', $today['date_start']);
        $this->assertSame('Member Alpha', $today['member_query']);
        $this->assertArrayNotHasKey('clarification_question', $today);
        $natural = $this->engine->resolveIntentAndPlan('Penjualan 1 Oktober 2026 sampai 2 Oktober 2026 perhari', []);
        $this->assertSame('2026-10-01', $natural['date_start']);
        $this->assertSame('2026-10-03', $natural['date_end']);
        $invalid = $this->engine->handleMessage('Penjualan 31 Februari 2026 perhari', 1, 'Administrator');
        $this->assertSame('clarification', $invalid['analysis_status']);
        $this->assertSame([], $invalid['metadata']['queries']);
    }

    public function testDynamicSalesCannotSilentlyOmitOrBroadenOperationalFilter(): void
    {
        foreach (["SELECT COUNT(id_transaksi) AS jumlah FROM tbltransaksipenjualan", "SELECT COUNT(id_transaksi) AS jumlah FROM tbltransaksipenjualan WHERE COALESCE(source_type,'NORMAL') IN ('NORMAL','LEGACY_HISTORY')"] as $sql) {
            $this->provider->replies = [['parsed_json' => ['decision' => 'dynamic']], ['parsed_json' => ['sql' => $sql, 'title' => 'Transaksi', 'chart_type' => 'none']]];
            $r = $this->engine->handleMessage('Hitung transaksi khusus bulan ini', 1, 'Administrator');
            $this->assertFalse($r['success']);
            $this->assertSame('business_filter_mismatch', $r['error_code']);
            $this->assertSame([], $r['metadata']['queries']);
        }
        $this->provider->replies = [['parsed_json' => ['decision' => 'dynamic']], ['parsed_json' => ['sql' => "SELECT COUNT(id_transaksi) AS jumlah FROM tbltransaksipenjualan WHERE COALESCE(source_type,'NORMAL') = 'NORMAL' AND tanggal >= '2026-10-01' AND tanggal < '2026-11-01'", 'title' => 'Transaksi Oktober 2026', 'chart_type' => 'none']]];
        $r = $this->engine->handleMessage('Hitung transaksi khusus bulan ini', 1, 'Administrator');
        $this->assertTrue($r['success']);
        $this->assertSame(2, (int) $r['context']['last_rows'][0]['jumlah']);
    }

    public function testExistingDynamicConversationRecoversItsSavedMonthBeforeMemberFollowup(): void
    {
        $service = new ConversationService();
        $conversation = $service->createConversation(1);
        $context = ['intent' => 'dynamic_llm_sql', 'domain' => 'sales', 'year' => 2026, 'month' => null, 'last_rows' => [], 'last_queries' => [
            ['success' => true, 'sql' => "SELECT DATE( p . tanggal ) AS tanggal, COUNT( * ) AS jumlah FROM tbltransaksipenjualan p WHERE COALESCE(p.source_type,'NORMAL')='NORMAL' AND p . tanggal >= '2026-09-01' AND p . tanggal < '2026-10-01' GROUP BY DATE( p . tanggal ) ORDER BY tanggal LIMIT 100", 'row_count' => 0],
        ]];
        $service->appendTurn((int) $conversation['id'], 'Penjualan bulan kemarin perhari', 'Tidak ada data', [], [], $context, null, 1, $conversation['conversation_uid']);
        $r = $this->engine->handleMessage('Pembelian member Member Alpha berapa?', 1, 'Administrator', $conversation['conversation_uid']);
        $this->assertTrue($r['success']);
        $this->assertSame('member_spend', $r['metadata']['intent']);
        $this->assertSame(9, $r['context']['month']);
        $this->assertSame(70.0, $r['context']['last_rows'][0]['total_belanja']);
    }
}
