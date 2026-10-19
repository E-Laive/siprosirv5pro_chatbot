<?php
namespace App\Services\AI;

/** Trusted compilers for explicit business plans; all execution still passes the shared read-only executor. */
final class OperationalReports
{
    private int $rowLimit;
    public function __construct(private ConversationScope $scope,int $rowLimit=500) { $this->rowLimit=max(1,min(500,$rowLimit)); }

    public function render(array $plan,array $context,callable $runSql): array
    {
        $start=$plan['date_start']??sprintf('%04d-%02d-01',$plan['year'],$plan['month']??1);
        $end=$plan['date_end']??(new \DateTimeImmutable($start))->modify(empty($plan['month'])?'+1 year':'+1 month')->format('Y-m-d');
        $period=$this->scope->periodLabel($plan);
        return match($plan['intent']) {
            'sales_returns','sales_returns_presence'=>$this->returns($plan,$context,$runSql,$start,$end,$period),
            'cash_transactions','cash_flow'=>$this->cash($plan,$context,$runSql,$start,$end,$period),
            'top_products'=>$this->products($plan,$context,$runSql,$start,$end,$period),
        };
    }
    private function returns(array $p,array $context,callable $run,string $start,string $end,string $period): array
    {
        $from=" FROM tblreturpenjualan r JOIN tbltransaksipenjualan p ON p.id_transaksi = r.id_transaksi WHERE COALESCE(r.hapus,0) = 0 AND UPPER(COALESCE(r.status,'')) IN ('FINAL','SELESAI','SUKSES') AND COALESCE(p.source_type,'NORMAL') = 'NORMAL' AND r.tanggal >= ? AND r.tanggal < ?";
        $a=$run('SELECT COUNT(DISTINCT r.id_retur) AS return_count, COALESCE(SUM(r.total_retur),0) AS return_amount'.$from,[$start,$end],'Periksa retur penjualan sah');
        $count=(int)$a['rows'][0]['return_count'];$amount=(float)$a['rows'][0]['return_amount'];
        $summary=$count===0?"Untuk {$period}, tidak ditemukan retur penjualan sah pada pemeriksaan POS terbaru.":"Untuk {$period}, tersedia **{$count} retur penjualan sah** dengan total **{$this->rp($amount)}**. Retur dihitung menurut tanggal retur, dari penjualan operasional NORMAL.";
        $rows=[];$columns=[];$chart=null;
        $facts=[ReportFacts::metric('return_documents',$count,'number'),ReportFacts::metric('returns',$amount)];
        if($count>0 && $p['intent']==='sales_returns') {
            $this->withinLimit($count);
            $r=$run('SELECT r.id_retur, r.id_transaksi, r.tanggal, r.total_retur, r.tipe_retur'.$from.' ORDER BY r.tanggal,r.id_retur LIMIT '.$this->rowLimit,[$start,$end],'Rincian retur penjualan sah');
            $rows=$r['rows'];$columns=$this->columns(['id_retur'=>['Dokumen Retur','text'],'id_transaksi'=>['Penjualan Asal','text'],'tanggal'=>['Tanggal Retur','text'],'total_retur'=>['Nominal Retur','currency'],'tipe_retur'=>['Penyelesaian','text']]);
            $this->assertStable($count,count($rows),$amount,array_sum(array_column($rows,'total_retur')));
            $chart=$this->dailyChart($rows,'total_retur','Retur penjualan '.$period);
        }
        return $this->result($p,$context,$summary,'Retur Penjualan — '.$period,$rows,$columns,$chart,$facts,$count===0);
    }
    private function cash(array $p,array $context,callable $run,string $start,string $end,string $period): array
    {
        $ledger=$p['intent']==='cash_flow';$bindings=[$start,$end];
        if($ledger){
            $from=" FROM tblbank_ledger l WHERE COALESCE(l.posting_role,'MAIN') NOT IN ('TRANSFER_IN','TRANSFER_OUT') AND l.tanggal >= ? AND l.tanggal < ?";
            $direction=$p['semantic_plan']['parameters']['cash_direction']??'BOTH';
            if($direction!=='BOTH')$from.=$direction==='MASUK'?' AND l.debit > 0':' AND l.kredit > 0';
            $aggregate='SELECT COUNT(l.id_ledger) AS documents, COALESCE(SUM(l.debit),0) AS masuk, COALESCE(SUM(l.kredit),0) AS keluar';
            $select='SELECT l.id_ledger, l.tanggal, l.id_bank, l.source_type, l.keterangan, l.debit AS masuk, l.kredit AS keluar';
            $order=' ORDER BY l.tanggal,l.id_ledger LIMIT '.$this->rowLimit;$title='Arus Kas dan Bank';
            $columns=$this->columns(['id_ledger'=>['ID Mutasi','text'],'tanggal'=>['Tanggal','text'],'id_bank'=>['Akun','text'],'source_type'=>['Sumber','text'],'keterangan'=>['Keterangan','text'],'masuk'=>['Masuk','currency'],'keluar'=>['Keluar','currency']]);
        }else{
            $from=" FROM tbltransaksi_kas c WHERE COALESCE(c.hapus,0) = 0 AND c.status = 'FINAL' AND c.jenis IN ('MASUK','KELUAR') AND c.tanggal >= ? AND c.tanggal < ?";
            $direction=$p['semantic_plan']['parameters']['cash_direction'];
            if($direction!=='BOTH'){$from.=' AND c.jenis = ?';$bindings[]=$direction;}
            $aggregate="SELECT COUNT(DISTINCT c.id_transaksi_kas) AS documents, COALESCE(SUM(CASE WHEN c.jenis = 'MASUK' THEN c.nominal ELSE 0 END),0) AS masuk, COALESCE(SUM(CASE WHEN c.jenis = 'KELUAR' THEN c.nominal ELSE 0 END),0) AS keluar";
            $select='SELECT c.id_transaksi_kas, c.tanggal, c.jenis, c.id_bank_asal, c.kategori, c.keterangan, c.nominal';
            $order=' ORDER BY c.tanggal,c.id_transaksi_kas LIMIT '.$this->rowLimit;$title='Kas Operasional '.($direction==='BOTH'?'Masuk dan Keluar':ucfirst(strtolower($direction)));
            $columns=$this->columns(['id_transaksi_kas'=>['Dokumen Kas','text'],'tanggal'=>['Tanggal','text'],'jenis'=>['Jenis','text'],'id_bank_asal'=>['Akun','text'],'kategori'=>['Kategori','text'],'keterangan'=>['Keterangan','text'],'nominal'=>['Nominal','currency']]);
        }
        $a=$run($aggregate.$from,$bindings,$title.' — ringkasan');$data=$a['rows'][0];$count=(int)$data['documents'];
        $in=(float)$data['masuk'];$out=(float)$data['keluar'];$rows=[];
        if($count>0){$this->withinLimit($count);$r=$run($select.$from.$order,$bindings,$title.' — rincian');$rows=$r['rows'];
            $actualIn=$ledger?array_sum(array_column($rows,'masuk')):array_sum(array_map(static fn($row)=>$row['jenis']==='MASUK'?(float)$row['nominal']:0,$rows));
            $actualOut=$ledger?array_sum(array_column($rows,'keluar')):array_sum(array_map(static fn($row)=>$row['jenis']==='KELUAR'?(float)$row['nominal']:0,$rows));
            $this->assertStable($count,count($rows),$in,$actualIn);$this->assertStable($count,count($rows),$out,$actualOut);
        }
        $totals=$direction==='BOTH'?"pemasukan **{$this->rp($in)}**, pengeluaran **{$this->rp($out)}**, selisih **{$this->rp($in-$out)}**":($direction==='MASUK'?"pemasukan **{$this->rp($in)}**":"pengeluaran **{$this->rp($out)}**");
        $summary=$count===0?"Untuk {$period}, tidak ditemukan transaksi {$title} sesuai cakupan yang diminta.":"Untuk {$period}, {$title}: {$totals} ({$count} ".($ledger?'mutasi':'dokumen').').';
        $summary.=$ledger?' Sumber: mutasi kas/bank yang sudah diposting. Transfer internal dikecualikan; angka ini bukan laba atau saldo akhir.':' Sumber: dokumen kas operasional manual FINAL yang tidak dihapus. Transfer antar akun dikecualikan; penerimaan penjualan dan pembayaran pembelian otomatis tidak digabungkan.';
        $facts=match($direction){'MASUK'=>[ReportFacts::metric('cash_inflow',$in),ReportFacts::metric('cash_documents',$count,'number')],'KELUAR'=>[ReportFacts::metric('cash_outflow',$out),ReportFacts::metric('cash_documents',$count,'number')],default=>[ReportFacts::metric('cash_inflow',$in),ReportFacts::metric('cash_outflow',$out),ReportFacts::metric('cash_difference',$in-$out)]};
        $chart=$rows===[]?null:$this->cashChart($rows,$ledger,$title.' — '.$period);
        if($chart!==null&&$direction!=='BOTH')$chart['series']=[$chart['series'][$direction==='MASUK'?0:1]];
        return $this->result($p,$context,$summary,$title.' — '.$period,$rows,$columns,$chart,$facts,$count===0);
    }
    private function products(array $p,array $context,callable $run,string $start,string $end,string $period): array
    {
        $metric=$p['metric']??'product_amount';
        $key=match($metric){'product_units'=>'total_unit_dasar','product_frequency'=>'frekuensi_nota',default=>'total_omzet'};
        $label=match($metric){'product_units'=>'jumlah unit dasar','product_frequency'=>'frekuensi nota',default=>'nominal penjualan item'};
        $direction=$p['ranking']['direction']??'desc';$limit=$p['ranking']['limit']??$p['limit']??5;
        $this->withinLimit($limit);
        $bindings=[$start,$end];$where="d.terjual = 1 AND COALESCE(p.source_type,'NORMAL') = 'NORMAL' AND p.tanggal >= ? AND p.tanggal < ?";
        if(!empty($p['category'])){$where.=' AND d.kategori = ?';$bindings[]=$p['category'];}
        $sql="SELECT d.id_produk, MAX(d.nama_produk) AS nama_produk, MAX(d.kategori) AS kategori, COUNT(DISTINCT p.id_transaksi) AS frekuensi_nota, COALESCE(SUM(d.jumlah * d.isi_satuan),0) AS total_unit_dasar, COALESCE(SUM(d.total_subtotal),0) AS total_omzet FROM tbltransaksipenjualan_detail d JOIN tbltransaksipenjualan p ON p.id_transaksi = d.id_transaksi WHERE {$where} GROUP BY d.id_produk ORDER BY {$key} ".strtoupper($direction).',d.id_produk LIMIT '.$this->rowLimit;
        $r=$run($sql,$bindings,'Peringkat produk berdasarkan '.$label);$all=$r['rows'];$rows=$all;
        if($rows!==[]){
            $cutoff=(float)$rows[min($limit,count($rows))-1][$key];
            $rows=array_values(array_filter($rows,static fn($row)=>$direction==='asc'?(float)$row[$key]<=$cutoff:(float)$row[$key]>=$cutoff));
            if(count($all)===$this->rowLimit&&(count($all)<=$limit||(float)end($all)[$key]===$cutoff))$this->withinLimit($this->rowLimit+1);
        }
        foreach($rows as $i=>&$row)$row=['peringkat'=>$i+1]+$row;unset($row);
        $count=count($rows);$title='Peringkat Produk — '.$period;
        $summary=$rows===[]?"Untuk {$period}, tidak ditemukan penjualan produk sesuai filter.":"Untuk {$period}, ditampilkan **{$count} produk** berdasarkan **{$label}** ".($direction==='asc'?'terendah':'tertinggi').'. Produk pertama: **'.$rows[0]['nama_produk'].'**, '.($key==='total_omzet'?$this->rp((float)$rows[0][$key]):number_format((float)$rows[0][$key],2,',','.')).($key==='total_unit_dasar'?' unit dasar':'').'.';
        if($count>$limit)$summary.=' Nilai seri pada batas peringkat ikut disertakan.';
        $columns=$this->columns(['peringkat'=>['Peringkat','number'],'id_produk'=>['Kode Produk','text'],'nama_produk'=>['Produk','text'],'kategori'=>['Kategori','text'],'total_unit_dasar'=>['Unit Dasar','number'],'total_omzet'=>['Nominal Item','currency'],'frekuensi_nota'=>['Frekuensi Nota','number']]);
        $chart=['chart_type'=>'bar','title'=>$title,'labels'=>array_column($rows,'nama_produk'),'series'=>[['name'=>$label,'data'=>array_map('floatval',array_column($rows,$key))]]];
        $facts=[ReportFacts::metric('ranked_products',$count,'number'),ReportFacts::metric($metric,(float)($rows[0][$key]??0),$metric==='product_amount'?'IDR':'number')];
        return $this->result($p,$context,$summary,$title,$rows,$columns,$chart,$facts,$rows===[]);
    }
    private function result(array $p,array $context,string $summary,string $title,array $rows,array $columns,?array $chart,array $facts,bool $empty): array
    {
        $kpis=array_map(static fn($fact)=>['label'=>$fact['label'],'value'=>$fact['value'],'fact'=>$fact],$facts);
        $blocks=[['type'=>'text','content'=>$summary]];
        if(!$empty&&$rows!==[]){$blocks[]=['type'=>'table','title'=>$title,'columns'=>$columns,'rows'=>$rows];if($chart!==null)$blocks[]=['type'=>'chart']+$chart;}
        return ['summary'=>$summary,'blocks'=>$blocks,'metadata'=>['intent'=>$p['intent'],'domain'=>$p['domain'],'empty_result'=>$empty],
            'updated_context'=>array_replace($context,['intent'=>$p['intent'],'domain'=>$p['domain'],'last_title'=>$title,'last_rows'=>$rows,'last_columns'=>$rows===[]?[]:$columns,'last_chart'=>$chart,'last_kpis'=>$kpis])];
    }
    private function dailyChart(array $rows,string $value,string $title): array
    {
        $days=[];foreach($rows as $row){$day=substr($row['tanggal'],0,10);$days[$day]=($days[$day]??0)+(float)$row[$value];}
        return ['chart_type'=>'line','title'=>$title,'labels'=>array_keys($days),'series'=>[['name'=>'Nominal retur','data'=>array_values($days)]]];
    }
    private function cashChart(array $rows,bool $ledger,string $title): array
    {
        $days=[];foreach($rows as $row){$day=substr($row['tanggal'],0,10);$days[$day]??=['masuk'=>0,'keluar'=>0];
            if($ledger){$days[$day]['masuk']+=(float)$row['masuk'];$days[$day]['keluar']+=(float)$row['keluar'];}
            else $days[$day][$row['jenis']==='MASUK'?'masuk':'keluar']+=(float)$row['nominal'];}
        return ['chart_type'=>'line','title'=>$title,'labels'=>array_keys($days),'series'=>[['name'=>'Nominal masuk','data'=>array_column($days,'masuk')],['name'=>'Nominal keluar','data'=>array_column($days,'keluar')]]];
    }
    private function columns(array $spec): array { $out=[];foreach($spec as $key=>[$label,$type])$out[]=['key'=>$key,'label'=>$label,'type'=>$type];return $out; }
    private function assertStable(int $expectedCount,int $actualCount,float $expected,float $actual): void { if($expectedCount!==$actualCount||abs($expected-$actual)>0.005)throw new AnalysisException('source_changed','Data POS berubah selama laporan diambil. Ringkasan dan rincian belum konsisten; ulangi permintaan. Tidak ada dataset baru yang disimpan.'); }
    private function withinLimit(int $count): void { if($count>$this->rowLimit)throw new AnalysisException('result_limit_exceeded','Rincian mencapai batas '.$this->rowLimit.' baris atau kelengkapan nilai seri belum dapat dibuktikan. Persempit periode atau filter agar laporan lengkap; tidak ada hasil terpotong yang disimpan.'); }
    private function rp(float $amount): string { return ($amount<0?'-':'').'Rp'.number_format(abs($amount),0,',','.'); }
}
