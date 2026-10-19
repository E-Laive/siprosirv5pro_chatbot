<?php

namespace App\Services;

use RuntimeException;

/** Single-host, atomic sliding window; unavailable storage fails closed. */
final class SecurityRateLimiter
{
    public function __construct(private ?string $directory = null) {}

    public function check(string $key, int $limit, int $window): int
    {
        $directory = $this->directory ?? WRITEPATH . 'security';
        if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) throw new RuntimeException('Security storage unavailable.');
        $file = @fopen($directory . '/rate-limits.json', 'c+');
        if (!$file || !flock($file, LOCK_EX)) { if ($file) fclose($file); throw new RuntimeException('Security storage unavailable.'); }
        try {
            $raw = stream_get_contents($file);
            $all = $raw === '' ? [] : json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($all)) throw new RuntimeException('Invalid security storage.');
            $now = time();
            foreach ($all as $hash => $entry) if (($entry['expires'] ?? 0) <= $now) unset($all[$hash]);
            $secret = (string) config('Encryption')->key;
            $hash = $secret !== '' ? hash_hmac('sha256', $key, $secret) : hash('sha256', $key);
            $hits = array_values(array_filter($all[$hash]['hits'] ?? [], static fn($hit) => is_int($hit) && $hit > $now - $window));
            $retry = count($hits) >= $limit ? max(1, $hits[0] + $window - $now) : 0;
            if (!$retry) $hits[] = $now;
            if (!isset($all[$hash]) && count($all) >= 10000) throw new RuntimeException('Security storage capacity exceeded.');
            $all[$hash] = ['hits' => $hits, 'expires' => $now + $window];
            $data = json_encode($all, JSON_THROW_ON_ERROR);
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, $data) !== strlen($data) || !fflush($file)) throw new RuntimeException('Security storage unavailable.');
            return $retry;
        } finally { flock($file, LOCK_UN); fclose($file); }
    }
}
