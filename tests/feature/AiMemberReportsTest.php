<?php
namespace Tests\Feature;

use App\Services\AI\{AiAnalysisPlan,AiOrchestrator,AnalysisException,BusinessClock,ConversationService,MemberSalesReport,ConversationScope,PosQueryExecutor,ReportContext,MemberSqlScope};
use CodeIgniter\Test\{CIUnitTestCase,DatabaseTestTrait,FeatureTestTrait};
use Tests\Support\FakeOnlineProvider;

final class AiMemberReportsTest extends CIUnitTestCase
{
    use DatabaseTestTrait,FeatureTestTrait,\Tests\Support\AuthenticatedSession;
    protected $migrate=true;protected $refresh=true;protected $namespace='App';
    private FakeOnlineProvider $provider;private AiOrchestrator $engine;private $pos;
    protected function setUp():void
    {
        parent::setUp();
        db_connect()->table('users')->insert(['id'=>1,'username'=>'member-reports','display_name'=>'QA','password_hash'=>password_hash('Testing123!',PASSWORD_DEFAULT),'role'=>'Administrator','is_active'=>1]);
        $this->pos=\Config\Database::connect(config('Database')->tests,false);$this->pos->initialize();
        foreach([
            'CREATE TABLE tbldata_member (id_member TEXT PRIMARY KEY,nama TEXT,poin INTEGER,hapus INTEGER)',
            'CREATE TABLE tbltransaksipenjualan (id_transaksi TEXT PRIMARY KEY,no_faktur TEXT,tanggal TEXT,grand_total REAL,total_kotor REAL,source_type TEXT,id_member TEXT)',
            "INSERT INTO tbldata_member VALUES ('POS_PRIVATE_ID','Hendra Wijaya',0,0),('M2','Hendra Saputra',0,0),('D','Deleted Member',0,1)",
            "INSERT INTO tbltransaksipenjualan VALUES ('t1','F1','2026-10-01 09:00:00',100,100,'NORMAL','POS_PRIVATE_ID'),('t2','F2','2026-10-01 10:00:00',200,200,NULL,'POS_PRIVATE_ID'),('t3','F3','2026-10-03 09:00:00',-50,-50,'NORMAL','POS_PRIVATE_ID'),('t4','F4','2026-10-05 09:00:00',0,0,'NORMAL','POS_PRIVATE_ID'),('s','FS','2026-09-01',70,70,'NORMAL','POS_PRIVATE_ID'),('d','FD','2026-12-31',40,40,'NORMAL','POS_PRIVATE_ID'),('l','FL','2026-10-01',999,999,'LEGACY_HISTORY','POS_PRIVATE_ID'),('other','FO','2026-10-01',999,999,'NORMAL','M2')",
        ] as $sql)$this->pos->query($sql);
        $this->provider=new FakeOnlineProvider();$this->provider->adaptLegacyPlans=false;$this->provider->baseUrlOverride='http://localhost:20128/v1';
        $this->engine=new AiOrchestrator(queryExecutor:new PosQueryExecutor(customPosConnection:$this->pos),providerClient:$this->provider,clock:new BusinessClock('Asia/Jakarta',new \DateTimeImmutable('2026-10-19')));
    }
    private function plan(array $replace=[]):array
    {
        return array_replace(['version'=>1,'intent'=>'member_spend','domain'=>'members','request'=>'Total belanja Hendra Wijaya pada Oktober 2026','period'=>['year'=>2026,'month'=>10,'date_start'=>null,'date_end'=>null],
            'entity'=>'member','metric'=>'invoice_amount','dimensions'=>['member'],'time_grain'=>null,'filters'=>[],'ranking'=>null,'output_preference'=>'auto','parameters'=>['member_query'=>'Hendra Wijaya']],$replace);
    }
    private function ask(array $plan,?string $uid=null,string $question='Pembelian member Hendra Wijaya bulan ini berapa?',string $role='Administrator'):array
    {
        $this->provider->replies=[['parsed_json'=>['decision'=>'analysis','plan'=>$plan]]];
        return $this->engine->handleMessage($question,1,$role,$uid);
    }
    private function lookup(string $name='Hendra Wijaya',?string $uid=null):array
    {
        return $this->ask($this->plan(['intent'=>'data_lookup','request'=>'Cari member '.$name,'period'=>null,'metric'=>null,'dimensions'=>[],
            'filters'=>[['scope'=>'row','field'=>'tbldata_member.nama','operator'=>'contains','value'=>$name]],'output_preference'=>'text',
            'parameters'=>['table'=>'tbldata_member','lookup_mode'=>'presence','columns'=>['id_member','nama']]]),$uid,'Member atas nama '.$name.' ada?');
    }
    private function ref(array $result):array{return ['member_ref'=>$result['context']['member_identity']['ref']];}
    private function daily(array $parameters):array{return $this->plan(['request'=>'Grafik pembelian harian member terpilih untuk Oktober 2026','dimensions'=>['date'],'time_grain'=>'day','output_preference'=>'chart','parameters'=>$parameters]);}
    public function testLookupThenDocumentsThenDailyAndYearMaintainIdentityAndCorrectGrain():void
    {
        $found=$this->lookup();$this->assertTrue($found['success'],$found['summary']);$this->assertNotEmpty($found['context']['member_identity']);
        $docs=$this->ask($this->plan(['intent'=>'member_transactions','metric'=>null,'dimensions'=>['transaction'],'output_preference'=>'table','parameters'=>$this->ref($found)]),$found['conversation_uid'],'tampilkan transaksi penjualan member tersebut di bulan ini');
        $this->assertTrue($docs['success'],$docs['summary']);$this->assertSame(['t1','t2','t3','t4'],array_column($docs['context']['last_rows'],'id_transaksi'));$this->assertStringContainsString('Rp250',$docs['summary']);
        $day=$this->ask($this->daily($this->ref($docs)),$docs['conversation_uid'],'saya ingin lihat grafik pembelian dia bulan ini. kamu buat data perhari ya');
        $this->assertTrue($day['success'],$day['summary']);$this->assertSame(['2026-10-01','2026-10-03','2026-10-05'],array_column($day['context']['last_rows'],'tanggal'));
        $this->assertSame([300.0,-50.0,0.0],$day['context']['last_chart']['series'][0]['data']);
        $this->assertEquals(4,array_sum(array_column($day['context']['last_rows'],'jumlah_transaksi')));$this->assertEquals(250,array_sum(array_column($day['context']['last_rows'],'total_belanja')));
        $year=$this->ask($this->plan(['period'=>['year'=>2026,'month'=>null,'date_start'=>null,'date_end'=>null],'metric'=>'transaction_count','parameters'=>$this->ref($day)]),$day['conversation_uid'],'transaksi tahun ini berapa member tersebut?');
        $this->assertTrue($year['success'],$year['summary']);$this->assertNull($year['context']['month']);$this->assertEquals(6,$year['context']['last_rows'][0]['jumlah_transaksi']);$this->assertStringContainsString('Rp360',$year['summary']);
        foreach([$found,$docs,$day,$year] as $r)$this->assertSame(1,$r['metadata']['provider_metrics']['calls']);
        $this->assertSame($docs['context']['member_identity']['ref'],$day['context']['member_identity']['ref']);
        $payload=json_decode(end($this->provider->calls)[1]['content'],true);
        $this->assertArrayNotHasKey('member_query',$payload['candidate_plan']);
        $this->assertNotSame('member',$payload['candidate_plan']['clarification_slot']??null);
    }
    public function testShortDailyThenTableAndOldReportExportAfterReloadDoNotQueryAgain():void
    {
        $first=$this->ask($this->daily(['member_query'=>'Hendra Wijaya']));
        $next=$this->ask(array_replace($this->daily($this->ref($first)),['output_preference'=>'table']),$first['conversation_uid'],'perhari');
        $this->assertTrue($next['success'],$next['summary']);$this->assertEquals($first['context']['last_rows'],$next['context']['last_rows']);
        $this->ask($this->plan(['parameters'=>['member_query'=>'Hendra Saputra']]),$first['conversation_uid']);
        $this->provider->replies=[['parsed_json'=>['decision'=>'reformat','format'=>'table','report_ref'=>$next['metadata']['report_ref']]]];
        $r=$this->engine->handleMessage('Tampilkan tabel harian sebelumnya',1,'Administrator',$first['conversation_uid'],'table',$next['metadata']['report_ref']);
        $this->assertTrue($r['success'],$r['summary']);$this->assertSame([],$r['metadata']['queries']);$this->assertEquals($first['context']['last_rows'],$r['context']['last_rows']);
        $loaded=(new ReportContext())->forClient((new ConversationService())->getConversation($first['conversation_uid'],1),'Administrator');
        $this->assertSame('member_spend',$loaded['context']['intent']);
        $csv=$this->asUser(['auth_user_id'=>1,'auth_role'=>'Administrator'])->get('/asisten-ai/export/'.$first['conversation_uid'].'/csv?report_ref='.$next['metadata']['report_ref']);$csv->assertStatus(200);
        $this->assertStringContainsString('2026-10-03',$csv->response()->getBody());$this->assertStringContainsString('-50',$csv->response()->getBody());
    }
    public function testMissingIdentityDiagnosticPointsToMemberParameterAndWrongShapeCannotSucceed():void
    {
        $first=$this->ask($this->plan());
        $bad=$this->ask($this->plan(['parameters'=>['limit'=>100]]),$first['conversation_uid'],'perhari');
        $this->assertFalse($bad['success']);$this->assertSame('plan.parameters.member_query',$bad['metadata']['plan_diagnostics']['issues'][0]['path']);$this->assertEquals($first['context'],$bad['context']);
        foreach([
            ['dimensions'=>['date'],'time_grain'=>null],['dimensions'=>['member'],'time_grain'=>'day'],
            ['output_preference'=>'chart'],['metric'=>'product_units'],['intent'=>'member_transactions','metric'=>'invoice_amount','dimensions'=>['member']],
        ] as $change){$r=$this->ask($this->plan($change));$this->assertFalse($r['success']);$this->assertSame([],$r['metadata']['queries']);}
    }
    public function testAmbiguousOrDeletedLookupCannotReuseOldMemberAndForgedReferenceIsRejected():void
    {
        $first=$this->lookup();$ambiguous=$this->lookup('Hendra',$first['conversation_uid']);$this->assertNull($ambiguous['context']['member_identity']);
        $bad=$this->ask($this->plan(['parameters'=>$this->ref($first)]),null,'Pembelian dia bulan ini berapa?');$this->assertFalse($bad['success']);$this->assertSame([],$bad['metadata']['queries']);
        $deleted=$this->lookup('Deleted Member',$first['conversation_uid']);$this->assertTrue($deleted['metadata']['empty_result']);$this->assertNull($deleted['context']['member_identity']);
        $bad=$this->ask($this->plan(['parameters'=>['member_ref'=>'member-forged']]),$first['conversation_uid']);$this->assertFalse($bad['success']);$this->assertSame('plan.parameters.member_ref',$bad['metadata']['plan_diagnostics']['issues'][0]['path']);
    }
    public function testDuplicateMemberClarifiesAndQueryOrProviderFailureNeverInventsZero():void
    {
        $first=$this->ask($this->plan());
        $this->pos->query("INSERT INTO tbldata_member VALUES ('M3','Hendra Wijaya',0,0)");
        $r=$this->ask($this->plan(),$first['conversation_uid']);$this->assertSame('clarification',$r['analysis_status']);$this->assertStringContainsString('M3',$r['summary']);$this->assertEquals($first['context']['last_rows'],$r['context']['last_rows']);
        $this->pos->query('DROP TABLE tbltransaksipenjualan');
        $r=$this->ask($this->daily($this->ref($first)),$first['conversation_uid']);$this->assertFalse($r['success']);$this->assertSame('query_failed',$r['error_code']);$this->assertStringNotContainsString('Rp0',$r['summary']);
        $this->provider->replies=[['used_remote'=>false,'error'=>'offline']];$r=$this->engine->handleMessage('perhari',1,'Administrator',$first['conversation_uid']);$this->assertSame('provider_unavailable',$r['error_code']);$this->assertSame([],$r['metadata']['queries']);
    }
    public function testReferenceBecomingAmbiguousDoesNotOverwriteSuccessfulSemanticContext():void
    {
        $first=$this->lookup();$this->pos->query("INSERT INTO tbldata_member VALUES ('M3','Hendra Wijaya',0,0)");
        $r=$this->ask($this->daily($this->ref($first)),$first['conversation_uid'],'Grafik perhari member tersebut');
        $this->assertSame('clarification',$r['analysis_status']);$this->assertEquals($first['context']['semantic_plan'],$r['context']['semantic_plan']);
        $this->assertEquals($first['context']['last_rows'],$r['context']['last_rows']);$this->assertSame('member',$r['context']['pending_request']['slot']);
    }
    public function testEmptyPeriodIsTextOnlyAndStillKeepsLocalIdentityForFollowup():void
    {
        $r=$this->ask(array_replace($this->daily(['member_query'=>'Hendra Wijaya']),['period'=>['year'=>2026,'month'=>2,'date_start'=>null,'date_end'=>null]]));
        $this->assertTrue($r['success'],$r['summary']);$this->assertTrue($r['metadata']['empty_result']);$this->assertSame(['text'],array_column($r['blocks'],'type'));
        $this->assertSame(['table'=>false,'chart'=>false,'export'=>false],$r['metadata']['actions']);$this->assertNotEmpty($r['context']['member_identity']);
        $next=$this->ask($this->plan(['parameters'=>$this->ref($r)]),$r['conversation_uid']);$this->assertTrue($next['success'],$next['summary']);
    }
    public function testDynamicFallbackUsesLocalPlaceholderAndProvesEveryMemberFilter():void
    {
        $first=$this->lookup();$p=$this->plan(['intent'=>'dynamic_llm_or_clarify','parameters'=>$this->ref($first),'output_preference'=>'text',
            'filters'=>[['scope'=>'aggregate','metric'=>'invoice_amount','operator'=>'>','value'=>200]]]);
        $this->provider->replies=[['parsed_json'=>['decision'=>'analysis','plan'=>$p]],['parsed_json'=>['sql'=>"SELECT SUM(p.grand_total) AS total_belanja FROM tbltransaksipenjualan p WHERE COALESCE(p.source_type,'NORMAL')='NORMAL' AND p.id_member='__selected_member__' AND p.tanggal>='2026-10-01' AND p.tanggal<'2026-11-01' HAVING SUM(p.grand_total)>200",'chart_type'=>'none']]];
        $r=$this->engine->handleMessage('Total belanja dia yang melebihi 200 bulan ini',1,'Administrator',$first['conversation_uid']);
        $this->assertTrue($r['success'],$r['summary']);$this->assertStringContainsString('Rp250',$r['summary']);
        $prompt=json_encode(end($this->provider->calls));$this->assertStringNotContainsString('POS_PRIVATE_ID',$prompt);$this->assertStringContainsString('__selected_member__',$prompt);
        $this->assertSame(2,$r['metadata']['provider_metrics']['calls']);
        $base="SELECT SUM(p.grand_total) FROM tbltransaksipenjualan p WHERE p.id_member='POS_PRIVATE_ID'";
        (new MemberSqlScope())->assertMatches($base,'POS_PRIVATE_ID');$this->addToAssertionCount(1);
        foreach([str_replace('POS_PRIVATE_ID','M2',$base),$base.' OR 1=1',str_replace("p.id_member='POS_PRIVATE_ID'",'1=1',$base)] as $sql){
            try{(new MemberSqlScope())->assertMatches($sql,'POS_PRIVATE_ID');$this->fail('Wrong member accepted');}catch(AnalysisException $e){$this->assertSame('business_member_mismatch',$e->errorCode);}
        }
    }
    public function testRoleOwnershipAndPromptPrivacyArePreserved():void
    {
        $first=$this->ask($this->daily(['member_query'=>'Hendra Wijaya']));
        $this->assertTrue($first['success'],$first['summary']);
        $this->provider->replies=[['parsed_json'=>['decision'=>'context','context_subject'=>'period','report_ref'=>$first['metadata']['report_ref']]]];
        $r=$this->engine->handleMessage('Itu data kapan?',1,'Administrator',$first['conversation_uid']);$this->assertSame([],$r['metadata']['queries']);
        $this->assertStringNotContainsString('POS_PRIVATE_ID',json_encode(end($this->provider->calls)));
        $bad=$this->ask($this->daily($this->ref($first)),$first['conversation_uid'],'perhari','Gudang');$this->assertSame([],$bad['metadata']['queries']);
        try{$this->engine->handleMessage('perhari',2,'Administrator',$first['conversation_uid']);$this->fail('Cross-owner access accepted');}catch(AnalysisException $e){$this->assertSame('conversation_not_found',$e->errorCode);}
    }
    public function testOldReportReferenceCanSelectEarlierMemberWithoutLeakingIdentity():void
    {
        $first=$this->ask($this->plan());$this->ask($this->plan(['parameters'=>['member_query'=>'Hendra Saputra']]),$first['conversation_uid']);
        $r=$this->ask($this->daily($this->ref($first)),$first['conversation_uid'],'Grafik harian member pada laporan pertama');
        $this->assertTrue($r['success'],$r['summary']);$this->assertEquals(250,array_sum(array_column($r['context']['last_rows'],'total_belanja')));
        $this->assertStringNotContainsString('POS_PRIVATE_ID',json_encode(end($this->provider->calls)));
    }
    public function testDailyAndDocumentLimitsCannotBecomeSilentlyTruncatedSuccess():void
    {
        $config=clone config(\Config\AiAnalyst::class);$config->maxRowLimit=2;
        $engine=new AiOrchestrator(queryExecutor:new PosQueryExecutor(customPosConnection:$this->pos,config:$config),providerClient:$this->provider,config:$config);
        foreach([$this->daily(['member_query'=>'Hendra Wijaya']),$this->plan(['intent'=>'member_transactions','metric'=>null,'dimensions'=>['transaction'],'output_preference'=>'table'])] as $plan){
            $this->provider->replies=[['parsed_json'=>['decision'=>'analysis','plan'=>$plan]]];$r=$engine->handleMessage('Rincian transaksi member bulan ini',1,'Administrator');
            $this->assertFalse($r['success']);$this->assertContains($r['error_code'],['source_changed','result_limit_exceeded']);$this->assertNotEmpty($r['metadata']['queries']);
        }
    }
    public function testChangedSourceCannotPassWhenDetailCountOrAmountDisagrees():void
    {
        $clock=new BusinessClock('Asia/Jakarta',new \DateTimeImmutable('2026-10-19'));$plan=(new AiAnalysisPlan($clock))->validate($this->daily(['member_query'=>'Hendra Wijaya']));
        $calls=0;$execute=function()use(&$calls):array{$calls++;return ['rows'=>match($calls){1=>[['id_member'=>'M','nama'=>'Hendra']],2=>[['jumlah_transaksi'=>2,'total_belanja'=>300]],default=>[['tanggal'=>'2026-10-01','jumlah_transaksi'=>1,'total_belanja'=>100]]}];};
        try{(new MemberSalesReport(new ConversationScope($clock)))->render($plan,[],$execute);$this->fail('Changed source accepted');}catch(AnalysisException $e){$this->assertSame('source_changed',$e->errorCode);}
    }
    public function testMonthYearAndExplicitDateBoundsKeepCorrectTotals():void
    {
        foreach([['month',['year'=>2026,'month'=>null,'date_start'=>null,'date_end'=>null],['2026-09','2026-10','2026-12'],360],
            ['year',['year'=>2026,'month'=>null,'date_start'=>null,'date_end'=>null],['2026'],360],
            ['day',['year'=>2026,'month'=>null,'date_start'=>'2026-10-01','date_end'=>'2026-10-02'],['2026-10-01'],300]] as [$grain,$period,$labels,$sum]){
            $r=$this->ask($this->plan(['dimensions'=>['date'],'time_grain'=>$grain,'period'=>$period,'output_preference'=>'chart']));
            $this->assertTrue($r['success'],$r['summary']);$this->assertSame($labels,$r['context']['last_chart']['labels']);$this->assertEquals($sum,array_sum(array_column($r['context']['last_rows'],'total_belanja')));
        }
    }
    public function testDynamicDailyRepairNeverSendsResolvedPosIdentityAndRunsOnlyCorrectedQuery():void
    {
        $first=$this->lookup();$plan=$this->daily($this->ref($first));$plan['intent']='dynamic_llm_or_clarify';
        $where=" FROM tbltransaksipenjualan p WHERE COALESCE(p.source_type,'NORMAL')='NORMAL' AND p.id_member='__selected_member__' AND p.tanggal>='2026-10-01' AND p.tanggal<'2026-11-01'";
        $this->provider->replies=[['parsed_json'=>['decision'=>'analysis','plan'=>$plan]],
            ['parsed_json'=>['sql'=>'SELECT SUM(p.grand_total) AS total_belanja'.$where,'chart_type'=>'none']],
            ['parsed_json'=>['sql'=>'SELECT DATE(p.tanggal) AS tanggal,SUM(p.grand_total) AS total_belanja'.$where.' GROUP BY DATE(p.tanggal) ORDER BY tanggal','chart_type'=>'line','label_column'=>'tanggal','value_column'=>'total_belanja']]];
        $r=$this->engine->handleMessage('Grafik pembelian harian member tersebut',1,'Administrator',$first['conversation_uid']);
        $this->assertTrue($r['success'],$r['summary']);$this->assertTrue($r['metadata']['sql_repair_used']);$this->assertSame(3,$r['metadata']['provider_metrics']['calls']);
        $this->assertSame(2,count($r['metadata']['queries']));$this->assertEquals(250,array_sum(array_column($r['context']['last_rows'],'total_belanja')));
        $this->assertStringNotContainsString('POS_PRIVATE_ID',json_encode($this->provider->calls));
    }
    public function testDynamicDocumentContractRejectsAggregateAndFailureRetainsExecutedIdentityLookup():void
    {
        $first=$this->lookup();$plan=$this->plan(['intent'=>'dynamic_llm_or_clarify','metric'=>null,'dimensions'=>['transaction'],'parameters'=>$this->ref($first)]);
        $sql="SELECT SUM(p.grand_total) AS total_belanja FROM tbltransaksipenjualan p WHERE COALESCE(p.source_type,'NORMAL')='NORMAL' AND p.id_member='__selected_member__' AND p.tanggal>='2026-10-01' AND p.tanggal<'2026-11-01'";
        $this->provider->replies=[['parsed_json'=>['decision'=>'analysis','plan'=>$plan]],['parsed_json'=>['sql'=>$sql,'chart_type'=>'none']]];
        $r=$this->engine->handleMessage('Daftar transaksi member itu',1,'Administrator',$first['conversation_uid']);
        $this->assertFalse($r['success']);$this->assertSame('business_requirement_mismatch',$r['error_code']);
        $this->assertCount(1,$r['metadata']['queries']);$this->assertTrue($r['metadata']['queries'][0]['success']);$this->assertEquals($first['context'],$r['context']);
    }
    public function testCrossYearLeapDayAndCountMetricHaveCorrectBuckets():void
    {
        $this->pos->query("INSERT INTO tbltransaksipenjualan VALUES ('d2','FD2','2026-12-31 23:59:59',60,60,'NORMAL','POS_PRIVATE_ID'),('j','FJ','2027-01-01 00:00:00',80,80,'NORMAL','POS_PRIVATE_ID'),('leap','FLEAP','2028-02-29',90,90,'NORMAL','POS_PRIVATE_ID')");
        $cross=$this->ask(array_replace($this->daily(['member_query'=>'Hendra Wijaya']),['period'=>['year'=>2026,'month'=>null,'date_start'=>'2026-12-31','date_end'=>'2027-01-02']]));
        $this->assertTrue($cross['success'],$cross['summary']);$this->assertSame(['2026-12-31','2027-01-01'],$cross['context']['last_chart']['labels']);$this->assertSame([100.0,80.0],$cross['context']['last_chart']['series'][0]['data']);
        $leap=$this->ask(array_replace($this->daily(['member_query'=>'Hendra Wijaya']),['period'=>['year'=>2028,'month'=>2,'date_start'=>null,'date_end'=>null]]));
        $this->assertTrue($leap['success'],$leap['summary']);$this->assertSame(['2028-02-29'],$leap['context']['last_chart']['labels']);
        $count=$this->ask(array_replace($this->daily(['member_query'=>'Hendra Wijaya']),['metric'=>'transaction_count']));
        $this->assertTrue($count['success'],$count['summary']);$this->assertSame([2.0,1.0,1.0],$count['context']['last_chart']['series'][0]['data']);
    }
}
