<?php
namespace App\Services\AI;

/** Bounded developer diagnostics; deliberately excludes free text, filter values and secrets. */
final class AiPlanDiagnostics
{
    public static function failure(mixed $input, string $path, string $rule): array
    {
        $shape=[];
        if (is_array($input)) foreach (['version','intent','domain','entity','metric','time_grain','output_preference','period','ranking','dimensions'] as $key) {
            if (array_key_exists($key,$input)) $shape[$key]=self::bounded($input[$key]);
        }
        if (is_array($input)) {
            $shape['provided_fields']=array_slice(array_keys($input),0,24);
            $shape['parameter_fields']=is_array($input['parameters']??null)?array_slice(array_keys($input['parameters']),0,16):get_debug_type($input['parameters']??null);
            $shape['filters']=array_map(static fn($f)=>is_array($f)?array_intersect_key($f,array_flip(['scope','metric','field','operator'])):get_debug_type($f),array_slice(is_array($input['filters']??null)?$input['filters']:[],0,12));
        }
        return ['stage'=>'business_plan','issues'=>[['path'=>mb_substr($path,0,160),'rule'=>$rule]],'plan_shape'=>self::bounded($shape)];
    }
    private static function bounded(mixed $value,int $depth=0): mixed
    {
        if($depth>4)return '[depth limit]';
        if(is_array($value)){ $out=[];foreach(array_slice($value,0,24,true) as $k=>$v)$out[is_string($k)?mb_substr($k,0,80):$k]=self::bounded($v,$depth+1);return $out; }
        return is_string($value)?mb_substr($value,0,100):(is_scalar($value)||$value===null?$value:get_debug_type($value));
    }
}
