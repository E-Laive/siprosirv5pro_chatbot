<?php
namespace Tests\Support;

use App\Services\AI\{AiProviderGateway, AiProviderClient};

/** Explicit test double. Production code contains no switch to bypass online AI. */
final class FakeOnlineProvider implements AiProviderGateway
{
    public array $calls = [];
    public bool $configured = true;
    public array $replies = [];
    public ?string $baseUrlOverride = null;
    public bool $adaptLegacyPlans = true;
    public function getActiveConfig(bool $includeSecretKey = false): array
    {
        return array_replace((new AiProviderClient())->getActiveConfig($includeSecretKey), ['configured' => $this->configured, 'model_id' => 'test-online-provider'], $this->baseUrlOverride !== null ? ['base_url' => $this->baseUrlOverride] : []);
    }
    public function isLoopback(string $url): bool { return false; }
    public function chatCompletion(array $messages): array
    {
        $this->calls[] = $messages;
        $reply=array_shift($this->replies)??[];
        $payload=json_decode($messages[1]['content']??'',true);
        // Legacy test fixtures explicitly approve parser candidates. Production has no such adapter.
        if ($this->adaptLegacyPlans && is_array($payload) && isset($payload['candidate_plan']) && (!array_key_exists('parsed_json',$reply) || in_array($reply['parsed_json']['decision']??'', ['use_plan','dynamic'],true))) {
            $candidate=$payload['candidate_plan']; $decision=$reply['parsed_json']['decision']??'use_plan';
            if (($candidate['intent']??'')==='reformat_previous') $reply['parsed_json']=['decision'=>'reformat','report_ref'=>$payload['period_context']['active_report_ref']??null,'format'=>$candidate['output_preference']];
            elseif (!empty($candidate['clarification_question']) && $decision==='use_plan') $reply['parsed_json']=['decision'=>'clarify','question'=>$candidate['clarification_question'],'slot'=>$candidate['clarification_slot']??'period'];
            else {
                $ranking=!empty($candidate['rank'])?['metric'=>$candidate['metric'],'direction'=>'desc','limit'=>$candidate['limit']??1]:null;
                $candidate['entity']??=null; $candidate['metric']??=null;
                $plan=['version'=>1,'intent'=>$candidate['intent'],'domain'=>$candidate['domain'],'request'=>$candidate['resolved_question']??$payload['question'],
                    'period'=>array_replace(['year'=>2026,'month'=>null,'date_start'=>null,'date_end'=>null],array_intersect_key($candidate,array_flip(['year','month','date_start','date_end']))),
                    'entity'=>$decision==='dynamic'?null:$candidate['entity'],'metric'=>$decision==='dynamic'?null:$candidate['metric'],'dimensions'=>$decision==='dynamic'?[]:($candidate['dimensions']??[]),
                    'time_grain'=>$candidate['time_grain']??$candidate['group_by']??null,'filters'=>[], 'ranking'=>$ranking,'output_preference'=>$candidate['output_preference']??'auto',
                    'parameters'=>array_intersect_key($candidate,array_flip(['comparison','comparison_year','comparison_month','category','member_query','member_ref','months_back','limit']))];
                if($candidate['intent']==='member_activity')$plan['parameters']=array_intersect_key($plan['parameters'],['limit'=>true]);
                if($candidate['intent']==='top_products'){$plan['entity']='product';$plan['metric']='product_amount';$plan['dimensions']=['product'];$plan['time_grain']=null;}
                if($candidate['intent']==='member_spend'&&$decision!=='dynamic'){
                    $plan['entity']='member';$plan['metric']='invoice_amount';$plan['dimensions']=$plan['time_grain']===null?['member']:['date'];
                    $plan['parameters']=array_intersect_key($plan['parameters'],array_flip(['member_query','member_ref','limit']));
                    if($plan['time_grain']===null&&in_array($plan['output_preference'],['chart','both'],true))$plan['output_preference']='auto';
                }
                $reply['parsed_json']=['decision'=>$decision==='dynamic'?'dynamic':'analysis','plan'=>$plan];
            }
        }
        return array_replace(['used_remote'=>true,'content'=>null,'parsed_json'=>['decision'=>'use_plan'],'model'=>'test-online-provider','error'=>null],$reply);
    }
}
