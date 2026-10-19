<?php
namespace App\Services\AI;

/** Loss-detection guard only: never chooses an analysis, SQL or an answer without the online planner. */
final class BusinessRequestGuard
{
    public function assertRoute(string $question,array $decision): void
    {
        $explanation=preg_match('/\b(maksud|artinya|arti|jelaskan|apa itu|data kapan|data dari mana)\b/iu',$question);
        if(!$explanation&&preg_match('/\b(kas|bank)\b/iu',$question)&&preg_match('/\b(pemasukan|pengeluaran|masuk|keluar|arus|mutasi)\b/iu',$question)){
            if(($decision['decision']??null)==='context'||($decision['plan']['intent']??null)==='cash_bank_summary')
                throw new AnalysisException('provider_route_mismatch','Permintaan arus kas belum boleh dijawab dengan posisi saldo atau laporan lama. Tidak ada angka baru yang dibuat.',[],[],AiPlanDiagnostics::failure($decision['plan']['semantic_plan']??null,'decision.plan.intent','cash_movements_are_not_current_balance'));
        }
        // Stable business nouns are negative assertions, not an expanding language-to-template parser.
        $return=preg_match('/\bretur\b/iu',$question)&&!preg_match('/\bretur\s+pembelian\b/iu',$question);
        if(preg_match('/\b(bersih|setelah|dikurangi|sebelum|laba|margin)\b/iu',$question))return;
        $lookup=preg_match('/\b(apakah|adakah|tersedia|berapa|laporan|daftar|siapa)\b/iu',$question);
        if(!$return||!$lookup||$explanation)return;
        $kind=$decision['decision'];
        if($kind==='clarify')return;
        if($kind==='reformat'&&!preg_match('/\b(apakah|adakah|tersedia|berapa|siapa)\b/iu',$question))return;
        $plan=$decision['plan']??null;
        if($kind!=='analysis'||($plan['entity']??null)!=='sale_return') {
            throw new AnalysisException('provider_route_mismatch','Rencana AI belum mempertahankan objek retur yang diminta. Tidak ada pemeriksaan penjualan pengganti atau angka baru yang dibuat; laporan sebelumnya tetap tersimpan.',[],[],AiPlanDiagnostics::failure($plan['semantic_plan']??null,'decision.plan.entity','sales_return_required_for_return_lookup'));
        }
    }
}
