<?php
namespace App\Services\AI;

/** Provider-declared capabilities; model aliases are never guessed from their names. */
final class AiReasoningPolicy
{
    public const EFFORTS=['auto','none','low','medium','high'];
    public function validate(mixed $effort): string
    {
        if(!is_string($effort)||!in_array($effort,self::EFFORTS,true))throw new \InvalidArgumentException('Pilihan reasoning tidak valid.');
        return $effort;
    }
    public function capabilities(array $model): array
    {
        $caps=is_array($model['capabilities']??null)?$model['capabilities']:[];
        $parameters=is_array($model['supported_parameters']??null)?$model['supported_parameters']:[];
        $supported=($caps['reasoning']??null)===true;
        $effort=$supported&&(($caps['thinkingEffortSupported']??null)===true||in_array('reasoning_effort',$parameters,true)||
            (($caps['thinkingFormat']??null)==='openai'&&($caps['thinkingEffortSupported']??null)!==false));
        return ['reasoning'=>$supported,'effort_supported'=>$effort,'can_disable'=>$effort&&($caps['thinkingCanDisable']??null)===true,
            'source'=>'provider_models','status'=>isset($caps['reasoning'])?'declared':'unknown'];
    }
    public function supports(array $caps,string $effort): bool
    {
        return $effort==='auto'||(!empty($caps['effort_supported'])&&($effort!=='none'||!empty($caps['can_disable'])));
    }
}
