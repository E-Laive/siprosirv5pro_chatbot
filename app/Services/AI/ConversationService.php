<?php
namespace App\Services\AI;

use Config\AiAnalyst;

/** Explicit storage selection. Never split a conversation across two backends. */
final class ConversationService
{
    private AiAnalyst $config;
    private string $storageDir;
    public function __construct(?AiAnalyst $config = null, ?string $storageDir = null)
    {
        $this->config = $config ?? config(AiAnalyst::class);
        $this->storageDir = $storageDir ?? WRITEPATH . 'ai_storage';
        if (!in_array($this->config->storageDriver, ['database', 'file'], true)) throw new \RuntimeException('Driver penyimpanan AI tidak valid.');
    }
    public function ensureStorageReady(): bool
    {
        if ($this->config->storageDriver === 'file') return false;
        $db = db_connect();
        if (!$db->tableExists('ai_conversations') || !$db->tableExists('ai_messages')) throw new \RuntimeException('Penyimpanan percakapan belum siap. Jalankan migration database aplikasi dan impor riwayat.');
        return true;
    }
    private function file(int $userId): AtomicJsonStore
    {
        if ($userId <= 0) throw new \InvalidArgumentException('Pemilik percakapan tidak valid.');
        return new AtomicJsonStore($this->storageDir . '/conversations_user_' . $userId . '.json');
    }
    private function now(): string { return (new BusinessClock($this->config->timezone))->now()->format('Y-m-d H:i:s'); }
    private function json(mixed $value): string { return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR); }
    private function transaction(int $userId, callable $change): mixed
    {
        // Single-host serialization protects read/count/update against lost updates.
        if (!is_dir($this->storageDir) && !@mkdir($this->storageDir, 0700, true) && !is_dir($this->storageDir)) throw new \RuntimeException('Lock penyimpanan tidak tersedia.');
        $lock = @fopen($this->storageDir . '/user_' . $userId . '.db.lock', 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) { if ($lock) fclose($lock); throw new \RuntimeException('Penyimpanan tidak dapat dikunci.'); }
        try {
            $db = db_connect(); $db->transException(true); $db->resetTransStatus(); if (!$db->transBegin()) throw new \RuntimeException('Transaksi percakapan tidak dapat dimulai.');
            try {
                $result = $change($db);
                if (!$db->transStatus()) throw new \RuntimeException('Transaksi percakapan gagal.');
                if (!$db->transCommit()) throw new \RuntimeException('Percakapan gagal disimpan.');
                return $result;
            } catch (\Throwable $e) { $db->transRollback(); $db->resetTransStatus(); throw $e; }
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
    public function listUserConversations(int $userId, string $search = ''): array
    {
        if ($this->ensureStorageReady()) {
            $b = db_connect()->table('ai_conversations')->where('user_id', $userId)->where('is_archived', 0);
            if (trim($search) !== '') $b->groupStart()->like('title', trim($search))->orLike('summary_text', trim($search))->groupEnd();
            $rows = $b->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')->limit(50)->get()->getResultArray();
        } else $rows = array_values(array_filter($this->file($userId)->read(), static fn ($c) => empty($c['is_archived']) && ($search === '' || stripos($c['title'] . ' ' . ($c['summary_text'] ?? ''), $search) !== false)));
        usort($rows, static fn ($a, $b) => strcmp($b['updated_at'], $a['updated_at']));
        return array_map(static fn ($c) => ['uid' => $c['conversation_uid'], 'title' => $c['title'], 'message_count' => (int) $c['message_count'], 'updated_at' => $c['updated_at'], 'summary' => $c['summary_text'] ?? ''], array_slice($rows, 0, 50));
    }
    public function createConversation(int $userId, string $initialTitle = 'Percakapan Baru'): array
    {
        $now = $this->now();
        $record = ['id' => 0, 'conversation_uid' => 'conv-' . bin2hex(random_bytes(8)), 'user_id' => $userId, 'title' => mb_substr(trim($initialTitle) ?: 'Percakapan Baru', 0, 150), 'context' => [], 'summary_text' => '', 'message_count' => 0, 'is_archived' => 0, 'messages' => [], 'created_at' => $now, 'updated_at' => $now];
        if ($this->ensureStorageReady()) {
            return $this->transaction($userId, function ($db) use ($record) {
                if (!$db->table('ai_conversations')->insert($this->dbRecord($record))) throw new \RuntimeException('Percakapan gagal dibuat.');
                return array_replace($record, ['id' => (int) $db->insertID()]);
            });
        }
        return $this->file($userId)->mutate(function (array &$store) use ($record) {
            $record['id'] = 1 + max([0, ...array_map(static fn ($v) => (int) $v['id'], $store)]);
            $store[$record['conversation_uid']] = $record; return $record;
        });
    }
    private function dbRecord(array $record): array
    {
        return ['conversation_uid' => $record['conversation_uid'], 'user_id' => $record['user_id'], 'title' => $record['title'], 'active_context_json' => $this->json($record['context'] ?? []), 'summary_text' => $record['summary_text'] ?? '', 'message_count' => count($record['messages'] ?? []), 'is_archived' => (int) ($record['is_archived'] ?? 0), 'created_at' => $record['created_at'], 'updated_at' => $record['updated_at']];
    }
    public function getConversation(string $uid, int $userId): ?array
    {
        if (!$this->ensureStorageReady()) {
            $c = $this->file($userId)->read()[$uid] ?? null;
            return $c && (int) $c['user_id'] === $userId && empty($c['is_archived']) ? $c : null;
        }
        $db = db_connect();
        $c = $db->table('ai_conversations')->where('conversation_uid', $uid)->where('user_id', $userId)->where('is_archived', 0)->get()->getRowArray();
        if (!$c) return null;
        $c['context'] = json_decode($c['active_context_json'], true, 512, JSON_THROW_ON_ERROR);
        unset($c['active_context_json']);
        $c['messages'] = array_map(static function ($m) {
            $m['blocks'] = json_decode($m['blocks_json'], true, 512, JSON_THROW_ON_ERROR);
            $m['metadata'] = json_decode($m['metadata_json'], true, 512, JSON_THROW_ON_ERROR);
            unset($m['blocks_json'], $m['metadata_json']); return $m;
        }, $db->table('ai_messages')->where('conversation_id', $c['id'])->orderBy('id', 'ASC')->get()->getResultArray());
        return $c;
    }
    public function renameConversation(string $uid, int $userId, string $newTitle): bool
    {
        $title = mb_substr(trim($newTitle), 0, 150);
        if ($title === '') return false;
        if ($this->ensureStorageReady()) return $this->transaction($userId, function ($db) use ($uid, $userId, $title) {
            if (!$this->getConversation($uid, $userId)) return false;
            return $db->table('ai_conversations')->where('conversation_uid', $uid)->where('user_id', $userId)->update(['title' => $title, 'updated_at' => $this->now()]);
        });
        return $this->file($userId)->mutate(function (array &$store) use ($uid, $title) {
            if (!isset($store[$uid])) return false;
            $store[$uid]['title'] = $title; $store[$uid]['updated_at'] = $this->now(); return true;
        });
    }
    public function deleteConversation(string $uid, int $userId): bool
    {
        if ($this->ensureStorageReady()) return $this->transaction($userId, function ($db) use ($uid, $userId) {
            $c = $this->getConversation($uid, $userId); if (!$c) return false;
            if (!$db->table('ai_messages')->where('conversation_id', $c['id'])->delete() || !$db->table('ai_conversations')->where('id', $c['id'])->where('user_id', $userId)->delete()) throw new \RuntimeException('Penghapusan percakapan gagal.');
            return true;
        });
        return $this->file($userId)->mutate(static function (array &$store) use ($uid) { if (!isset($store[$uid])) return false; unset($store[$uid]); return true; });
    }
    public function appendTurn(int $conversationId, string $userMessage, string $assistantSummary, array $blocks, array $metadata, array $updatedContext, ?string $autoTitle = null, ?int $userId = null, ?string $conversationUid = null): void
    {
        $userId ??= (int) session('auth_user_id');
        if ($userId <= 0 || !$conversationUid) throw new \RuntimeException('Identitas percakapan tidak lengkap.');
        $now = $this->now();
        $messages = [
            ['role' => 'user', 'content' => $userMessage, 'blocks' => [['type' => 'text', 'content' => $userMessage]], 'metadata' => ['timestamp' => $now], 'created_at' => $now],
            ['role' => 'assistant', 'content' => $assistantSummary, 'blocks' => $blocks, 'metadata' => $metadata, 'created_at' => $now],
        ];
        $apply = function (array &$c) use ($messages, $updatedContext, $autoTitle, $now) {
            foreach ($messages as $m) { $m['id'] = count($c['messages']) + 1; $c['messages'][] = $m; }
            $c['message_count'] = count($c['messages']); $c['context'] = $updatedContext; $c['updated_at'] = $now;
            if ($autoTitle !== null && $c['title'] === 'Percakapan Baru') $c['title'] = mb_substr($autoTitle, 0, 150);
            if ($c['message_count'] >= $this->config->summarizeThreshold) $c['summary_text'] = $this->buildCompactSummary($updatedContext, $c['messages']);
        };
        if ($this->ensureStorageReady()) {
            $this->transaction($userId, function ($db) use ($conversationUid, $conversationId, $userId, $messages, $apply) {
                $c = $this->getConversation($conversationUid, $userId);
                if (!$c || (int) $c['id'] !== $conversationId) throw new \RuntimeException('Percakapan tidak ditemukan.');
                $apply($c);
                foreach ($messages as $m) if (!$db->table('ai_messages')->insert(['conversation_id' => $conversationId, 'role' => $m['role'], 'content' => $m['content'], 'blocks_json' => $this->json($m['blocks']), 'metadata_json' => $this->json($m['metadata']), 'created_at' => $m['created_at']])) throw new \RuntimeException('Pesan gagal disimpan.');
                $updates = $this->dbRecord($c); unset($updates['conversation_uid'], $updates['user_id'], $updates['created_at']);
                if (!$db->table('ai_conversations')->where('id', $conversationId)->where('user_id', $userId)->update($updates)) throw new \RuntimeException('Konteks gagal disimpan.');
            });
        } else $this->file($userId)->mutate(static function (array &$store) use ($conversationUid, $conversationId, $apply) {
            if (!isset($store[$conversationUid]) || (int) $store[$conversationUid]['id'] !== $conversationId) throw new \RuntimeException('Percakapan tidak ditemukan.');
            $apply($store[$conversationUid]);
        });
    }
    public function getRecentPromptMessages(int $conversationId, ?int $userId = null, ?string $conversationUid = null): array
    {
        if (!$conversationUid || !$userId) return [];
        $c = $this->getConversation($conversationUid, $userId);
        if (!$c || (int) $c['id'] !== $conversationId) return [];
        return array_map(static fn ($m) => ['role' => $m['role'], 'content' => $m['content']], array_slice($c['messages'], -$this->config->maxContextMessages));
    }
    /** Only user requests and clarification questions; business reports never enter the provider prompt. */
    public function getUnderstandingMessages(int $conversationId, int $userId, string $conversationUid, bool $includeConversationReplies = false, ?array $allowedDomains = null, bool $allowAggregateHistory = false, string $question = ''): array
    {
        $c = $this->getConversation($conversationUid, $userId);
        if (!$c || (int) $c['id'] !== $conversationId) return [];
        $messages = [];
        foreach ($c['messages'] as $m) {
            if ($m['role'] === 'assistant' && ($m['metadata']['response_kind'] ?? '') === 'conversation') {
                if (!empty($m['metadata']['aggregate_context_attached']) && !$allowAggregateHistory) continue;
                if ($allowedDomains !== null && array_diff($m['metadata']['context_domains'] ?? [], $allowedDomains)) continue;
            }
            if ($m['role'] === 'user' || ($m['metadata']['intent'] ?? '') === 'clarification_needed' || ($includeConversationReplies && ($m['metadata']['response_kind'] ?? '') === 'conversation')) {
                $content = ($m['role'] === 'assistant' && ($m['metadata']['clarification_slot'] ?? '') === 'member') ? 'Sebutkan nama lengkap atau ID member yang dimaksud.' : $m['content'];
                $messages[] = ['role' => $m['role'], 'content' => mb_substr($content, 0, 2000)];
            }
        }
        return (new ConversationMemory())->retrieve($messages, $this->config->maxContextMessages, $question);
    }
    private function buildCompactSummary(array $context, array $messages = []): string
    {
        $memory = (new ConversationMemory())->build($messages, $context);
        return 'Topik: ' . ($context['last_title'] ?? '') . ' | Memori: ' . $this->json($memory);
    }
    public function importFiles(): array
    {
        if (!$this->ensureStorageReady()) throw new \RuntimeException('Impor memerlukan ai.storageDriver=database.');
        $counts = ['imported' => 0, 'skipped' => 0];
        foreach (glob($this->storageDir . '/conversations_user_*.json') ?: [] as $path) {
            if (!preg_match('/conversations_user_(\d+)\.json$/', $path, $m)) continue;
            $userId = (int) $m[1]; $store = (new AtomicJsonStore($path))->read();
            $backup = $this->storageDir . '/backups';
            if (!is_dir($backup) && !mkdir($backup, 0700, true)) throw new \RuntimeException('Backup impor gagal.');
            $backupPath = $backup . '/' . basename($path) . '.' . hash_file('sha256', $path) . '.bak';
            if (!is_file($backupPath) && !copy($path, $backupPath)) throw new \RuntimeException('Backup impor gagal.');
            $delta = $this->transaction($userId, function ($db) use ($store, $userId) {
                $result = ['imported' => 0, 'skipped' => 0];
                if (!$db->table('users')->where('id', $userId)->countAllResults()) throw new \RuntimeException('Pemilik riwayat impor tidak ditemukan: ' . $userId);
                foreach ($store as $uid => $c) {
                    if (($c['conversation_uid'] ?? '') !== $uid || (int) ($c['user_id'] ?? 0) !== $userId) throw new \RuntimeException('Kepemilikan riwayat impor tidak valid.');
                    $existing = $db->table('ai_conversations')->where('conversation_uid', $uid)->get()->getRowArray();
                    if ($existing) {
                        if ((int) $existing['user_id'] !== $userId) throw new \RuntimeException('UID riwayat impor bertabrakan.');
                        $result['skipped']++; continue;
                    }
                    if (!$db->table('ai_conversations')->insert($this->dbRecord($c))) throw new \RuntimeException('Impor percakapan gagal.');
                    $id = $db->insertID();
                    foreach ($c['messages'] ?? [] as $msg) if (!$db->table('ai_messages')->insert(['conversation_id' => $id, 'role' => $msg['role'], 'content' => $msg['content'], 'blocks_json' => $this->json($msg['blocks'] ?? []), 'metadata_json' => $this->json($msg['metadata'] ?? []), 'created_at' => $msg['created_at'] ?? $c['created_at']])) throw new \RuntimeException('Impor pesan gagal.');
                    $result['imported']++;
                }
                return $result;
            });
            foreach ($delta as $key => $n) $counts[$key] += $n;
        }
        return $counts;
    }
}
