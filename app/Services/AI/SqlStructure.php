<?php
namespace App\Services\AI;

/** Deliberately restricted SELECT grammar; unsupported table syntax fails closed. */
final class SqlStructure
{
    public const ALLOWED_FUNCTIONS_AND_GROUPS = ['COUNT','SUM','AVG','MIN','MAX','COALESCE','IFNULL','NULLIF','YEAR','MONTH','DATE','DATE_FORMAT','UPPER','LOWER','ROUND','ABS','CONCAT','CONCAT_WS','CAST','EXTRACT','TRIM','SUBSTRING','POSITION','DAY','DATEDIFF','DATE_ADD','DATE_SUB','CURDATE','CURRENT_DATE','NOW','CEIL','FLOOR','GREATEST','LEAST','IF','IN','NOT','EXISTS','AS','USING','AND','OR','WHERE','ON','HAVING','BY','SELECT','DISTINCT','WHEN','THEN','ELSE'];
    public array $tables = [];
    public array $scopes = [];

    public function inspect(string $sql): array
    {
        $this->tables = []; $this->scopes = [];
        $tokens = $this->tokens($sql);
        $this->query($tokens, []);
        return ['sql' => $this->render($tokens), 'clean' => implode(' ', array_column($tokens, 'value')), 'tables' => array_values(array_unique($this->tables)), 'scopes' => $this->scopes];
    }

    /** Lex the whole statement even when structural inspection stops at an unsupported clause. */
    public function tokens(string $sql): array
    {
        if (strlen($sql) > 20000) throw new \InvalidArgumentException('Query SQL terlalu panjang.');
        $tokens = [];
        $length = strlen($sql);
        for ($i = 0; $i < $length;) {
            $remaining = substr($sql, $i);
            if (preg_match('/^\s+/', $remaining, $m)) { $i += strlen($m[0]); continue; }
            if (str_starts_with($remaining, '--') || $remaining[0] === '#') {
                $end = strpos($sql, "\n", $i); $i = $end === false ? $length : $end + 1; continue;
            }
            if (str_starts_with($remaining, '/*')) {
                if (preg_match('/^\/\*(?:!|M!|\+)/i', $remaining)) throw new \InvalidArgumentException('Komentar eksekusi/hint SQL dilarang.');
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) throw new \InvalidArgumentException('Komentar SQL tidak ditutup.');
                $i = $end + 2; continue;
            }
            $ch = $sql[$i];
            if ($ch === '"' || $ch === '\\' || $ch === '@' || $ch === ';') throw new \InvalidArgumentException('Sintaks SQL ambigu, variabel, atau multi-statement dilarang.');
            if ($ch === "'" || $ch === '`') {
                $quote = $ch; $start = $i++; $value = ''; $closed = false;
                while ($i < $length) {
                    if ($sql[$i] === '\\') throw new \InvalidArgumentException('Gunakan kutip SQL ganda untuk escape, bukan backslash.');
                    if ($sql[$i] === $quote) {
                        if ($i + 1 < $length && $sql[$i + 1] === $quote) { $value .= $quote; $i += 2; continue; }
                        $i++; $closed = true; break;
                    }
                    $value .= $sql[$i++];
                }
                if (!$closed) throw new \InvalidArgumentException('Kutip SQL tidak ditutup.');
                if ($quote === '`' && !preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D', $value)) throw new \InvalidArgumentException('Identifier SQL tidak didukung.');
                $tokens[] = ['value' => $quote === '`' ? strtoupper($value) : "''", 'raw' => substr($sql, $start, $i - $start), 'type' => $quote === '`' ? 'identifier' : 'string'];
                continue;
            }
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*|^\d+(?:\.\d+)?/', $remaining, $m)) {
                $tokens[] = ['value' => strtoupper($m[0]), 'raw' => $m[0], 'type' => ctype_digit($m[0][0]) ? 'number' : 'word'];
                $i += strlen($m[0]); continue;
            }
            if (preg_match('/^(?:<=>|>=|<=|<>|!=|\|\||&&)/', $remaining, $operator)) {
                $tokens[] = ['value' => $operator[0], 'raw' => $operator[0], 'type' => 'symbol'];
                $i += strlen($operator[0]); continue;
            }
            if (!str_contains('(),.*+-/%=<>!?|&', $ch)) throw new \InvalidArgumentException('Karakter SQL tidak didukung.');
            $tokens[] = ['value' => $ch, 'raw' => $ch, 'type' => 'symbol']; $i++;
        }
        return $tokens;
    }

    private function render(array $tokens): string
    {
        $sql = ''; $previous = null;
        foreach ($tokens as $token) {
            // MariaDB requires builtin function names to touch their opening parenthesis.
            $separator = $token['value'] === '(' && in_array($previous['type'] ?? '', ['word', 'identifier'], true) ? '' : ' ';
            $sql .= ($sql === '' ? '' : $separator) . $token['raw'];
            $previous = $token;
        }
        return $sql;
    }

    private function groupEnd(array $tokens, int $start): int
    {
        $depth = 0;
        for ($i = $start; $i < count($tokens); $i++) {
            if ($tokens[$i]['value'] === '(' && ++$depth > 32) throw new \InvalidArgumentException('Kedalaman SQL melampaui batas.');
            if ($tokens[$i]['value'] === ')' && --$depth === 0) return $i;
        }
        throw new \InvalidArgumentException('Kurung SQL tidak seimbang.');
    }

    private function query(array $tokens, array $ctes): int
    {
        if (($tokens[0]['value'] ?? '') === 'WITH') {
            $i = 1;
            do {
                $name = $tokens[$i]['value'] ?? '';
                if ($name === 'RECURSIVE') throw new UnsupportedSqlException('unsupported_recursive_cte', 'CTE rekursif belum didukung.', 'Gunakan SELECT agregasi biasa tanpa WITH RECURSIVE. Jangan membuat generator kalender SQL; tanggal kosong ditangani aplikasi pada template harian.');
                if (!in_array($tokens[$i]['type'] ?? '', ['word', 'identifier'], true) || isset($ctes[$name])) throw new \InvalidArgumentException('CTE tidak didukung.');
                if (($tokens[$i + 1]['value'] ?? '') !== 'AS' || ($tokens[$i + 2]['value'] ?? '') !== '(') throw new \InvalidArgumentException('CTE harus memakai AS (SELECT ...).');
                $end = $this->groupEnd($tokens, $i + 2);
                $ctes[$name] = $this->query(array_slice($tokens, $i + 3, $end - $i - 3), $ctes);
                $i = $end + 1;
                if (($tokens[$i]['value'] ?? '') !== ',') break;
                $i++;
            } while (true);
            $tokens = array_slice($tokens, $i);
        }
        if (($tokens[0]['value'] ?? '') !== 'SELECT') throw new \InvalidArgumentException('Hanya SELECT atau WITH SELECT diizinkan.');
        foreach ($tokens as $token) if ($token['type'] === 'word' && in_array($token['value'], ['UNION','INTERSECT','EXCEPT'], true)) throw new UnsupportedSqlException('unsupported_set_operation', 'Operasi penggabungan himpunan SQL belum didukung.', 'Susun satu SELECT dengan CASE WHEN atau subquery/CTE nonrekursif, tanpa UNION/INTERSECT/EXCEPT.');
        $flat = $this->groups($tokens, $ctes);
        $from = null;
        foreach ($flat as $i => $token) {
            if ($token['type'] === 'word' && in_array($token['value'], ['ALL', 'DISTINCTROW', 'SQL_CALC_FOUND_ROWS', 'SQL_NO_CACHE', 'SQL_CACHE'], true)) throw new \InvalidArgumentException('Modifier SELECT belum didukung.');
            if ($token['type'] === 'word' && in_array($token['value'], ['UNION', 'INTERSECT', 'EXCEPT'], true)) throw new UnsupportedSqlException('unsupported_set_operation', 'Operasi penggabungan himpunan SQL belum didukung.', 'Susun satu SELECT dengan agregasi bersyarat CASE WHEN atau subquery/CTE nonrekursif pada tabel yang sama, tanpa UNION/INTERSECT/EXCEPT.');
            if ($token['type'] === 'word' && in_array($token['value'], ['PROCEDURE', 'INTO', 'FOR', 'WINDOW', 'QUALIFY'], true)) throw new \InvalidArgumentException('Klausa SQL belum didukung.');
            if ($token['value'] === 'FROM') { $from = $i; break; }
            if ($token['value'] === '*' && (in_array($flat[$i - 1]['value'] ?? '', ['SELECT', 'DISTINCT', ',','.'], true))) throw new \InvalidArgumentException('SELECT wildcard dilarang.');
        }
        if ($from === null) throw new UnsupportedSqlException('unsupported_select_without_table', 'SELECT harus merujuk tabel bisnis.', 'Gunakan tabel bisnis yang diizinkan sebagai sumber SELECT; jangan membuat tabel/deret konstanta di SQL.');
        $direct = []; $sources = []; $relations = []; $i = $from + 1;
        $this->source($flat, $i, $ctes, $direct, $sources, $relations);
        while ($i < count($flat)) {
            $v = $flat[$i]['value'];
            if ($v === ',') throw new \InvalidArgumentException('FROM dengan koma dilarang; gunakan JOIN eksplisit.');
            if (in_array($v, ['WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT'], true)) break;
            if (in_array($v, ['LEFT','RIGHT','INNER'], true)) {
                $i++; if (($flat[$i]['value'] ?? '') === 'OUTER') $i++;
            }
            if (($flat[$i]['value'] ?? '') !== 'JOIN') throw new \InvalidArgumentException('Sintaks tabel/JOIN tidak didukung.');
            $i++; $this->source($flat, $i, $ctes, $direct, $sources, $relations);
            if (!in_array($flat[$i]['value'] ?? '', ['ON','USING'], true)) throw new \InvalidArgumentException('JOIN wajib memiliki ON atau USING.');
            $i++; $start = $i;
            while ($i < count($flat) && !in_array($flat[$i]['value'], [',','JOIN','LEFT','RIGHT','INNER','WHERE','GROUP','HAVING','ORDER','LIMIT'], true)) $i++;
            if ($i === $start) throw new \InvalidArgumentException('Kondisi JOIN kosong.');
        }
        // No trailing unsupported clauses, even after WHERE / ORDER BY.
        foreach ($flat as $token) {
            if ($token['type'] === 'word' && in_array($token['value'], ['UNION','INTERSECT','EXCEPT'], true)) throw new UnsupportedSqlException('unsupported_set_operation', 'Operasi penggabungan himpunan SQL belum didukung.', 'Susun satu SELECT dengan CASE WHEN atau subquery/CTE nonrekursif, tanpa UNION/INTERSECT/EXCEPT.');
            if ($token['type'] === 'word' && in_array($token['value'], ['PROCEDURE','INTO','FOR','CROSS','NATURAL','STRAIGHT_JOIN','TABLE','VALUES'], true)) throw new \InvalidArgumentException('Klausa SQL tidak didukung.');
        }
        $this->scopes[] = ['tables' => $direct, 'sources' => $sources, 'relations' => $relations, 'tokens' => $tokens, 'sums_header' => $this->sumsHeader($tokens)];
        return count($this->scopes) - 1;
    }

    private function sumsHeader(array $tokens): bool
    {
        foreach ($tokens as $i => $token) {
            if ($token['value'] !== 'SUM' || ($tokens[$i + 1]['value'] ?? '') !== '(') continue;
            $end = $this->groupEnd($tokens, $i + 1);
            foreach (array_slice($tokens, $i + 2, $end - $i - 2) as $argument) {
                if (in_array($argument['type'], ['word', 'identifier'], true) && in_array($argument['value'], ['GRAND_TOTAL', 'TOTAL_KOTOR', 'POTONGAN', 'PPN', 'BAYAR'], true)) return true;
            }
        }
        return false;
    }

    private function source(array $flat, int &$i, array $ctes, array &$direct, array &$sources, array &$relations): void
    {
        $table = null;
        $token = $flat[$i] ?? [];
        $child = null;
        if (!empty($token['query'])) { $child = $token['query_scope']; $i++; }
        else {
            if (!in_array($token['type'] ?? '', ['word','identifier'], true)) throw new \InvalidArgumentException('Referensi tabel tidak didukung.');
            $name = strtolower($token['value']); $i++;
            if (($flat[$i]['value'] ?? '') === '.' || ($flat[$i]['value'] ?? '') === '()') throw new \InvalidArgumentException('Schema/function tabel dilarang.');
            if (!isset($ctes[strtoupper($name)])) { $this->tables[] = $name; $direct[] = $name; $table = $name; }
            else $child = $ctes[strtoupper($name)];
        }
        $alias = $table ?? ($name ?? null);
        if (($flat[$i]['value'] ?? '') === 'AS') {
            $i++; if (!in_array($flat[$i]['type'] ?? '', ['word','identifier'], true)) throw new \InvalidArgumentException('Alias SQL tidak valid.'); $alias = strtolower($flat[$i]['value']); $i++;
        } elseif (in_array($flat[$i]['type'] ?? '', ['word','identifier'], true) && !in_array($flat[$i]['value'], ['JOIN','LEFT','RIGHT','INNER','WHERE','GROUP','HAVING','ORDER','LIMIT','ON','USING','CROSS','NATURAL','STRAIGHT_JOIN'], true)) { $alias = strtolower($flat[$i]['value']); $i++; }
        if ($table !== null) $sources[] = ['table' => $table, 'alias' => $alias];
        $relations[] = ['table' => $table, 'alias' => $alias, 'scope' => $child];
    }

    private function groups(array $tokens, array $ctes): array
    {
        $flat = [];
        for ($i = 0; $i < count($tokens); $i++) {
            if ($tokens[$i]['value'] === ')') throw new \InvalidArgumentException('Kurung SQL tidak seimbang.');
            if ($tokens[$i]['value'] !== '(') { $flat[] = $tokens[$i]; continue; }
            $end = $this->groupEnd($tokens, $i);
            $inside = array_slice($tokens, $i + 1, $end - $i - 1);
            $query = in_array($inside[0]['value'] ?? '', ['SELECT','WITH'], true);
            $queryScope = null;
            if ($query) $queryScope = $this->query($inside, $ctes);
            else {
                foreach ($inside as $token) if ($token['type'] === 'word' && in_array($token['value'], ['TABLE', 'VALUES'], true)) throw new \InvalidArgumentException('Subquery wajib memakai SELECT.');
                $previous = $tokens[$i - 1] ?? [];
                if (($tokens[$i - 2]['value'] ?? '') === '.') throw new \InvalidArgumentException('Fungsi lintas schema dilarang.');
                if (in_array($previous['type'] ?? '', ['word','identifier'], true) && !in_array($previous['value'], self::ALLOWED_FUNCTIONS_AND_GROUPS, true)) throw new \InvalidArgumentException('Fungsi SQL tidak diizinkan.');
                $this->groups($inside, $ctes);
                foreach ($inside as $j => $t) if ($t['value'] === '*' && ($tokens[$i - 1]['value'] ?? '') !== 'COUNT' && in_array($inside[$j - 1]['value'] ?? '(', ['(', ',', '.'], true)) throw new \InvalidArgumentException('Wildcard hanya diperbolehkan pada COUNT(*).');
            }
            $flat[] = ['value' => '()', 'raw' => '()', 'type' => 'group', 'query' => $query, 'query_scope' => $queryScope]; $i = $end;
        }
        return $flat;
    }
}
