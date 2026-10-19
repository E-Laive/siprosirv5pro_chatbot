<?php
namespace Tests\Feature;

use App\Services\AI\{AiOrchestrator,AiProviderClient,AiReasoningPolicy,AtomicJsonStore,MeasuredAiProvider};
use CodeIgniter\Test\{CIUnitTestCase,DatabaseTestTrait,FeatureTestTrait};
use Config\AiAnalyst as AnalystConfig;

final class AiReasoningTest extends CIUnitTestCase
{
    use DatabaseTestTrait,FeatureTestTrait,\Tests\Support\AuthenticatedSession;
    protected $migrate=true;protected $refresh=true;protected $namespace='App';
    private array $requests=[];
    private array $modelBody=[];
    private array $completion=[];
    private int $modelStatus=200;
    private string $directory;
    protected function setUp():void
    {
        parent::setUp();
        foreach([1=>'Administrator',2=>'Kasir'] as $id=>$role)db_connect()->table('users')->insert(['id'=>$id,'username'=>'reason-'.$id,'display_name'=>$role,'password_hash'=>password_hash('Testing123!',PASSWORD_DEFAULT),'role'=>$role,'is_active'=>1]);
        $this->directory=WRITEPATH.'reasoning-'.bin2hex(random_bytes(4));
        $this->modelBody=['data'=>[['id'=>'business-combo','capabilities'=>['reasoning'=>true,'thinkingFormat'=>'openai','thinkingCanDisable'=>true]]]];
        $this->completion=['choices'=>[['message'=>['content'=>'{"decision":"general","reply":"Halo."}','reasoning_content'=>'PRIVATE_REASONING'],'finish_reason'=>'stop']],
            'usage'=>['prompt_tokens'=>30,'completion_tokens'=>20,'total_tokens'=>50,'completion_tokens_details'=>['reasoning_tokens'=>12]]];
    }
    private function client(string $effort='auto'):AiProviderClient
    {
        $config=new AnalystConfig();$config->baseUrl='http://localhost:20128/v1';$config->model='business-combo';$config->apiKey='PRIVATE_KEY';$config->reasoningEffort=$effort;
        return new AiProviderClient($config,$this->directory,function($method,$url,$headers,$payload,$timeout){
            $this->requests[]=['method'=>$method,'url'=>$url,'payload'=>$payload,'timeout'=>$timeout];
            return ['status'=>$method==='GET'?$this->modelStatus:200,'body'=>json_encode($method==='GET'?$this->modelBody:$this->completion),'error'=>''];
        });
    }
    public function testAutoPreservesProviderDefaultsWithoutExtraRequest():void
    {
        $client=$this->client();$r=$client->chatCompletion([['role'=>'user','content'=>'Halo']]);
        $this->assertTrue($r['used_remote']);$this->assertSame('general',$r['parsed_json']['decision']);$this->assertCount(1,$this->requests);
        $this->assertArrayNotHasKey('reasoning_effort',$this->requests[0]['payload']);$this->assertArrayHasKey('temperature',$this->requests[0]['payload']);
        $this->assertFalse($this->requests[0]['payload']['stream']);$this->assertSame(12,$r['usage']['reasoning_tokens']);
        $this->assertStringNotContainsString('PRIVATE_REASONING',json_encode($r));$this->assertStringNotContainsString('PRIVATE_KEY',json_encode($client->getActiveConfig()));
    }
    public function testExplicitEffortIsValidatedSavedAndSentWithCachedCapabilities():void
    {
        $client=$this->client();$saved=$client->updateConfig(['reasoning_effort'=>'medium'],1);
        $this->assertSame('medium',$saved['reasoning_effort']);$this->assertTrue($saved['reasoning_capabilities']['effort_supported']);
        $client->chatCompletion([]);$client->chatCompletion([]);
        $this->assertSame(['GET','POST','POST'],array_column($this->requests,'method'));
        $this->assertSame('medium',$this->requests[1]['payload']['reasoning_effort']);$this->assertArrayNotHasKey('temperature',$this->requests[1]['payload']);
        $this->assertSame('verified',$client->getActiveConfig()['connection_status']);
        $this->assertSame('medium',(new AiProviderClient(null,$this->directory))->getActiveConfig()['reasoning_effort']);
        $this->assertSame('business-combo',db_connect()->table('ai_provider_configs')->get()->getRowArray()['model_id']);
        $none=$client->updateConfig(['reasoning_effort'=>'none'],1);$this->assertSame('unchecked',$none['connection_status']);
    }
    public function testUnsupportedOrInvalidEffortNeverReplacesWorkingConfig():void
    {
        $client=$this->client();$client->updateConfig(['reasoning_effort'=>'auto'],1);
        foreach(['ultra',[],null] as $value){
            if($value===null)continue;
            try{$client->updateConfig(['reasoning_effort'=>$value],1);$this->fail('Invalid effort accepted');}catch(\InvalidArgumentException){}
        }
        $this->modelBody['data'][0]['capabilities']['reasoning']=false;
        try{$client->updateConfig(['reasoning_effort'=>'high'],1);$this->fail('Unsupported model accepted');}catch(\InvalidArgumentException $e){$this->assertStringContainsString('belum menyatakan dukungan',$e->getMessage());}
        $this->assertSame('auto',$client->getActiveConfig()['reasoning_effort']);$this->assertNotContains('POST',array_column($this->requests,'method'));
        $policy=new AiReasoningPolicy();
        foreach([
            ['reasoning'=>true,'thinkingFormat'=>'openai','thinkingEffortSupported'=>false],
            ['reasoning'=>true,'thinkingFormat'=>'claude'],[],['reasoning'=>false],
        ] as $caps)$this->assertFalse($policy->supports($policy->capabilities(['capabilities'=>$caps]),'medium'));
        $this->assertFalse($policy->supports($policy->capabilities(['capabilities'=>['reasoning'=>true,'thinkingFormat'=>'openai']]),'none'));
    }
    public function testCapabilityFailureStopsBeforeChatAndNeverFallsBack():void
    {
        $this->modelStatus=503;$client=$this->client('medium');
        $r=(new AiOrchestrator(providerClient:$client))->handleMessage('Halo',1,'Administrator');
        $this->assertFalse($r['success']);$this->assertSame('provider_capabilities_unavailable',$r['error_code']);$this->assertSame([],$r['metadata']['queries']);
        $this->assertSame(['GET'],array_column($this->requests,'method'));
    }
    public function testExpiredOrChangedModelCapabilitiesCannotSilentlyKeepReasoning():void
    {
        $client=$this->client();$client->updateConfig(['reasoning_effort'=>'low'],1);
        (new AtomicJsonStore($this->directory.'/provider_capabilities.json'))->mutate(static function(array &$data){$data['expires_at']=0;});
        $this->modelBody['data'][0]['capabilities']['reasoning']=false;
        $r=$client->chatCompletion([]);$this->assertSame('provider_reasoning_unsupported',$r['error']);$this->assertSame(['GET','GET'],array_column($this->requests,'method'));
        try{$client->updateConfig(['model_id'=>'different-model','reasoning_effort'=>'low'],1);$this->fail('Unknown model accepted');}catch(\InvalidArgumentException){}
        $this->assertSame('business-combo',$client->getActiveConfig()['model_id']);
    }
    public function testReasoningOnlyAndTruncatedResponsesCannotBecomePlannerJson():void
    {
        $client=$this->client();
        foreach([null,'', '<think>{"decision":"analysis"}'] as $content){
            $this->completion['choices'][0]['message']['content']=$content;
            $r=$client->chatCompletion([]);$this->assertSame('provider_output_incomplete',$r['error']);$this->assertNull($r['parsed_json']);$this->assertNull($r['content']);
            $this->assertSame(12,$r['usage']['reasoning_tokens']);$this->assertStringNotContainsString('PRIVATE_REASONING',json_encode($r));
        }
        $this->completion['choices'][0]['message']['content']='{"decision":"general","reply":"Halo"}';$this->completion['choices'][0]['finish_reason']='length';
        $this->assertSame('provider_output_incomplete',$client->chatCompletion([])['error']);
    }
    public function testOnlyFinalAnswerIsParsedAfterThinkingBlock():void
    {
        $this->completion['choices'][0]['message']['content']='<think>{"decision":"analysis","sql":"PRIVATE_THOUGHT"}</think>{"decision":"general","reply":"Halo"}';
        $r=$this->client()->chatCompletion([]);$this->assertSame('general',$r['parsed_json']['decision']);$this->assertStringNotContainsString('PRIVATE_THOUGHT',json_encode($r));
        $this->completion['choices'][0]['message']['content']='{"decision":"general","reply":"Tag <think> adalah teks contoh."}';
        $r=$this->client()->chatCompletion([]);$this->assertSame('Tag <think> adalah teks contoh.',$r['parsed_json']['reply']);
    }
    public function testMetricsDistinguishUnreportedReasoningFromZeroAndStoreNoThinking():void
    {
        $client=$this->client();$measured=new MeasuredAiProvider($client);$measured->chatCompletion([]);
        $this->assertSame(12,$measured->metrics()['reasoning_tokens']);$this->assertSame(1,$measured->metrics()['reasoning_token_reports']);
        unset($this->completion['usage']['completion_tokens_details']);$measured->chatCompletion([]);
        $this->assertNull($measured->metrics()['reasoning_tokens']);$this->assertSame(1,$measured->metrics()['reasoning_token_reports']);
        $this->assertStringNotContainsString('PRIVATE_REASONING',json_encode($measured->metrics()));
        $measured->reset();$this->completion['usage']['completion_tokens_details']['reasoning_tokens']=0;$measured->chatCompletion([]);$this->assertSame(0,$measured->metrics()['reasoning_tokens']);
    }
    public function testOnlyAdministratorCanSaveReasoningAndSecretStaysPrivate():void
    {
        $client=$this->client();\Config\Services::injectMock('aiProviderClient',$client);
        $this->asUser(['auth_user_id'=>2,'auth_display_name'=>'Kasir','auth_role'=>'Kasir'])->withHeaders(['X-Requested-With'=>'XMLHttpRequest'])->post('/asisten-ai/provider-config',['reasoning_effort'=>'medium',csrf_token()=>csrf_hash()])->assertStatus(403);
        $this->assertCount(0,$this->requests);
        $r=$this->asUser(['auth_user_id'=>1,'auth_display_name'=>'Administrator','auth_role'=>'Administrator'])->withHeaders(['X-Requested-With'=>'XMLHttpRequest'])->post('/asisten-ai/provider-config',['reasoning_effort'=>'medium',csrf_token()=>csrf_hash()]);$r->assertStatus(200);
        $body=$r->response()->getBody();$this->assertSame('medium',json_decode($body,true)['provider']['reasoning_effort']);$this->assertStringNotContainsString('PRIVATE_KEY',$body);
    }
    public function testReasoningErrorsHaveStableCodesWithoutPosExecution():void
    {
        foreach(['provider_reasoning_unsupported','provider_output_incomplete'] as $code){
            $p=new \Tests\Support\FakeOnlineProvider();$p->replies=[['used_remote'=>false,'error'=>$code]];
            $r=(new AiOrchestrator(providerClient:$p))->handleMessage('Berapa penjualan bulan ini?',1,'Administrator');
            $this->assertFalse($r['success']);$this->assertSame($code,$r['error_code']);$this->assertSame([],$r['metadata']['queries']);
        }
    }
    public function testReasoningControlIsRenderedOnlyForAdministrator():void
    {
        $provider=$this->client('medium')->getActiveConfig();
        $admin=view('pages/ai_chat',['user'=>['role'=>'Administrator'],'providerConfig'=>$provider]);
        $this->assertStringContainsString('name="reasoning_effort"',$admin);
        $this->assertStringContainsString('value="medium" selected',$admin);
        $this->assertStringContainsString('aria-describedby="aiReasoningHelp"',$admin);
        $this->assertStringContainsString('modal-dialog-scrollable',$admin);
        $cashier=view('pages/ai_chat',['user'=>['role'=>'Kasir'],'providerConfig'=>$provider]);
        $this->assertStringNotContainsString('name="reasoning_effort"',$cashier);
    }
}
