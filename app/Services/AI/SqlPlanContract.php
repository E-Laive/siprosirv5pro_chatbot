<?php
namespace App\Services\AI;

/** Proves the machine-readable business conditions; an executor success alone is insufficient. */
final class SqlPlanContract
{
    public function assertMatches(string $sql, array $plan, array $bindings = []): void
    {
        if (empty($plan['semantic_plan'])) return;
        $expanded=$this->expand($sql,$bindings);
        $this->assertScope($expanded,$plan);
        $lexer=new SqlStructure();
        $structure=$lexer->inspect($expanded); $scope=end($structure['scopes']);
        $p=new SqlPredicate(); $aliases=[];
        foreach($scope['sources'] as $source) $aliases[$source['table']][]=strtoupper($source['alias']);
        $named=[];
        $outputs=$this->outputs($this->projection($scope['tokens']),$named);
        if(($plan['entity']??null)==='member'&&in_array('transaction',$plan['dimensions']??[],true)){
            if(count($aliases['tbltransaksipenjualan']??[])!==1)$this->reject();
            $sale=$aliases['tbltransaksipenjualan'][0];
            if(!in_array($sale.'.ID_TRANSAKSI',$outputs,true)||!in_array($sale.'.TANGGAL',$outputs,true)||!in_array($sale.'.GRAND_TOTAL',$outputs,true))$this->reject();
            if($p->clause($scope['tokens'],'GROUP')!==[]||preg_match('/\b(SUM|COUNT|AVG|MIN|MAX)\(/',$p->text($this->projection($scope['tokens']))))$this->reject();
        }
        if(($plan['entity']??null)==='member'&&in_array($plan['time_grain']??null,['month','year'],true)){
            if(count($aliases['tbltransaksipenjualan']??[])!==1)$this->reject();$sale=$aliases['tbltransaksipenjualan'][0];
            $expected=$plan['time_grain']==='month'?"DATE_FORMAT({$sale}.TANGGAL,'%Y-%m')":"YEAR({$sale}.TANGGAL)";
            if(!in_array($expected,$outputs,true)||!str_contains($p->text($p->clause($scope['tokens'],'GROUP')),$expected))$this->reject();
        }
        if (($plan['entity']??null)==='member' && in_array('member',$plan['dimensions']??[],true) && empty($plan['_member_id'])) {
            if (count($aliases['tbldata_member']??[])!==1) $this->reject();
            $group=$p->text($p->clause($scope['tokens'],'GROUP'));
            $member=$aliases['tbldata_member'][0];
            if (!preg_match('/(?:^BY|,)'.preg_quote($member,'/').'\.ID_MEMBER(?:,|$)/D',$group)) $this->reject();
            foreach($p->branches($p->tree($p->clause($scope['tokens'],'WHERE'))) as $branch) {
                $atoms=array_map(fn($atom)=>$this->normalize($p->text($atom)),$branch);
                if (!in_array('COALESCE('.$member.'.HAPUS,0)=0',$atoms,true) && !in_array($member.'.HAPUS=0',$atoms,true)) $this->reject();
                $identified=false;
                foreach($atoms as $atom) if(in_array($atom,[$member.".ID_MEMBER<>''",$member.".ID_MEMBER!=''"],true) || preg_match('/^'.preg_quote($member,'/')."\.ID_MEMBER='[^']+'$/D",$atom)) $identified=true;
                if(!$identified)$this->reject();
            }
        }
        if(($plan['entity']??null)==='product'){
            if(count($aliases['tbltransaksipenjualan_detail']??[])!==1)$this->reject();
            $group=$p->text($p->clause($scope['tokens'],'GROUP'));
            if(!preg_match('/(?:^BY|,)'.preg_quote($aliases['tbltransaksipenjualan_detail'][0],'/').'\.ID_PRODUK(?:,|$)/D',$group))$this->reject();
        }
        if (isset(AiAnalysisPlan::METRICS[$plan['metric']??''])) {
            $measure=$this->measure($plan['metric'],$aliases);
            $canonical=$this->normalize($measure);
            if (!in_array($canonical,$outputs,true) && !in_array('COALESCE('.$canonical.',0)',$outputs,true)) $this->reject();
        }
        foreach ($plan['filters']??[] as $filter) {
            if ($filter['scope']==='aggregate') $expression=$this->measure($filter['metric'],$aliases);
            else {
                [$table,$column]=explode('.',$filter['field']);
                if (count($aliases[$table]??[])!==1) $this->reject();
                $expression=$aliases[$table][0].'.'.strtoupper($column);
            }
            $clause=$p->clause($scope['tokens'],$filter['scope']==='aggregate'?'HAVING':'WHERE');
            if ($clause===[]) $this->reject();
            $literal=is_string($filter['value']) ? "'".str_replace("'","''",$filter['value'])."'" : $this->number($filter['value']);
            $expected=$this->normalize($expression.$filter['operator'].$literal);
            foreach($p->branches($p->tree($clause)) as $branch) {
                $proven=false;
                foreach($branch as $atom) if ($this->normalize($p->text($atom))===$expected) $proven=true;
                if (!$proven) $this->reject();
            }
        }
        if (!empty($plan['ranking'])) {
            $column=$this->rankingColumn($expanded,$plan['ranking']['metric']);
            $order=$p->clause($scope['tokens'],'ORDER');
            if(($order[0]['value']??'')==='BY') array_shift($order);
            $first=[];$depth=0;
            foreach($order as $t){if($t['value']===','&&$depth===0)break;if($t['value']==='(')$depth++;if($t['value']===')')$depth--;$first[]=$t;}
            $direction=array_pop($first)['value']??null;
            if($direction!==strtoupper($plan['ranking']['direction']))$this->reject();
            $expression=$this->normalize($p->text($first));
            if($expression!==strtoupper($column) && $expression!==$this->normalize($this->measure($plan['ranking']['metric'],$aliases)))$this->reject();
        }
    }

    public function expand(string $sql,array $bindings=[]): string
    {
        $lexer=new SqlStructure(); $tokens=$lexer->tokens($sql); $position=0;
        foreach ($tokens as &$token) if ($token['value']==='?') {
            if (!array_key_exists($position,$bindings)) $this->reject();
            $v=$bindings[$position++];
            if (is_int($v)||is_float($v)) $token=['value'=>(string)$v,'raw'=>(string)$v,'type'=>'number'];
            elseif (is_string($v)) $token=['value'=>"''",'raw'=>"'".str_replace("'","''",$v)."'",'type'=>'string'];
            else $this->reject();
        }
        unset($token);
        if($position!==count($bindings))$this->reject();
        return implode(' ',array_column($tokens,'raw'));
    }

    public function assertScope(string $sql,array $plan): void
    {
        $structure=(new SqlStructure())->inspect($sql);$scope=end($structure['scopes']);$p=new SqlPredicate();
        $entity=$plan['entity']??null;
        $table=match($entity){'sale_return'=>'tblreturpenjualan','cash_transaction'=>'tbltransaksi_kas','cash_entry'=>'tblbank_ledger','product'=>'tbltransaksipenjualan_detail',default=>null};
        if($table===null)return;
        $sources=array_values(array_filter($scope['sources'],static fn($s)=>$s['table']===$table));
        if(count($sources)!==1)$this->reject();$a=strtoupper($sources[0]['alias']);
        foreach($p->branches($p->tree($p->clause($scope['tokens'],'WHERE'))) as $branch){
            $atoms=array_map(fn($atom)=>$this->normalize($p->text($atom)),$branch);
            $has=fn($choices)=>(bool)array_intersect($choices,$atoms);
            if($entity==='sale_return'){
                if(!$has(["COALESCE({$a}.HAPUS,0)=0","{$a}.HAPUS=0"])||!$has(["UPPER(COALESCE({$a}.STATUS,''))IN('FINAL','SELESAI','SUKSES')","{$a}.STATUS='FINAL'","UPPER({$a}.STATUS)='FINAL'"]))$this->reject();
                $sales=array_filter($scope['sources'],static fn($s)=>$s['table']==='tbltransaksipenjualan');
                if(count($sales)!==1)$this->reject();
                $sale=strtoupper(array_values($sales)[0]['alias']);
                if(count($scope['sources'])!==2)$this->reject();
                $on=$p->clause($scope['tokens'],'ON');$depth=0;
                foreach($on as $i=>$t){if($depth===0&&$t['value']==='WHERE'){$on=array_slice($on,0,$i);break;}if($t['value']==='(')$depth++;if($t['value']===')')$depth--;}
                // Every ON branch must tie the original document; no cartesian or broad join.
                foreach($p->branches($p->tree($on)) as $b){$joined=false;foreach($b as $atom)if(in_array($p->text($atom),["{$sale}.ID_TRANSAKSI={$a}.ID_TRANSAKSI","{$a}.ID_TRANSAKSI={$sale}.ID_TRANSAKSI"],true))$joined=true;if(!$joined)$this->reject();}
            }
            if($entity==='cash_transaction'){
                if(!$has(["COALESCE({$a}.HAPUS,0)=0","{$a}.HAPUS=0"])||!$has(["{$a}.STATUS='FINAL'"]))$this->reject();
                $direction=$plan['semantic_plan']['parameters']['cash_direction']??'BOTH';
                if(!$has($direction==='BOTH'?["{$a}.JENISIN('MASUK','KELUAR')"]:["{$a}.JENIS='{$direction}'"]))$this->reject();
            }
            if($entity==='cash_entry'&&!$has(["COALESCE({$a}.POSTING_ROLE,'MAIN')NOTIN('TRANSFER_IN','TRANSFER_OUT')","{$a}.POSTING_ROLENOTIN('TRANSFER_IN','TRANSFER_OUT')"]))$this->reject();
            if($entity==='cash_entry'&&($plan['semantic_plan']['parameters']['cash_direction']??'BOTH')!=='BOTH'){
                $field=$plan['semantic_plan']['parameters']['cash_direction']==='MASUK'?'DEBIT':'KREDIT';if(!$has(["{$a}.{$field}>0"]))$this->reject();
            }
            if($entity==='product'&&!$has(["{$a}.TERJUAL=1"]))$this->reject();
        }
        if(($plan['time_grain']??null)==='day'){
            $group=$p->text($p->clause($scope['tokens'],'GROUP'));
            $projection=$this->normalize($p->text($this->projection($scope['tokens'])));
            if(!str_contains($group,"DATE({$a}.TANGGAL)")||!str_contains($projection,"DATE({$a}.TANGGAL)"))$this->reject();
        }
        (new SqlBusinessPolicy())->assertMatches($sql,$plan);
    }

    private function measure(string $metric,array $aliases): string
    {
        if(!isset(AiAnalysisPlan::METRICS[$metric]))$this->reject();
        $tables=['p'=>'tbltransaksipenjualan','d'=>'tbltransaksipenjualan_detail','r'=>'tblreturpenjualan','c'=>'tbltransaksi_kas','l'=>'tblbank_ledger'];
        return strtoupper(preg_replace_callback('/\b([pdrcl])\./',function($m)use($aliases,$tables){$table=$tables[$m[1]];if(count($aliases[$table]??[])!==1)$this->reject();return $aliases[$table][0].'.';},AiAnalysisPlan::METRICS[$metric]));
    }

    private function projection(array $tokens): array
    {
        $out=[];$depth=0;
        foreach(array_slice($tokens,1) as $token) {
            if ($depth===0&&$token['value']==='FROM') break;
            if ($token['value']==='(') $depth++;
            if ($token['value']===')') $depth--;
            $out[]=$token;
        }
        return $out;
    }

    private function normalize(string $text): string
    {
        // Preserve string case and spacing; only structural tokens and numeric forms are canonicalized.
        $tokens=(new SqlStructure())->tokens($text); $parts=[];
        foreach($tokens as $token) $parts[]=$token['type']==='number'?$this->number((float)$token['raw']):($token['type']==='string'?$token['raw']:$token['value']);
        return implode('',$parts);
    }

    public function rankingColumn(string $sql,string $metric): string
    {
        $s=(new SqlStructure())->inspect($sql);$scope=end($s['scopes']);$aliases=[];
        foreach($scope['sources'] as $source)$aliases[$source['table']][]=strtoupper($source['alias']);
        $named=[];$this->outputs($this->projection($scope['tokens']),$named);
        $measure=$this->normalize($this->measure($metric,$aliases));
        foreach($named as $name=>$expression)if($expression===$measure||$expression==='COALESCE('.$measure.',0)')return $name;
        $this->reject();
    }

    private function outputs(array $tokens,?array &$named=null): array
    {
        $parts=[];$part=[];$depth=0;
        foreach($tokens as $token) {
            if($token['value']===','&&$depth===0){$parts[]=$part;$part=[];continue;}
            if($token['value']==='(')$depth++;
            if($token['value']===')')$depth--;
            $part[]=$token;
        }
        $parts[]=$part;$out=[];$p=new SqlPredicate();
        foreach($parts as $part){
            $depth=0;$name=null;
            foreach($part as $i=>$token){
                if($depth===0&&$token['value']==='AS'){$name=$part[$i+1]['raw']??null;$part=array_slice($part,0,$i);break;}
                if($token['value']==='(')$depth++;
                if($token['value']===')')$depth--;
            }
            if(count($part)>1 && in_array(end($part)['type'],['word','identifier'],true) && ($part[count($part)-2]['value']??'')!=='.') $name=array_pop($part)['raw'];
            $expression=$this->normalize($p->text($part));$out[]=$expression;
            if($name!==null)$named[trim($name,'`')]=$expression;
        }
        return $out;
    }

    private function number(float|int $value): string { return rtrim(rtrim(sprintf('%.8F',$value),'0'),'.'); }
    private function reject(): never { throw new AnalysisException('business_requirement_mismatch','Query belum memenuhi seluruh metrik, pengelompokan, atau filter rencana bisnis. Tidak ada angka baru yang dihitung; laporan sebelumnya tetap tersimpan.'); }
}
