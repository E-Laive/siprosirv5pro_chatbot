<?php
namespace App\Services;
final class ExportGuard
{
    public function run(string $resource, string $format, int $rows, callable $render): string
    {
        $policy = config('AppSecurity');
        $limit = $format === 'excel' ? $policy->maxExportRows : min($policy->maxExportRows, $policy->maxPdfRows);
        if ($rows > $limit) throw new \LengthException('Terlalu banyak baris. Persempit filter ekspor.');
        $retry = service('securityRateLimiter')->check('export-user:' . (int) session('auth_user_id'), 10, 60);
        if ($retry) throw new \OverflowException((string) $retry);
        $directory = WRITEPATH . 'security';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException('Export storage unavailable.');
        $slot = null;
        for ($i = 0; $i < $policy->maxConcurrentExports; $i++) {
            $handle = @fopen($directory . '/export-' . $i . '.lock', 'c');
            if ($handle && flock($handle, LOCK_EX | LOCK_NB)) { $slot = $handle; break; }
            if ($handle) fclose($handle);
        }
        if (!$slot) throw new \RuntimeException('Ekspor sedang sibuk. Coba kembali sebentar lagi.');
        $id = (int) session('auth_user_id');
        session()->close();
        try {
            $bytes = $render();
            service('auditLogger')->record('export.completed', ['user_id' => $id, 'resource' => $resource, 'format' => $format, 'rows' => $rows]);
            return $bytes;
        } finally { flock($slot, LOCK_UN); fclose($slot); }
    }
}
