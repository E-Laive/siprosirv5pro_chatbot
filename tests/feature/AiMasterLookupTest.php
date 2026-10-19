<?php
namespace Tests\Feature;

use App\Services\AI\{AiAnalysisPlan,AiOrchestrator,AnalysisException,BusinessClock,ConversationService,PosQueryExecutor,ReportContext};
use CodeIgniter\Test\{CIUnitTestCase,DatabaseTestTrait};
use Tests\Support\FakeOnlineProvider;

final class AiMasterLookupTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    protected $migrate=true;protected $refresh=true;protected $namespace='App';
    private FakeOnlineProvider $provider;private AiOrchestrator $engine;private $pos;
    protected function setUp():void
    {
        parent::setUp();
        db_connect()->table('users')->insert(['id'=>1,'username'=>'lookup-qa','display_name'=>'QA','password_hash'=>password_hash('Testing123!',PASSWORD_DEFAULT),'role'=>'Administrator','is_active'=>1]);
        $this->pos=\Config\Database::connect(config('Database')->tests,false);$this->pos->initialize();
        foreach([
            'CREATE TABLE tbldata_member (id_member TEXT PRIMARY KEY,nama TEXT,no_hp TEXT,poin INTEGER,hapus INTEGER)',
            'CREATE TABLE tbldatabarang (id_produk TEXT PRIMARY KEY,nama TEXT,hapus INTEGER)',
            'CREATE TABLE tbldata_supiller (id_supiller TEXT PRIMARY KEY,nama TEXT,hapus INTEGER)',
            "INSERT INTO tbldata_member VALUES ('M1','Hendra Wijaya','08123456789',20,0),('M2','Hendra Saputra','private-contact',40,0),('M3','Deleted Member','secret',0,1),('M4','Literal 100%_!','x',0,0),('M5','POS_ONLY_PERSON','POS_ONLY_CONTACT',0,0)",
            "INSERT INTO tbldatabarang VALUES ('P1','Kopi Bubuk',0),('P2','Kopi Dihapus',1)",
            "INSERT INTO tbldata_supiller VALUES ('S1','Sumber Makmur',0)",
        ] as $sql)$this->pos->query($sql);
        $this->provider=new FakeOnlineProvider();$this->provider->adaptLegacyPlans=false;$this->provider->baseUrlOverride='http://localhost:20128/v1';
        $this->engine=new AiOrchestrator(queryExecutor:new PosQueryExecutor(customPosConnection:$this->pos),providerClient:$this->provider,clock:new BusinessClock('Asia/Jakarta',new \DateTimeImmutable('2026-10-19')));
    }
    private function plan(string $name='Hendra Wijaya',array $replace=[]):array
    {
        return array_replace(['version'=>1,'intent'=>'data_lookup','domain'=>'members','request'=>'Cari member yang namanya mengandung '.$name,
            'period'=>null,'entity'=>'member','metric'=>null,'dimensions'=>[],'time_grain'=>null,'filters'=>[['scope'=>'row','field'=>'tbldata_member.nama','operator'=>'contains','value'=>$name]],
            'ranking'=>null,'output_preference'=>'text','parameters'=>['table'=>'tbldata_member','lookup_mode'=>'presence','columns'=>['id_member','nama']]],$replace);
    }
    private function ask(array $plan,?string $uid=null,string $role='Administrator',string $question='member atas nama hendra wijaya ada?'):array
    {
        $this->provider->replies=[['parsed_json'=>['decision'=>'analysis','plan'=>$plan]]];
        return $this->engine->handleMessage($question,1,$role,$uid);
    }
    public function testExistenceDoesNotRequireTransactionsPeriodOrSecondAiCall():void
    {
        $r=$this->ask($this->plan());$this->assertTrue($r['success'],$r['summary']);
        $this->assertStringContainsString('1 data member',$r['summary']);$this->assertSame(['text'],array_column($r['blocks'],'type'));
        $this->assertCount(1,$r['metadata']['queries']);$this->assertSame(1,$r['metadata']['provider_metrics']['calls']);
        $this->assertSame(['table'=>false,'chart'=>false,'export'=>false],$r['metadata']['actions']);
        $this->assertNull($r['context']['year']);$this->assertSame(1,$r['metadata']['report_facts']['metrics'][0]['value']);
        $this->assertStringNotContainsString('tbltransaksipenjualan',$r['metadata']['queries'][0]['sql']);
    }
    public function testAbsenceDeletedAndDuplicateNamesProduceAppropriateTextAndReload():void
    {
        foreach(['Tidak Ada','Deleted Member'] as $name){
            $r=$this->ask($this->plan($name));$this->assertTrue($r['success'],$r['summary']);$this->assertTrue($r['metadata']['empty_result']);
            $this->assertStringContainsString('Tidak ditemukan data member',$r['summary']);$this->assertStringNotContainsString('penjualan',$r['summary']);
            $loaded=(new ReportContext())->forClient((new ConversationService())->getConversation($r['conversation_uid'],1),'Administrator');
            $last=end($loaded['messages']);$this->assertStringContainsString('Tidak ditemukan data master',$last['content']);$this->assertFalse($last['metadata']['actions']['export']);
        }
        $r=$this->ask($this->plan('Hendra'));$this->assertStringContainsString('2 data member',$r['summary']);$this->assertStringContainsString('beberapa kecocokan',$r['summary']);
    }
    public function testCaseInsensitiveAndWildcardCharactersAreLiteral():void
    {
        foreach(['hEnDrA wIjAyA','100%_!'] as $name){$r=$this->ask($this->plan($name));$this->assertTrue($r['success'],$r['summary']);$this->assertStringContainsString('1 data member',$r['summary']);}
        $r=$this->ask($this->plan("' OR 1=1 --"));$this->assertTrue($r['success'],$r['summary']);$this->assertTrue($r['metadata']['empty_result']);
    }
    public function testSameCompilerSupportsProductsAndSuppliers():void
    {
        foreach([['tbldatabarang','products_inventory','product','nama','Kopi','id_produk'],['tbldata_supiller','purchasing_payables','supplier','nama','Makmur','id_supiller']] as [$table,$domain,$entity,$field,$value,$id]){
            $p=$this->plan($value,['domain'=>$domain,'entity'=>$entity,'filters'=>[['scope'=>'row','field'=>$table.'.'.$field,'operator'=>'contains','value'=>$value]],'parameters'=>['table'=>$table,'lookup_mode'=>'records','columns'=>[$id,$field]]]);
            $r=$this->ask($p,null,'Administrator','Cari data '.$entity.' '.$value);$this->assertTrue($r['success'],$r['summary']);$this->assertCount(1,$r['context']['last_rows']);
            $this->assertSame(['text'],array_column($r['blocks'],'type'));$this->assertTrue($r['metadata']['actions']['table']);$this->assertFalse($r['metadata']['actions']['chart']);
        }
    }
    public function testRecordsRemainAvailableForOldReportReformatWithoutPosQuery():void
    {
        $p=$this->plan('Hendra Wijaya',['parameters'=>['table'=>'tbldata_member','lookup_mode'=>'records','columns'=>['id_member','nama','poin']]]);
        $first=$this->ask($p);$this->assertTrue($first['success'],$first['summary']);
        $next=$this->ask($this->plan('Hendra Saputra'),$first['conversation_uid']);
        $this->provider->replies=[['parsed_json'=>['decision'=>'reformat','format'=>'table','report_ref'=>$first['metadata']['report_ref']]]];
        $r=$this->engine->handleMessage('Tampilkan tabel laporan pertama',1,'Administrator',$next['conversation_uid'],'table',$first['metadata']['report_ref']);
        $this->assertTrue($r['success'],$r['summary']);$this->assertSame([],$r['metadata']['queries']);$this->assertSame('Hendra Wijaya',$r['context']['last_rows'][0]['nama']);
    }
    public function testPosRowsAndContactsNeverEnterNextProviderPrompt():void
    {
        $r=$this->ask($this->plan('semua',['request'=>'Tampilkan daftar member beserta kontak','filters'=>[],'parameters'=>['table'=>'tbldata_member','lookup_mode'=>'records','columns'=>['id_member','nama','no_hp']]]),null,'Administrator','Tampilkan daftar member beserta kontak');
        $this->assertTrue($r['success'],$r['summary']);
        $this->provider->replies=[['parsed_json'=>['decision'=>'general','reply'=>'Sama-sama.']]];
        $this->engine->handleMessage('Terima kasih',1,'Administrator',$r['conversation_uid']);
        $prompt=json_encode(end($this->provider->calls));$this->assertStringNotContainsString('POS_ONLY_PERSON',$prompt);$this->assertStringNotContainsString('POS_ONLY_CONTACT',$prompt);
    }
    public function testRoleAndForbiddenTableAndColumnCannotBeBypassed():void
    {
        $denied=$this->ask($this->plan(),null,'Gudang');$this->assertSame('refused',$denied['analysis_status']);$this->assertSame('rbac_denied',$denied['error_code']);$this->assertSame([],$denied['metadata']['queries']);
        $payload=json_decode($this->provider->calls[0][1]['content'],true);$this->assertArrayNotHasKey('tbldata_member',$payload['lookup_catalog']);
        foreach([
            ['parameters'=>['table'=>'tbluser','lookup_mode'=>'records','columns'=>['password']]],
            ['parameters'=>['table'=>'tbldata_member','lookup_mode'=>'records','columns'=>['password']]],
            ['domain'=>'sales'],['metric'=>'invoice_amount'],['period'=>['year'=>2026,'month'=>10]],
            ['filters'=>[['scope'=>'row','field'=>'tbldatabarang.nama_produk','operator'=>'contains','value'=>'Kopi']]],
        ] as $change){$r=$this->ask($this->plan('Hendra',$change));$this->assertFalse($r['success']);$this->assertSame([],$r['metadata']['queries']);}
    }
    public function testFailuresPreservePreviousSuccessfulContextAndNeverClaimAbsence():void
    {
        $first=$this->ask($this->plan());$this->pos->query('DROP TABLE tbldata_member');
        $bad=$this->ask($this->plan(),$first['conversation_uid']);$this->assertFalse($bad['success']);$this->assertSame('query_failed',$bad['error_code']);
        $this->assertEquals($first['context'],$bad['context']);$this->assertStringNotContainsString('Tidak ditemukan',$bad['summary']);
        $this->provider->replies=[['used_remote'=>false,'error'=>'offline']];
        $bad=$this->engine->handleMessage('Ada member Hendra?',1,'Administrator',$first['conversation_uid']);$this->assertSame('provider_unavailable',$bad['error_code']);$this->assertSame([],$bad['metadata']['queries']);
    }
    public function testBoundedRecordsHaveVisiblePartialWarningAndNoFalseTotal():void
    {
        $r=$this->ask($this->plan('Hendra',['filters'=>[],'parameters'=>['table'=>'tbldata_member','lookup_mode'=>'records','columns'=>['id_member','nama'],'limit'=>1]]));
        $this->assertTrue($r['success'],$r['summary']);$this->assertTrue($r['metadata']['partial_result']);$this->assertCount(1,$r['context']['last_rows']);
        $this->assertContains('warning',array_column($r['blocks'],'type'));$this->assertStringContainsString('masih ada hasil lain',$r['summary']);
        $this->assertSame('displayed_records',$r['metadata']['report_facts']['metrics'][0]['key']);
    }
    public function testDynamicAnalysisOutsideTemplatesCanReturnVerifiedScalarAsText():void
    {
        $plan=$this->plan('semua',['intent'=>'dynamic_llm_or_clarify','request'=>'Hitung total poin seluruh member yang tidak dihapus','entity'=>null,'filters'=>[],'parameters'=>[]]);
        $this->provider->replies=[['parsed_json'=>['decision'=>'analysis','plan'=>$plan]],['parsed_json'=>['sql'=>'SELECT SUM(m.poin) AS total_poin FROM tbldata_member m WHERE COALESCE(m.hapus,0)=0','chart_type'=>'none']]];
        $r=$this->engine->handleMessage('Hitung total poin seluruh member yang tidak dihapus',1,'Administrator');
        $this->assertTrue($r['success'],$r['summary']);$this->assertSame(['text'],array_column($r['blocks'],'type'));
        $this->assertStringContainsString('60',$r['summary']);$this->assertSame(2,$r['metadata']['provider_metrics']['calls']);
        $this->assertCount(1,$r['metadata']['queries']);$this->assertSame(60.0,(float)$r['context']['last_rows'][0]['total_poin']);
    }
    public function testDynamicTextRecordsAreEscapedAndCanReformatAfterReload():void
    {
        $this->pos->query("INSERT INTO tbldata_member VALUES ('M6','<script>alert(1)</script>','private',1,0)");
        $plan=$this->plan('semua',['intent'=>'dynamic_llm_or_clarify','request'=>'Tampilkan daftar nama dan poin member','entity'=>null,'filters'=>[],'parameters'=>[]]);
        $this->provider->replies=[['parsed_json'=>['decision'=>'analysis','plan'=>$plan]],['parsed_json'=>['sql'=>'SELECT m.id_member,m.nama,m.poin FROM tbldata_member m WHERE COALESCE(m.hapus,0)=0 ORDER BY m.id_member','chart_type'=>'none']]];
        $first=$this->engine->handleMessage('Tampilkan daftar nama dan poin member sebagai teks',1,'Administrator');
        $this->assertTrue($first['success'],$first['summary']);$this->assertSame(['text'],array_column($first['blocks'],'type'));
        $this->assertStringNotContainsString('<script>',$first['summary']);$this->assertStringContainsString('&lt;script&gt;',$first['summary']);
        $this->ask($this->plan(),$first['conversation_uid']);
        $this->provider->replies=[['parsed_json'=>['decision'=>'reformat','format'=>'table','report_ref'=>$first['metadata']['report_ref']]]];
        $r=$this->engine->handleMessage('Tabel laporan pertama',1,'Administrator',$first['conversation_uid'],'table',$first['metadata']['report_ref']);
        $this->assertTrue($r['success'],$r['summary']);$this->assertSame([],$r['metadata']['queries']);$this->assertCount(5,$r['context']['last_rows']);
    }
}
