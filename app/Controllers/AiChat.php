<?php

namespace App\Controllers;

use App\Services\ExportGuard;
use App\Services\PageRenderer;
use App\Services\ReportExporter;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Controller untuk Standalone POS AI Chat Analyst.
 * Menangani halaman workspace SPA serta endpoint JSON untuk percakapan, analisis SQL,
 * konfigurasi provider, dan ekspor laporan (CSV, Excel, PDF).
 */
final class AiChat extends BaseController
{
    public function index(): ResponseInterface { return $this->storageResponse(fn () => $this->indexReady()); }
    public function conversations(): ResponseInterface { return $this->storageResponse(fn () => $this->conversationsReady()); }
    public function showConversation(string $uid): ResponseInterface { return $this->storageResponse(fn () => $this->showConversationReady($uid)); }
    public function createConversation(): ResponseInterface { return $this->storageResponse(fn () => $this->createConversationReady()); }
    public function renameConversation(string $uid): ResponseInterface { return $this->storageResponse(fn () => $this->renameConversationReady($uid)); }
    public function deleteConversation(string $uid): ResponseInterface { return $this->storageResponse(fn () => $this->deleteConversationReady($uid)); }
    public function updateProviderConfig(): ResponseInterface { return $this->storageResponse(fn () => $this->updateProviderConfigReady()); }
    public function export(string $conversationUid, string $format): ResponseInterface { return $this->storageResponse(fn () => $this->exportReady($conversationUid, $format)); }

    private function storageResponse(callable $action): ResponseInterface
    {
        try { return $action(); }
        catch (\InvalidArgumentException $e) { return $this->jsonResponse(422, ['success' => false, 'error_code' => 'invalid_input', 'message' => $e->getMessage()]); }
        catch (Throwable) {
            service('auditLogger')->record('ai.storage.unavailable', ['user_id' => (int) session('auth_user_id')]);
            return $this->response->setHeader('Retry-After', '10')->setStatusCode(503)->setJSON(['success' => false, 'analysis_status' => 'error', 'error_code' => 'storage_unavailable', 'message' => 'Penyimpanan AI belum tersedia. Hubungi Administrator; riwayat tidak dialihkan ke penyimpanan lain.', 'csrf_token' => csrf_hash()]);
        }
    }

    private function indexReady(): ResponseInterface
    {
        $userId = (int) session('auth_user_id');
        $userRole = (string) session('auth_role');

        $convService = service('conversationService');
        $schemaService = service('posSchemaService');
        $queryExecutor = service('posQueryExecutor');
        $providerClient = service('aiProviderClient');

        $conversations = $convService->listUserConversations($userId);
        $activeConversation = null;
        $requestedUid = trim((string) ($this->request->getGet('c') ?? ''));

        if ($requestedUid !== '') {
            $activeConversation = $convService->getConversation($requestedUid, $userId);
        } elseif ($conversations !== []) {
            $activeConversation = $convService->getConversation($conversations[0]['uid'], $userId);
        }
        if ($activeConversation) $activeConversation = (new \App\Services\AI\ReportContext())->forClient($activeConversation, $userRole);

        // Gunakan PageRenderer untuk kontrak halaman penuh & fragmen SPA yang konsisten
        $renderer = new PageRenderer($this->request, $this->response);
        return $renderer->render(
            'pages/ai_chat',
            'Asisten AI Analis POS',
            'Analisis interaktif database POS berbasis bahasa alami, memori konteks, dan visualisasi dinamis',
            'asisten-ai',
            [
                'conversations' => $conversations,
                'activeConversation' => $activeConversation,
                'allowedDomains' => $schemaService->allowedDomainsForRole($userRole),
                'posStatus' => $queryExecutor->connectionStatus(),
                'providerConfig' => $providerClient->getActiveConfig(),
            ]
        );
    }

    private function conversationsReady(): ResponseInterface
    {
        $userId = (int) session('auth_user_id');
        $search = trim((string) ($this->request->getGet('q') ?? ''));
        $list = service('conversationService')->listUserConversations($userId, $search);

        return $this->jsonResponse(200, [
            'success' => true,
            'conversations' => $list,
        ]);
    }

    private function showConversationReady(string $uid): ResponseInterface
    {
        $userId = (int) session('auth_user_id');
        $conv = service('conversationService')->getConversation($uid, $userId);
        if (!$conv) {
            return $this->jsonResponse(404, [
                'success' => false,
                'message' => 'Percakapan tidak ditemukan atau bukan milik akun Anda.',
            ]);
        }
        $conv = (new \App\Services\AI\ReportContext())->forClient($conv, (string) session('auth_role'));

        return $this->jsonResponse(200, [
            'success' => true,
            'conversation' => $conv,
        ]);
    }

    private function createConversationReady(): ResponseInterface
    {
        $userId = (int) session('auth_user_id');
        $title = trim((string) ($this->request->getPost('title') ?? 'Percakapan Baru'));
        $conv = service('conversationService')->createConversation($userId, $title ?: 'Percakapan Baru');

        return $this->jsonResponse(200, [
            'success' => true,
            'conversation' => $conv + ['messages' => []],
        ]);
    }

    private function renameConversationReady(string $uid): ResponseInterface
    {
        $userId = (int) session('auth_user_id');
        $title = trim((string) ($this->request->getPost('title') ?? ''));
        if ($title === '' || mb_strlen($title) > 150) {
            return $this->jsonResponse(422, [
                'success' => false,
                'message' => 'Judul percakapan harus berisi 1–150 karakter.',
            ]);
        }

        $updated = service('conversationService')->renameConversation($uid, $userId, $title);
        if (!$updated) {
            return $this->jsonResponse(404, [
                'success' => false,
                'message' => 'Percakapan tidak ditemukan.',
            ]);
        }

        return $this->jsonResponse(200, [
            'success' => true,
            'uid' => $uid,
            'title' => $title,
            'message' => 'Nama percakapan berhasil diperbarui.',
        ]);
    }

    private function deleteConversationReady(string $uid): ResponseInterface
    {
        $userId = (int) session('auth_user_id');
        $deleted = service('conversationService')->deleteConversation($uid, $userId);
        if (!$deleted) {
            return $this->jsonResponse(404, [
                'success' => false,
                'message' => 'Percakapan tidak ditemukan.',
            ]);
        }

        return $this->jsonResponse(200, [
            'success' => true,
            'uid' => $uid,
            'message' => 'Percakapan telah dihapus.',
        ]);
    }

    public function sendMessage(): ResponseInterface
    {
        $userId = (int) session('auth_user_id');
        $userRole = (string) session('auth_role');

        // Rate limit per user untuk mencegah penyalahgunaan query analitik berat
        try {
            $retry = service('securityRateLimiter')->check('ai-chat-user:' . $userId, 30, 60);
            if ($retry) {
                return $this->response
                    ->setStatusCode(429)
                    ->setHeader('Retry-After', (string) $retry)
                    ->setJSON([
                        'success' => false,
                        'analysis_status' => 'error', 'error_code' => 'rate_limited',
                        'message' => 'Terlalu banyak permintaan analisis dalam waktu singkat. Silakan tunggu sebentar.',
                        'retry_after' => $retry,
                        'csrf_token' => csrf_hash(),
                    ]);
            }
        } catch (Throwable) {
            service('auditLogger')->record('ai.limiter.unavailable', ['user_id' => $userId]);
            return $this->jsonResponse(503, ['success' => false, 'analysis_status' => 'error', 'error_code' => 'limiter_unavailable', 'message' => 'Layanan pembatas permintaan belum tersedia. Coba kembali nanti.']);
        }

        $question = trim((string) ($this->request->getPost('message') ?? ''));
        $reportRef = $this->request->getPost('report_ref');
        if ($reportRef !== null && (!is_string($reportRef) || !preg_match('/^(?:report-[a-f0-9]{16}|legacy-(?:active|\d+))$/D', $reportRef))) return $this->jsonResponse(422, ['success' => false, 'error_code' => 'invalid_report_ref', 'message' => 'Referensi laporan tidak valid.']);
        $presentation = null;
        if ($this->request->getPost('action') === 'reformat') {
            $presentation = (string) $this->request->getPost('format');
            if (!in_array($presentation, ['table', 'chart', 'both'], true)) return $this->jsonResponse(422, ['success' => false, 'analysis_status' => 'error', 'error_code' => 'invalid_format', 'message' => 'Format tampilan tidak valid.']);
            $question = $presentation === 'table' ? 'Ubah menjadi tabel.' : ($presentation === 'chart' ? 'Tampilkan grafiknya.' : 'Tampilkan tabel dan grafik.');
        }
        $conversationUid = trim((string) ($this->request->getPost('conversation_uid') ?? ''));

        if ($question === '' || mb_strlen($question) > 1500) {
            return $this->jsonResponse(422, [
                'success' => false,
                'analysis_status' => 'error', 'error_code' => 'invalid_input',
                'message' => 'Pertanyaan wajib diisi (maksimal 1.500 karakter).',
            ]);
        }

        try {
            $result = service('aiOrchestrator')->handleMessage(
                $question,
                $userId,
                $userRole,
                $conversationUid !== '' ? $conversationUid : null,
                $presentation,
                $reportRef
            );

            return $this->jsonResponse($result['success'] ? 200 : 503, $result);
        } catch (\App\Services\AI\AnalysisException $e) {
            return $this->jsonResponse($e->errorCode === 'conversation_not_found' ? 404 : ($e->errorCode === 'conversation_busy' ? 409 : 422), ['success' => false, 'analysis_status' => 'error', 'error_code' => $e->errorCode, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            service('auditLogger')->record('ai.message.failed', ['user_id' => $userId]);
            return $this->jsonResponse(503, [
                'analysis_status' => 'error', 'error_code' => 'storage_or_service_unavailable',
                'success' => false,
                'message' => 'Maaf, terjadi kendala saat menyusun analisis data. Silakan coba kembali.',
            ]);
        }
    }

    public function status(): ResponseInterface { return $this->storageResponse(fn () => $this->statusReady()); }

    private function statusReady(): ResponseInterface
    {
        $userRole = (string) session('auth_role');
        $schemaService = service('posSchemaService');
        $ddlTables = $schemaService->parseReferenceDdl();

        return $this->jsonResponse(200, [
            'success' => true,
            'pos_connection' => service('posQueryExecutor')->connectionStatus(),
            'provider' => service('aiProviderClient')->getActiveConfig(),
            'allowed_domains' => $schemaService->allowedDomainsForRole($userRole),
            'allowed_tables_count' => count($schemaService->allowedTablesForRole($userRole)),
            'reference_ddl_tables_count' => count($ddlTables),
        ]);
    }

    private function updateProviderConfigReady(): ResponseInterface
    {
        $userRole = (string) session('auth_role');
        $userId = (int) session('auth_user_id');

        if ($userRole !== 'Administrator') {
            return service('accessPolicy')->deny('ai.settings', $this->request, $this->response);
        }

        try {
            $updated = service('aiProviderClient')->updateConfig($this->request->getPost(), $userId);
            service('auditLogger')->record('ai.provider.updated', ['user_id' => $userId]);
            return $this->jsonResponse(200, [
                'success' => true,
                'message' => 'Konfigurasi AI Provider berhasil diperbarui.',
                'provider' => $updated,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse(422, [
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mengekspor dataset hasil analisis percakapan ke format CSV, Excel (.xlsx), atau PDF.
     */
    private function exportReady(string $conversationUid, string $format): ResponseInterface
    {
        if (!in_array($format, ['csv', 'excel', 'pdf'], true)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $userId = (int) session('auth_user_id');
        $userRole = (string) session('auth_role');

        $conv = service('conversationService')->getConversation($conversationUid, $userId);
        if (!$conv) {
            return $this->jsonResponse(404, [
                'success' => false,
                'message' => 'Percakapan tidak ditemukan atau Anda tidak memiliki akses.',
            ]);
        }

        $reportRef = $this->request->getGet('report_ref');
        if ($reportRef !== null && (!is_string($reportRef) || !preg_match('/^(?:report-[a-f0-9]{16}|legacy-(?:active|\d+))$/D', $reportRef))) return $this->jsonResponse(422, ['success' => false, 'error_code' => 'invalid_report_ref', 'message' => 'Referensi laporan tidak valid.']);
        $report = (new \App\Services\AI\ReportContext())->select($conv, $userRole, $reportRef);
        if ($reportRef !== null && !$report) return $this->jsonResponse(404, ['success' => false, 'error_code' => 'report_not_found', 'message' => 'Laporan tidak ditemukan atau tidak tersedia untuk peran akun Anda.']);
        $context = $report['context'] ?? ($conv['context'] ?? []);
        if (array_filter($context['last_queries'] ?? [], static fn ($q) => ($q['success'] ?? true) === false)) return $this->jsonResponse(422, ['success' => false, 'error_code' => 'incomplete_result', 'message' => 'Hasil lama mengandung query gagal. Jalankan analisis kembali sebelum ekspor.']);
        $sourceMode = $context['source_mode'] ?? ($context['last_queries'][0]['source_mode'] ?? 'unknown');
        $domain = (string) ($context['domain'] ?? 'sales');
        $allowedDomains = service('posSchemaService')->allowedDomainsForRole($userRole);
        if (!in_array($domain, $allowedDomains, true)) {
            return service('accessPolicy')->deny('ai.domain.' . $domain, $this->request, $this->response);
        }

        if (!empty($context['last_empty_result']) || !empty($report['empty'])) return $this->jsonResponse(422, ['success' => false, 'error_code' => 'empty_result', 'message' => 'Tidak ada data pada laporan ini untuk diekspor.']);
        $rows = is_array($context['last_rows'] ?? null) ? $context['last_rows'] : [];
        $rawCols = is_array($context['last_columns'] ?? null) ? $context['last_columns'] : [];
        if ($rows === [] || $rawCols === []) {
            return $this->jsonResponse(422, [
                'success' => false,
                'message' => 'Belum ada tabel hasil analisis pada percakapan ini untuk diekspor.',
            ]);
        }

        $formattedColumns = [];
        foreach ($rawCols as $col) {
            $key = (string) ($col['key'] ?? '');
            $label = (string) ($col['label'] ?? $key);
            $type = (string) ($col['type'] ?? 'text');
            if ($key !== '') {
                $formattedColumns[$key] = [$label, $type];
            }
        }

        $report = [
            'key' => 'ai_analysis',
            'title' => (string) ($context['last_title'] ?? $conv['title'] ?? 'Laporan Analisis AI POS'),
            'note' => 'AI ASISTAN — ' . ($sourceMode === 'pos_verification_fixture' ? 'DATA SIMULASI' : ($sourceMode === 'live_pos_db' ? 'POS live' : 'Sumber belum diverifikasi')) . ' — Peran: ' . $userRole . (!empty($context['comparison']) ? ' — ' . ($context['last_notes'] ?? '') . (!empty($context['comparison']['partial_today']) ? ' Data hari ini masih berjalan.' : '') : ''),
            'filters' => [
                'Percakapan' => (string) $conv['title'],
                'Domain' => $domain,
            ],
            'columns' => $formattedColumns,
            'rows' => $rows,
        ];

        $exporter = new ReportExporter();
        $guardFormat = $format === 'pdf' ? 'pdf' : 'excel';

        try {
            $content = (new ExportGuard())->run('ai_' . $domain, $guardFormat, count($rows), static function () use ($exporter, $format, $report): string {
                return match ($format) {
                    'csv' => $exporter->csv($report),
                    'excel' => $exporter->excel($report),
                    'pdf' => $exporter->pdf($report),
                };
            });
        } catch (\LengthException $e) {
            return $this->jsonResponse(422, ['success' => false, 'message' => $e->getMessage()]);
        } catch (\OverflowException $e) {
            return $this->response->setStatusCode(429)->setHeader('Retry-After', $e->getMessage())->setJSON(['success' => false, 'message' => 'Batas frekuensi ekspor tercapai. Tunggu sebentar lalu coba kembali.']);
        } catch (\RuntimeException) {
            return $this->response->setStatusCode(503)->setHeader('Retry-After', '10')->setJSON(['success' => false, 'message' => 'Layanan ekspor sedang sibuk.']);
        }

        [$mime, $ext] = match ($format) {
            'csv' => ['text/csv; charset=UTF-8', 'csv'],
            'excel' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx'],
            'pdf' => ['application/pdf', 'pdf'],
        };

        $slug = preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) $report['title'])) ?: 'analisis-ai';
        $filename = trim($slug, '-') . '.' . $ext;

        return $this->response
            ->setContentType($mime, $format === 'csv' ? 'UTF-8' : '')
            ->setHeader('Content-Length', (string) strlen($content))
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($content);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function jsonResponse(int $status, array $payload): ResponseInterface
    {
        $payload = (new \App\Services\AI\AiClientDiagnostics())->present($payload);
        $payload['csrf_token'] = csrf_hash();
        return $this->response
            ->setStatusCode($status)
            ->setHeader('Cache-Control', 'private, no-store')
            ->setJSON($payload);
    }
}
