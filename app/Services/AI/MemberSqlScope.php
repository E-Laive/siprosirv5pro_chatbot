<?php
namespace App\Services\AI;

/** Applies a local identity placeholder and proves it restricts every sales branch. */
final class MemberSqlScope
{
    public function bind(string $sql,string $memberId):string
    {
        $tokens=(new SqlStructure())->tokens($sql);
        foreach($tokens as &$token)if($token['type']==='string'&&$token['raw']==="'__selected_member__'")$token['raw']=(new SqlPlanContract())->expand('?',[$memberId]);
        unset($token);
        return implode(' ',array_column($tokens,'raw'));
    }
    public function assertMatches(string $sql,string $memberId):void
    {
        $structure=(new SqlStructure())->inspect($sql);$predicate=new SqlPredicate();$found=false;
        foreach($structure['scopes'] as $scope){
            foreach($scope['sources'] as $source){
                if($source['table']!=='tbltransaksipenjualan')continue;
                $found=true;$literal=(new SqlPlanContract())->expand('?',[$memberId]);
                $expected=$predicate->text((new SqlStructure())->tokens(strtoupper($source['alias']).'.id_member='.$literal));
                foreach($predicate->branches($predicate->tree($predicate->clause($scope['tokens'],'WHERE'))) as $branch){
                    $proven=false;foreach($branch as $atom)if($predicate->text($atom)===$expected)$proven=true;
                    if(!$proven)$this->reject();
                }
            }
        }
        if(!$found)$this->reject();
    }
    private function reject():never{throw new AnalysisException('business_member_mismatch','Query belum membatasi seluruh transaksi pada member yang dipilih. Tidak ada angka baru yang dihitung; laporan sebelumnya tetap tersimpan.');}
}
