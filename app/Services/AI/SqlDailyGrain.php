<?php
namespace App\Services\AI;

/** Proves a complete date in the result scope, following projections through supported CTEs/subqueries. */
final class SqlDailyGrain
{
    public function matches(array $scopes): bool
    {
        $known = [];
        $catalog = (new PosSchemaService())->catalog();
        foreach ($scopes as $id => $scope) {
            $qualified = []; $bare = []; $inherited = false;
            $relations = $scope['relations'] ?? [];
            foreach ($relations as $relation) {
                if ($relation['table'] !== null) {
                    $cols = array_fill_keys(array_map('strtoupper', array_keys($catalog[$relation['table']]['columns'] ?? [])), 'unknown');
                    if ($relation['table'] === 'tbltransaksipenjualan') $cols['TANGGAL'] = 'timestamp';
                } else $cols = $known[$relation['scope']]['columns'] ?? [];
                foreach ($cols as $name => $type) {
                    $qualified[strtoupper((string) $relation['alias']) . '.' . $name] = $type;
                    $bare[$name][] = $type;
                }
                if (count($relations) === 1 && !empty($known[$relation['scope']]['daily'])) $inherited = true;
            }
            $p = new SqlPredicate();
            $projection = []; $depth = 0;
            foreach (array_slice($scope['tokens'], 1) as $t) {
                if ($depth === 0 && $t['value'] === 'FROM') break;
                if ($t['value'] === '(') $depth++;
                if ($t['value'] === ')') $depth--;
                $projection[] = $t;
            }
            $expressions = $this->split($projection);
            $aliases = []; $types = []; $aggregate = false;
            foreach ($expressions as $expression) {
                $alias = null; $depth = 0;
                foreach ($expression as $i => $t) {
                    if ($depth === 0 && $t['value'] === 'AS') { $alias = $expression[$i + 1]['value'] ?? null; $expression = array_slice($expression, 0, $i); break; }
                    if ($t['value'] === '(') $depth++;
                    if ($t['value'] === ')') $depth--;
                }
                if ($alias === null && count($expression) > 1 && in_array(end($expression)['type'], ['word','identifier'], true) && ($expression[count($expression) - 2]['value'] ?? '') !== '.') $alias = array_pop($expression)['value'];
                $text = $p->text($expression);
                $type = $this->type($text, $qualified, $bare, count($relations));
                $types[] = $type;
                if ($alias === null && preg_match('/^(?:\w+\.)?(\w+)$/D', $text, $m)) $alias = $m[1];
                if ($alias !== null && $type !== null) $aliases[$alias] = $type;
                if (preg_match('/\b(?:SUM|AVG|COUNT|MIN|MAX)\(/', $text)) $aggregate = true;
            }
            $group = $p->clause($scope['tokens'], 'GROUP');
            if (($group[0]['value'] ?? '') === 'BY') array_shift($group);
            $groupDate = false;
            foreach ($this->split($group) as $expression) {
                $text = $p->text($expression);
                $type = $this->type($text, $qualified, $bare, count($relations));
                // SQL resolves source columns before SELECT aliases. Never let an alias mask a timestamp.
                if ($type === null && !isset($bare[$text])) $type = $aliases[$text] ?? null;
                if (ctype_digit($text)) $type = $types[(int) $text - 1] ?? null;
                if ($type === 'date') $groupDate = true;
            }
            $known[$id] = ['columns' => $aliases, 'daily' => in_array('date', $types, true) && ($groupDate || ($group === [] && !$aggregate && $inherited))];
        }
        return !empty($known[array_key_last($known)]['daily']);
    }

    private function type(string $expression, array $qualified, array $bare, int $sources): ?string
    {
        if (isset($qualified[$expression])) return $qualified[$expression];
        if ($sources === 1 && count($bare[$expression] ?? []) === 1) return $bare[$expression][0];
        $reference = null;
        if (preg_match('/^DATE\(([^()]+)\)$/D', $expression, $m)) $reference = $m[1];
        elseif (preg_match("/^DATE_FORMAT\(([^(),]+),'%Y-%m-%d'\)$/D", $expression, $m)) $reference = $m[1];
        if ($reference !== null && in_array($this->type($reference, $qualified, $bare, $sources), ['timestamp','date'], true)) return 'date';
        return null;
    }

    private function split(array $tokens): array
    {
        if ($tokens === []) return [];
        $parts = []; $part = []; $depth = 0;
        foreach ($tokens as $t) {
            if ($t['value'] === ',' && $depth === 0) { $parts[] = $part; $part = []; continue; }
            if ($t['value'] === '(') $depth++;
            if ($t['value'] === ')') $depth--;
            $part[] = $t;
        }
        $parts[] = $part;
        return $parts;
    }
}
