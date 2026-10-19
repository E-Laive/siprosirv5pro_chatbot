<?php
namespace Tests\Feature;

use App\Services\AI\{AiOrchestrator, BusinessClock, ConversationService, PosQueryExecutor, ReportContext};
use CodeIgniter\Test\{CIUnitTestCase, DatabaseTestTrait, FeatureTestTrait};
use Tests\Support\FakeOnlineProvider;

final class AiNaturalConversationTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait, \Tests\Support\AuthenticatedSession;
    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';
    private FakeOnlineProvider $provider;
    private AiOrchestrator $engine;
    private ConversationService $store;
    private \CodeIgniter\Database\BaseConnection $pos;
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([1,2] as $id) db_connect()->table('users')->insert(['id' => $id, 'username' => 'natural-' . $id, 'display_name' => 'Natural', 'password_hash' => password_hash('Testing123!', PASSWORD_DEFAULT), 'role' => 'Administrator', 'is_active' => 1]);
        $this->pos = \Config\Database::connect(config('Database')->tests, false); $this->pos->initialize();
        $this->pos->query('CREATE TABLE tbltransaksipenjualan (id_transaksi TEXT, tanggal TEXT, grand_total REAL, total_kotor REAL, source_type TEXT, id_member TEXT)');
        $this->pos->query("INSERT INTO tbltransaksipenjualan VALUES ('s','2026-09-01',100,110,'NORMAL','M1'),('o','2026-10-01',300,310,'NORMAL','M1')");
        $this->provider = new FakeOnlineProvider();
        $this->engine = new AiOrchestrator(queryExecutor: new PosQueryExecutor(customPosConnection: $this->pos), providerClient: $this->provider, clock: new BusinessClock('Asia/Jakarta', new \DateTimeImmutable('2026-10-17')));
        $this->store = new ConversationService();
    }
    private function reply(string $decision, string $reply, ?string $ref = null, string $subject = 'meaning'): void
    {
        $this->provider->replies = [['parsed_json' => ['decision' => $decision, 'reply' => $reply, 'report_ref' => $ref, 'context_subject' => $subject]]];
    }
    private function report(?string $uid = null, string $month = 'September'): array { return $this->engine->handleMessage("Laporan penjualan {$month} 2026 perhari", 1, 'Administrator', $uid); }

    public function testEmptyDailyResultIsTextOnlyThroughReloadReformatAndExport(): void
    {
        $r = $this->engine->handleMessage('Laporan penjualan 17 Oktober 2026 perhari', 1, 'Administrator');
        $this->assertTrue($r['success'], $r['summary']);
        $this->assertTrue($r['metadata']['empty_result']);
        $this->assertSame(['text'], array_column($r['blocks'], 'type'));
        $this->assertStringContainsString('17 Oktober 2026', $r['summary']);
        $this->assertStringNotContainsString('dirinci', $r['summary']);
        $this->assertSame(['table'=>false,'chart'=>false,'export'=>false], $r['metadata']['actions']);
        $this->assertSame([], $r['context']['last_facts']['metrics']);
        $queries = db_connect()->table('ai_query_logs')->countAllResults();
        foreach (['table','chart'] as $format) {
            $again = $this->engine->handleMessage('Tampilkan ' . $format, 1, 'Administrator', $r['conversation_uid'], $format, $r['metadata']['report_ref']);
            $this->assertTrue($again['success'], $again['summary']);
            $this->assertSame(['text'], array_column($again['blocks'],'type'));
            $this->assertSame([], $again['metadata']['queries']);
            $this->assertTrue($again['context']['last_empty_result']);
        }
        $this->assertSame($queries,db_connect()->table('ai_query_logs')->countAllResults());
        $stored = $this->store->getConversation($r['conversation_uid'],1);
        // Old renderers may have stored visuals for an empty report; loading must suppress them too.
        $stored['messages'][1]['blocks'][] = ['type'=>'table','rows'=>[['amount'=>0]],'columns'=>[['key'=>'amount','label'=>'Amount','type'=>'currency']]];
        $loaded = (new ReportContext())->forClient($stored,'Administrator');
        $this->assertSame(['text'],array_column($loaded['messages'][1]['blocks'],'type'));
        $this->assertFalse($loaded['messages'][1]['metadata']['actions']['table']);
        $session = ['auth_user_id'=>1,'auth_display_name'=>'Natural','auth_role'=>'Administrator'];
        $this->asUser($session)->get('/asisten-ai/export/' . $r['conversation_uid'] . '/csv?report_ref=' . $r['metadata']['report_ref'])->assertStatus(422);
        $this->report($r['conversation_uid']);
        $old = $this->engine->handleMessage('Tampilkan grafik laporan lama',1,'Administrator',$r['conversation_uid'],'chart',$r['metadata']['report_ref']);
        $this->assertTrue($old['success'],$old['summary']);
        $this->assertSame(['text'],array_column($old['blocks'],'type'));
        $this->assertSame([], $old['metadata']['queries']);
    }

    public function testARealZeroValueTransactionStillHasItsTableAndChart(): void
    {
        $this->pos->query("INSERT INTO tbltransaksipenjualan VALUES ('zero','2026-10-17',0,0,'NORMAL','M1')");
        $r = $this->engine->handleMessage('Laporan penjualan 17 Oktober 2026 perhari',1,'Administrator');
        $this->assertTrue($r['success']);
        $this->assertFalse($r['metadata']['empty_result']);
        $this->assertContains('table',array_column($r['blocks'],'type'));
        $this->assertContains('chart',array_column($r['blocks'],'type'));
        $this->assertTrue($r['metadata']['actions']['chart']);
        $this->assertFalse($r['context']['last_empty_result']);
    }

    public function testEmptyComparisonVirtualCalendarIsNotDisplayedOrExportable(): void
    {
        $r = $this->engine->handleMessage('Bandingkan Juli dan Agustus 2026 perhari',1,'Administrator');
        $this->assertTrue($r['success']);
        $this->assertCount(31,$r['context']['last_rows']);
        $this->assertSame(['text'],array_column($r['blocks'],'type'));
        $this->assertSame(['table'=>false,'chart'=>false,'export'=>false],$r['metadata']['actions']);
        $this->assertStringContainsString('Juli 2026 dan Agustus 2026',$r['summary']);
        $session = ['auth_user_id'=>1,'auth_display_name'=>'Natural','auth_role'=>'Administrator'];
        $this->asUser($session)->get('/asisten-ai/export/' . $r['conversation_uid'] . '/csv')->assertStatus(422);
    }

    public function testAggregateSalesWithoutDocumentsIsEmptyEvenThoughSqlReturnsOneRow(): void
    {
        $this->pos->query('ALTER TABLE tbltransaksipenjualan ADD COLUMN potongan REAL');
        $this->pos->query('CREATE TABLE tblreturpenjualan (id_retur TEXT, tanggal TEXT, total_retur REAL, hapus INTEGER, status TEXT)');
        $r = $this->engine->handleMessage('Berapa total penjualan Januari 2026?',1,'Administrator');
        $this->assertTrue($r['success'],$r['summary']);
        $this->assertTrue($r['metadata']['empty_result']);
        $this->assertSame(['text'],array_column($r['blocks'],'type'));
        $this->assertFalse($r['metadata']['actions']['export']);
        $this->assertSame([1,1],array_column($r['metadata']['queries'],'row_count'));
    }

    public function testMixedConversationUsesOneRoutingCallAndNoQueryForTextReplies(): void
    {
        $this->reply('general', 'Halo! Ada yang bisa saya bantu?');
        $hello = $this->engine->handleMessage('halo', 1, 'Administrator');
        $this->assertSame('conversation', $hello['metadata']['response_kind']);
        $this->assertCount(1, $this->provider->calls);
        $this->assertSame([], $hello['metadata']['queries']);
        $report = $this->report($hello['conversation_uid']);
        $this->assertTrue($report['success']);
        $this->assertCount(2, $this->provider->calls);
        $queryCount = db_connect()->table('ai_query_logs')->countAllResults();
        $ref = $report['metadata']['report_ref'];
        $this->reply('context', 'Periode yang salah sengaja untuk menguji sumber fakta.', $ref, 'period');
        $period = $this->engine->handleMessage('Itu tadi data kapan?', 1, 'Administrator', $hello['conversation_uid']);
        $this->assertStringContainsString('September 2026', $period['summary']);
        $this->assertStringContainsString('2026-10-17', $period['summary']);
        $this->assertSame('report_context', $period['metadata']['response_kind']);
        $this->assertEquals($report['context']['last_rows'], $period['context']['last_rows']);
        $this->assertSame($report['context']['last_request'], $period['context']['last_request']);
        foreach ([['context','maksudnya apa?','Angka tersebut adalah total omzet faktur pada periode laporan.'], ['general','terima kasih','Sama-sama.'], ['general','Bantu buat promosi toko','Belanja hemat, kebutuhan lengkap!']] as [$kind,$question,$text]) {
            $this->reply($kind, $text, $kind === 'context' ? $ref : null);
            $r = $this->engine->handleMessage($question, 1, 'Administrator', $hello['conversation_uid']);
            $this->assertTrue($r['success']);
            $this->assertSame([], $r['metadata']['queries']);
            $this->assertSame(['table' => false, 'chart' => false, 'export' => false], $r['metadata']['actions']);
            $this->assertEquals($report['context']['last_rows'], $r['context']['last_rows']);
        }
        $this->assertSame($queryCount, db_connect()->table('ai_query_logs')->countAllResults());
        $this->provider->replies = [['parsed_json' => ['decision' => 'reformat', 'format' => 'chart', 'report_ref' => $ref]]];
        $chart = $this->engine->handleMessage('Tampilkan grafiknya.', 1, 'Administrator', $hello['conversation_uid']);
        $this->assertTrue($chart['success'], $chart['summary']);
        $this->assertSame($ref, $chart['metadata']['report_ref']);
        $this->assertSame([], $chart['metadata']['queries']);
        $this->assertCount(7, $this->provider->calls);
    }

    public function testGeneralChatWorksWithNoDataRoleAndBrokenPosWithoutTouchingPos(): void
    {
        $this->pos->query('DROP TABLE tbltransaksipenjualan');
        $this->reply('general', 'Halo, saya siap membantu.');
        $r = $this->engine->handleMessage('Halo', 1, 'NoDataRole');
        $this->assertTrue($r['success']);
        $this->assertSame(0, db_connect()->table('ai_query_logs')->countAllResults());
        $this->assertSame('conversation', $r['metadata']['response_kind']);
        $this->provider->replies = [['used_remote' => false, 'error' => 'offline']];
        $failed = $this->engine->handleMessage('Halo lagi', 1, 'NoDataRole', $r['conversation_uid']);
        $this->assertSame('provider_unavailable', $failed['error_code']);
        $this->assertEquals($r['context'], $failed['context']);
    }

    public function testOldReportReferenceSurvivesNewerReportAndExportsExactDataset(): void
    {
        $a = $this->report(); $b = $this->report($a['conversation_uid'], 'Oktober');
        $ref = $a['metadata']['report_ref'];
        $this->assertNotSame($ref, $b['metadata']['report_ref']);
        $this->provider->replies = [['parsed_json' => ['decision' => 'reformat', 'format' => 'chart', 'report_ref' => $b['metadata']['report_ref']]]];
        $r = $this->engine->handleMessage('Ubah menjadi tabel.', 1, 'Administrator', $a['conversation_uid'], 'table', $ref);
        $this->assertEquals($a['context']['last_rows'], $r['context']['last_rows']);
        $this->assertSame($ref, $r['metadata']['report_ref']);
        $this->assertSame('table', $r['metadata']['presentation']);
        $this->assertSame([], $r['metadata']['queries']);
        $session = ['auth_user_id' => 1, 'auth_display_name' => 'Natural', 'auth_role' => 'Administrator'];
        $csv = $this->asUser($session)->get('/asisten-ai/export/' . $a['conversation_uid'] . '/csv?report_ref=' . $ref);
        $csv->assertStatus(200);
        $this->assertStringContainsString('2026-09-01', $csv->response()->getBody());
        $this->assertStringNotContainsString('2026-10-01', $csv->response()->getBody());
        $cross = $this->report();
        $this->asUser($session)->get('/asisten-ai/export/' . $cross['conversation_uid'] . '/csv?report_ref=' . $ref)->assertStatus(404);
    }

    public function testPendingClarificationIsNotConsumedByGreetingAndCanBeCancelled(): void
    {
        $pending = $this->engine->handleMessage('Pembelian member Alpha berapa?', 1, 'Administrator');
        $this->assertSame('clarification', $pending['analysis_status']);
        $this->reply('general', 'Halo!');
        $hello = $this->engine->handleMessage('halo', 1, 'Administrator', $pending['conversation_uid']);
        $this->assertEquals($pending['context']['pending_request'], $hello['context']['pending_request']);
        $this->assertSame([], $hello['metadata']['queries']);
        $this->reply('general', 'Permintaan sebelumnya dibatalkan.');
        $cancelled = $this->engine->handleMessage('batal', 1, 'Administrator', $pending['conversation_uid']);
        $this->assertArrayNotHasKey('pending_request', $cancelled['context']);
    }

    public function testRoleChangeRedactsOldReportsBeforePromptAndClientResponse(): void
    {
        $r = $this->report();
        $this->reply('general', 'Halo.');
        $chat = $this->engine->handleMessage('halo', 1, 'Gudang', $r['conversation_uid']);
        $payload = json_decode(end($this->provider->calls)[1]['content'], true);
        $this->assertSame([], $payload['reports']);
        $this->assertArrayNotHasKey('last_rows', $chat['context']);
        $conv = (new ReportContext())->forClient($this->store->getConversation($r['conversation_uid'], 1), 'Gudang');
        $this->assertSame('refused', $conv['messages'][1]['metadata']['response_kind']);
        $this->assertStringNotContainsString('Rp100', $conv['messages'][1]['content']);
        $this->assertSame(['table' => false, 'chart' => false, 'export' => false], $conv['messages'][1]['metadata']['actions']);
    }

    public function testFactsContainOnlyBoundedAggregatesAndNoIdentitiesRowsOrSql(): void
    {
        $service = new ReportContext();
        $r = $this->report();
        $snapshot = $service->select($this->store->getConversation($r['conversation_uid'], 1), 'Administrator');
        unset($snapshot['context']['last_facts']); // Exercise compatibility for historical reports without canonical numeric facts.
        $snapshot['context']['last_rows'][0]['nama'] = 'PRIVATE_MEMBER';
        $snapshot['context']['last_kpis'] = [['label' => 'PRIVATE_MEMBER', 'value' => 'Rp1.000'], ['label' => 'Omzet', 'value' => 'Rp2.000'], ['label' => 'Selisih', 'value' => '-Rp1.000'], ['label' => 'extra', 'value' => 'Rp5']];
        $facts = $service->facts($snapshot, true);
        $this->assertCount(3, $facts['metrics']);
        $this->assertSame(1000.0, $facts['metrics'][0]['value']);
        $snapshot['context']['last_kpis'][0]['value'] = '1.000';
        $this->assertSame(1000.0, $service->facts($snapshot, true)['metrics'][0]['value']);
        $text = json_encode($facts);
        $this->assertStringNotContainsString('PRIVATE_MEMBER', $text);
        $this->assertStringNotContainsString('SELECT', $text);
        $this->assertArrayNotHasKey('metrics', $service->facts($snapshot, false));
        for ($i = 0; $i < 4; $i++) $this->report($r['conversation_uid']);
        $this->provider->baseUrlOverride = 'https://not-approved.invalid/v1';
        $this->reply('general', 'Halo.');
        $this->engine->handleMessage('halo', 1, 'Administrator', $r['conversation_uid']);
        $payload = json_decode(end($this->provider->calls)[1]['content'], true);
        $this->assertCount(3, $payload['reports']);
        $this->assertArrayNotHasKey('metrics', $payload['reports'][0]); // Test provider URL is not the approved live endpoint.
        $this->provider->baseUrlOverride = 'http://localhost:20128/v1';
        $this->reply('general', 'Halo.');
        $this->engine->handleMessage('halo', 1, 'Administrator', $r['conversation_uid']);
        $payload = json_decode(end($this->provider->calls)[1]['content'], true);
        $this->assertArrayHasKey('metrics', $payload['reports'][0]);
    }

    public function testGeneralHistoryIsIncludedButReportBodiesAreExcluded(): void
    {
        $this->reply('general', 'Contoh slogan: Belanja nyaman.');
        $r = $this->engine->handleMessage('Buat slogan', 1, 'Administrator');
        $this->report($r['conversation_uid']);
        $this->reply('general', 'Versi lebih singkat: Belanja nyaman!');
        $this->engine->handleMessage('singkatkan slogan tadi', 1, 'Administrator', $r['conversation_uid']);
        $payload = json_decode(end($this->provider->calls)[1]['content'], true);
        $text = json_encode($payload['recent_dialogue']);
        $this->assertStringContainsString('Belanja nyaman', $text);
        $this->assertStringNotContainsString('Rp100', $text);
        $this->assertStringNotContainsString('SELECT', $text);
    }

    public function testEducationIsTextButActualMutationIsStillRefused(): void
    {
        $this->reply('general', 'SQL injection adalah penyisipan perintah SQL melalui input yang tidak aman.');
        $r = $this->engine->handleMessage('Apa itu SQL injection dan DROP TABLE?', 1, 'Administrator');
        $this->assertSame('conversation', $r['metadata']['response_kind']);
        $this->assertSame([], $r['metadata']['queries']);
        $blocked = $this->engine->handleMessage('DROP TABLE tbltransaksipenjualan', 1, 'Administrator');
        $this->assertSame('refused', $blocked['analysis_status']);
        $this->assertSame('security_refusal', $blocked['error_code']);
    }

    public function testFailedAttemptAndMissingReferenceDoNotInventNewReport(): void
    {
        $r = $this->report();
        $this->provider->replies = [['used_remote' => false, 'error' => 'offline']];
        $this->engine->handleMessage('Analisis ulang', 1, 'Administrator', $r['conversation_uid']);
        $this->reply('context', 'Periode', $r['metadata']['report_ref'], 'period');
        $reply = $this->engine->handleMessage('itu data kapan?', 1, 'Administrator', $r['conversation_uid']);
        $this->assertStringContainsString('terbaru gagal', $reply['summary']);
        $this->assertStringContainsString('September 2026', $reply['summary']);
        $this->reply('context', 'Data kapan?', 'report-0000000000000000', 'period');
        $missing = $this->engine->handleMessage('itu data kapan?', 1, 'Administrator');
        $this->assertSame('clarification', $missing['analysis_status']);
        $this->assertSame([], $missing['metadata']['queries']);
        $this->assertArrayHasKey('pending_report_reference', $missing['context']);
    }

    public function testComparisonContextNamesBothMonthsAndKeepsEmptyCalendarStatus(): void
    {
        $r = $this->engine->handleMessage('Bandingkan Juli dan Agustus 2026 perhari', 1, 'Administrator');
        $this->assertTrue($r['success'], $r['summary']);
        $this->assertTrue($r['metadata']['empty_result']);
        $this->reply('context', 'Periode', $r['metadata']['report_ref'], 'period');
        $reply = $this->engine->handleMessage('itu tadi data kapan?', 1, 'Administrator', $r['conversation_uid']);
        $this->assertStringContainsString('Juli 2026 dan Agustus 2026', $reply['summary']);
        $this->assertStringContainsString('Tidak ditemukan transaksi', $reply['summary']);
        $this->assertSame([], $reply['metadata']['queries']);
        $this->assertFalse($reply['metadata']['actions']['chart']);
    }

    public function testGeneralGreetingDoesNotPreventPendingBusinessAnswerAfterReload(): void
    {
        $pending = $this->engine->handleMessage('Bandingkan Agustus perhari', 1, 'Administrator');
        $this->reply('general', 'Halo.');
        $this->engine->handleMessage('halo', 1, 'Administrator', $pending['conversation_uid']);
        $this->provider->replies = [];
        $answer = $this->engine->handleMessage('Agustus dan September 2026', 1, 'Administrator', $pending['conversation_uid']);
        $this->assertTrue($answer['success'], $answer['summary']);
        $this->assertSame('month_comparison_daily', $answer['metadata']['intent']);
        $this->assertSame(8, $answer['context']['comparison_month']);
        $this->assertSame(9, $answer['context']['month']);
        $this->assertArrayNotHasKey('pending_request', $answer['context']);
    }

    public function testDiscussingOldReportSelectsItForNextReformatWithoutReplacingLastDataset(): void
    {
        $a = $this->report(); $b = $this->report($a['conversation_uid'], 'Oktober');
        $this->reply('context', 'Laporan September.', $a['metadata']['report_ref'], 'period');
        $context = $this->engine->handleMessage('Yang September tadi data kapan?', 1, 'Administrator', $a['conversation_uid']);
        $this->assertEquals($b['context']['last_rows'], $context['context']['last_rows']);
        $this->provider->replies = [];
        $table = $this->engine->handleMessage('Ubah menjadi tabel.', 1, 'Administrator', $a['conversation_uid']);
        $this->assertEquals($a['context']['last_rows'], $table['context']['last_rows']);
        $this->assertSame($a['metadata']['report_ref'], $table['metadata']['report_ref']);
        $this->assertSame([], $table['metadata']['queries']);
        $pending = $this->engine->handleMessage('Bandingkan Agustus perhari', 1, 'Administrator', $a['conversation_uid']);
        $again = $this->engine->handleMessage('Ubah menjadi tabel.', 1, 'Administrator', $a['conversation_uid'], 'table', $b['metadata']['report_ref']);
        $this->assertEquals($pending['context']['pending_request'], $again['context']['pending_request']);
    }

    public function testAggregateBackedConversationHistoryIsFilteredAfterProviderOrRoleChange(): void
    {
        $this->provider->baseUrlOverride = 'http://localhost:20128/v1';
        $report = $this->report();
        $this->reply('general', 'PRIVATE_AGGREGATE_REPLY Rp100');
        $chat = $this->engine->handleMessage('Halo', 1, 'Administrator', $report['conversation_uid']);
        $this->assertTrue($chat['metadata']['aggregate_context_attached']);
        $this->provider->baseUrlOverride = 'https://not-approved.invalid/v1';
        $this->reply('general', 'Halo.');
        $this->engine->handleMessage('Halo lagi', 1, 'Administrator', $report['conversation_uid']);
        $payload = json_decode(end($this->provider->calls)[1]['content'], true);
        $this->assertStringNotContainsString('PRIVATE_AGGREGATE_REPLY', json_encode($payload['recent_dialogue']));
        $this->assertArrayNotHasKey('metrics', $payload['reports'][0]);
        $this->provider->baseUrlOverride = 'http://localhost:20128/v1';
        $this->reply('general', 'Halo.');
        $this->engine->handleMessage('Halo lagi', 1, 'Gudang', $report['conversation_uid']);
        $payload = json_decode(end($this->provider->calls)[1]['content'], true);
        $this->assertSame([], $payload['reports']);
        $this->assertStringNotContainsString('PRIVATE_AGGREGATE_REPLY', json_encode($payload['recent_dialogue']));
        $client = (new ReportContext())->forClient($this->store->getConversation($report['conversation_uid'], 1), 'Gudang');
        $this->assertStringNotContainsString('PRIVATE_AGGREGATE_REPLY', json_encode($client));
    }
}
