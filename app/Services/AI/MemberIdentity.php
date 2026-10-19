<?php
namespace App\Services\AI;

/** Owned conversation references resolve locally; POS identities never enter provider context. */
final class MemberIdentity
{
    public static function fromContext(array $context): ?array
    {
        if(($context['domain']??null)!=='members')return null;
        if(array_key_exists('member_identity',$context))return is_array($context['member_identity'])?$context['member_identity']:null;
        // Read compatibility for historical single-member reports, without rewriting their messages.
        if(!in_array($context['intent']??null,['member_spend','member_transactions'],true))return null;
        $member=$context['focus_member']??null;
        if(!$member&&count($context['last_rows']??[])===1)$member=$context['last_rows'][0];
        if(empty($member['id_member']))return null;
        $identity=self::create([['scope'=>'row','field'=>'tbldata_member.id_member','operator'=>'=','value'=>$member['id_member']]]);
        $identity['ref']='legacy-member-'.substr(hash('sha256',(string)$member['id_member']),0,16);
        return $identity;
    }
    public static function create(array $filters): array
    {
        return ['ref'=>'member-'.bin2hex(random_bytes(8)),'filters'=>$filters];
    }
    public static function find(array $context,string $ref):?array
    {
        $identity=self::fromContext($context);
        if(($identity['ref']??null)===$ref)return $identity;
        $identity=$context['_member_references'][$ref]??null;
        return is_array($identity)&&($identity['ref']??null)===$ref?$identity:null;
    }
    public function resolve(array $plan,array $context,callable $runSql): array
    {
        if(!empty($plan['member_ref'])){
            $identity=$plan['_member_identity']??self::find($context,$plan['member_ref']);
            if(!$identity||$identity['ref']!==$plan['member_ref'])throw new AnalysisException('member_reference_invalid','Referensi member tidak tersedia pada laporan ini. Sebutkan nama atau ID member yang dimaksud.');
            $where=[];$bindings=[];
            foreach($identity['filters'] as $filter){
                if(($filter['scope']??null)!=='row'||!str_starts_with($filter['field']??'','tbldata_member.'))throw new AnalysisException('member_reference_invalid','Referensi member tidak valid. Sebutkan nama atau ID member yang dimaksud.');
                $field=substr($filter['field'],strlen('tbldata_member.'));
                if(!isset((new PosSchemaService())->catalog()['tbldata_member']['columns'][$field])||(new PosSchemaService())->isColumnForbidden($field))throw new AnalysisException('member_reference_invalid','Referensi member tidak valid.');
                $op=$filter['operator'];$value=$filter['value'];
                if(in_array($op,['contains','starts_with','ends_with'],true)){
                    $value=str_replace(['!','%','_'],['!!','!%','!_'],$value);
                    $bindings[]=($op!=='starts_with'?'%':'').$value.($op!=='ends_with'?'%':'');
                    $where[]="LOWER(`{$field}`) LIKE LOWER(?) ESCAPE '!'";
                }else{
                    if(!in_array($op,AiAnalysisPlan::OPERATORS,true))throw new AnalysisException('member_reference_invalid','Referensi member tidak valid.');
                    $where[]="`{$field}` {$op} ?";$bindings[]=$value;
                }
            }
            $result=$runSql('SELECT id_member,nama FROM tbldata_member WHERE COALESCE(hapus,0)=0'.($where!==[]?' AND '.implode(' AND ',$where):'').' ORDER BY id_member LIMIT 6',$bindings,'Pulihkan referensi member');
        }else{
            $name=trim((string)($plan['member_query']??''));
            if($name==='')return ['member'=>null,'candidates'=>[]];
            $result=$runSql('SELECT id_member,nama FROM tbldata_member WHERE COALESCE(hapus,0)=0 AND (id_member=? OR LOWER(nama)=LOWER(?)) ORDER BY id_member LIMIT 2',[$name,$name],'Cocokkan identitas member');
            if($result['rows']===[]){
                $literal=str_replace(['!','%','_'],['!!','!%','!_'],$name);
                $result=$runSql("SELECT id_member,nama FROM tbldata_member WHERE COALESCE(hapus,0)=0 AND LOWER(nama) LIKE LOWER(?) ESCAPE '!' ORDER BY nama,id_member LIMIT 6",['%'.$literal.'%'],'Cari pilihan member');
            }
        }
        if(count($result['rows'])!==1)return ['member'=>null,'candidates'=>$result['rows']];
        $member=$result['rows'][0];
        if(trim((string)$member['id_member'])==='')return ['member'=>null,'candidates'=>[]];
        $filters=[['scope'=>'row','field'=>'tbldata_member.id_member','operator'=>'=','value'=>$member['id_member']]];
        $existing=$plan['_member_identity']??self::fromContext($context);
        $identity=is_array($existing)&&($existing['filters']??null)===$filters?$existing:self::create($filters);
        return ['member'=>$member,'identity'=>$identity,'candidates'=>[]];
    }
}
