<?php

namespace App\Services\AI;

use Config\AiAnalyst;

/**
 * SQL Safety Engine (Tokenizer & AST/Structural Validator untuk MariaDB).
 *
 * Menjamin bahwa setiap query yang dieksekusi ke database POS:
 * 1. Hanya berupa 1 statement SELECT atau WITH ... SELECT (read-only murni).
 * 2. Bebas dari operasi DDL/DML, fungsi sistem/file/locking berbahaya, dan akses lintas schema.
 * 3. Hanya mengakses tabel dalam allowlist sesuai role pengguna.
 * 4. Tidak pernah memilih atau memfilter kolom sensitif (password, token_sync, payload_json, dll.).
 * 5. Mencegah jebakan duplikasi agregasi (fan-out JOIN antara header dan detail penjualan).
 * 6. Memiliki batas LIMIT baris yang aman.
 */
final class SqlSafetyValidator
{
    private AiAnalyst $config;
    private PosSchemaService $schemaService;

    /** @var list<string> */
    private array $forbiddenKeywords = [
        'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'TRUNCATE', 'CREATE', 'REPLACE',
        'RENAME', 'GRANT', 'REVOKE', 'LOCK', 'UNLOCK', 'CALL', 'EXECUTE', 'PREPARE',
        'DEALLOCATE', 'SET', 'USE', 'SHOW', 'DESCRIBE', 'EXPLAIN', 'HANDLER', 'LOAD',
        'OUTFILE', 'DUMPFILE', 'SHUTDOWN', 'KILL', 'FLUSH', 'RESET', 'PURGE', 'INSTALL',
        'UNINSTALL', 'ANALYZE', 'CHECK', 'CHECKSUM', 'OPTIMIZE', 'REPAIR', 'BINLOG',
    ];

    /** @var list<string> */
    private array $forbiddenFunctions = [
        'SLEEP', 'BENCHMARK', 'GET_LOCK', 'RELEASE_LOCK', 'RELEASE_ALL_LOCKS',
        'IS_FREE_LOCK', 'IS_USED_LOCK', 'LOAD_FILE', 'SYSTEM_USER', 'SESSION_USER',
        'CURRENT_USER', 'USER', 'DATABASE', 'SCHEMA', 'VERSION', 'ROW_COUNT',
        'LAST_INSERT_ID', 'MASTER_POS_WAIT', 'NAME_CONST', ' extractvalue', 'updatexml',
    ];

    /** @var list<string> */
    private array $systemSchemas = [
        'information_schema', 'mysql', 'performance_schema', 'sys', 'dbkerangka',
    ];

    public function __construct(?PosSchemaService $schemaService = null, ?AiAnalyst $config = null)
    {
        $this->config = $config ?? config(AiAnalyst::class);
        $this->schemaService = $schemaService ?? new PosSchemaService($this->config);
    }

    /**
     * Memvalidasi query SQL dan mengembalikan struktur hasil validasi beserta query yang telah diamankan (dengan LIMIT).
     *
     * @return array{valid: bool, sql: string, error: ?string, tables: list<string>, warnings: list<string>}
     */
    public function validate(string $rawSql, ?string $role = null): array
    {
        $sql = trim($rawSql);
        if ($sql === '') {
            return ['valid' => false, 'sql' => '', 'error' => 'Query SQL kosong.', 'tables' => [], 'warnings' => []];
        }

        // Bersihkan trailing semicolon tunggal di akhir query jika ada, lalu cek apakah masih ada semicolon lain
        $sql = rtrim($sql);
        if (str_ends_with($sql, ';')) {
            $sql = rtrim(substr($sql, 0, -1));
        }

        try {
            $structure = (new SqlStructure())->inspect($sql);
            $sql = $structure['sql'];
        } catch (UnsupportedSqlException $e) {
            $tables = $this->repairableTables($sql, $role);
            return ['valid' => false, 'sql' => $sql, 'error' => $e->getMessage(), 'tables' => $tables ?? [], 'warnings' => [],
                'error_code' => $tables !== null ? $e->reasonCode : 'sql_security_rejected', 'retryable' => $tables !== null,
                'repair_hint' => $tables !== null ? $e->repairHint : null];
        } catch (\InvalidArgumentException $e) {
            return ['valid' => false, 'sql' => $sql, 'error' => $e->getMessage(), 'tables' => [], 'warnings' => []];
        }

        // 1. Blok komentar injeksi berbahaya (/*! ... */ executable MySQL comments)
        if (preg_match('/\/\*![0-9]*/', $sql)) {
            return ['valid' => false, 'sql' => $sql, 'error' => 'Komentar eksekusi bersyarat MySQL tidak diizinkan.', 'tables' => [], 'warnings' => []];
        }

        // 2. Tokenisasi leksikal untuk memisahkan string literal, identifier, komentar, dan kode SQL aktif
        $tokenized = $this->tokenize($sql);
        if (!$tokenized['valid']) {
            return ['valid' => false, 'sql' => $sql, 'error' => $tokenized['error'], 'tables' => [], 'warnings' => []];
        }

        $cleanSql = $tokenized['clean_sql'];
        $upperWords = $tokenized['words'];

        if ($upperWords === []) {
            return ['valid' => false, 'sql' => $sql, 'error' => 'Query tidak memiliki instruksi SQL yang valid.', 'tables' => [], 'warnings' => []];
        }

        // 3. Wajib diawali oleh SELECT atau WITH
        $firstWord = $upperWords[0];
        if (!in_array($firstWord, ['SELECT', 'WITH'], true)) {
            return [
                'valid' => false,
                'sql' => $sql,
                'error' => "Operasi `{$firstWord}` ditolak. Koneksi POS hanya mengizinkan query pembacaan (`SELECT`).",
                'tables' => [],
                'warnings' => [],
            ];
        }

        if ($firstWord === 'WITH' && !in_array('SELECT', $upperWords, true)) {
            return ['valid' => false, 'sql' => $sql, 'error' => 'CTE (`WITH`) wajib diikuti oleh pernyataan `SELECT`.', 'tables' => [], 'warnings' => []];
        }

        // 4. Cek keyword DDL/DML/administratif terlarang pada token kata
        foreach ($upperWords as $word) {
            if (in_array($word, $this->forbiddenKeywords, true)) {
                return [
                    'valid' => false,
                    'sql' => $sql,
                    'error' => "Kata kunci SQL `{$word}` dilarang pada koneksi analitik read-only.",
                    'tables' => [],
                    'warnings' => [],
                ];
            }
        }

        // 5. Cek variabel sistem (@@version, @var) dan fungsi berbahaya
        if (preg_match('/@@?[a-zA-Z0-9_]+/', $cleanSql)) {
            return [
                'valid' => false,
                'sql' => $sql,
                'error' => 'Akses ke variabel sesi atau variabel sistem (`@` / `@@`) tidak diizinkan.',
                'tables' => [],
                'warnings' => [],
            ];
        }

        foreach ($this->forbiddenFunctions as $fn) {
            $fnTrimmed = trim($fn);
            if (preg_match('/\b' . preg_quote($fnTrimmed, '/') . '\s*\(/i', $cleanSql)) {
                return [
                    'valid' => false,
                    'sql' => $sql,
                    'error' => "Pemanggilan fungsi `{$fnTrimmed}()` diblokir oleh kebijakan keamanan SQL.",
                    'tables' => [],
                    'warnings' => [],
                ];
            }
        }

        // 6. Cek SELECT ... INTO (INTO OUTFILE / INTO DUMPFILE / INTO @var)
        if (preg_match('/\bINTO\s+(OUTFILE|DUMPFILE|@|[a-zA-Z_])/i', $cleanSql)) {
            return [
                'valid' => false,
                'sql' => $sql,
                'error' => 'Klausa `SELECT ... INTO` tidak diizinkan.',
                'tables' => [],
                'warnings' => [],
            ];
        }

        // 7. Cek locking reads (FOR UPDATE / LOCK IN SHARE MODE)
        if (preg_match('/\b(FOR\s+UPDATE|LOCK\s+IN\s+SHARE\s+MODE)\b/i', $cleanSql)) {
            return [
                'valid' => false,
                'sql' => $sql,
                'error' => 'Locking read (`FOR UPDATE` / `LOCK IN SHARE MODE`) dilarang pada database POS.',
                'tables' => [],
                'warnings' => [],
            ];
        }

        // 8. Ekstrak nama CTE, tabel, dan alias dari FROM / JOIN
        $tableRefs = ['tables' => $structure['tables'], 'cross_schema' => []];

        if ($tableRefs['cross_schema'] !== []) {
            $badSchema = $tableRefs['cross_schema'][0];
            return [
                'valid' => false,
                'sql' => $sql,
                'error' => "Akses lintas schema (`{$badSchema}`) ditolak oleh kebijakan isolasi database.",
                'tables' => [],
                'warnings' => [],
            ];
        }

        $referencedTables = $tableRefs['tables'];
        if ($referencedTables === []) {
            return [
                'valid' => false,
                'sql' => $sql,
                'error' => 'Query harus merujuk minimal satu tabel bisnis POS yang valid.',
                'tables' => [],
                'warnings' => [],
            ];
        }

        // 9. Verifikasi setiap tabel terhadap daftar terlarang dan izin role pengguna
        foreach ($referencedTables as $table) {
            if ($this->schemaService->isTableForbidden($table)) {
                return [
                    'valid' => false,
                    'sql' => $sql,
                    'error' => "Tabel `{$table}` merupakan tabel internal/keamanan sistem dan tidak boleh diakses.",
                    'tables' => $referencedTables,
                    'warnings' => [],
                ];
            }
            if (!$this->schemaService->isTableAllowedForRole($table, $role)) {
                return [
                    'valid' => false,
                    'sql' => $sql,
                    'error' => "Peran akun Anda tidak memiliki izin untuk mengakses tabel `{$table}`.",
                    'tables' => $referencedTables,
                    'warnings' => [],
                ];
            }
        }

        // 10. Cek kolom sensitif/terlarang dan larang SELECT *
        if (preg_match('/\bSELECT\s+(DISTINCT\s+)?\*/i', $cleanSql) || preg_match('/\b[a-zA-Z0-9_`]+\.\*/', $cleanSql)) {
            return [
                'valid' => false,
                'sql' => $sql,
                'error' => 'Penggunaan `SELECT *` tidak diizinkan demi mencegah eksposur kolom sinkronisasi/internal (`token_sync`). Sebutkan kolom yang diperlukan secara eksplisit.',
                'tables' => $referencedTables,
                'warnings' => [],
            ];
        }

        foreach ($tokenized['identifiers'] as $identifier) {
            if ($this->schemaService->isColumnForbidden($identifier)) {
                return [
                    'valid' => false,
                    'sql' => $sql,
                    'error' => "Akses terhadap kolom sensitif/internal `{$identifier}` diblokir.",
                    'tables' => $referencedTables,
                    'warnings' => [],
                ];
            }
        }

        // 12. Cek jebakan duplikasi agregasi (fan-out header + detail penjualan)
        $warnings = [];
        foreach ($structure['scopes'] as $scope) {
        if (in_array('tbltransaksipenjualan', $scope['tables'], true)
            && array_intersect(['tbltransaksipenjualan_detail', 'tblreturpenjualan', 'tbltransaksipenjualan_bank'], $scope['tables']) !== []
            && $scope['sums_header']) {
            return [
                'valid' => false,
                'sql' => $sql,
                'error' => 'Terdeteksi potensi duplikasi agregasi: menghitung `SUM(grand_total)` atau `SUM(total_kotor)` dengan `JOIN` langsung ke `tbltransaksipenjualan_detail` akan melipatgandakan nilai faktur. Gunakan `SUM(d.total_subtotal)` atau pisahkan agregasi dalam CTE/subquery.',
                'tables' => $referencedTables,
                'warnings' => [],
            ];
        }
        }

        // 13. Terapkan batas LIMIT yang aman
        $enforcedSql = $this->enforceLimit($sql);

        return [
            'valid' => true,
            'sql' => $enforcedSql,
            'error' => null,
            'tables' => $referencedTables,
            'warnings' => $warnings,
        ];
    }

    /** Machine-readable capabilities sent before generating SQL, not inferred through failed requests. */
    public function capabilities(): array
    {
        return ['version' => 1, 'read_only' => true, 'single_statement' => true,
            'supported' => ['SELECT with explicit columns', 'COUNT/SUM/AVG/MIN/MAX', 'CASE WHEN', 'GROUP BY/ORDER BY', 'JOIN with ON/USING', 'nonrecursive CTE', 'SELECT subqueries', 'DATE/YEAR/MONTH/DATE_FORMAT'],
            'unsupported' => ['WITH RECURSIVE', 'UNION/UNION ALL/INTERSECT/EXCEPT', 'SELECT without a business table', 'SELECT * or alias.*', 'comma joins', 'cross-schema access', 'DML/DDL', 'session variables', 'unlisted functions'],
            'max_rows' => max(1, min(500, $this->config->maxRowLimit)), 'max_sql_bytes' => 20000,
            'repair' => ['max_attempts' => 1, 'only' => 'known unsupported syntax or approved daily grain correction; never access denial, filters, periods or runtime errors'],
            'alternatives' => ['calendar' => 'Aggregate DATE(tanggal); the application has daily calendar templates.', 'set_operations' => 'Use CASE WHEN or a nonrecursive subquery over the same business tables.']];
    }

    /** Repairs may change syntax, not business tables, filter literals, or aggregate measures. */
    public function isCompatibleRepair(string $original, string $replacement, array $originalTables, array $replacementTables, bool $correctDailyGrain = false): bool
    {
        sort($originalTables); sort($replacementTables);
        if ($originalTables !== $replacementTables) return false;
        $lexer = new SqlStructure();
        $before = $lexer->tokens($original); $after = $lexer->tokens($replacement);
        $uniqueSorted = static function (array $values): array { $values = array_values(array_unique($values)); sort($values); return $values; };
        $strings = static fn ($tokens) => array_column(array_filter($tokens, static fn ($t) => $t['type'] === 'string'), 'raw');
        if ($uniqueSorted($strings($before)) !== $uniqueSorted($strings($after))) return false;
        $columns = [];
        foreach ($this->schemaService->catalog() as $table) foreach (array_keys($table['columns'] ?? []) as $column) $columns[strtoupper($column)] = true;
        $numericFilters = static function ($tokens) use ($columns): array {
            $filters = [];
            foreach ($tokens as $i => $t) if ($t['type'] === 'number' && isset($columns[$tokens[$i - 2]['value'] ?? '']) && in_array($tokens[$i - 1]['value'] ?? '', ['=','>=','<=','>','<','!=','<>'], true)) $filters[] = $tokens[$i - 2]['value'] . $tokens[$i - 1]['value'] . $t['raw'];
            return $filters;
        };
        if ($uniqueSorted($numericFilters($before)) !== $uniqueSorted($numericFilters($after))) return false;
        $grouping = static function ($tokens) use ($uniqueSorted): array {
            $groups = [];
            foreach ($tokens as $i => $t) {
                if ($t['value'] !== 'GROUP' || ($tokens[$i + 1]['value'] ?? '') !== 'BY') continue;
                $parts = []; $depth = 0;
                for ($j = $i + 2; $j < count($tokens); $j++) {
                    $value = $tokens[$j]['value'];
                    if ($depth === 0 && in_array($value, [')','HAVING','ORDER','LIMIT','UNION','INTERSECT','EXCEPT'], true)) break;
                    if ($value === '(') $depth++;
                    if ($value === ')') $depth--;
                    // Table aliases may change; column and grouping expressions must remain.
                    if ($value === '.' || ($tokens[$j + 1]['value'] ?? '') === '.') continue;
                    $parts[] = $tokens[$j]['type'] === 'string' ? $tokens[$j]['raw'] : $value;
                }
                $groups[] = implode(' ', $parts);
            }
            return $uniqueSorted($groups);
        };
        $beforeGroups = $grouping($before); $afterGroups = $grouping($after);
        if ($beforeGroups !== $afterGroups) {
            if (!$correctDailyGrain) return false;
            // Only replace temporal expressions; member/product/etc. dimensions must remain identical.
            $nonTemporal = static function (array $groups): array {
                $remaining = array_map(static fn ($g) => trim(preg_replace('/\s*,\s*/', ',', preg_replace('/\b(?:DATE|DAY|MONTH|YEAR)\s*\(\s*TANGGAL\s*\)/', '', $g)), ' ,'), $groups);
                return array_values(array_filter($remaining, static fn ($g) => $g !== ''));
            };
            if ($nonTemporal($beforeGroups) !== $nonTemporal($afterGroups)) return false;
        }
        $measures = static function ($tokens) use ($columns): array {
            $result = [];
            foreach ($tokens as $i => $t) {
                if (!in_array($t['value'], ['SUM','AVG','COUNT','MIN','MAX'], true) || ($tokens[$i + 1]['value'] ?? '') !== '(') continue;
                $depth = 1; $arguments = [];
                for ($j = $i + 2; $j < count($tokens) && $depth > 0; $j++) {
                    if ($tokens[$j]['value'] === '(') $depth++;
                    if ($tokens[$j]['value'] === ')' && --$depth === 0) break;
                    $arguments[] = $tokens[$j];
                }
                $distinct = in_array('DISTINCT', array_column($arguments, 'value'), true) ? ':DISTINCT' : '';
                $result[] = $t['value'] . $distinct;
                foreach ($arguments as $k => $argument) {
                    $value = $argument['value'];
                    if (isset($columns[$value]) && !in_array($value, ['TANGGAL','SOURCE_TYPE','STATUS','HAPUS'], true)
                        && !in_array($arguments[$k + 1]['value'] ?? '', ['=','>=','<=','>','<','!=','<>','IN','IS','LIKE'], true)) $result[] = $t['value'] . $distinct . ':' . $value;
                }
            }
            $result = array_values(array_unique($result)); sort($result); return $result;
        };
        return $measures($before) === $measures($after)
            && $this->businessSignatures($before, $columns, $originalTables) === $this->businessSignatures($after, $columns, $replacementTables);
    }

    /** Preserve boolean operators, joins and aggregate arithmetic, not just the set of literals. */
    private function businessSignatures(array $tokens, array $columns, array $tables): array
    {
        $aliases = []; $sourceAliases = [];
        foreach ($tokens as $i => $t) {
            if (!in_array($t['value'], ['FROM','JOIN'], true)) continue;
            $name = strtolower($tokens[$i + 1]['value'] ?? '');
            if (!in_array($name, $tables, true)) continue;
            $j = $i + 2; if (($tokens[$j]['value'] ?? '') === 'AS') $j++;
            $alias = strtoupper($name);
            if (in_array($tokens[$j]['type'] ?? '', ['word','identifier'], true) && !in_array($tokens[$j]['value'], ['WHERE','JOIN','LEFT','RIGHT','INNER','ON','USING','GROUP','ORDER','LIMIT','UNION'], true)) $alias = $tokens[$j]['value'];
            $sourceAliases[$name][$alias] ??= count($sourceAliases[$name] ?? []) + 1;
            $aliases[$alias] = strtoupper($name) . '#' . $sourceAliases[$name][$alias];
            $aliases[strtoupper($name)] ??= strtoupper($name) . '#1';
        }
        $normalized = [];
        foreach ($tokens as $i => $t) {
            if (($tokens[$i + 1]['value'] ?? '') === '.' && isset($aliases[$t['value']])) $t['value'] = $aliases[$t['value']];
            elseif (isset($columns[$t['value']]) && ($tokens[$i - 1]['value'] ?? '') !== '.' && count($tables) === 1) $t['value'] = strtoupper($tables[0]) . '#1.' . $t['value'];
            $normalized[] = $t;
        }
        $p = new SqlPredicate(); $result = [];
        foreach ($normalized as $i => $t) {
            if (in_array($t['value'], ['WHERE','ON','HAVING'], true)) {
                $body = []; $depth = 0;
                for ($j = $i + 1; $j < count($normalized); $j++) {
                    $v = $normalized[$j]['value'];
                    if ($depth === 0 && in_array($v, [')','GROUP','HAVING','ORDER','LIMIT','UNION','JOIN','LEFT','RIGHT','INNER','WHERE'], true)) break;
                    if ($v === '(') $depth++;
                    if ($v === ')') $depth--;
                    $body[] = $normalized[$j];
                }
                // Ignore calendar-generator predicates that do not refer to a business column.
                $business = array_filter($body, static fn ($a) => isset($columns[$a['value']]) || str_contains($a['value'], '.'));
                if ($business !== []) $result[] = $t['value'] . ':' . $p->canonical($p->tree($body));
            }
            if (in_array($t['value'], ['SUM','COUNT','AVG','MIN','MAX'], true) && ($normalized[$i + 1]['value'] ?? '') === '(') {
                $body = []; $depth = 1;
                for ($j = $i + 2; $j < count($normalized); $j++) {
                    if ($normalized[$j]['value'] === '(') $depth++;
                    if ($normalized[$j]['value'] === ')' && --$depth === 0) break;
                    $body[] = $normalized[$j];
                }
                $result[] = $t['value'] . ':' . $p->text($body);
            }
        }
        $result = array_values(array_unique($result)); sort($result); return $result;
    }

    /** A conservative lexical screen of the WHOLE rejected query; unsupported syntax cannot hide denied access. */
    private function repairableTables(string $sql, ?string $role): ?array
    {
        try { $tokens = (new SqlStructure())->tokens($sql); }
        catch (\InvalidArgumentException) { return null; }
        if (!in_array($tokens[0]['value'] ?? '', ['SELECT', 'WITH'], true)) return null;
        $catalog = $this->schemaService->catalog();
        $ctes = [];
        foreach ($tokens as $i => $token) {
            if (($tokens[$i + 1]['value'] ?? '') === 'AS' && ($tokens[$i + 2]['value'] ?? '') === '(' && in_array($tokens[$i + 3]['value'] ?? '', ['SELECT', 'WITH'], true)) $ctes[strtolower($token['value'])] = true;
        }
        $tables = []; $depth = 0; $from = [];
        foreach ($tokens as $i => $token) {
            $value = $token['value'];
            if ($value === '(') { $depth++; continue; }
            if ($value === ')') { unset($from[$depth]); $depth--; continue; }
            if (in_array($value, ['WHERE', 'GROUP', 'HAVING', 'ORDER', 'LIMIT'], true)) $from[$depth] = false;
            if ($value === ',' && !empty($from[$depth])) return null;
            if ($value === '*' && !(($tokens[$i - 1]['value'] ?? '') === '(' && ($tokens[$i - 2]['value'] ?? '') === 'COUNT')) return null;
            if (!in_array($token['type'], ['word', 'identifier'], true)) continue;
            $name = strtolower($value);
            if (in_array($value, $this->forbiddenKeywords, true) || in_array($name, $this->systemSchemas, true) || in_array($value, ['FOR','INTO','CROSS','NATURAL','STRAIGHT_JOIN','TABLE','VALUES'], true)
                || $this->schemaService->isTableForbidden($name) || $this->schemaService->isColumnForbidden($name)) return null;
            if (isset($catalog[$name]) && !$this->schemaService->isTableAllowedForRole($name, $role)) return null;
            if (($tokens[$i + 1]['value'] ?? '') === '(' && (!in_array($value, SqlStructure::ALLOWED_FUNCTIONS_AND_GROUPS, true) || ($tokens[$i - 1]['value'] ?? '') === '.')) return null;
            if (in_array($value, ['FROM','JOIN'], true)) {
                if ($value === 'FROM') $from[$depth] = true;
                $next = $tokens[$i + 1] ?? [];
                if (($next['value'] ?? '') === '(') continue;
                $source = strtolower($next['value'] ?? '');
                if (($tokens[$i + 2]['value'] ?? '') === '.' || !in_array($next['type'] ?? '', ['word','identifier'], true)) return null;
                if (isset($ctes[$source])) continue;
                if (!$this->schemaService->isTableAllowedForRole($source, $role)) return null;
                $tables[] = $source;
            }
        }
        $values = array_column($tokens, 'value');
        if (in_array('tbltransaksipenjualan', $tables, true) && array_intersect($tables, ['tbltransaksipenjualan_detail', 'tblreturpenjualan', 'tbltransaksipenjualan_bank'])
            && in_array('SUM', $values, true) && array_intersect($values, ['GRAND_TOTAL', 'TOTAL_KOTOR', 'POTONGAN', 'PPN', 'BAYAR'])) return null;
        return $tables === [] ? null : array_values(array_unique($tables));
    }

    /**
     * Tokenisasi leksikal untuk memvalidasi tanda kutip seimbang, menolak titik koma (multi-statement),
     * dan mengekstrak kata kunci serta identifier di luar string literal.
     *
     * @return array{valid: bool, error: ?string, clean_sql: string, words: list<string>, identifiers: list<string>}
     */
    private function tokenize(string $sql): array
    {
        $len = strlen($sql);
        $i = 0;
        $clean = '';
        $words = [];
        $identifiers = [];

        while ($i < $len) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            // Komentar satu baris -- atau #
            if (($ch === '-' && $next === '-') || $ch === '#') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                $clean .= ' ';
                continue;
            }

            // Komentar blok /* ... */
            if ($ch === '/' && $next === '*') {
                $i += 2;
                $closed = false;
                while ($i + 1 < $len) {
                    if ($sql[$i] === '*' && $sql[$i + 1] === '/') {
                        $closed = true;
                        $i += 2;
                        break;
                    }
                    $i++;
                }
                if (!$closed) {
                    return ['valid' => false, 'error' => 'Komentar blok SQL (`/* ... */`) tidak ditutup dengan benar.', 'clean_sql' => '', 'words' => [], 'identifiers' => []];
                }
                $clean .= ' ';
                continue;
            }

            // String literal '...' atau "..."
            if ($ch === "'" || $ch === '"') {
                $quote = $ch;
                $i++;
                $closed = false;
                while ($i < $len) {
                    if ($sql[$i] === '\\') {
                        $i += 2;
                        continue;
                    }
                    if ($sql[$i] === $quote) {
                        if ($i + 1 < $len && $sql[$i + 1] === $quote) {
                            $i += 2;
                            continue;
                        }
                        $closed = true;
                        $i++;
                        break;
                    }
                    $i++;
                }
                if (!$closed) {
                    return ['valid' => false, 'error' => 'Tanda kutip string dalam query SQL tidak seimbang.', 'clean_sql' => '', 'words' => [], 'identifiers' => []];
                }
                $clean .= " '' ";
                continue;
            }

            // Backtick identifier `...`
            if ($ch === '`') {
                $i++;
                $ident = '';
                $closed = false;
                while ($i < $len) {
                    if ($sql[$i] === '`') {
                        $closed = true;
                        $i++;
                        break;
                    }
                    $ident .= $sql[$i];
                    $i++;
                }
                if (!$closed) {
                    return ['valid' => false, 'error' => 'Identifier backtick (`` ` ``) tidak ditutup dengan benar.', 'clean_sql' => '', 'words' => [], 'identifiers' => []];
                }
                $identifiers[] = strtolower($ident);
                $clean .= ' ' . $ident . ' ';
                continue;
            }

            // Blokir titik koma di tengah query (multi-statement)
            if ($ch === ';') {
                return ['valid' => false, 'error' => 'Multi-statement SQL (`;`) dilarang keras demi keamanan.', 'clean_sql' => '', 'words' => [], 'identifiers' => []];
            }

            $clean .= $ch;
            $i++;
        }

        if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\b/', $clean, $wordMatches)) {
            foreach ($wordMatches[1] as $w) {
                $words[] = strtoupper($w);
                $identifiers[] = strtolower($w);
            }
        }

        return [
            'valid' => true,
            'error' => null,
            'clean_sql' => $clean,
            'words' => $words,
            'identifiers' => array_values(array_unique($identifiers)),
        ];
    }

    private function enforceLimit(string $sql): string
    {
        $maxLimit = max(1, min(500, $this->config->maxRowLimit));
        $defaultLimit = max(1, min($maxLimit, $this->config->defaultRowLimit));

        if (preg_match('/\bLIMIT\s+(\d+)(?:\s*,\s*(\d+))?\s*$/i', $sql, $match)) {
            $hasOffsetComma = isset($match[2]) && $match[2] !== '';
            $requested = $hasOffsetComma ? (int) $match[2] : (int) $match[1];
            if ($requested > $maxLimit) {
                if ($hasOffsetComma) {
                    return (string) preg_replace('/\bLIMIT\s+(\d+)\s*,\s*(\d+)\s*$/i', 'LIMIT $1, ' . $maxLimit, $sql);
                }
                return (string) preg_replace('/\bLIMIT\s+\d+\s*$/i', 'LIMIT ' . $maxLimit, $sql);
            }
            return $sql;
        }

        if (preg_match('/\bLIMIT\s+(\d+)\s+OFFSET\s+(\d+)\s*$/i', $sql, $match)) {
            $requested = (int) $match[1];
            if ($requested > $maxLimit) {
                return (string) preg_replace('/\bLIMIT\s+\d+(\s+OFFSET\s+\d+)\s*$/i', 'LIMIT ' . $maxLimit . '$1', $sql);
            }
            return $sql;
        }

        return $sql . ' LIMIT ' . $defaultLimit;
    }
}
