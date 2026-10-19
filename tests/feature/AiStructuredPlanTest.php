<?php
namespace Tests\Feature;

use App\Services\AI\{AiAnalysisPlan,AiClientDiagnostics,AiOrchestrator,AnalysisException,BusinessClock,ConversationService,PosQueryExecutor,ReportContext,SqlPlanContract};
use CodeIgniter\Test\{CIUnitTestCase,DatabaseTestTrait};
use Tests\Support\FakeOnlineProvider;

final class AiStructuredPlanTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $migrate=true; protected $refresh=true; protected $namespace='App';
    private FakeOnlineProvider $provider; private AiOrchestrator $engine;
    protected function setUp():void
    {
        parent::setUp();
        db_connect()->table('users')->insert(['id'=>1,'username'=>'ai-plan-test','display_name'=>'QA','password_hash'=>password_hash('Testing123!',PASSWORD_DEFAULT),'role'=>'Administrator','is_active'=>1]);
        $pos=\Config\Database::connect(config('Database')->tests,false);$pos->initialize();
        $pos->query('CREATE TABLE tbldata_member (id_member TEXT,nama TEXT,hapus INTEGER)');
        $pos->query('CREATE TABLE tbltransaksipenjualan (id_transaksi TEXT,id_member TEXT,tanggal TEXT,grand_total REAL,total_kotor REAL,source_type TEXT)');
        $pos->query("INSERT INTO tbldata_member VALUES ('A','Private Alpha',0),('B','Private Beta',0),('C','Private Gamma',0),('Z','Zero',0),('D','Deleted',1)");
        $pos->query("INSERT INTO tbltransaksipenjualan VALUES ('a1','A','2026-10-01',9000000,9000000,'NORMAL'),('a2','A','2026-10-19',9067500,9067500,'NORMAL'),('b1','B','2026-10-19',6000000,6000000,'NORMAL'),('c1','C','2026-10-19',5000000,5000000,'NORMAL'),('z','Z','2026-10-19',0,0,'NORMAL'),('d','D','2026-10-19',50000000,50000000,'NORMAL'),('anon','','2026-10-19',80000000,80000000,'NORMAL'),('legacy','B','2026-10-19',90000000,90000000,'LEGACY_HISTORY'),('sept','B','2026-09-01',90000000,90000000,'NORMAL')");
        $this->provider=new FakeOnlineProvider();$this->provider->baseUrlOverride='http://localhost:20128/v1';
        $this->engine=new AiOrchestrator(queryExecutor:new PosQueryExecutor(customPosConnection:$pos),providerClient:$this->provider,clock:new BusinessClock('Asia/Jakarta',new \DateTimeImmutable('2026-10-19')));
    }
    private function plan(array $replace=[]):array
    {
        return array_replace(['version'=>1,'intent'=>'member_activity','domain'=>'members','request'=>'Daftar member aktif dengan total belanja Oktober 2026 di atas Rp17 juta.','period'=>['year'=>2026,'month'=>10,'date_start'=>null,'date_end'=>null],
            'entity'=>'member','metric'=>'invoice_amount','dimensions'=>['member'],'time_grain'=>null,'filters'=>[['scope'=>'aggregate','metric'=>'invoice_amount','operator'=>'>','value'=>17000000]],'ranking'=>null,'output_preference'=>'table','parameters'=>['limit'=>100]],$replace);
    }
    private function ask(string $question,array $plan,?string $uid=null,string $role='Administrator'):array
    {
        $this->provider->replies=[['parsed_json'=>['decision'=>'analysis','plan'=>$plan]]];
        return $this->engine->handleMessage($question,1,$role,$uid);
    }
    public function testAiPlanOverridesWrongParserAndPersistsEveryFilterAcrossFollowupAndReload():void
    {
        $r=$this->ask('member yang bertransaksi bulan ini yang jumlah nominalnya melebihi 17 juta',$this->plan());
        $this->assertTrue($r['success'],$r['summary']);$this->assertEquals(['A'],array_column($r['context']['last_rows'],'id_member'));
        $this->assertStringContainsString('Private Alpha',$r['summary']);$this->assertStringContainsString('Rp17.000.000',$r['summary']);
        $this->assertStringContainsString('HAVING',$r['metadata']['queries'][0]['sql']);
        $this->assertSame(1,$r['metadata']['provider_metrics']['calls']);
        $updated=$this->plan(['request'=>'Daftar member dengan total belanja Oktober 2026 di atas Rp5 juta.','filters'=>[['scope'=>'aggregate','metric'=>'invoice_amount','operator'=>'>','value'=>5000000]]]);
        $next=$this->ask('kalau yang di atas 5 juta',$updated,$r['conversation_uid']);
        $this->assertTrue($next['success'],$next['summary']);$this->assertEquals(['A','B'],array_column($next['context']['last_rows'],'id_member'));
        $this->assertSame('invoice_amount',$next['context']['metric']);$this->assertSame(10,$next['context']['month']);
        $payload=json_decode(end($this->provider->calls)[1]['content'],true);
        $this->assertSame(17000000,$payload['period_context']['active_semantic_plan']['filters'][0]['value']);
        $this->assertStringNotContainsString('Private Alpha',json_encode($payload));
        $this->assertStringNotContainsString('SELECT ',json_encode($payload));
        $loaded=(new ConversationService())->getConversation($r['conversation_uid'],1);
        $reports=(new ReportContext())->snapshots($loaded,'Administrator');
        $this->assertSame(17000000,$reports[$r['metadata']['report_ref']]['context']['semantic_plan']['filters'][0]['value']);
        $this->assertSame(5000000,$loaded['context']['semantic_plan']['filters'][0]['value']);
        $this->assertSame(1,$next['metadata']['provider_metrics']['calls']);
    }
    public function testAiCanResolveWordsOutsideParserVocabularyAndSupersedeItsWrongClarification():void
    {
        foreach(['member transaksi dengan nominal terbanyak hari ini','siapa pelanggan dengan nilai belanja paling jumbo sepanjang Oktober?'] as $q){
            $p=$this->plan(['request'=>'Peringkat member nominal belanja Oktober 2026.','filters'=>[],'ranking'=>['metric'=>'invoice_amount','direction'=>'desc','limit'=>1]]);
            $r=$this->ask($q,$p);$this->assertTrue($r['success'],$r['summary']);$this->assertSame('member_activity',$r['metadata']['intent']);
            $this->assertSame('A',$r['context']['last_rows'][0]['id_member']);$this->assertSame(1,$r['metadata']['provider_metrics']['calls']);
        }
    }
    public function testNewOnlinePlanAfterChartUsesNewPeriodAndKeepsOldReportReference():void
    {
        $first=$this->ask('Member total belanja Oktober 2026.',$this->plan(['filters'=>[]]));
        $ref=$first['metadata']['report_ref'];
        $this->provider->replies=[['parsed_json'=>['decision'=>'reformat','report_ref'=>$ref,'format'=>'chart']]];
        $chart=$this->engine->handleMessage('Tampilkan grafiknya.',1,'Administrator',$first['conversation_uid']);
        $this->assertTrue($chart['success'],$chart['summary']);$this->assertSame([],$chart['metadata']['queries']);
        $next=$this->ask('Laporan member September 2026.',$this->plan(['request'=>'Laporan member September 2026.','period'=>['year'=>2026,'month'=>9,'date_start'=>null,'date_end'=>null],'filters'=>[]]),$first['conversation_uid']);
        $this->assertTrue($next['success'],$next['summary']);$this->assertSame(9,$next['context']['month']);
        $this->assertNotSame($ref,$next['metadata']['report_ref']);$this->assertNotEmpty($next['metadata']['queries']);
        $this->assertEquals(['B'],array_column($next['context']['last_rows'],'id_member'));
        $prompt=$this->provider->calls[array_key_last($this->provider->calls)][0]['content'];
        $this->assertStringContainsString('Intent kandidat reformat_previous tidak mewajibkan reformat',$prompt);
        $this->provider->replies=[['parsed_json'=>['decision'=>'reformat','report_ref'=>$ref,'format'=>'table']]];
        $old=$this->engine->handleMessage('Ubah menjadi tabel.',1,'Administrator',$first['conversation_uid'],'table',$ref);
        $this->assertTrue($old['success'],$old['summary']);$this->assertSame(10,$old['context']['month']);
        $this->assertSame($ref,$old['metadata']['report_ref']);$this->assertSame([],$old['metadata']['queries']);
    }
    public function testAggregateBoundaryOperatorsEmptyAndZeroAreAppliedBeforeLimit():void
    {
        foreach([['>',5000000,['A','B']],['>=',5000000,['A','B','C']],['=',0,['Z']],['<',5000000,['Z']],['>',50000000,[]]] as [$op,$value,$ids]){
            $r=$this->ask('Variasi batas nominal sesuai rencana AI.',$this->plan(['filters'=>[['scope'=>'aggregate','metric'=>'invoice_amount','operator'=>$op,'value'=>$value]]]));
            $this->assertTrue($r['success'],$r['summary']);$this->assertEquals($ids,array_column($r['context']['last_rows'],'id_member'));
            $this->assertSame($ids===[],$r['metadata']['empty_result']);
            if($ids===[]) {$this->assertSame(['text'],array_column($r['blocks'],'type'));$this->assertStringContainsString('memenuhi seluruh filter',$r['summary']);}
        }
    }
    public function testRowAndAggregateBindingsStayInCorrectOrderAndRankingMayAscend():void
    {
        $r=$this->ask('Cek member spesifik beserta nominalnya.',$this->plan(['filters'=>[
            ['scope'=>'aggregate','metric'=>'invoice_amount','operator'=>'>','value'=>5000000],
            ['scope'=>'row','field'=>'tbldata_member.id_member','operator'=>'=','value'=>'B']
        ]]));
        $this->assertTrue($r['success'],$r['summary']);$this->assertSame('B',$r['context']['last_rows'][0]['id_member']);
        $low=$this->ask('Urutkan belanja dari terkecil.',$this->plan(['filters'=>[],'ranking'=>['metric'=>'invoice_amount','direction'=>'asc','limit'=>1]]));
        $this->assertSame('Z',$low['context']['last_rows'][0]['id_member']);$this->assertStringContainsString('terendah',$low['summary']);
        $count=$this->ask('Frekuensi belanja lebih dari satu kali.',$this->plan(['metric'=>'transaction_count','filters'=>[['scope'=>'aggregate','metric'=>'transaction_count','operator'=>'>','value'=>1]]]));
        $this->assertTrue($count['success'],$count['summary']);$this->assertEquals(['A'],array_column($count['context']['last_rows'],'id_member'));
    }
    public function testInvalidOrIncompleteOnlinePlansNeverFallBackToTheParser():void
    {
        $bad=[];$p=$this->plan();unset($p['filters']);$bad[]=$p;
        $bad[]=$this->plan(['filters'=>[['scope'=>'aggregate','metric'=>'invoice_amount','operator'=>'>; DROP TABLE users','value'=>1]]]);
        $bad[]=$this->plan(['period'=>['year'=>2026,'month'=>null,'date_start'=>'2026-02-30','date_end'=>'2026-03-01']]);
        $bad[]=$this->plan(['domain'=>'cash_finance']);
        $bad[]=$this->plan(['filters'=>[['scope'=>'row','field'=>'tbluser.password','operator'=>'=','value'=>'secret']]]);
        $bad[]=$this->plan(['parameters'=>['sql'=>'SELECT 1']]);
        $bad[]=$this->plan(['parameters'=>['member_query'=>'Private Alpha']]);
        $bad[]=$this->plan(['filters'=>[['scope'=>'aggregate','metric'=>['invoice_amount'],'operator'=>'>','value'=>1]]]);
        foreach($bad as $plan){$r=$this->ask('Laporan penjualan bulan ini',$plan);$this->assertFalse($r['success']);$this->assertSame('provider_invalid_plan',$r['error_code']);$this->assertSame([],$r['metadata']['queries']);}
        $this->assertSame(0,db_connect()->table('ai_query_logs')->countAllResults());
    }
    public function testClarificationAndGeneralConversationPreserveLastSuccessfulSemanticPlan():void
    {
        $first=$this->ask('Member total belanja di atas Rp17 juta.',$this->plan());
        foreach([['decision'=>'general','reply'=>'Halo, ada yang bisa dibantu?'],['decision'=>'clarify','question'=>'Berdasarkan nominal atau jumlah transaksi?','slot'=>'metric']] as $reply){
            $this->provider->replies=[['parsed_json'=>$reply]];
            $r=$this->engine->handleMessage('Pertanyaan percakapan.',1,'Administrator',$first['conversation_uid']);
            $this->assertSame($first['context']['semantic_plan'],$r['context']['semantic_plan']);$this->assertSame([],$r['metadata']['queries']);
        }
        $this->provider->replies=[['parsed_json'=>['decision'=>'context','report_ref'=>$first['metadata']['report_ref'],'context_subject'=>'period']]];
        $period=$this->engine->handleMessage('itu tadi data kapan?',1,'Administrator',$first['conversation_uid']);
        $this->assertTrue($period['success'],$period['summary']);$this->assertStringContainsString('Oktober 2026',$period['summary']);$this->assertSame([],$period['metadata']['queries']);
    }
    public function testCustomSqlCannotOmitReverseOrApplyThresholdToIndividualTransactions():void
    {
        $first=$this->ask('Daftar member total belanja Oktober.',$this->plan());
        $base="SELECT m.id_member,m.nama,SUM(p.grand_total) AS total_belanja FROM tbltransaksipenjualan p JOIN tbldata_member m ON m.id_member=p.id_member WHERE COALESCE(p.source_type,'NORMAL')='NORMAL' AND p.tanggal >= '2026-10-01' AND p.tanggal < '2026-11-01' AND COALESCE(m.hapus,0)=0 AND m.id_member<>''";
        foreach([$base.' GROUP BY m.id_member,m.nama', $base.' AND p.grand_total>5000000 GROUP BY m.id_member,m.nama', $base.' GROUP BY m.id_member,m.nama HAVING SUM(p.grand_total)<5000000', $base.' GROUP BY m.id_member,m.nama HAVING SUM(p.grand_total)>5000000 OR 1=1'] as $sql){
            $plan=$this->plan(['intent'=>'dynamic_llm_or_clarify','filters'=>[['scope'=>'aggregate','metric'=>'invoice_amount','operator'=>'>','value'=>5000000]]]);
            $this->provider->replies=[['parsed_json'=>['decision'=>'analysis','plan'=>$plan]],['parsed_json'=>['sql'=>$sql]]];
            $r=$this->engine->handleMessage('Cek total nominal di atas lima juta.',1,'Administrator',$first['conversation_uid']);
            $this->assertFalse($r['success']);$this->assertSame('business_requirement_mismatch',$r['error_code']);$this->assertSame([],$r['metadata']['queries']);
            $this->assertCount(1,$r['metadata']['sql_diagnostics']);$this->assertEquals($first['context'],$r['context']);
            $this->assertStringNotContainsString($sql,json_encode((new AiClientDiagnostics())->present($r,true)));
        }
    }
    public function testCorrectCustomContractExecutesAndPromptContainsFullSemanticPlan():void
    {
        $plan=$this->plan(['intent'=>'dynamic_llm_or_clarify','filters'=>[['scope'=>'aggregate','metric'=>'invoice_amount','operator'=>'>','value'=>5000000]]]);
        $sql="SELECT m.id_member,m.nama,SUM(p.grand_total) AS total_belanja FROM tbltransaksipenjualan p JOIN tbldata_member m ON m.id_member=p.id_member WHERE COALESCE(p.source_type,'NORMAL')='NORMAL' AND p.tanggal >= '2026-10-01' AND p.tanggal < '2026-11-01' AND COALESCE(m.hapus,0)=0 AND m.id_member<>'' GROUP BY m.id_member,m.nama HAVING SUM(p.grand_total)>5000000 ORDER BY total_belanja DESC";
        $this->provider->replies=[['parsed_json'=>['decision'=>'analysis','plan'=>$plan]],['parsed_json'=>['sql'=>$sql]]];
        $r=$this->engine->handleMessage('Yang melebihi lima juta.',1,'Administrator');
        $this->assertTrue($r['success'],$r['summary']);$this->assertEquals(['A','B'],array_column($r['context']['last_rows'],'id_member'));
        $this->assertStringContainsString('5000000',$this->provider->calls[1][0]['content']);
        $this->assertSame(2,$r['metadata']['provider_metrics']['calls']);
    }
    public function testCrossOwnerAndChangedRoleCannotReadAStoredPlan():void
    {
        $r=$this->ask('Daftar member total belanja.',$this->plan());
        $this->assertNull((new ConversationService())->getConversation($r['conversation_uid'],2));
        $this->assertSame([],(new ReportContext())->snapshots((new ConversationService())->getConversation($r['conversation_uid'],1),'Gudang'));
        $before=count($this->provider->calls);
        try{$this->engine->handleMessage('kalau di atas 5 juta',2,'Administrator',$r['conversation_uid']);$this->fail('Cross-owner read');}
        catch(AnalysisException $e){$this->assertSame('conversation_not_found',$e->errorCode);}
        $this->assertCount($before,$this->provider->calls);
    }

    public function testDynamicRankingCannotChangeOrderOrHideTiesBehindLimitOne():void
    {
        $plan=$this->plan(['intent'=>'dynamic_llm_or_clarify','filters'=>[],'ranking'=>['metric'=>'invoice_amount','direction'=>'desc','limit'=>1]]);
        $sql="SELECT m.id_member,m.nama,SUM(p.grand_total) AS total_belanja FROM tbltransaksipenjualan p JOIN tbldata_member m ON m.id_member=p.id_member WHERE COALESCE(p.source_type,'NORMAL')='NORMAL' AND p.tanggal >= '2026-10-01' AND p.tanggal < '2026-11-01' AND COALESCE(m.hapus,0)=0 AND m.id_member<>'' GROUP BY m.id_member,m.nama ORDER BY total_belanja DESC LIMIT 500";
        foreach([str_replace('DESC','ASC',$sql)=>'business_requirement_mismatch',str_replace('LIMIT 500','LIMIT 1',$sql)=>'result_limit_exceeded'] as $bad=>$code){
            $this->provider->replies=[['parsed_json'=>['decision'=>'analysis','plan'=>$plan]],['parsed_json'=>['sql'=>$bad]]];
            $r=$this->engine->handleMessage('Tampilkan peringkat nominal tertinggi.',1,'Administrator');
            $this->assertFalse($r['success']);$this->assertSame($code,$r['error_code']);
        }
        $this->provider->replies=[['parsed_json'=>['decision'=>'analysis','plan'=>$plan]],['parsed_json'=>['sql'=>$sql]]];
        $r=$this->engine->handleMessage('Tampilkan peringkat nominal tertinggi.',1,'Administrator');
        $this->assertTrue($r['success'],$r['summary']);$this->assertCount(1,$r['context']['last_rows']);$this->assertSame('A',$r['context']['last_rows'][0]['id_member']);
    }

    public function testMetricArithmeticCannotMasqueradeAsTheApprovedMeasure():void
    {
        $p=(new AiAnalysisPlan())->validate($this->plan());
        $sql="SELECT m.id_member,SUM(p.grand_total)*2 AS total_belanja FROM tbltransaksipenjualan p JOIN tbldata_member m ON m.id_member=p.id_member WHERE COALESCE(m.hapus,0)=0 AND m.id_member<>'' GROUP BY m.id_member HAVING SUM(p.grand_total)>17000000";
        $this->expectException(AnalysisException::class);
        (new SqlPlanContract())->assertMatches($sql,$p);
    }

    public function testBareParserApprovalAndUnspecifiedContextSubjectNeverBecomeDataAnswers():void
    {
        $this->provider->adaptLegacyPlans=false;
        foreach([['decision'=>'use_plan'],['decision'=>'dynamic'],['decision'=>'context','reply'=>'Data kapan saja.']] as $parsed){
            $this->provider->replies=[['parsed_json'=>$parsed]];
            $r=$this->engine->handleMessage('member yang bertransaksi bulan ini yang nominalnya melebihi 17 juta',1,'Administrator');
            $this->assertFalse($r['success']);$this->assertSame([],$r['metadata']['queries']);
            $this->assertContains($r['error_code'],['provider_invalid_plan','provider_invalid_response']);
        }
        $this->assertSame(0,db_connect()->table('ai_query_logs')->countAllResults());
    }

    public function testConsistentMonthAndDateRepresentationsNormalizeWithoutDroppingThePeriod():void
    {
        $validator=new AiAnalysisPlan();
        $p=$validator->validate($this->plan(['period'=>['year'=>2026,'month'=>10,'date_start'=>'2026-10-01','date_end'=>'2026-11-01']]));
        $this->assertSame(10,$p['month']);$this->assertNull($p['date_start']);$this->assertNull($p['semantic_plan']['period']['date_start']);
        $day=$validator->validate($this->plan(['period'=>['year'=>2026,'month'=>10,'date_start'=>'2026-10-15','date_end'=>'2026-10-16']]));
        $this->assertNull($day['month']);$this->assertSame('2026-10-15',$day['date_start']);
        $this->expectException(AnalysisException::class);
        $validator->validate($this->plan(['period'=>['year'=>2026,'month'=>10,'date_start'=>'2026-09-01','date_end'=>'2026-11-01']]));
    }
}
