<?php
namespace App\Services\AI;

/** AI chooses the table, fields and conditions. This compiler never parses user wording. */
final class MasterDataLookup
{
    public function __construct(private int $maxRows=500) {}
    public function render(array $plan,array $context,callable $runSql): array
    {
        $parameters=$plan['semantic_plan']['parameters'];$table=$parameters['table'];
        $schema=new PosSchemaService();$meta=$schema->lookupCatalog()[$table];
        $conditions=[];$bindings=[];
        if($meta['status_filter']!=='')$conditions[]='('.$meta['status_filter'].')';
        foreach($plan['filters'] as $filter){
            $column=substr($filter['field'],strlen($table)+1);$op=$filter['operator'];$value=$filter['value'];
            if(in_array($op,['contains','starts_with','ends_with'],true)){
                $literal=str_replace(['!','%','_'],['!!','!%','!_'],$value);
                $bindings[]=($op==='ends_with'||$op==='contains'?'%':'').$literal.($op==='starts_with'||$op==='contains'?'%':'');
                $conditions[]="LOWER(`{$column}`) LIKE LOWER(?) ESCAPE '!'";
            }else{
                $conditions[]="`{$column}` {$op} ?";$bindings[]=$value;
            }
        }
        $where=$conditions===[]?'':' WHERE '.implode(' AND ',$conditions);
        $label=match($table){'tbldata_member'=>'member','tbldatabarang'=>'produk','tbldata_supiller'=>'supplier','tblkaryawan'=>'karyawan',default=>'master'};
        $title='Pencarian data '.$label;$rows=[];$columns=[];
        $presence=$parameters['lookup_mode']==='presence';$partial=false;
        if($presence){
            $result=$runSql("SELECT COUNT(*) AS jumlah_data FROM `{$table}`".$where,$bindings,'Periksa keberadaan data master');
            $count=(int)$result['rows'][0]['jumlah_data'];
            $summary=$count>0?'Ya, ditemukan **'.number_format($count,0,',','.').' data '.$label.'** yang sesuai pencarian.':'Tidak ditemukan data '.$label.' yang sesuai pencarian.';
            if($count>1)$summary.=' Ada beberapa kecocokan; gunakan nama lengkap atau ID jika ingin memilih satu data.';
        }else{
            $keys=array_values(array_unique($parameters['columns']));$select=implode(', ',array_map(static fn($c)=>'`'.$c.'`',$keys));
            $limit=min($parameters['limit']??10,100,max(1,$this->maxRows-1));$order=implode(', ',array_map(static fn($c)=>'`'.$c.'`',$meta['primary_key']));
            $fetchLimit=min($limit+1,max(1,$this->maxRows));
            $result=$runSql("SELECT {$select} FROM `{$table}`".$where.' ORDER BY '.$order.' LIMIT '.$fetchLimit,$bindings,'Cari data master');
            $partial=count($result['rows'])>$limit||($fetchLimit<=$limit&&count($result['rows'])===$fetchLimit);$rows=array_slice($result['rows'],0,$limit);$count=count($rows);
            foreach($keys as $key)$columns[]=['key'=>$key,'label'=>ucwords(str_replace('_',' ',$key)),'type'=>'text'];
            $summary=$count>0?'Ditemukan **'.$count.' data '.$label.'** yang sesuai pencarian.':'Tidak ditemukan data '.$label.' yang sesuai pencarian.';
            if($partial)$summary='Ditampilkan **'.$count.' data '.$label.' pertama**; masih ada hasil lain. Persempit pencarian untuk melihat data tertentu.';
        }
        $blocks=[['type'=>'text','content'=>$summary]];
        if(!$presence&&$rows!==[]){
            if($plan['output_preference']==='table')$blocks[]=['type'=>'table','title'=>$title,'columns'=>$columns,'rows'=>$rows];
            else{
                $lines=[];
                foreach(array_slice($rows,0,5) as $row){
                    $parts=[];foreach($columns as $column){$value=$row[$column['key']]??null;$parts[]=$column['label'].': '.($value===null?'—':$this->plain((string)$value));}
                    $lines[]=implode(' · ',$parts);
                }
                $summary.="\n\n".implode("\n\n",$lines);
                if(count($rows)>5)$summary.="\n\nRincian lainnya tersedia melalui tombol Tabel.";
                $blocks=[['type'=>'text','content'=>$summary]];
            }
        }
        if($partial)$blocks[]=['type'=>'warning','title'=>'Hasil dibatasi','content'=>'Hasil belum mencakup seluruh kecocokan. Persempit filter pencarian.'];
        $updated=array_replace($context,['domain'=>$plan['domain'],'intent'=>'data_lookup','year'=>null,'month'=>null,'date_start'=>null,'date_end'=>null,
            'last_report_period'=>[],'source_filter'=>'master_catalog_policy','lookup_count'=>$count,'lookup_partial'=>$partial,
            'last_title'=>$title,'last_rows'=>$rows,'last_columns'=>$columns,'last_chart'=>null,'last_kpis'=>[],'last_queries'=>[],
            'last_empty_result'=>$count===0,'last_empty_explanation'=>'Tidak ditemukan data '.$label.' yang sesuai pencarian.']);
        $identity=$table==='tbldata_member'&&$count===1&&!$partial?MemberIdentity::create($plan['filters']):null;
        // A new empty/ambiguous lookup must not retain an older selected member.
        $updated['member_identity']=$identity;$updated['member_ref']=$identity['ref']??null;unset($updated['focus_member']);
        return ['summary'=>$summary,'blocks'=>$blocks,'metadata'=>['empty_result'=>$count===0,'partial_result'=>$partial,'lookup_count'=>$count,'lookup_partial'=>$partial,
            'member_identity'=>$identity,'lookup_result'=>['columns'=>$columns,'rows'=>$rows]], 'updated_context'=>$updated];
    }

    private function plain(string $value): string
    {
        // Data is printed as escaped text inside Markdown, never as instructions or HTML.
        $value=mb_substr(str_replace(["\r","\n"],' ',$value),0,300);
        return str_replace(['\\','*','_','[',']','`','<','>'],['\\\\','\\*','\\_','\\[','\\]','\\`','&lt;','&gt;'],$value);
    }
}
