<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Konfigurasi Standalone POS AI Chat Analyst
 */
class AiAnalyst extends BaseConfig
{
    /** Nama grup koneksi database POS Read-Only */
    public string $posDatabaseGroup = 'posReadonly';

    /** Path ke file referensi struktur SQL POS untuk introspeksi skema offline/hybrid */
    public string $schemaSqlPath = APPPATH . 'Database/Reference/dbsiprosir_online_v5_x2_struktur.sql';

    /** Zona waktu default untuk interpretasi periode analisis bisnis */
    public string $timezone = 'Asia/Jakarta';

    /** Konfigurasi AI Provider (OpenAI-compatible API abstraction) */
    public string $provider = 'openai-compatible';
    public string $baseUrl = 'https://api.openai.com/v1';
    public string $apiKey = '';
    public string $model = 'gpt-4o-mini';
    public float $temperature = 0.1;
    public int $maxTokens = 2048;
    public int $timeoutSeconds = 25;
    public string $reasoningEffort = 'auto';
    public bool $streamingSupported = false;
    public bool $toolCallingSupported = false;
    public string $storageDriver = 'database';

    /** Batas keamanan & performa eksekusi SQL */
    public int $defaultRowLimit = 100;
    public int $maxRowLimit = 500;
    public int $maxExportRows = 5000;
    public int $maxPdfExportRows = 1000;
    public int $queryTimeoutSeconds = 8;
    public int $maxToolIterations = 4;
    public int $maxContextMessages = 10;
    public int $summarizeThreshold = 14;

    /**
     * @deprecated Retained for configuration compatibility. Operational mode ignores it;
     * fixtures are restricted to ENVIRONMENT=testing.
     */
    public bool $allowAnalyticalSandboxWhenPosOffline = false;

    /**
     * Daftar tabel internal/keamanan POS yang DILARANG keras diakses oleh AI Analyst.
     *
     * @var list<string>
     */
    public array $forbiddenTables = [
        'migrations',
        'tbluser',
        'tblrole',
        'tblpermission',
        'tblrole_permission',
        'tblsecurity_audit',
        'tblapi_idempotency',
        'tblbusiness_number_sequence',
        'tblproduk_foto',
        'tblproduk_foto_transfer',
        'tblproduk_import_job',
        'tblproduk_import_row',
        'tblsync_capture_queue',
        'tblsync_conflict',
        'tblsync_cursor',
        'tblsync_event',
        'tblsync_identity_audit',
        'tblsync_identity_quarantine',
        'tblsync_inbox',
        'tblsync_outbox',
        'tblsync_return_effect_log',
        'tblsync_run',
        'tblsync_schema_version',
        'tblsync_stock_baseline',
        'tblsync_stock_baseline_chunk',
        'tblsync_stock_baseline_detail',
        'tblsync_stock_recovery_log',
        'tblsync_transaction_identity',
    ];

    /**
     * Daftar kolom sensitif/internal yang DILARANG dipilih atau difilter oleh AI Analyst.
     *
     * @var list<string>
     */
    public array $forbiddenColumns = [
        'password',
        'password_hash',
        'credential_version',
        'password_changed_at',
        'token_sync',
        'request_hash',
        'response_data',
        'payload',
        'payload_json',
        'errors_json',
        'before_json',
        'after_json',
        'file_sha256',
        'source_checksum',
        'payload_checksum',
        'content_checksum',
        'chunk_checksum',
        'local_checksum',
        'remote_checksum',
        'source_hash',
        'identity_hash',
    ];

    /**
     * Pemetaan domain bisnis yang diizinkan berdasarkan peran pengguna aplikasi AI.
     *
     * @var array<string, list<string>>
     */
    public array $roleDomains = [
        'Administrator' => ['sales', 'returns_receivables', 'products_inventory', 'members', 'purchasing_payables', 'cash_finance', 'hr_payroll'],
        'Kasir'         => ['sales', 'returns_receivables', 'products_inventory', 'members'],
        'Gudang'        => ['products_inventory', 'purchasing_payables'],
    ];

    public function __construct()
    {
        parent::__construct();
        $this->storageDriver = (string) env('ai.storageDriver', $this->storageDriver);

        $this->posDatabaseGroup = (string) env('ai.posDatabaseGroup', $this->posDatabaseGroup);
        $this->timezone         = (string) env('ai.timezone', $this->timezone);
        $this->provider         = (string) env('ai.provider', $this->provider);
        $this->baseUrl          = rtrim((string) env('ai.baseUrl', $this->baseUrl), '/');
        $this->apiKey           = (string) env('ai.apiKey', $this->apiKey);
        $this->model            = (string) env('ai.model', $this->model);
        $this->temperature      = (float) env('ai.temperature', $this->temperature);
        $this->maxTokens        = (int) env('ai.maxTokens', $this->maxTokens);
        $this->timeoutSeconds   = (int) env('ai.timeoutSeconds', $this->timeoutSeconds);
        $this->reasoningEffort = (string) env('ai.reasoningEffort', $this->reasoningEffort);
        $this->streamingSupported = (bool) env('ai.streamingSupported', $this->streamingSupported);
        $this->toolCallingSupported = (bool) env('ai.toolCallingSupported', $this->toolCallingSupported);
        $this->defaultRowLimit  = (int) env('ai.defaultRowLimit', $this->defaultRowLimit);
        $this->maxRowLimit      = (int) env('ai.maxRowLimit', $this->maxRowLimit);
        $this->queryTimeoutSeconds = (int) env('ai.queryTimeoutSeconds', $this->queryTimeoutSeconds);
        $this->allowAnalyticalSandboxWhenPosOffline = filter_var(
            env('ai.allowAnalyticalSandboxWhenPosOffline', $this->allowAnalyticalSandboxWhenPosOffline),
            FILTER_VALIDATE_BOOLEAN
        );
    }
}
