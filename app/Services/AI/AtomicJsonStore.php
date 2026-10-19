<?php
namespace App\Services\AI;

final class AtomicJsonStore
{
    public function __construct(private string $path) {}

    public function read(): array
    {
        return $this->access(false, static fn (array &$value) => $value);
    }

    public function mutate(callable $change): mixed { return $this->access(true, $change); }

    private function access(bool $write, callable $change): mixed
    {
        $dir = dirname($this->path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) throw new \RuntimeException('Penyimpanan AI tidak tersedia.');
        $lock = @fopen($this->path . '.lock', 'c+');
        if (!$lock || !flock($lock, $write ? LOCK_EX : LOCK_SH)) {
            if ($lock) fclose($lock);
            throw new \RuntimeException('Penyimpanan AI tidak dapat dikunci.');
        }
        $temp = null;
        try {
            $value = [];
            if (is_file($this->path)) {
                $raw = file_get_contents($this->path);
                if ($raw === false) throw new \RuntimeException('Penyimpanan AI tidak dapat dibaca.');
                $value = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($value)) throw new \RuntimeException('Format penyimpanan AI rusak.');
            }
            $result = $change($value);
            if ($write) {
                $data = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
                $temp = tempnam($dir, '.ai-');
                if ($temp === false || file_put_contents($temp, $data) !== strlen($data) || !@chmod($temp, 0600) || !@rename($temp, $this->path)) throw new \RuntimeException('Penyimpanan AI gagal; perubahan tidak diterapkan.');
                $temp = null;
            }
            return $result;
        } finally {
            if ($temp && is_file($temp)) @unlink($temp);
            flock($lock, LOCK_UN); fclose($lock);
        }
    }
}
