<?php
namespace Tests\Feature;

use App\Services\AI\{AiOrchestrator, AiProviderClient, AtomicJsonStore, BusinessClock, ConversationService, PosQueryExecutor, SqlSafetyValidator};
use App\Services\ReportExporter;
use CodeIgniter\Test\{CIUnitTestCase, DatabaseTestTrait, FeatureTestTrait};
use Config\AiAnalyst;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class AiReliabilityTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait, \Tests\Support\AuthenticatedSession;
    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([1 => 'Administrator', 2 => 'Gudang'] as $id => $role) db_connect()->table('users')->insert(['id' => $id, 'username' => 'reliable-' . $id, 'display_name' => $role, 'password_hash' => password_hash('TestPassword123!', PASSWORD_DEFAULT), 'role' => $role, 'is_active' => 1]);
    }
    private function engine(?PosQueryExecutor $executor = null, string $date = '2026-10-16'): AiOrchestrator
    {
        return new AiOrchestrator(queryExecutor: $executor, providerClient: new \Tests\Support\FakeOnlineProvider(), clock: new BusinessClock('Asia/Jakarta', new \DateTimeImmutable($date, new \DateTimeZone('Asia/Jakarta'))));
    }
    public function testEveryStandardAnalysisRequiresFreshOnlineDecision(): void
    {
        $p = new \Tests\Support\FakeOnlineProvider();
        $engine = new AiOrchestrator(providerClient: $p, clock: new BusinessClock('Asia/Jakarta', new \DateTimeImmutable('2026-10-16')));
        $first = $engine->handleMessage('Berapa total penjualan bulan lalu?',1,'Administrator');
        $this->assertTrue($first['success']); $this->assertCount(1,$p->calls);
        $this->assertTrue($first['metadata']['provider_used']);
        $count = db_connect()->table('ai_query_logs')->countAllResults();
        $table = $engine->handleMessage('Ubah menjadi tabel.',1,'Administrator',$first['conversation_uid'],'table');
        $this->assertTrue($table['success']); $this->assertCount(2,$p->calls);
        $this->assertSame($count,db_connect()->table('ai_query_logs')->countAllResults());
        $prompt = json_decode($p->calls[1][1]['content'],true);
        $this->assertArrayNotHasKey('last_rows',$prompt['period_context']);
        $this->assertArrayNotHasKey('last_kpis',$prompt['period_context']);
        $p->replies = [['used_remote'=>false,'error'=>'connection failed']];
        $failed = $engine->handleMessage('Berapa total penjualan bulan lalu?',1,'Administrator',$first['conversation_uid']);
        $this->assertFalse($failed['success']); $this->assertSame('provider_unavailable',$failed['error_code']);
        $this->assertSame($count,db_connect()->table('ai_query_logs')->countAllResults());
        $this->assertEquals($table['context'],$failed['context']);
        $this->assertNotContains('kpi',array_column($failed['blocks'],'type'));
    }
    public function testMissingOrMalformedOnlineProviderNeverFallsBack(): void
    {
        foreach ([['used_remote'=>false,'error'=>null],['parsed_json'=>null],['parsed_json'=>['decision'=>'offline']],['parsed_json'=>['decision'=>'clarify','question'=>'']]] as $reply) {
            $p = new \Tests\Support\FakeOnlineProvider(); $p->replies=[$reply];
            $r = (new AiOrchestrator(providerClient:$p))->handleMessage('Berapa penjualan bulan ini?',1,'Administrator');
            $this->assertFalse($r['success']); $this->assertSame(0,db_connect()->table('ai_query_logs')->countAllResults());
            $this->assertSame([], $r['context']);
        }
        $p = new \Tests\Support\FakeOnlineProvider(); $p->configured=false;
        $r = (new AiOrchestrator(providerClient:$p))->handleMessage('Berapa penjualan bulan ini?',1,'Administrator');
        $this->assertFalse($r['success']); $this->assertSame('provider_not_configured',$r['error_code']); $this->assertCount(0,$p->calls);
        $config = new AiAnalyst(); $config->apiKey = ''; $config->baseUrl = 'https://audit.invalid/v1';
        $client = new AiProviderClient($config, WRITEPATH.'missing-provider');
        $response = $client->chatCompletion([['role'=>'user','content'=>'test']]);
        $this->assertSame('provider_not_configured',$response['error']); $this->assertFalse($response['used_remote']);
    }
    public function testOnlineDecisionCanRejectAnIncorrectLocalCandidate(): void
    {
        $p = new \Tests\Support\FakeOnlineProvider();
        $p->replies=[['parsed_json'=>['decision'=>'clarify','question'=>'Periode penjualan yang dimaksud kapan?']]];
        $r=(new AiOrchestrator(providerClient:$p))->handleMessage('Berapa penjualan?',1,'Administrator');
        $this->assertTrue($r['success']); $this->assertSame('Untuk periode kapan? Misalnya bulan ini, bulan lalu, atau September 2026.',$r['summary']);
        $this->assertSame([], $r['metadata']['queries']); $this->assertTrue($r['metadata']['provider_used']);
        $p->replies=[['parsed_json'=>['decision'=>'dynamic']],['parsed_json'=>['title'=>'Jumlah produk','sql'=>'SELECT COUNT(id_produk) AS jumlah FROM tbldatabarang','explanation'=>'Menghitung produk','chart_type'=>'none']]];
        $r=(new AiOrchestrator(providerClient:$p))->handleMessage('Berapa jumlah produk aktif?',1,'Administrator');
        $this->assertTrue($r['success']); $this->assertSame('dynamic_llm_sql',$r['metadata']['intent']);
        $this->assertCount(3,$p->calls);
    }
    public function testGeneratedSqlPreservesCompoundComparisonOperators(): void
    {
        $v = new SqlSafetyValidator();
        foreach (['>=','<=','<>','!=','<=>'] as $operator) {
            $validated=$v->validate("SELECT id_produk FROM tbldatabarang WHERE harga_jual {$operator} 10000",'Administrator');
            $this->assertTrue($validated['valid']);
            $this->assertStringContainsString('harga_jual '.$operator.' 10000',$validated['sql']);
        }
        $result=(new PosQueryExecutor())->execute("SELECT COUNT(id_transaksi) AS records FROM tbltransaksipenjualan WHERE tanggal >= '2026-09-01' AND tanggal <= '2026-09-30' AND grand_total != 0",role:'Administrator',userId:1);
        $this->assertTrue($result['success']); $this->assertSame(1,(int)$result['rows'][0]['records']);
    }
    public function testUnsafeSqlShapesAreRejectedAcrossScopes(): void
    {
        $v = new SqlSafetyValidator();
        foreach ([
            'SELECT b.id_produk, u.username FROM tbldatabarang b, tbluser u',
            'SELECT b.id_produk FROM tbldatabarang b, dbkerangka.users u',
            'SELECT b.id_produk, g.total_bayar FROM tbldatabarang b, tblgaji g',
            'SELECT b.id_produk FROM tbldatabarang b JOIN tblgaji g ON 1=1',
            'SELECT b.id_produk FROM tbldatabarang b WHERE EXISTS (SELECT username FROM tbluser)',
            'SELECT b.id_produk, * FROM tbldatabarang b',
            'SELECT b . * FROM tbldatabarang b',
            'SELECT id_produk FROM tbldatabarang /*M! UNION SELECT username FROM tbluser */',
            'SELECT arbitrary_udf(id_produk) FROM tbldatabarang',
            'SELECT dbsiprosir_online_v5_x2.SUM(stok) FROM tbldatabarang_stok',
            'SELECT ALL id_produk FROM tbldatabarang',
            'SELECT id_produk FROM tbldatabarang WHERE id_produk IN (TABLE tbluser)',
            'WITH a AS (SELECT username FROM tbluser) SELECT id_produk FROM tbldatabarang',
            'WITH a AS (SELECT id_produk FROM tbldatabarang) SELECT g.total_bayar FROM a JOIN tblgaji g ON 1=1',
        ] as $sql) $this->assertFalse($v->validate($sql, 'Gudang')['valid'], $sql);
    }
    public function testSupportedJoinsCtesAndSubqueriesRemainUsable(): void
    {
        $v = new SqlSafetyValidator();
        foreach ([
            'SELECT b.id_produk, s.stok FROM tbldatabarang b JOIN tbldatabarang_stok s ON b.id_produk = s.id_produk',
            'WITH a AS (SELECT id_produk FROM tbldatabarang) SELECT id_produk FROM a',
            'SELECT a.id_produk FROM (SELECT id_produk FROM tbldatabarang) a',
            'SELECT id_produk FROM tbldatabarang WHERE id_produk IN (SELECT id_produk FROM tbldatabarang_stok)',
            'SELECT COUNT(*) AS jumlah FROM tbldatabarang',
        ] as $sql) $this->assertTrue($v->validate($sql, 'Gudang')['valid'], $sql);
        $normalized = $v->validate("SELECT COALESCE(SUM(stok),0) AS total FROM tbldatabarang_stok WHERE 'SUM ( literal'='SUM ( literal'", 'Gudang');
        $this->assertTrue($normalized['valid']);
        $this->assertStringContainsString('COALESCE( SUM(', $normalized['sql']);
        $this->assertStringContainsString("'SUM ( literal'", $normalized['sql']);
        $commented = $v->validate('SELECT id_produk FROM tbldatabarang -- trailing LIMIT 999999', 'Gudang');
        $this->assertTrue($commented['valid']);
        $this->assertStringEndsWith('LIMIT 100', $commented['sql']);
        $this->assertStringNotContainsString('--', $commented['sql']);
        $this->assertStringEndsWith('LIMIT 500', $v->validate('SELECT id_produk FROM tbldatabarang LIMIT 999999', 'Gudang')['sql']);
        foreach (['SUM(p.grand_total+0)', 'SUM(COALESCE(p.grand_total,0))', 'SUM(DISTINCT p.grand_total)'] as $sum) $this->assertFalse($v->validate("SELECT {$sum} AS total FROM tbltransaksipenjualan p JOIN tbltransaksipenjualan_detail d ON p.id_transaksi=d.id_transaksi", 'Administrator')['valid']);
        $this->assertFalse($v->validate('WITH a AS (SELECT SUM(p.grand_total) AS total FROM tbltransaksipenjualan p JOIN tbltransaksipenjualan_detail d ON p.id_transaksi=d.id_transaksi) SELECT total FROM a', 'Administrator')['valid']);
    }
    public function testFailedQueryPreservesPriorResultAndNeverFabricatesZero(): void
    {
        $ok = $this->engine()->handleMessage('Berapa total penjualan bulan lalu?', 1, 'Administrator');
        $this->assertTrue($ok['success']);
        $this->assertSame(16550000.0, (float) $ok['context']['last_rows'][4]['nilai']);
        $empty = \Config\Database::connect(config('Database')->tests, false);
        $empty->initialize();
        $bad = $this->engine(new PosQueryExecutor(customPosConnection: $empty))->handleMessage('Berapa total penjualan bulan lalu?', 1, 'Administrator', $ok['conversation_uid']);
        $this->assertFalse($bad['success']);
        $this->assertSame('error', $bad['analysis_status']);
        $this->assertSame(['warning'], array_column($bad['blocks'], 'type'));
        $this->assertEquals($ok['context'], $bad['context']);
        $this->assertStringNotContainsString('Rp0', $bad['summary']);
    }
    public function testMonthBoundariesAndStructuredReformat(): void
    {
        $engine = $this->engine(date: '2027-01-03');
        foreach (['Berapa total penjualan bulan lalu?', 'Berapa laba kotor bulan lalu?'] as $q) {
            $plan = $engine->resolveIntentAndPlan($q, []);
            $this->assertSame(2026, $plan['year']); $this->assertSame(12, $plan['month']);
        }
        $plan = $engine->resolveIntentAndPlan('Bandingkan dengan bulan sebelumnya.', ['year' => 2027, 'month' => 1]);
        $this->assertSame(2026, $plan['comparison_year']); $this->assertSame(12, $plan['comparison_month']);
        $engine = $this->engine();
        $first = $engine->handleMessage('Berapa total penjualan bulan lalu?', 1, 'Administrator');
        $count = db_connect()->table('ai_query_logs')->countAllResults();
        $again = $engine->handleMessage('Tampilkan grafiknya.', 1, 'Administrator', $first['conversation_uid'], 'chart');
        $this->assertTrue($again['success']);
        $this->assertSame('chart', $again['metadata']['presentation']);
        $this->assertSame($count, db_connect()->table('ai_query_logs')->countAllResults());
    }
    public function testEmptyDatasetReformatsWithoutQueryingAgain(): void
    {
        $service = new ConversationService(); $c = $service->createConversation(1);
        $context = ['domain'=>'sales', 'year'=>2026, 'month'=>9, 'last_title'=>'Hasil kosong', 'last_rows'=>[], 'last_columns'=>[['key'=>'nilai','label'=>'Nilai','type'=>'number']], 'last_queries'=>[], 'last_chart'=>['chart_type'=>'bar','labels'=>[],'series'=>[]], 'source_mode'=>'live_pos_db'];
        $service->appendTurn((int)$c['id'], 'Analisis kosong', 'Tidak ada data', [], [], $context, null, 1, $c['conversation_uid']);
        foreach (['Tampilkan grafiknya.', 'Ubah menjadi tabel.'] as $question) {
            $r = $this->engine()->handleMessage($question,1,'Administrator',$c['conversation_uid']);
            $this->assertTrue($r['success']); $this->assertSame('reformat_previous',$r['metadata']['intent']);
            $this->assertSame([], $r['metadata']['queries']); $this->assertSame([], $r['context']['last_rows']);
        }
        $this->assertSame(0, db_connect()->table('ai_query_logs')->countAllResults());
    }
    public function testStatusStorageFailureReturns503WithoutSecret(): void
    {
        $dir = WRITEPATH.'status-provider'; mkdir($dir); file_put_contents($dir.'/provider_config.json', '{invalid');
        \Config\Services::injectMock('aiProviderClient', new AiProviderClient(storageDirectory:$dir));
        $r = $this->asUser(['auth_user_id'=>1,'auth_role'=>'Administrator'])->withHeaders(['X-Requested-With'=>'XMLHttpRequest'])->get('/asisten-ai/status');
        $r->assertStatus(503); $this->assertStringContainsString('storage_unavailable',$r->response()->getBody());
        $this->assertStringNotContainsString('api_key', $r->response()->getBody());
        \Config\Services::resetSingle('aiProviderClient');
    }
    public function testPeriodsAndKnownProfitAndProductFigures(): void
    {
        $engine = $this->engine();
        $plan = $engine->resolveIntentAndPlan('Kenapa turun?', ['intent'=>'year_comparison','year'=>2025,'comparison_year'=>2026,'month'=>7,'comparison_month'=>6]);
        $this->assertSame(2026,$plan['year']); $this->assertSame(2026,$plan['comparison_year']);
        $r = $engine->handleMessage('Berapa laba kotor September 2026 setelah retur?',1,'Administrator');
        $this->assertTrue($r['success']); $this->assertSame(2830000.0,(float)$r['context']['last_rows'][2]['setelah_retur']);
        $r = $engine->handleMessage('Produk apa saja yang terlaris bulan lalu kategori Minuman?',1,'Administrator');
        $this->assertTrue($r['success']); $this->assertSame(9,$r['context']['month']);
        $this->assertCount(1,$r['context']['last_rows']);
        $this->assertSame('BRG-003',$r['context']['last_rows'][0]['id_produk']);
        $this->assertSame(5440000.0,(float)$r['context']['last_rows'][0]['total_omzet']);
        $r = $engine->handleMessage('Hitung rata-rata transaksi per hari pada 2024.',1,'Administrator');
        $this->assertTrue($r['success']); $this->assertStringContainsString('366 hari', $r['summary']);
    }
    public function testYearComparisonIncludesZeroMonthsAndIgnoresFutureMonths(): void
    {
        $db = \Config\Database::connect(config('Database')->tests,false); $db->initialize();
        $db->query('CREATE TABLE tbltransaksipenjualan (id_transaksi TEXT, tanggal TEXT, grand_total REAL, source_type TEXT)');
        $db->query("INSERT INTO tbltransaksipenjualan VALUES ('a','2025-01-15',100,'NORMAL'),('b','2025-02-15',100,'NORMAL'),('c','2026-01-15',100,'NORMAL'),('future','2026-04-15',1000,'NORMAL')");
        $engine = $this->engine(new PosQueryExecutor(customPosConnection:$db), '2026-03-16');
        $r = $engine->handleMessage('Bandingkan 2025 dan 2026.',1,'Administrator');
        $this->assertTrue($r['success']);
        $this->assertSame(-100.0,(float)$r['context']['last_rows'][1]['selisih_rp']);
        $this->assertSame('-100,00%',$r['context']['last_rows'][1]['pertumbuhan_yoy']);
        $this->assertSame('Belum berjalan',$r['context']['last_rows'][3]['pertumbuhan_yoy']);
        $this->assertStringContainsString('-50,00%',$r['summary']);
        $db->query('ALTER TABLE tbltransaksipenjualan ADD COLUMN total_kotor REAL DEFAULT 0');
        $db->query('ALTER TABLE tbltransaksipenjualan ADD COLUMN potongan REAL DEFAULT 0');
        $db->query("INSERT INTO tbltransaksipenjualan (id_transaksi,tanggal,grand_total,source_type) VALUES ('zero','2026-03-01',0,'NORMAL')");
        $zero = $engine->handleMessage('Bandingkan Maret dengan Februari 2026.',1,'Administrator');
        $this->assertTrue($zero['success']); $this->assertStringContainsString('dasar pembanding nol',$zero['summary']);
        $this->assertStringNotContainsString('+0,00%',$zero['summary']);
        $decline = $engine->handleMessage('Bulan mana yang penurunannya terbesar tahun 2026?',1,'Administrator');
        $this->assertTrue($decline['success']); $this->assertSame(2,$decline['context']['month']);
        $db->query("INSERT INTO tbltransaksipenjualan (id_transaksi,tanggal,grand_total,source_type) VALUES ('feb','2026-02-15',200,'NORMAL'),('mar','2026-03-15',300,'NORMAL')");
        $rise = $engine->handleMessage('Bulan mana yang penurunannya terbesar tahun 2026?',1,'Administrator');
        $this->assertTrue($rise['success']); $this->assertNull($rise['context']['month']);
        $this->assertStringContainsString('Tidak ditemukan penurunan',$rise['summary']);
    }
    public function testConversationApiLifecycleAndExports(): void
    {
        \Config\Services::injectMock('aiOrchestrator',$this->engine());
        $session = ['auth_user_id'=>1,'auth_role'=>'Administrator'];
        $post = fn($url,$payload) => $this->asUser($session)->withHeaders(['X-Requested-With'=>'XMLHttpRequest'])->post($url,$payload+[csrf_token()=>csrf_hash()]);
        $created = $post('/asisten-ai/conversations/new',['title'=>'QA lifecycle']); $created->assertStatus(200);
        $uid = json_decode($created->response()->getBody(),true)['conversation']['conversation_uid'];
        $message = $post('/asisten-ai/message',['conversation_uid'=>$uid,'message'=>'Berapa total penjualan bulan lalu?']); $message->assertStatus(200);
        $this->assertTrue(json_decode($message->response()->getBody(),true)['success']);
        $format = $post('/asisten-ai/message',['conversation_uid'=>$uid,'action'=>'reformat','format'=>'table']); $format->assertStatus(200);
        $this->assertSame([],json_decode($format->response()->getBody(),true)['metadata']['queries']);
        $post('/asisten-ai/conversations/'.$uid.'/rename',['title'=>'QA renamed'])->assertStatus(200);
        $loaded = $this->asUser($session)->get('/asisten-ai/conversations/'.$uid); $loaded->assertStatus(200);
        $saved = json_decode($loaded->response()->getBody(),true)['conversation'];
        $this->assertSame('QA renamed',$saved['title']); $this->assertCount(4,$saved['messages']);
        foreach (['csv'=>"DATA SIMULASI", 'excel'=>'PK', 'pdf'=>'%PDF'] as $format=>$signature) {
            $export = $this->asUser($session)->get('/asisten-ai/export/'.$uid.'/'.$format); $export->assertStatus(200);
            $this->assertStringContainsString($signature,$export->response()->getBody());
            $this->assertSame((string)strlen($export->response()->getBody()),$export->response()->getHeaderLine('Content-Length'));
            if ($format !== 'csv') $this->assertStringNotContainsString('charset',$export->response()->getHeaderLine('Content-Type'));
        }
        $post('/asisten-ai/conversations/'.$uid.'/delete',[])->assertStatus(200);
        $this->asUser($session)->get('/asisten-ai/conversations/'.$uid)->assertStatus(404);
        $this->assertSame(0,db_connect()->table('ai_messages')->countAllResults());
        \Config\Services::resetSingle('aiOrchestrator');
    }
    public function testOwnershipAndConcurrentConversationGuard(): void
    {
        $first = $this->engine()->handleMessage('Berapa total penjualan bulan lalu?', 1, 'Administrator');
        $this->assertNull(service('conversationService')->getConversation($first['conversation_uid'], 2));
        $this->assertFalse(service('conversationService')->deleteConversation($first['conversation_uid'], 2));
        $lock = fopen(WRITEPATH . 'security/ai-user-1.lock', 'c+'); flock($lock, LOCK_EX);
        try {
            $this->engine()->handleMessage('Berapa total penjualan bulan lalu?', 1, 'Administrator');
            $this->fail('Concurrent execution must be rejected.');
        } catch (\App\Services\AI\AnalysisException $e) { $this->assertSame('conversation_busy', $e->errorCode); }
        finally { flock($lock, LOCK_UN); fclose($lock); }
    }
    public function testFileImportIsIdempotentAndPreservesMessages(): void
    {
        $config = new AiAnalyst(); $config->storageDriver = 'file';
        $dir = WRITEPATH . 'import-case-' . bin2hex(random_bytes(4));
        $files = new ConversationService($config, $dir);
        $c = $files->createConversation(1, 'Riwayat lama');
        $files->appendTurn($c['id'], 'Pertanyaan', 'Jawaban', [['type' => 'text', 'content' => 'Jawaban']], [], ['year' => 2026], null, 1, $c['conversation_uid']);
        $config->storageDriver = 'database'; $db = new ConversationService($config, $dir);
        $this->assertSame(['imported' => 1, 'skipped' => 0], $db->importFiles());
        $this->assertSame(['imported' => 0, 'skipped' => 1], $db->importFiles());
        $copy = $db->getConversation($c['conversation_uid'], 1);
        $this->assertCount(2, $copy['messages']);
        $this->assertSame(['year' => 2026], $copy['context']);
        $this->assertFileExists($dir . '/conversations_user_1.json');
        $this->assertCount(1, glob($dir . '/backups/*.bak'));
    }
    public function testDatabaseFailureDoesNotFallBackToFile(): void
    {
        $db = db_connect();
        $db->query('DROP TABLE ' . $db->protectIdentifiers($db->prefixTable('ai_messages')));
        $db->resetDataCache();
        $this->expectException(\RuntimeException::class);
        (new ConversationService())->listUserConversations(1);
    }
    public function testCorruptOrUnwritableFileCannotReportSuccess(): void
    {
        $dir = WRITEPATH . 'corrupt'; mkdir($dir);
        file_put_contents($dir . '/data.json', '{invalid');
        try { (new AtomicJsonStore($dir . '/data.json'))->mutate(static function (&$s) { $s = []; }); $this->fail('Corruption must fail.'); }
        catch (\JsonException) { $this->assertSame('{invalid', file_get_contents($dir . '/data.json')); }
        file_put_contents($dir . '/parent-file', 'keep');
        $this->expectException(\RuntimeException::class);
        (new AtomicJsonStore($dir . '/parent-file/data.json'))->mutate(static function (&$s) { $s = []; });
    }
    public function testExportMetadataAndDataAreAlwaysText(): void
    {
        $report = ['title' => '=1+1', 'note' => '@SUM(1)', 'filters' => ['Filter' => '=1+1'], 'columns' => ['name' => ['+Header', 'text']], 'rows' => [['name' => "\t=1+1"]]];
        $export = new ReportExporter(); $path = WRITEPATH . 'formula.xlsx'; file_put_contents($path, $export->excel($report));
        $sheet = IOFactory::load($path)->getActiveSheet();
        foreach (['A1', 'A2', 'A3', 'A5', 'A6'] as $cell) $this->assertSame('s', $sheet->getCell($cell)->getDataType(), $cell);
        $csv = $export->csv($report);
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString("'@SUM(1)", $csv);
        $this->assertStringContainsString("'+Header", $csv);
        $this->assertStringContainsString("'\t=1+1", $csv);
    }
    public function testProviderConfigurationIsTruthfulAndValidatesUrl(): void
    {
        $p = new AiProviderClient();
        foreach (['https://api.example.com/v1', 'http://127.0.0.1:11434/v1', 'http://[::1]:11434/v1'] as $url) $p->validateBaseUrl($url);
        foreach (['http://example.com/v1', 'http://127.0.0.1.evil.test/v1', 'file:///secret', 'https://user:pass@example.com/v1'] as $url) {
            try { $p->validateBaseUrl($url); $this->fail($url); } catch (\InvalidArgumentException) { $this->assertTrue(true); }
        }
        $p->updateConfig(['api_key' => 'synthetic-test-key', 'base_url' => 'https://audit.invalid/v1', 'streaming_enabled' => 1, 'tools_enabled' => 1], 1);
        $config = $p->getActiveConfig();
        $this->assertArrayNotHasKey('api_key', $config);
        $this->assertTrue($config['configured']); $this->assertSame('unchecked', $config['connection_status']);
        $this->assertFalse($config['streaming_enabled']); $this->assertFalse($config['tools_enabled']);
        $p->updateConfig(['clear_api_key' => 1], 1);
        $this->assertFalse($p->getActiveConfig()['has_api_key']);
    }
    public function testLimiterFailureReturns503(): void
    {
        $path = WRITEPATH . 'not-directory'; file_put_contents($path, 'keep');
        \Config\Services::injectMock('securityRateLimiter', new \App\Services\SecurityRateLimiter($path));
        $this->asUser(['auth_user_id' => 1, 'auth_role' => 'Administrator'])->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->post('/asisten-ai/message', [csrf_token() => csrf_hash(), 'message' => 'Berapa penjualan?'])->assertStatus(503);
        $this->assertSame(0, db_connect()->table('ai_conversations')->countAllResults());
    }
    public function testCompactWorkspaceAndAiLanding(): void
    {
        $r = $this->asUser(['auth_user_id' => 1, 'auth_role' => 'Administrator'])->get('/asisten-ai');
        $r->assertStatus(200);
        $this->assertSame(3, substr_count($r->response()->getBody(), 'class="ai-starter-card"'));
        $this->assertStringContainsString('id="aiInformationModal"', $r->response()->getBody());
        $this->assertStringNotContainsString('name="streaming_enabled"', $r->response()->getBody());
        $this->asUser(['auth_user_id' => 1, 'auth_role' => 'Administrator'])->get('/')->assertRedirectTo(site_url('asisten-ai'));
    }
    public function testFailedAssistantInsertRollsBackWholeTurn(): void
    {
        $service = new ConversationService(); $c = $service->createConversation(1);
        $db = db_connect();
        $db->query("CREATE TRIGGER reject_assistant BEFORE INSERT ON " . $db->prefixTable('ai_messages') . " WHEN NEW.role='assistant' BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");
        try { $service->appendTurn((int) $c['id'], 'Question', 'Answer', [], [], ['changed' => true], null, 1, $c['conversation_uid']); $this->fail('Write must fail.'); }
        catch (\Throwable $e) { $this->assertNotSame('', $e->getMessage()); }
        finally { $db->query('DROP TRIGGER reject_assistant'); }
        $saved = $service->getConversation($c['conversation_uid'], 1);
        $this->assertCount(0, $saved['messages']); $this->assertSame([], $saved['context']);
        $service->appendTurn((int) $c['id'], 'Retry', 'Recovered', [], [], [], null, 1, $c['conversation_uid']);
        $this->assertCount(2, $service->getConversation($c['conversation_uid'], 1)['messages']);
    }
    public function testConcurrentFileWritesDoNotLoseUpdates(): void
    {
        $path = WRITEPATH . 'concurrent.json';
        $children = [];
        for ($i = 0; $i < 2; $i++) {
            $pipes = [];
            $process = proc_open([PHP_BINARY, TESTPATH . '_support/atomic-writer.php', $path], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $this->assertIsResource($process); fclose($pipes[0]); $children[] = [$process, $pipes];
        }
        foreach ($children as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
            $this->assertSame(0, proc_close($process), $output);
        }
        $this->assertSame(30, (new AtomicJsonStore($path))->read()['count']);
    }
    public function testProviderUnavailableIsAnErrorNotAZeroAnalysis(): void
    {
        $cfg = new AiAnalyst(); $cfg->apiKey = ''; $cfg->baseUrl = 'http://127.0.0.1:1/v1';
        $provider = new AiProviderClient($cfg, WRITEPATH . 'provider-unavailable');
        $engine = new AiOrchestrator(providerClient: $provider);
        $r = $engine->handleMessage('Hitung jumlah jenis barang dikelompokkan menurut kategori.', 1, 'Administrator');
        $this->assertFalse($r['success']); $this->assertSame('provider_unavailable', $r['error_code']);
        $this->assertNotContains('kpi', array_column($r['blocks'], 'type'));
        $this->assertSame('unavailable', $provider->getActiveConfig()['connection_status']);
    }
}
