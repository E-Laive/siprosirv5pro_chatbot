<?php
namespace Tests\Feature;

use App\Services\AI\{AiAnalysisPlan,AiClientDiagnostics,AiOrchestrator,AnalysisException,BusinessClock,ConversationService,PosQueryExecutor,ReportContext,SqlPlanContract};
use CodeIgniter\Test\{CIUnitTestCase,DatabaseTestTrait,FeatureTestTrait};
use Tests\Support\FakeOnlineProvider;

final class AiOperationalReportsTest extends CIUnitTestCase
{
    use DatabaseTestTrait,FeatureTestTrait,\Tests\Support\AuthenticatedSession;
    protected $migrate=true;protected $refresh=true;protected $namespace='App';
    private FakeOnlineProvider $provider;private AiOrchestrator $engine;private $pos;
    protected function setUp():void
    {
        parent::setUp();
        db_connect()->table('users')->insert(['id'=>1,'username'=>'operational-qa','display_name'=>'QA','password_hash'=>password_hash('Testing123!',PASSWORD_DEFAULT),'role'=>'Administrator','is_active'=>1]);
        $this->pos=\Config\Database::connect(config('Database')->tests,false);$this->pos->initialize();
        foreach([
            'CREATE TABLE tbltransaksipenjualan (id_transaksi TEXT,tanggal TEXT,grand_total REAL,total_kotor REAL,source_type TEXT)',
            'CREATE TABLE tbltransaksipenjualan_detail (id_transaksi TEXT,id_produk TEXT,nama_produk TEXT,kategori TEXT,jumlah REAL,isi_satuan REAL,total_subtotal REAL,terjual INTEGER)',
            'CREATE TABLE tblreturpenjualan (id_retur TEXT,id_transaksi TEXT,tanggal TEXT,total_retur REAL,status TEXT,hapus INTEGER,tipe_retur TEXT)',
            'CREATE TABLE tbltransaksi_kas (id_transaksi_kas TEXT,tanggal TEXT,jenis TEXT,id_bank_asal TEXT,kategori TEXT,keterangan TEXT,nominal REAL,status TEXT,hapus INTEGER)',
            'CREATE TABLE tblbank_ledger (id_ledger TEXT,tanggal TEXT,id_bank TEXT,source_type TEXT,posting_role TEXT,keterangan TEXT,debit REAL,kredit REAL)',
            "INSERT INTO tbltransaksipenjualan VALUES ('oct','2026-10-19',900,900,'NORMAL'),('sept','2026-09-01',10,10,'NORMAL'),('legacy','2026-10-19',999,999,'LEGACY_HISTORY')",
            "INSERT INTO tbltransaksipenjualan_detail VALUES ('oct','A','Unit Winner','X',2,5,100,1),('oct','B','Amount Winner','X',2,1,500,1),('oct','C','Eight','X',8,1,90,1),('oct','D','Four','X',4,1,80,1),('oct','E','Three E','X',3,1,70,1),('oct','F','Three F','X',3,1,60,1),('legacy','L','Legacy','X',999,1,9999,1),('oct','N','Unsold','X',999,1,9999,0),('sept','S','September','X',999,1,9999,1)",
            "INSERT INTO tblreturpenjualan VALUES ('r1','sept','2026-10-19',24,'FINAL',0,'TUNAI'),('draft','oct','2026-10-19',900,'DRAFT',0,'TUNAI'),('deleted','oct','2026-10-19',900,'FINAL',1,'TUNAI'),('legacy','legacy','2026-10-19',900,'FINAL',0,'TUNAI'),('tomorrow','oct','2026-10-20',900,'FINAL',0,'TUNAI')",
            "INSERT INTO tbltransaksi_kas VALUES ('in','2026-10-19','MASUK','CASH','Modal','Extra',100,'FINAL',0),('out','2026-10-19','KELUAR','CASH','Biaya','Listrik',40,'FINAL',0),('transfer','2026-10-19','TRANSFER','CASH','Transfer','Internal',999,'FINAL',0),('draft','2026-10-19','KELUAR','CASH','Biaya','Draft',999,'DRAFT',0),('deleted','2026-10-19','MASUK','CASH','Modal','Deleted',999,'FINAL',1),('old','2026-09-19','MASUK','CASH','Modal','Old',999,'FINAL',0)",
            "INSERT INTO tblbank_ledger VALUES ('1','2026-10-19','CASH','SALE','MAIN','Sale',300,0),('2','2026-10-19','CASH','PURCHASE','MAIN','Purchase',0,80),('3','2026-10-19','CASH','CASH_TRANSACTION','TRANSFER_OUT','Internal',0,1000),('4','2026-10-19','BCA','CASH_TRANSACTION','TRANSFER_IN','Internal',1000,0)",
        ] as $sql)$this->pos->query($sql);
        $this->provider=new FakeOnlineProvider();$this->provider->baseUrlOverride='http://localhost:20128/v1';$this->provider->adaptLegacyPlans=false;
        $this->engine=new AiOrchestrator(queryExecutor:new PosQueryExecutor(customPosConnection:$this->pos),providerClient:$this->provider,clock:new BusinessClock('Asia/Jakarta',new \DateTimeImmutable('2026-10-19')));
    }
    private function plan(string $intent='sales_returns_presence',array $replace=[]): array
    {
        [$domain,$entity,$metric]=match($intent){'top_products'=>['sales','product','product_units'],'sales_daily'=>['sales','sale','invoice_amount'],'sales_presence'=>['sales','sale','transaction_count'],'cash_transactions'=>['cash_finance','cash_transaction','cash_amount'],'cash_flow'=>['cash_finance','cash_entry','cash_inflow'],default=>['returns_receivables','sale_return',$intent==='sales_returns_presence'?'return_count':'return_amount']};
        return array_replace(['version'=>1,'intent'=>$intent,'domain'=>$domain,'request'=>'Permintaan bisnis dengan periode lengkap.','period'=>['year'=>2026,'month'=>null,'date_start'=>'2026-10-19','date_end'=>'2026-10-20'],'entity'=>$entity,'metric'=>$metric,'dimensions'=>$intent==='top_products'?['product']:($intent==='sales_daily'?['date']:[]),'time_grain'=>$intent==='sales_daily'?'day':null,'filters'=>[],'ranking'=>$intent==='top_products'?['metric'=>$metric,'direction'=>'desc','limit'=>5]:null,'output_preference'=>'auto','parameters'=>$intent==='cash_transactions'?['cash_direction'=>'BOTH']:[]],$replace);
    }
    private function ask(string $question,array $plan,?string $uid=null):array
    {
        $this->provider->replies=[['parsed_json'=>['decision'=>'analysis','plan'=>$plan]]];
        return $this->engine->handleMessage($question,1,'Administrator',$uid);
    }
    public function testReturnPresenceUsesReturnDateAndValidNormalSourceWithoutTableActions():void
    {
        $r=$this->ask('retur penjualan hari ini apakah tersedia?',$this->plan());
        $this->assertTrue($r['success'],$r['summary']);$this->assertStringContainsString('1 retur penjualan sah',$r['summary']);$this->assertStringContainsString('Rp24',$r['summary']);
        $this->assertCount(1,$r['metadata']['queries']);$this->assertSame(['text'],array_column($r['blocks'],'type'));
        $this->assertFalse($r['metadata']['actions']['table']);$this->assertFalse($r['metadata']['actions']['chart']);
        $this->assertSame(1,$r['metadata']['provider_metrics']['calls']);
        $this->assertStringContainsString('r . tanggal',$r['metadata']['queries'][0]['sql']);
        $this->provider->replies=[['parsed_json'=>['decision'=>'context','context_subject'=>'meaning','report_ref'=>$r['metadata']['report_ref']]]];
        $bad=$this->engine->handleMessage('apakah ada yang melakukan retur?',1,'Administrator',$r['conversation_uid']);
        $this->assertFalse($bad['success']);$this->assertSame('provider_route_mismatch',$bad['error_code']);$this->assertEquals($r['context'],$bad['context']);
        $next=$this->ask('apakah ada yang melakukan retur?',$this->plan(),$r['conversation_uid']);
        $this->assertTrue($next['success'],$next['summary']);$this->assertCount(1,$next['metadata']['queries']);
    }
    public function testReturnQuestionCannotBeAnsweredBySalesPresencePlan():void
    {
        $r=$this->ask('retur penjualan hari ini apakah tersedia?',$this->plan('sales_presence'));
        $this->assertFalse($r['success']);$this->assertSame('provider_route_mismatch',$r['error_code']);$this->assertSame([],$r['metadata']['queries']);
        $this->assertSame(0,db_connect()->table('ai_query_logs')->countAllResults());
    }
    public function testEmptyReturnsExplainAbsenceWithoutInventingZeroRowsAndReloadKeepsFacts():void
    {
        $p=$this->plan('sales_returns',['period'=>['year'=>2026,'month'=>null,'date_start'=>'2026-10-18','date_end'=>'2026-10-19']]);
        $r=$this->ask('Laporan retur penjualan tanggal 18 Oktober 2026.',$p);
        $this->assertTrue($r['success'],$r['summary']);$this->assertTrue($r['metadata']['empty_result']);$this->assertStringContainsString('tidak ditemukan retur penjualan sah',$r['summary']);
        $this->assertSame(['text'],array_column($r['blocks'],'type'));
        $loaded=(new ReportContext())->forClient((new ConversationService())->getConversation($r['conversation_uid'],1),'Administrator');
        $last=end($loaded['messages']);$this->assertStringContainsString('tidak ditemukan retur penjualan sah',$last['content']);$this->assertFalse($last['metadata']['actions']['export']);
    }
    public function testReturnReportHasOriginalDocumentAndCorrectReferenceForReformat():void
    {
        $r=$this->ask('Laporan retur penjualan hari ini.',$this->plan('sales_returns'));
        $this->assertTrue($r['success'],$r['summary']);$this->assertCount(2,$r['metadata']['queries']);
        $this->assertSame(['r1'],array_column($r['context']['last_rows'],'id_retur'));$this->assertTrue($r['metadata']['actions']['export']);
        $this->provider->replies=[['parsed_json'=>['decision'=>'reformat','report_ref'=>$r['metadata']['report_ref'],'format'=>'table']]];
        $next=$this->engine->handleMessage('Ubah menjadi tabel.',1,'Administrator',$r['conversation_uid'],'table',$r['metadata']['report_ref']);
        $this->assertTrue($next['success'],$next['summary']);$this->assertSame([],$next['metadata']['queries']);$this->assertEquals($r['context']['last_rows'],$next['context']['last_rows']);
    }
    public function testProductQuantityClarificationAndFullRequestGiveIdenticalUnitRankingWithTies():void
    {
        $this->provider->replies=[['parsed_json'=>['decision'=>'clarify','question'=>'Berdasarkan nominal atau jumlah unit?','slot'=>'metric']]];
        $c=$this->engine->handleMessage('5 produk yang paling laris di bulan ini',1,'Administrator');
        $this->assertSame('clarification',$c['analysis_status']);
        $r=$this->ask('jumlah',$this->plan('top_products'),$c['conversation_uid']);
        $this->assertTrue($r['success'],$r['summary']);$this->assertSame(['A','C','D','E','F'],array_column($r['context']['last_rows'],'id_produk'));
        $this->assertSame(10.0,(float)$r['context']['last_rows'][0]['total_unit_dasar']);$this->assertStringContainsString('unit dasar',$r['summary']);
        $full=$this->ask('5 produk yang paling laris bulan ini berdasarkan jumlah terjualnya',$this->plan('top_products'));
        $this->assertSame($r['context']['last_rows'],$full['context']['last_rows']);
        $amount=$this->ask('Peringkat nominal produk',$this->plan('top_products',['metric'=>'product_amount','ranking'=>['metric'=>'product_amount','direction'=>'desc','limit'=>1]]));
        $this->assertTrue($amount['success'],$amount['summary']);$this->assertSame('B',$amount['context']['last_rows'][0]['id_produk']);
        $ties=$this->ask('Dua produk tertinggi',$this->plan('top_products',['ranking'=>['metric'=>'product_units','direction'=>'desc','limit'=>4]]));
        $this->assertCount(5,$ties['context']['last_rows']);$this->assertStringContainsString('Nilai seri',$ties['summary']);
    }
    public function testNewDailySalesAfterInvalidPlanDoesNotInheritFailedRanking():void
    {
        $p=$this->plan('top_products');$p['ranking']['metric']='nonexistent';
        $bad=$this->ask('Produk terlaris',$p);$this->assertFalse($bad['success']);
        $r=$this->ask('laporan penjualan hari ini',$this->plan('sales_daily'),$bad['conversation_uid']);
        $this->assertTrue($r['success'],$r['summary']);$this->assertStringContainsString('Rp900',$r['summary']);$this->assertSame(null,$r['context']['semantic_plan']['ranking']);
    }
    public function testCashManualDirectionsAndLedgerExcludeTransfersAndUnfinalDocuments():void
    {
        foreach([['BOTH',2,100,40],['MASUK',1,100,0],['KELUAR',1,0,40]] as [$direction,$count,$in,$out]){
            $r=$this->ask('Laporan kas operasional manual hari ini',$this->plan('cash_transactions',['parameters'=>['cash_direction'=>$direction]]));
            $this->assertTrue($r['success'],$r['summary']);$this->assertCount($count,$r['context']['last_rows']);
            $facts=array_column($r['metadata']['report_facts']['metrics'],'value','key');
            if($direction!=='KELUAR')$this->assertEquals($in,$facts['cash_inflow']);else $this->assertArrayNotHasKey('cash_inflow',$facts);
            if($direction!=='MASUK')$this->assertEquals($out,$facts['cash_outflow']);else $this->assertArrayNotHasKey('cash_outflow',$facts);
            $this->assertStringContainsString('Transfer antar akun dikecualikan',$r['summary']);
        }
        $ledger=$this->ask('Seluruh arus kas dan bank',$this->plan('cash_flow'));
        $this->assertTrue($ledger['success'],$ledger['summary']);$this->assertCount(2,$ledger['context']['last_rows']);
        $this->assertEquals(300,$ledger['metadata']['report_facts']['metrics'][0]['value']);$this->assertEquals(80,$ledger['metadata']['report_facts']['metrics'][1]['value']);
    }
    public function testPlanDiagnosticsIdentifyFieldAndProductionHidesThemWithoutEchoingFreeText():void
    {
        $p=$this->plan();unset($p['filters']);$p['request']='[PRIVATE] password and arbitrary text';
        $r=$this->ask('Permintaan retur',$p);
        $this->assertSame('plan.filters',$r['metadata']['plan_diagnostics']['issues'][0]['path']);
        $this->assertStringNotContainsString('[PRIVATE]',json_encode($r['metadata']['plan_diagnostics']));
        $this->assertArrayNotHasKey('plan_diagnostics',(new AiClientDiagnostics())->present($r,true)['metadata']);
        $this->assertArrayHasKey('plan_diagnostics',(new AiClientDiagnostics())->present($r,false)['metadata']);
    }
    public function testWrongReturnSourcePeriodOrUnfinalStatusCannotPassDynamicContract():void
    {
        $plan=(new AiAnalysisPlan(new BusinessClock()))->validate($this->plan());
        foreach([
            "SELECT COUNT(DISTINCT p.id_transaksi) AS return_count FROM tbltransaksipenjualan p WHERE COALESCE(p.source_type,'NORMAL')='NORMAL' AND p.tanggal >= '2026-10-19' AND p.tanggal < '2026-10-20'",
            "SELECT COUNT(DISTINCT r.id_retur) AS return_count FROM tblreturpenjualan r JOIN tbltransaksipenjualan p ON p.id_transaksi=r.id_transaksi WHERE COALESCE(p.source_type,'NORMAL')='NORMAL' AND r.tanggal >= '2026-10-19' AND r.tanggal < '2026-10-20'",
            "SELECT COUNT(DISTINCT r.id_retur) AS return_count FROM tblreturpenjualan r JOIN tbltransaksipenjualan p ON p.id_transaksi=r.id_transaksi OR 1=1 WHERE COALESCE(r.hapus,0)=0 AND r.status='FINAL' AND COALESCE(p.source_type,'NORMAL')='NORMAL' AND r.tanggal >= '2026-10-19' AND r.tanggal < '2026-10-20'",
        ] as $sql){try{(new SqlPlanContract())->assertMatches($sql,$plan);$this->fail('Wrong source accepted');}catch(AnalysisException $e){$this->assertSame('business_requirement_mismatch',$e->errorCode);}}
    }
    public function testQueryOrProviderFailureKeepsPriorResultAndNeverClaimsNoReturns():void
    {
        $first=$this->ask('Laporan penjualan hari ini',$this->plan('sales_daily'));
        $this->pos->query('DROP TABLE tblreturpenjualan');
        $failed=$this->ask('retur penjualan hari ini apakah tersedia?',$this->plan(),$first['conversation_uid']);
        $this->assertFalse($failed['success']);$this->assertSame('query_failed',$failed['error_code']);$this->assertEquals($first['context'],$failed['context']);$this->assertStringNotContainsString('tidak ditemukan',$failed['summary']);
        $this->provider->replies=[['error'=>'offline','used_remote'=>false]];
        $offline=$this->engine->handleMessage('retur penjualan hari ini apakah tersedia?',1,'Administrator',$first['conversation_uid']);
        $this->assertSame('provider_unavailable',$offline['error_code']);$this->assertSame([],$offline['metadata']['queries']);
    }
    public function testValidExplicitDateRangeCanSupplyYearAndPresenceMayRequestText():void
    {
        $r=$this->ask('retur penjualan hari ini apakah tersedia?',$this->plan('sales_returns_presence',['period'=>['year'=>null,'month'=>null,'date_start'=>'2026-10-19','date_end'=>'2026-10-20'],'output_preference'=>'text']));
        $this->assertTrue($r['success'],$r['summary']);$this->assertSame(2026,$r['context']['semantic_plan']['period']['year']);$this->assertSame(['text'],array_column($r['blocks'],'type'));
        $bad=$this->ask('retur penjualan',$this->plan('sales_returns_presence',['period'=>['year'=>2025,'month'=>null,'date_start'=>'2026-10-19','date_end'=>'2026-10-20']]));
        $this->assertFalse($bad['success']);$this->assertSame('provider_invalid_plan',$bad['error_code']);
    }
    public function testOldReturnReferenceExportsOriginalRowsAfterProductReportAndRejectsOtherOwner():void
    {
        $r=$this->ask('Laporan retur penjualan hari ini',$this->plan('sales_returns'));
        $p=$this->ask('Peringkat produk',$this->plan('top_products'),$r['conversation_uid']);$this->assertTrue($p['success'],$p['summary']);
        $url='/asisten-ai/export/'.$r['conversation_uid'].'/csv?report_ref='.$r['metadata']['report_ref'];
        $session=['auth_user_id'=>1,'auth_display_name'=>'QA','auth_role'=>'Administrator'];
        $csv=$this->asUser($session)->get($url);$csv->assertStatus(200);$body=$csv->response()->getBody();
        $this->assertStringContainsString('r1',$body);$this->assertStringNotContainsString('Unit Winner',$body);
        db_connect()->table('users')->insert(['id'=>2,'username'=>'other','display_name'=>'Other','password_hash'=>password_hash('Testing123!',PASSWORD_DEFAULT),'role'=>'Administrator','is_active'=>1]);
        $this->asUser(['auth_user_id'=>2,'auth_display_name'=>'Other','auth_role'=>'Administrator'])->get($url)->assertStatus(404);
    }
    public function testCashDirectionAndTransferProofRejectsOrBypassAndWrongPeriod():void
    {
        $plan=(new AiAnalysisPlan())->validate($this->plan('cash_transactions',['parameters'=>['cash_direction'=>'KELUAR']]));
        $base="SELECT SUM(c.nominal) AS nominal FROM tbltransaksi_kas c WHERE COALESCE(c.hapus,0)=0 AND c.status='FINAL' AND c.jenis='KELUAR' AND c.tanggal>='2026-10-19' AND c.tanggal<'2026-10-20'";
        (new SqlPlanContract())->assertMatches($base,$plan);$this->addToAssertionCount(1);
        foreach([$base.' OR 1=1',str_replace("c.jenis='KELUAR'","c.jenis='MASUK'",$base),str_replace('2026-10-19','2026-09-19',$base)] as $sql){
            try{(new SqlPlanContract())->assertMatches($sql,$plan);$this->fail('Cash mismatch accepted');}catch(AnalysisException $e){$this->assertContains($e->errorCode,['business_requirement_mismatch','business_period_mismatch']);}
        }
    }
    public function testUnsupportedDimensionCannotSilentlyRunCompiledTotalsAndHistoricalBalanceIsRejected():void
    {
        $p=$this->plan('cash_flow',['dimensions'=>['date'],'time_grain'=>'day']);$validated=(new AiAnalysisPlan())->validate($p);
        $this->assertSame('dynamic_llm_or_clarify',$validated['intent']);
        $balance=$this->plan('cash_transactions',['intent'=>'cash_bank_summary','entity'=>'bank_account','metric'=>'cash_balance','parameters'=>[]]);
        $r=$this->ask('Saldo bulan lalu',$balance);$this->assertFalse($r['success']);$this->assertSame('plan.period',$r['metadata']['plan_diagnostics']['issues'][0]['path']);
    }
    public function testConfiguredRowLimitCannotSilentlyTruncateCashOrProductTies():void
    {
        $config=clone config(\Config\AiAnalyst::class);$config->maxRowLimit=1;
        $this->engine=new AiOrchestrator(queryExecutor:new PosQueryExecutor(customPosConnection:$this->pos,config:$config),providerClient:$this->provider,config:$config);
        $cash=$this->ask('Kas manual tanggal 19 Oktober',$this->plan('cash_transactions'));
        $this->assertFalse($cash['success']);$this->assertSame('result_limit_exceeded',$cash['error_code']);
        $this->assertCount(1,$cash['metadata']['queries']);$this->assertTrue($cash['metadata']['queries'][0]['success']);
        $this->assertSame([],$cash['context']['last_rows']??[]);
        $top=$this->ask('Lima produk berdasarkan unit',$this->plan('top_products'));
        $this->assertFalse($top['success']);$this->assertSame('result_limit_exceeded',$top['error_code']);
    }
    public function testChangedRoleCannotExportOrReceiveStoredCashFacts():void
    {
        $cash=$this->ask('Kas operasional manual hari ini',$this->plan('cash_transactions'));
        $this->assertTrue($cash['success'],$cash['summary']);
        $loaded=(new ReportContext())->forClient((new ConversationService())->getConversation($cash['conversation_uid'],1),'Kasir');
        $this->assertStringNotContainsString('Listrik',json_encode($loaded));$this->assertSame([],$loaded['context']);
        db_connect()->table('users')->where('id',1)->update(['role'=>'Kasir']);
        $this->asUser(['auth_user_id'=>1,'auth_display_name'=>'QA','auth_role'=>'Kasir'])->get('/asisten-ai/export/'.$cash['conversation_uid'].'/csv?report_ref='.$cash['metadata']['report_ref'])->assertStatus(404);
    }
    public function testCashMovementQuestionCannotUseCurrentBalanceInstead():void
    {
        $r=$this->ask('Laporan pengeluaran kas bulan ini',$this->plan('cash_transactions',['intent'=>'cash_bank_summary','entity'=>'bank_account','metric'=>'cash_balance','parameters'=>[],'period'=>['year'=>2026,'month'=>null,'date_start'=>null,'date_end'=>null]]));
        $this->assertFalse($r['success']);$this->assertSame('provider_route_mismatch',$r['error_code']);$this->assertSame([],$r['metadata']['queries']);
    }
    public function testCompletedReturnStatusesAndMissingPhysicalColumnDiagnostics():void
    {
        $this->pos->query("INSERT INTO tblreturpenjualan VALUES ('done','oct','2026-10-19',5,'selesai',0,'TUNAI'),('success','oct','2026-10-19',6,'SUKSES',0,'TUNAI')");
        $r=$this->ask('retur penjualan hari ini apakah tersedia?',$this->plan());
        $this->assertTrue($r['success'],$r['summary']);$this->assertStringContainsString('3 retur penjualan sah',$r['summary']);$this->assertStringContainsString('Rp35',$r['summary']);
        $bad=$this->ask('Retur penjualan',$this->plan('sales_returns',['filters'=>[['scope'=>'row','field'=>'sale_return.deleted','operator'=>'=','value'=>0]]]));
        $this->assertSame('plan.filters.0.field',$bad['metadata']['plan_diagnostics']['issues'][0]['path']);
    }
    public function testRenamedHistoricalProductStaysOneSkuInUnitRanking():void
    {
        $this->pos->query("INSERT INTO tbltransaksipenjualan_detail VALUES ('oct','A','Older Product Name','Old Category',1,1,1,1)");
        $r=$this->ask('Peringkat unit produk',$this->plan('top_products'));
        $this->assertTrue($r['success'],$r['summary']);$this->assertSame('A',$r['context']['last_rows'][0]['id_produk']);
        $this->assertEquals(11,$r['context']['last_rows'][0]['total_unit_dasar']);$this->assertSame(1,count(array_filter($r['context']['last_rows'],static fn($row)=>$row['id_produk']==='A')));
    }
    public function testChangingPosRowsBetweenSummaryAndDetailsDoesNotProduceMixedReport():void
    {
        $plan=(new AiAnalysisPlan())->validate($this->plan('sales_returns'));$calls=0;
        $run=function()use(&$calls){$calls++;return ['rows'=>$calls===1?[['return_count'=>1,'return_amount'=>24]]:[['id_retur'=>'r1','id_transaksi'=>'sept','tanggal'=>'2026-10-19','total_retur'=>25,'tipe_retur'=>'TUNAI']]];};
        $this->expectException(AnalysisException::class);$this->expectExceptionMessage('Data POS berubah');
        (new \App\Services\AI\OperationalReports(new \App\Services\AI\ConversationScope(new BusinessClock())))->render($plan,[],$run);
    }
}
