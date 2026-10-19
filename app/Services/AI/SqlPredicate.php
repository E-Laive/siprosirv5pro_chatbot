<?php
namespace App\Services\AI;

/** Bounded boolean decomposition. Unknown atoms never prove a required business filter. */
final class SqlPredicate
{
    public function clause(array $tokens, string $name): array
    {
        $depth = 0; $start = null;
        foreach ($tokens as $i => $t) {
            $v = $t['value'];
            if ($depth === 0 && $v === $name && $start === null) { $start = $i + 1; continue; }
            if ($start !== null && $depth === 0 && in_array($v, ['GROUP','HAVING','ORDER','LIMIT','UNION','INTERSECT','EXCEPT'], true)) return array_slice($tokens, $start, $i - $start);
            if ($v === '(') $depth++;
            if ($v === ')') $depth--;
        }
        return $start === null ? [] : array_slice($tokens, $start);
    }

    public function tree(array $tokens): array
    {
        foreach ($tokens as $t) if ($t['type'] !== 'string' && in_array($t['value'], ['XOR','||','&&'], true)) throw new AnalysisException('business_filter_mismatch', 'Operator kondisi query belum dapat diverifikasi. Analisis dihentikan tanpa angka baru.');
        while (($tokens[0]['value'] ?? '') === '(' && $this->outerGroup($tokens)) $tokens = array_slice($tokens, 1, -1);
        foreach (['OR','AND'] as $op) {
            $parts = []; $depth = 0; $start = 0; $between = false;
            foreach ($tokens as $i => $t) {
                $v = $t['value'];
                if ($v === '(') $depth++;
                if ($v === ')') $depth--;
                if ($depth !== 0 || $t['type'] === 'string') continue;
                if ($v === 'BETWEEN') $between = true;
                elseif ($v === 'AND' && $between) { $between = false; continue; }
                elseif ($v === $op) { $parts[] = array_slice($tokens, $start, $i - $start); $start = $i + 1; }
            }
            if ($parts !== []) { $parts[] = array_slice($tokens, $start); return ['op' => $op, 'children' => array_map(fn ($p) => $this->tree($p), $parts)]; }
        }
        return ['op' => 'atom', 'tokens' => $tokens];
    }

    public function branches(array $tree): array
    {
        if ($tree['op'] === 'atom') return [[$tree['tokens']]];
        $result = $tree['op'] === 'AND' ? [[]] : [];
        foreach ($tree['children'] as $child) {
            $next = $this->branches($child);
            if ($tree['op'] === 'OR') $result = array_merge($result, $next);
            else { $product = []; foreach ($result as $left) foreach ($next as $right) $product[] = array_merge($left, $right); $result = $product; }
            if (count($result) > 32) throw new AnalysisException('business_filter_mismatch', 'Kondisi query terlalu kompleks untuk diverifikasi. Sederhanakan cakupan analisis.');
        }
        return $result;
    }

    public function canonical(array $tree): string
    {
        if ($tree['op'] === 'atom') return $this->text($tree['tokens']);
        $children = array_map(fn ($c) => $this->canonical($c), $tree['children']);
        $children = array_values(array_unique($children)); sort($children);
        return $tree['op'] . '(' . implode('|', $children) . ')';
    }

    public function text(array $tokens): string
    {
        return implode('', array_map(static fn ($t) => $t['type'] === 'string' ? $t['raw'] : $t['value'], $tokens));
    }

    private function outerGroup(array $tokens): bool
    {
        $depth = 0;
        foreach ($tokens as $i => $t) { if ($t['value'] === '(') $depth++; if ($t['value'] === ')') $depth--; if ($depth === 0) return $i === count($tokens) - 1; }
        return false;
    }
}
