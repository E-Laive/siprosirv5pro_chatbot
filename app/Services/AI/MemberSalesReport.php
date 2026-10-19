<?php
namespace App\Services\AI;

/** Explicit document/grain compilers; a successful monthly aggregate cannot satisfy a daily plan. */
final class MemberSalesReport
{
    public function __construct(private ConversationScope $dialogue,private int $maxRows=500) {}
    public function render(array $plan,array $context,callable $runSql): array
    {
        $resolved=(new MemberIdentity())->resolve($plan,$context,$runSql);
        if(!$resolved['member']){
            $candidates=$resolved['candidates'];
            $summary=$candidates===[]?'Saya belum menemukan member yang cocok. Sebutkan nama lengkap atau ID member yang dimaksud.'
                :'Ada beberapa member yang cocok. Sebutkan ID member: '.implode('; ',array_map(static fn($row)=>$row['nama'].' ('.$row['id_member'].')',$candidates)).'.';
            return ['summary'=>$summary,'blocks'=>[['type'=>'text','content'=>$summary]],'metadata'=>['intent'=>'clarification_needed','analysis_status'=>'clarification','clarification_slot'=>'member'],'updated_context'=>$context];
        }
        return $this->renderResolved($plan,$context,$resolved,$runSql);
    }
    public function renderResolved(array $plan,array $context,array $resolved,callable $runSql): array
    {
        $member=$resolved['member'];$grain=$plan['time_grain']??null;$details=$plan['intent']==='member_transactions';
        $executor=$runSql;
        $runSql=static function(string $sql,array $bindings,string $label)use($executor,$member,$plan):array{
            $expanded=(new SqlPlanContract())->expand($sql,$bindings);
            (new MemberSqlScope())->assertMatches($expanded,(string)$member['id_member']);
            // The control aggregate intentionally has no output grain; the detail query must preserve it.
            $scope=$label==='Kontrol total transaksi member'?array_replace($plan,['group_by'=>null]):$plan;
            (new SqlBusinessPolicy())->assertMatches($expanded,$scope);
            return $executor($sql,$bindings,$label);
        };
        if(!in_array($grain,[null,'day','month','year'],true)||($details&&$grain!==null))throw new AnalysisException('business_requirement_mismatch','Penyajian transaksi member belum sesuai rincian rencana. Tidak ada angka baru yang dihitung.');
        [$start,$end]=$this->bounds($plan);$bindings=[$member['id_member'],$start,$end];
        $where=" FROM tbltransaksipenjualan p WHERE COALESCE(p.source_type,'NORMAL')='NORMAL' AND p.id_member=? AND p.tanggal>=? AND p.tanggal<?";
        $control=$runSql('SELECT COUNT(p.id_transaksi) AS jumlah_transaksi,COALESCE(SUM(p.grand_total),0) AS total_belanja'.$where,$bindings,'Kontrol total transaksi member');
        $count=(int)$control['rows'][0]['jumlah_transaksi'];$total=(float)$control['rows'][0]['total_belanja'];
        $period=$this->dialogue->periodLabel($plan);$name=$this->plain((string)$member['nama']);$title='Transaksi '.$member['nama'].' — '.$period;
        $summary=$count===0?"Untuk {$period}, member **{$name}** tidak memiliki transaksi penjualan operasional."
            :"Untuk {$period}, total belanja **{$name}** sebesar **{$this->rp($total)}** dari **{$count} transaksi**. Nilai ini memakai omzet faktur, sebelum pengurangan retur.";
        $rows=[];$columns=[];$chart=null;
        if($count>0&&$details){
            if($count>$this->maxRows)throw new AnalysisException('result_limit_exceeded','Jumlah transaksi member melebihi batas rincian. Persempit periode; daftar terpotong tidak disimpan sebagai hasil lengkap.');
            $result=$runSql('SELECT p.id_transaksi,p.no_faktur,p.tanggal,p.grand_total AS total_belanja'.$where.' ORDER BY p.tanggal,p.id_transaksi LIMIT '.max(1,$this->maxRows),$bindings,'Daftar nota penjualan member');
            $rows=$result['rows'];
            if(count($rows)!==$count||abs(array_sum(array_column($rows,'total_belanja'))-$total)>0.005)$this->sourceChanged();
            if(count(array_unique(array_column($rows,'id_transaksi')))!==$count)$this->sourceChanged();
            foreach($rows as $row)if(($row['tanggal']??'')<$start||($row['tanggal']??'')>=$end)$this->sourceChanged();
            $columns=[['key'=>'id_transaksi','label'=>'ID Transaksi','type'=>'text'],['key'=>'no_faktur','label'=>'No. Faktur','type'=>'text'],['key'=>'tanggal','label'=>'Waktu Transaksi','type'=>'text'],['key'=>'total_belanja','label'=>'Nominal Faktur','type'=>'currency']];
            $summary.=' Daftar **'.$count.' nota** ditampilkan pada tabel.';
        }elseif($count>0&&$grain!==null){
            $expression=match($grain){'day'=>'DATE(p.tanggal)','month'=>"DATE_FORMAT(p.tanggal,'%Y-%m')",'year'=>'YEAR(p.tanggal)'};
            $result=$runSql('SELECT '.$expression.' AS tanggal,COUNT(p.id_transaksi) AS jumlah_transaksi,SUM(p.grand_total) AS total_belanja'.$where.' GROUP BY '.$expression.' ORDER BY tanggal LIMIT '.max(1,$this->maxRows),$bindings,'Agregasi transaksi member per '.$grain);
            $rows=$result['rows'];
            if((int)array_sum(array_column($rows,'jumlah_transaksi'))!==$count||abs(array_sum(array_column($rows,'total_belanja'))-$total)>0.005)$this->sourceChanged();
            if(count(array_unique(array_column($rows,'tanggal')))!==count($rows))$this->sourceChanged();
            foreach($rows as $row){
                $date=(string)($row['tanggal']??'');$format=match($grain){'day'=>'/^\d{4}-\d{2}-\d{2}$/D','month'=>'/^\d{4}-\d{2}$/D','year'=>'/^\d{4}$/D'};
                if(!preg_match($format,$date)||($grain==='day'&&($date<$start||$date>=$end)))$this->sourceChanged();
            }
            $columns=[['key'=>'tanggal','label'=>$grain==='day'?'Tanggal':($grain==='month'?'Bulan':'Tahun'),'type'=>'text'],['key'=>'jumlah_transaksi','label'=>'Jumlah Transaksi','type'=>'number'],['key'=>'total_belanja','label'=>'Nominal Belanja','type'=>'currency']];
            $metric=$plan['metric']==='transaction_count'?'jumlah_transaksi':'total_belanja';
            $chart=['chart_type'=>'line','title'=>$title,'subtitle'=>'Hanya transaksi NORMAL; tanggal tanpa transaksi tidak diberi angka buatan.',
                'labels'=>array_map('strval',array_column($rows,'tanggal')),'series'=>[['name'=>$metric==='total_belanja'?'Nominal Belanja':'Jumlah Transaksi','data'=>array_map('floatval',array_column($rows,$metric))]]];
            $summary.=' Rincian **per '.match($grain){'day'=>'hari','month'=>'bulan','year'=>'tahun'}.'** memakai tanggal transaksi.';
        }elseif($count>0){
            $rows=[['id_member'=>$member['id_member'],'nama'=>$member['nama'],'jumlah_transaksi'=>$count,'total_belanja'=>$total]];
            $columns=[['key'=>'id_member','label'=>'ID Member','type'=>'text'],['key'=>'nama','label'=>'Member','type'=>'text'],['key'=>'jumlah_transaksi','label'=>'Transaksi','type'=>'number'],['key'=>'total_belanja','label'=>'Total Belanja','type'=>'currency']];
        }
        $blocks=[['type'=>'text','content'=>$summary]];
        if($rows!==[]&&$plan['output_preference']!=='text')$blocks[]=['type'=>'table','title'=>$title,'columns'=>$columns,'rows'=>$rows];
        if($rows!==[]&&$details&&$plan['output_preference']==='text'){
            foreach(array_slice($rows,0,5) as $row)$summary.="\n\n".$this->plain((string)$row['id_transaksi']).' · '.$this->plain((string)$row['tanggal']).' · '.$this->rp((float)$row['total_belanja']);
            if(count($rows)>5)$summary.="\n\nSeluruh nota tersedia melalui tombol Tabel.";
            $blocks=[['type'=>'text','content'=>$summary]];
        }
        if($chart!==null&&$plan['output_preference']!=='text')$blocks[]=['type'=>'chart']+$chart;
        $kpis=[['fact'=>ReportFacts::metric('member_spend',$total)],['fact'=>ReportFacts::metric('transactions',$count,'number')]];
        $updated=array_replace($context,['intent'=>$plan['intent'],'domain'=>'members','year'=>$plan['year'],'month'=>$plan['month']??null,
            'focus_member'=>$member,'member_identity'=>$resolved['identity'],'member_ref'=>$resolved['identity']['ref'],
            'last_title'=>$title,'last_rows'=>$rows,'last_columns'=>$columns,'last_chart'=>$chart,'last_kpis'=>$kpis,'last_queries'=>[]]);
        return ['summary'=>$summary,'blocks'=>$blocks,'metadata'=>['intent'=>$plan['intent'],'empty_result'=>$count===0,'period_label'=>$period,
            'member_identity'=>$resolved['identity'],'result_snapshot'=>['columns'=>$columns,'rows'=>$rows],'result_facts'=>['version'=>1,'metrics'=>array_column($kpis,'fact')]],'updated_context'=>$updated];
    }
    private function bounds(array $plan): array
    {
        if(!empty($plan['date_start'])&&!empty($plan['date_end']))return [$plan['date_start'],$plan['date_end']];
        $start=new \DateTimeImmutable(sprintf('%04d-%02d-01',$plan['year'],$plan['month']??1));
        return [$start->format('Y-m-d'),$start->modify(!empty($plan['month'])?'+1 month':'+1 year')->format('Y-m-d')];
    }
    private function sourceChanged():never{throw new AnalysisException('source_changed','Rincian transaksi member tidak cocok dengan total kontrol. Data mungkin berubah saat dibaca atau melampaui batas rincian. Tidak ada hasil baru yang disimpan.');}
    private function rp(float $value):string{return ($value<0?'-':'').'Rp'.number_format(abs($value),0,',','.');}
    private function plain(string $value):string{return str_replace(['\\','*','_','[',']','`','<','>'],['\\\\','\\*','\\_','\\[','\\]','\\`','&lt;','&gt;'],$value);}
}
