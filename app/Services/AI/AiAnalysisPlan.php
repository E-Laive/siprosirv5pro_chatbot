<?php
namespace App\Services\AI;

/** Validate the online AI's complete business plan. No natural-language routing takes place here. */
final class AiAnalysisPlan
{
    public const INTENTS = ['data_lookup','sales_period_summary','sales_daily','sales_presence','sales_returns_presence','sales_returns','cash_transactions','cash_flow','member_activity','member_spend','member_transactions','sales_trend_monthly','year_comparison','month_comparison','month_comparison_daily','steepest_decline_month','root_cause_decline','daily_average','top_products','product_decline','gross_profit','member_growth','low_stock','stock_by_location','top_suppliers','unpaid_receivables','cash_bank_summary','payroll_summary','dynamic_llm_or_clarify'];
    public const OPERATORS = ['>','>=','<','<=','=','!='];
    public const METRICS = ['invoice_amount'=>'SUM(p.grand_total)','transaction_count'=>'COUNT(DISTINCT p.id_transaksi)',
        'product_units'=>'SUM(d.jumlah * d.isi_satuan)','product_amount'=>'SUM(d.total_subtotal)','product_frequency'=>'COUNT(DISTINCT p.id_transaksi)',
        'return_amount'=>'SUM(r.total_retur)','return_count'=>'COUNT(DISTINCT r.id_retur)',
        'cash_amount'=>'SUM(c.nominal)','cash_count'=>'COUNT(DISTINCT c.id_transaksi_kas)',
        'cash_inflow'=>'SUM(l.debit)','cash_outflow'=>'SUM(l.kredit)'];
    private string $path='plan';
    private mixed $input=null;

    public function __construct(private ?BusinessClock $clock = null) { $this->clock ??= new BusinessClock(); }

    public function instructions(): string
    {
        return 'Anda pengarah utama, bukan pemeriksa kandidat parser. Untuk SETIAP analisis data balas decision analysis dan plan LENGKAP, tanpa SQL. '
            . 'Bentuk plan: {"version":1,"intent":"member_activity","domain":"members","request":"Permintaan bisnis lengkap setelah menyambungkan konteks","period":{"year":2026,"month":10,"date_start":null,"date_end":null},"entity":"member","metric":"invoice_amount","dimensions":["member"],"time_grain":null,"filters":[{"scope":"aggregate","metric":"invoice_amount","operator":">","value":17000000}],"ranking":null,"output_preference":"table","parameters":{"limit":100}}. '
            . 'Intent tersedia: ' . implode(', ', self::INTENTS) . '. Domain sesuai katalog hak akses aplikasi. period berupa year+month untuk bulan, year+month null untuk tahun, atau date_start/date_end ISO akhir eksklusif. period null hanya untuk data tanpa periode transaksi seperti stok. '
            . 'Ambil tanggal relatif dari business_date Asia/Jakarta, bukan dari waktu laporan lama. Bulan ini harus bulan bisnis sekarang; pertahankan periode sebelumnya jika follow-up tidak menggantinya. '
            . 'entity/metric boleh null untuk analisis khusus; dimensions wajib daftar dimensi; time_grain day|month|year|null. filters wajib array, kosong hanya jika benar-benar tidak ada filter bisnis tambahan. '
            . 'Setiap syarat harus dicatat: scope aggregate untuk total per kelompok (SUM atau COUNT), scope row untuk syarat per transaksi/member/produk. Aggregate metric invoice_amount=SUM(grand_total), transaction_count=COUNT(DISTINCT id_transaksi). Row field harus nama tabel.kolom katalog bisnis; operator >|>=|<|<=|=|!= dan value angka/teks tunggal. '
            . 'Nominal, nominalnya, belanja, nilai, melebihi, di atas, minimal, tidak sampai dan bahasa lain dipahami secara semantik; contoh melebihi 17 juta => invoice_amount > 17000000. Jumlah nominal berarti nominal, bukan jumlah transaksi. Jangan memilih template bila harus menghilangkan filter. '
            . 'ranking null untuk daftar/filter biasa; untuk peringkat gunakan {"metric":"nama metrik katalog","direction":"desc|asc","limit":5}. Member memakai invoice_amount atau transaction_count. Produk memakai product_units (jumlah * isi_satuan dasar), product_amount (subtotal item), atau product_frequency (frekuensi nota). top_products memakai entity product, dimensions [product], time_grain null, ranking dengan metrik yang sama. Terlaris tanpa metrik jelas boleh clarify nominal atau jumlah unit; jawaban jumlah melanjutkan pending sebagai product_units. '
            . 'member_spend hanya TOTAL nominal/frekuensi satu member, atau agregatnya per hari/bulan/tahun. entity member, metric invoice_amount|transaction_count. Total: dimensions [member], time_grain null. Harian: dimensions [date], time_grain day; bulanan: dimensions [date], time_grain month; tahunan: dimensions [date], time_grain year. member_transactions adalah DAFTAR NOTA/transaksi satu member, dimensions [transaction], time_grain null, metric null, output table. Permintaan tampilkan transaksi bukan permintaan total; grafik per hari harus memakai member_spend time_grain day. parameters WAJIB salah satu member_query (nama/ID dari ucapan pengguna) ATAU member_ref (salin ref opaque dari period_context.member_reference atau reports.member_reference). Jangan mengarang ID/nama dari baris POS yang tidak dikirim. Follow-up singkat perhari dan transaksi tahun ini mempertahankan member_reference; ganti tahun/periode sesuai pesan terbaru. Jika tidak ada rujukan tunggal, clarify identitas bisnis. '
            . 'Contoh rincian harian member yang sudah dipilih: {"version":1,"intent":"member_spend","domain":"members","request":"Grafik pembelian harian member terpilih untuk Oktober 2026","period":{"year":2026,"month":10,"date_start":null,"date_end":null},"entity":"member","metric":"invoice_amount","dimensions":["date"],"time_grain":"day","filters":[],"ranking":null,"output_preference":"chart","parameters":{"member_ref":"SALIN_REF_DARI_KONTEKS"}}. '
            . 'Retur penjualan adalah objek sale_return, domain returns_receivables, BUKAN sale/sales_presence. sales_returns_presence memeriksa ada/tidak retur sah, metric return_count, dimensions [], time_grain null. sales_returns menampilkan laporan retur, metric return_amount. Tanggal memakai tanggal retur, status FINAL/SELESAI/SUKSES, tidak dihapus, penjualan asal NORMAL. Jangan menyimpulkan retur dari banyaknya penjualan. '
            . 'cash_transactions adalah transaksi kas operasional manual FINAL dari tbltransaksi_kas, entity cash_transaction, metric cash_amount atau cash_count. parameters.cash_direction MASUK|KELUAR|BOTH, transfer dikecualikan. cash_flow adalah seluruh mutasi akun yang sudah diposting dari tblbank_ledger, entity cash_entry, metric cash_inflow atau cash_outflow; transfer internal dikecualikan. cash_bank_summary hanya saldo SAAT INI, tidak mendukung pemasukan/pengeluaran atau saldo historis. Bila cakupan kas manual versus semua arus bank belum jelas, tanyakan satu klarifikasi bisnis. '
            . 'Kontrak penyajian output_preference hanya auto|text|table|chart|both; text boleh untuk data_lookup, query dinamis, dan intent presence. Untuk rentang tanggal, year tetap tahun date_start (bukan null). Bulan ini gunakan year dan month, tanggal null; jangan membuat cutoff tanggal sendiri kecuali diminta sampai tanggal tertentu. '
            . 'data_lookup adalah kemampuan umum pencarian DATA MASTER dari lookup_catalog, bukan laporan transaksi. Gunakan untuk mencari keberadaan/nama/ID/atribut member, produk, supplier, lokasi, kategori, dan master lain yang tersedia. Tidak perlu periode atau transaksi untuk membuktikan master terdaftar. period null, metric null, time_grain null, ranking null, dimensions [], parameters {"table":"nama_tabel_katalog","lookup_mode":"presence|records","columns":["kolom_yang_diminta"],"limit":10}. presence untuk ada/tidak, records untuk atribut/daftar. Pilih kolom minimal; jangan tampilkan kontak/alamat jika tidak diminta. filters scope row, field tabel.kolom, operator =|!=|>|>=|<|<=|contains|starts_with|ends_with; contains berarti pencarian teks literal tanpa membedakan kapital. Nama yang dicari HANYA nama, bukan kata atas nama atau ada. Untuk data_lookup seluruh filter harus berasal dari tabel yang sama. Pencarian tidak memakai GROUP BY. Jika perlu relasi, perhitungan atau laporan di luar template pilih dynamic_llm_or_clarify, bukan menolak karena nama intent belum ada. '
            . 'Contoh pencarian member: {"version":1,"intent":"data_lookup","domain":"members","request":"Periksa apakah member bernama Hendra Wijaya terdaftar","period":null,"entity":"member","metric":null,"dimensions":[],"time_grain":null,"filters":[{"scope":"row","field":"tbldata_member.nama","operator":"contains","value":"Hendra Wijaya"}],"ranking":null,"output_preference":"text","parameters":{"table":"tbldata_member","lookup_mode":"presence","columns":["id_member","nama"]}}. Identitas ambigu seperti dia merujuk siapa harus clarify; jangan mengarang nama/ID dari hasil yang tidak dikirim. '
            . 'Perlindungan NORMAL, FINAL, hapus dan pengecualian transfer SUDAH diterapkan compiler: JANGAN mengulangnya di filters. filters=[] untuk permintaan tanpa filter tambahan pengguna. Jangan mengarang field sale_return.status, sale_return.deleted, sale.source, is_internal_transfer; nama entitas bukan nama tabel. '
            . 'Contoh pemeriksaan retur hari ini: {"version":1,"intent":"sales_returns_presence","domain":"returns_receivables","request":"Periksa keberadaan retur penjualan hari ini","period":{"year":2026,"month":null,"date_start":"2026-10-19","date_end":"2026-10-20"},"entity":"sale_return","metric":"return_count","dimensions":[],"time_grain":null,"filters":[],"ranking":null,"output_preference":"text","parameters":{}}. Sesuaikan tanggal dari business_date, bukan contoh. '
            . 'Contoh arus semua kas dan bank bulan ini: {"version":1,"intent":"cash_flow","domain":"cash_finance","request":"Arus kas dan bank bulan ini tanpa transfer internal","period":{"year":2026,"month":10,"date_start":null,"date_end":null},"entity":"cash_entry","metric":"cash_inflow","dimensions":[],"time_grain":null,"filters":[],"ranking":null,"output_preference":"table","parameters":{}}. Pemasukan dan pengeluaran adalah dua metrik, bukan dimensions category. Laporan manual cash_transactions memakai bentuk sama dengan entity cash_transaction, metric cash_amount, parameters {"cash_direction":"MASUK|KELUAR|BOTH"}. '
            . 'parameters analisis hanya comparison,comparison_year,comparison_month,category,member_query,member_ref,months_back,limit,cash_direction; khusus data_lookup gunakan table,lookup_mode,columns,limit. Agregat lain: return_amount,return_count,cash_amount,cash_count,cash_inflow,cash_outflow. Intent template hanya alat eksekusi; untuk kebutuhan lain pilih dynamic_llm_or_clarify dengan request lengkap dan filters yang tetap diterapkan. '
            . 'Follow-up seperti kalau yang di atas 5 juta mengubah batas pada active_semantic_plan menjadi 5000000, sambil mempertahankan entitas, metrik, dimensi dan periode. Kirim rencana penuh hasil perubahan, bukan potongan. Mengganti filter daftar tidak otomatis mempertahankan ranking top 1 dari laporan peringkat lama. '
            . 'Untuk percakapan umum/context/reformat/clarify tetap gunakan jalur yang sesuai dalam SATU respons yang sama. Pertanyaan data baru jangan dijawab general/context. '
            . 'Untuk pertanyaan waktu laporan seperti itu tadi data kapan, balas {"decision":"context","context_subject":"period","report_ref":"referensi laporan relevan"}. context_subject source untuk sumber, meaning untuk arti angka, summary untuk ringkasan. context_subject wajib; teks reply boleh dikosongkan karena backend menyusun penjelasan dari fakta tersimpan. '
            . 'Klarifikasi hanya informasi bisnis yang ambigu; jangan tanyakan dukungan SQL/aplikasi. Jangan mengarang angka atau menghapus syarat agar rencana diterima. Jangan menulis keputusan use_plan atau dynamic tanpa plan.';
    }

    public function validate(mixed $input,array $context=[]): array
    {
        $this->input=$input;$this->path='plan';
        if (!is_array($input) || ($input['version'] ?? null) !== 1) $this->invalid();
        $this->keys($input, ['version','intent','domain','request','period','entity','metric','dimensions','time_grain','filters','ranking','output_preference','parameters']);
        foreach (['intent','domain','request','period','entity','metric','dimensions','time_grain','filters','ranking','output_preference'] as $key) if (!array_key_exists($key,$input)) $this->invalid('plan.'.$key,'required');
        $this->path='plan.intent';
        if (!in_array($input['intent'],self::INTENTS,true) || !in_array($input['domain'],['sales','members','products_inventory','purchasing_payables','returns_receivables','cash_finance','hr_payroll'],true)) $this->invalid();
        $domain=match($input['intent']) {
            'member_activity','member_spend','member_transactions','member_growth'=>'members', 'low_stock','stock_by_location'=>'products_inventory',
            'top_suppliers'=>'purchasing_payables','unpaid_receivables','sales_returns','sales_returns_presence'=>'returns_receivables','cash_bank_summary','cash_transactions','cash_flow'=>'cash_finance','payroll_summary'=>'hr_payroll',
            'data_lookup','dynamic_llm_or_clarify'=>$input['domain'], default=>'sales',
        };
        if ($input['domain']!==$domain) $this->invalid('plan.domain','intent_domain_mismatch');
        $this->path='plan.request';
        if (!is_string($input['request']) || trim($input['request']) === '' || mb_strlen($input['request']) > 4000) $this->invalid();
        foreach (['entity','metric'] as $key) if ($input[$key] !== null && (!is_string($input[$key]) || !preg_match('/^[a-z_]{1,60}$/D',$input[$key]))) $this->invalid('plan.'.$key,'identifier_required');
        $this->path='plan.dimensions';
        if (!is_array($input['dimensions']) || !array_is_list($input['dimensions']) || count($input['dimensions'])>8) $this->invalid();
        foreach ($input['dimensions'] as $dimension) if (!is_string($dimension) || !preg_match('/^[a-z_]{1,60}$/D',$dimension)) $this->invalid();
        if (!in_array($input['time_grain'],[null,'day','month','year'],true)) $this->invalid('plan.time_grain','unsupported_grain');
        if (!in_array($input['output_preference'],['auto','text','table','chart','both'],true)) $this->invalid('plan.output_preference','unsupported_presentation');
        if($input['output_preference']==='text'&&!in_array($input['intent'],['member_spend','member_transactions','data_lookup','dynamic_llm_or_clarify','sales_presence','sales_returns_presence'],true))$this->invalid('plan.output_preference','text_only_supported_for_presence');
        $this->path='plan.period';
        $period = $this->period($input['period'],$input['intent']);
        if($input['period']!==null)$input['period']=$period;
        $this->path='plan.filters';$filters = $this->filters($input['filters'],$input['intent']==='data_lookup');
        $this->path='plan.parameters';
        $parameters = $input['parameters'] ?? [];
        if (!is_array($parameters)) $this->invalid();
        $this->keys($parameters,['comparison','comparison_year','comparison_month','category','member_query','member_ref','months_back','limit','cash_direction','table','lookup_mode','columns']);
        if(isset($parameters['member_ref'])){
            $identity=is_string($parameters['member_ref'])?MemberIdentity::find($context,$parameters['member_ref']):null;
            if(!is_string($parameters['member_ref'])||!$identity||$identity['ref']!==$parameters['member_ref'])$this->invalid('plan.parameters.member_ref','owned_member_reference_required');
            if(!empty($parameters['member_query']))$this->invalid('plan.parameters.member_ref','choose_query_or_reference');
            if($input['domain']!=='members'||$input['entity']!=='member'||!in_array($input['intent'],['member_spend','member_transactions','dynamic_llm_or_clarify'],true))$this->invalid('plan.parameters.member_ref','member_analysis_required');
        }
        if($input['intent']==='data_lookup')$this->validateLookup($input,$parameters);
        elseif(array_intersect(array_keys($parameters),['table','lookup_mode','columns']))$this->invalid('plan.parameters','lookup_parameters_require_lookup');
        if(isset($parameters['cash_direction'])&&!in_array($parameters['cash_direction'],['MASUK','KELUAR','BOTH'],true))$this->invalid('plan.parameters.cash_direction','unsupported_direction');
        foreach (['comparison_year','comparison_month','months_back','limit'] as $key) if (isset($parameters[$key]) && !is_int($parameters[$key])) $this->invalid();
        if (isset($parameters['limit']) && ($parameters['limit']<1 || $parameters['limit']>100)) $this->invalid();
        if (isset($parameters['months_back']) && ($parameters['months_back']<1 || $parameters['months_back']>120)) $this->invalid();
        foreach (['category','member_query'] as $key) if (isset($parameters[$key]) && (!is_string($parameters[$key]) || mb_strlen($parameters[$key])>200)) $this->invalid();
        if (isset($parameters['comparison_year']) && ($parameters['comparison_year']<1900 || $parameters['comparison_year']>2199)) $this->invalid();
        if (isset($parameters['comparison_month']) && ($parameters['comparison_month']<1 || $parameters['comparison_month']>12)) $this->invalid();
        if (isset($parameters['comparison'])) {
            $this->comparison($parameters['comparison']);
            $parameters['comparison']=(new MonthComparisonScope($this->clock))->fromPeriods(
                array_intersect_key($parameters['comparison']['baseline'],array_flip(['year','month'])),
                array_intersect_key($parameters['comparison']['target'],array_flip(['year','month'])));
            $parameters['comparison_year']=$parameters['comparison']['baseline']['year'];
            $parameters['comparison_month']=$parameters['comparison']['baseline']['month'];
            $period=['year'=>$parameters['comparison']['target']['year'],'month'=>$parameters['comparison']['target']['month'],'date_start'=>null,'date_end'=>null];
        }
        $this->path='plan.ranking';$ranking = $input['ranking'];
        if ($ranking !== null) {
            if (!is_array($ranking)) $this->invalid();
            $this->keys($ranking,['metric','direction','limit']);
            if (!is_string($ranking['metric']??null) || !isset(self::METRICS[$ranking['metric']]))$this->invalid('plan.ranking.metric','unsupported_metric');
            if (!in_array($ranking['direction']??null,['asc','desc'],true))$this->invalid('plan.ranking.direction','unsupported_direction');
            if (!is_int($ranking['limit']??null)||$ranking['limit']<1||$ranking['limit']>100)$this->invalid('plan.ranking.limit','integer_between_1_and_100_required');
        }
        if ($input['intent'] === 'member_activity') {
            if ($input['domain']!=='members' || $input['entity']!=='member' || !in_array($input['metric'],['invoice_amount','transaction_count'],true) || $input['dimensions']!==['member'] || $input['time_grain']!==null) $this->invalid('plan.entity','member_template_mismatch');
            if ($ranking !== null && $ranking['metric']!==$input['metric']) $this->invalid();
            if (array_filter(array_diff_key($parameters,['limit'=>true]),static fn($value)=>$value!==null && $value!=='' && $value!==[])!==[]) $this->invalid();
        }
        if(in_array($input['intent'],['member_spend','member_transactions'],true)){
            if(empty(trim((string)($parameters['member_query']??'')))&&empty($parameters['member_ref']))$this->invalid('plan.parameters.member_query','member_query_or_reference_required');
            if($input['entity']!=='member')$this->invalid('plan.entity','member_required');
            if($ranking!==null)$this->invalid('plan.ranking','single_member_has_no_ranking');
            if($input['intent']==='member_spend'){
                if(!in_array($input['metric'],['invoice_amount','transaction_count'],true))$this->invalid('plan.metric','member_aggregate_metric_required');
                $dimensions=$input['time_grain']===null?['member']:['date'];
                if($input['dimensions']!==$dimensions)$this->invalid('plan.dimensions','member_aggregate_grain_mismatch');
                if($input['time_grain']===null&&in_array($input['output_preference'],['chart','both'],true))$this->invalid('plan.output_preference','member_chart_requires_time_grain');
            }else{
                if($input['dimensions']!==['transaction']||$input['time_grain']!==null||$input['metric']!==null)$this->invalid('plan.dimensions','member_transactions_require_document_rows');
                if(in_array($input['output_preference'],['chart','both'],true))$this->invalid('plan.output_preference','transaction_list_has_no_chart');
            }
            foreach($parameters as $key=>$value)if(!in_array($key,['member_query','member_ref','limit'],true)&&$value!==null)$this->invalid('plan.parameters.'.$key,'parameter_not_supported_for_member_template');
        }
        $intent = $input['intent'];
        $expected=match($intent){'sales_presence'=>['sale',['transaction_count']], 'sales_daily'=>['sale',['invoice_amount']],
            'sales_returns_presence'=>['sale_return',['return_count']], 'sales_returns'=>['sale_return',['return_amount','return_count']],
            'cash_transactions'=>['cash_transaction',['cash_amount','cash_count']], 'cash_flow'=>['cash_entry',['cash_inflow','cash_outflow']],
            'top_products'=>['product',['product_amount','product_units','product_frequency']],default=>null};
        if($expected!==null){
            // Null values from older clients receive only the selected template's explicit default.
            $input['entity']??=$expected[0];$input['metric']??=$expected[1][0];
            if($input['entity']!==$expected[0])$this->invalid('plan.entity','intent_entity_mismatch');
            if(!in_array($input['metric'],$expected[1],true))$this->invalid('plan.metric','intent_metric_mismatch');
            if($intent==='top_products' && ($input['dimensions']!==['product']||$input['time_grain']!==null))$this->invalid('plan.dimensions','product_ranking_requires_product_grain');
            if($intent==='top_products'&&$ranking!==null&&$ranking['metric']!==$input['metric'])$this->invalid('plan.ranking.metric','ranking_metric_mismatch');
            if(in_array($intent,['sales_presence','sales_returns_presence'],true)&&($input['dimensions']!==[]||$input['time_grain']!==null))$this->invalid('plan.time_grain','presence_has_no_grouping');
        }
        if($intent==='cash_bank_summary'&&($input['metric']!==null&&!in_array($input['metric'],['cash_balance'],true)))$this->invalid('plan.metric','balance_is_not_cash_movements');
        if($intent==='cash_bank_summary'&&(!empty($period['month'])||!empty($period['date_start'])||!in_array($input['entity'],[null,'bank_account'],true)))$this->invalid('plan.period','balance_is_current_snapshot_only');
        if($intent==='cash_transactions'&&!isset($parameters['cash_direction']))$this->invalid('plan.parameters.cash_direction','required');
        if(isset($parameters['cash_direction'])&&!in_array($intent,['cash_transactions','cash_flow','dynamic_llm_or_clarify'],true))$this->invalid('plan.parameters.cash_direction','parameter_not_supported_for_intent');
        if(in_array($intent,['sales_returns','sales_returns_presence','cash_transactions','cash_flow'],true)){
            foreach($parameters as $key=>$value)if(!in_array($key,['limit','cash_direction'],true)&&$value!==null)$this->invalid('plan.parameters.'.$key,'parameter_not_supported_for_intent');
            if($input['dimensions']!==[]||$input['time_grain']!==null)$intent='dynamic_llm_or_clarify';
        }
        $metricEntity=match($input['metric']){'product_units','product_amount','product_frequency'=>'product','return_amount','return_count'=>'sale_return','cash_amount','cash_count'=>'cash_transaction','cash_inflow','cash_outflow'=>'cash_entry',default=>null};
        if($metricEntity!==null&&$input['entity']!==$metricEntity)$this->invalid('plan.metric','metric_entity_mismatch');
        // Unsupported template dimensions/filters keep their complete contract and go to the SQL planner.
        if ($filters !== [] && !in_array($intent,['data_lookup','member_activity'],true)) $intent='dynamic_llm_or_clarify';
        if ($ranking!==null && !in_array($intent,['member_activity','top_products'],true)) $intent='dynamic_llm_or_clarify';
        if ($intent==='member_activity' && array_filter($filters,static fn($f)=>$f['scope']==='row' && !in_array($f['field'],['tbldata_member.id_member','tbldata_member.nama'],true))) $intent='dynamic_llm_or_clarify';
        $result = array_replace(['comparison'=>null,'comparison_year'=>null,'comparison_month'=>null,'category'=>null,'member_query'=>null,'limit'=>100,'months_back'=>12],$period,$parameters,
            ['intent'=>$intent,'domain'=>$input['domain'],'entity'=>$input['entity'],'metric'=>$input['metric'],'dimensions'=>$input['dimensions'],'time_grain'=>$input['time_grain'],
             'group_by'=>$input['time_grain']==='day'?'day':null,'filters'=>$filters,'ranking'=>$ranking,'rank'=>$ranking!==null,'sort_direction'=>$ranking['direction']??'desc',
             'output_preference'=>$input['output_preference'],'period_known'=>true,'requires_fresh'=>true,'raw_question'=>trim($input['request']),'resolved_question'=>trim($input['request']),'semantic_plan'=>$input]);
        if ($ranking!==null) $result['limit']=$ranking['limit'];
        if(!empty($parameters['member_ref']))$result['_member_identity']=MemberIdentity::find($context,$parameters['member_ref']);
        if (in_array($intent,['month_comparison','month_comparison_daily'],true) && empty($parameters['comparison'])) $this->invalid();
        return $result;
    }

    private function period(mixed $period,string $intent): array
    {
        if ($period===null) {
            if (!in_array($intent,['data_lookup','low_stock','stock_by_location','cash_bank_summary','unpaid_receivables','dynamic_llm_or_clarify'],true)) $this->invalid();
            return ['year'=>(int)$this->clock->now()->format('Y'),'month'=>null,'date_start'=>null,'date_end'=>null];
        }
        if (!is_array($period)) $this->invalid();
        $this->keys($period,['year','month','date_start','date_end']);
        $year=$period['year']??null; $month=$period['month']??null;
        if($year===null&&is_string($period['date_start']??null)&&preg_match('/^((?:19|20|21)\d{2})-\d{2}-\d{2}$/D',$period['date_start'],$y))$year=(int)$y[1];
        if (!is_int($year) || $year<1900 || $year>2199 || ($month!==null && (!is_int($month)||$month<1||$month>12))) $this->invalid();
        $start=$period['date_start']??null; $end=$period['date_end']??null;
        if ($start!==null || $end!==null) {
            foreach ([$start,$end] as $date) {
                if (!is_string($date)) $this->invalid();
                $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d',$date);
                if (!$parsed || $parsed->format('Y-m-d')!==$date) $this->invalid();
            }
            if ($end<=$start || (int)substr($start,0,4)!==$year) $this->invalid();
            if($month!==null){
                $first=sprintf('%04d-%02d-01',$year,$month);
                $next=(new \DateTimeImmutable($first))->modify('+1 month')->format('Y-m-d');
                if($start<$first||$end>$next)$this->invalid();
                // Redundant but equivalent month/date bounds are a valid AI representation.
                if($start===$first&&$end===$next){$start=null;$end=null;}
                else $month=null;
            }
        }
        return ['year'=>$year,'month'=>$month,'date_start'=>$start,'date_end'=>$end];
    }

    private function filters(mixed $filters,bool $lookup=false): array
    {
        if (!is_array($filters) || !array_is_list($filters) || count($filters)>12) $this->invalid();
        $schema=new PosSchemaService(); $catalog=$schema->catalog();
        foreach ($filters as $i=>$filter) {
            $this->path='plan.filters.'.$i;
            if (!is_array($filter) || !in_array($filter['scope']??'', ['row','aggregate'],true)) $this->invalid();
            $this->keys($filter,$filter['scope']==='aggregate'?['scope','metric','operator','value']:['scope','field','operator','value']);
            $operators=$lookup&&$filter['scope']==='row'?array_merge(self::OPERATORS,['contains','starts_with','ends_with']):self::OPERATORS;
            if (!in_array($filter['operator']??'',$operators,true))$this->invalid($this->path.'.operator','unsupported_operator');
            if (!array_key_exists('value',$filter))$this->invalid($this->path.'.value','required');
            if ($filter['scope']==='aggregate') {
                if (!is_string($filter['metric']??null) || !isset(self::METRICS[$filter['metric']]) || !(is_int($filter['value'])||is_float($filter['value'])) || !is_finite((float)$filter['value'])) $this->invalid();
            } else {
                $field=$filter['field']??'';
                if (!is_string($field)||!preg_match('/^([a-z_][a-z0-9_]*)\.([a-z_][a-z0-9_]*)$/D',$field,$m) || !isset($catalog[$m[1]]['columns'][$m[2]]) || $schema->isColumnForbidden($m[2]) || $schema->isTableForbidden($m[1])) $this->invalid($this->path.'.field','allowed_physical_column_required');
                if (!(is_string($filter['value']) || is_int($filter['value']) || is_float($filter['value'])) || (is_string($filter['value']) && mb_strlen($filter['value'])>200) || (is_float($filter['value'])&&!is_finite($filter['value']))) $this->invalid();
            }
        }
        return $filters;
    }

    private function validateLookup(array $input,array $parameters): void
    {
        $catalog=(new PosSchemaService())->lookupCatalog();$table=$parameters['table']??null;
        if(!is_string($table)||!isset($catalog[$table]))$this->invalid('plan.parameters.table','lookup_table_required');
        if($input['domain']!==$catalog[$table]['domain'])$this->invalid('plan.domain','table_domain_mismatch');
        if(!in_array($parameters['lookup_mode']??null,['presence','records'],true))$this->invalid('plan.parameters.lookup_mode','lookup_mode_required');
        if($input['period']!==null||$input['metric']!==null||$input['time_grain']!==null||$input['ranking']!==null||$input['dimensions']!==[])$this->invalid('plan','lookup_is_not_aggregate_analysis');
        if(!in_array($input['output_preference'],['text','auto','table'],true))$this->invalid('plan.output_preference','lookup_has_no_chart');
        foreach($parameters as $key=>$value)if(!in_array($key,['table','lookup_mode','columns','limit'],true))$this->invalid('plan.parameters.'.$key,'parameter_not_supported_for_lookup');
        $columns=$parameters['columns']??[];$schema=new PosSchemaService();
        if(!is_array($columns)||!array_is_list($columns)||count($columns)>12||($parameters['lookup_mode']==='records'&&$columns===[]))$this->invalid('plan.parameters.columns','explicit_columns_required');
        foreach($columns as $column)if(!is_string($column)||!isset($catalog[$table]['columns'][$column])||$schema->isColumnForbidden($column))$this->invalid('plan.parameters.columns','allowed_column_required');
        foreach($input['filters'] as $filter){
            if($filter['scope']!=='row'||!str_starts_with($filter['field'],$table.'.'))$this->invalid('plan.filters','lookup_requires_same_table_row_filters');
            if(in_array($filter['operator'],['contains','starts_with','ends_with'],true)&&(!is_string($filter['value'])||trim($filter['value'])===''))$this->invalid('plan.filters','nonempty_text_required');
        }
    }

    private function comparison(mixed $comparison): void
    {
        if (!is_array($comparison)) $this->invalid();
        $this->keys($comparison,['baseline','target','cutoff_day','include_today','metric','group_by','alignment','partial_today','date_start','date_end','coverage']);
        foreach (['baseline','target'] as $side) {
            if (!isset($comparison[$side]) || !is_array($comparison[$side])) $this->invalid();
            $this->keys($comparison[$side],['year','month','start','end']);
            $this->period(array_intersect_key($comparison[$side],array_flip(['year','month'])),'month_comparison');
            if (empty($comparison[$side]['month'])) $this->invalid();
        }
        if (isset($comparison['cutoff_day']) && (!is_int($comparison['cutoff_day'])||$comparison['cutoff_day']<1||$comparison['cutoff_day']>31)) $this->invalid();
        if (isset($comparison['include_today']) && !is_bool($comparison['include_today'])) $this->invalid();
    }

    private function keys(array $input,array $allowed): void { $unknown=array_diff(array_keys($input),$allowed);if ($unknown!==[]) $this->invalid($this->path.'.'.(string)reset($unknown),'unknown_field'); }
    private function invalid(?string $path=null,string $rule='invalid_value'): never { throw new AnalysisException('provider_invalid_plan','AI belum menghasilkan rencana bisnis yang lengkap dan dapat diperiksa. Tidak ada query baru yang dijalankan; laporan sebelumnya tetap tersedia.',[],[],AiPlanDiagnostics::failure($this->input,$path??$this->path,$rule)); }
}
