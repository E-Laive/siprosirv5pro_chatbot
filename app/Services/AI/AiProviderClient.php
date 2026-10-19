<?php

namespace App\Services\AI;

use Config\AiAnalyst;
use Throwable;

/**
 * HTTP client untuk provider AI; tidak menghasilkan jawaban/planner offline.
 *
 * Mengisolasi konfigurasi provider, base URL, API key (disimpan aman di server, tidak pernah dikirim balik ke browser),
 * model ID, temperature, timeout, serta kemampuan Text-to-SQL / JSON structured planning.
 */
final class AiProviderClient implements AiProviderGateway
{
    private AiAnalyst $config;
    private string $configFile;
    private ?\Closure $transport;

    public function __construct(?AiAnalyst $config = null, ?string $storageDirectory = null, ?callable $transport = null)
    {
        $this->config = $config ?? config(AiAnalyst::class);
        $this->transport=$transport===null?null:\Closure::fromCallable($transport);
        $dir = $storageDirectory ?? rtrim(WRITEPATH, '/\\') . DIRECTORY_SEPARATOR . 'ai_storage';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $this->configFile = $dir . DIRECTORY_SEPARATOR . 'provider_config.json';
    }

    /**
     * Mengembalikan konfigurasi aktif (menggabungkan pengaturan server dengan .env).
     * API Key tidak pernah dikembalikan dalam nilai teks penuh ke tampilan publik.
     *
     * @return array<string, mixed>
     */
    public function getActiveConfig(bool $includeSecretKey = false): array
    {
        $apiKey = trim($this->config->apiKey);
        $base = [
            'provider_name' => $this->config->provider,
            'base_url' => $this->config->baseUrl,
            'model_id' => $this->config->model,
            'temperature' => $this->config->temperature,
            'max_tokens' => $this->config->maxTokens,
            'timeout_seconds' => $this->config->timeoutSeconds,
            'reasoning_effort'=>$this->config->reasoningEffort,
            'streaming_enabled' => $this->config->streamingSupported,
            'tools_enabled' => $this->config->toolCallingSupported,
        ];

        // 1. Cek file konfigurasi lokal di writable/ai_storage/provider_config.json
        if (is_file($this->configFile)) {
            $saved = (new AtomicJsonStore($this->configFile))->read();
            if (is_array($saved)) {
                $base['provider_name'] = (string) ($saved['provider_name'] ?? $base['provider_name']);
                $base['base_url'] = rtrim((string) ($saved['base_url'] ?? $base['base_url']), '/');
                $base['model_id'] = (string) ($saved['model_id'] ?? $base['model_id']);
                $base['temperature'] = (float) ($saved['temperature'] ?? $base['temperature']);
                $base['max_tokens'] = (int) ($saved['max_tokens'] ?? $base['max_tokens']);
                $base['timeout_seconds'] = (int) ($saved['timeout_seconds'] ?? $base['timeout_seconds']);
                $base['reasoning_effort']=$saved['reasoning_effort']??$base['reasoning_effort'];
                $base['streaming_enabled'] = (bool) ($saved['streaming_enabled'] ?? $base['streaming_enabled']);
                $base['tools_enabled'] = (bool) ($saved['tools_enabled'] ?? $base['tools_enabled']);
                if (isset($saved['api_key']) && is_string($saved['api_key'])) {
                    $apiKey = trim($saved['api_key']);
                }
            }
        }

        $base['has_api_key'] = $apiKey !== '';
        $base['api_key_masked'] = $apiKey !== ''
            ? '••••••••'
            : 'Belum diatur (Mode Analitik Lokal Aktif)';

        $base['streaming_enabled'] = false;
        $base['tools_enabled'] = false;
        $base['reasoning_effort']=(new AiReasoningPolicy())->validate($base['reasoning_effort']);
        $capabilityCache=(new AtomicJsonStore(dirname($this->configFile).'/provider_capabilities.json'))->read();
        $base['reasoning_capabilities']=($capabilityCache['fingerprint']??'')===$this->capabilityFingerprint($base,$apiKey)&&($capabilityCache['expires_at']??0)>time()
            ?$capabilityCache['capabilities']:['status'=>'unchecked','reasoning'=>false,'effort_supported'=>false,'can_disable'=>false];
        $base['configured'] = $apiKey !== '' || $this->isLoopback((string) $base['base_url']);
        $health = (new AtomicJsonStore(dirname($this->configFile) . '/provider_health.json'))->read();
        $fingerprint = $this->healthFingerprint($base,$apiKey);
        $base['connection_status'] = ($health['fingerprint'] ?? '') === $fingerprint ? ($health['status'] ?? 'unchecked') : 'unchecked';
        $base['last_checked_at'] = ($health['fingerprint'] ?? '') === $fingerprint ? ($health['at'] ?? null) : null;
        if ($includeSecretKey) {
            $base['api_key'] = $apiKey;
        }

        return $base;
    }

    /**
     * Menyimpan pembaruan konfigurasi AI Provider (khusus Administrator).
     *
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function updateConfig(array $input, int $userId): array
    {
        $current = $this->getActiveConfig(true);
        $providerName = trim((string) ($input['provider_name'] ?? $current['provider_name']));
        $baseUrl = rtrim(trim((string) ($input['base_url'] ?? $current['base_url'])), '/');
        $modelId = trim((string) ($input['model_id'] ?? $current['model_id']));
        $temperature = max(0.0, min(1.0, (float) ($input['temperature'] ?? $current['temperature'])));
        $maxTokens = max(256, min(8192, (int) ($input['max_tokens'] ?? $current['max_tokens'])));
        $timeoutSeconds = max(5, min(120, (int) ($input['timeout_seconds'] ?? $current['timeout_seconds'])));
        $reasoningEffort=(new AiReasoningPolicy())->validate($input['reasoning_effort']??$current['reasoning_effort']);
        $streamingEnabled = false;
        $toolsEnabled = false;

        $newApiKey = trim((string) ($input['api_key'] ?? ''));
        $effectiveApiKey = $newApiKey !== '' ? $newApiKey : (string) ($current['api_key'] ?? '');
        if (!empty($input['clear_api_key'])) {
            $effectiveApiKey = '';
        }

        $this->validateBaseUrl($baseUrl);
        if($reasoningEffort!=='auto'){
            $caps=$this->reasoningCapabilities(['base_url'=>$baseUrl,'model_id'=>$modelId,'api_key'=>$effectiveApiKey]);
            if(!(new AiReasoningPolicy())->supports($caps,$reasoningEffort))throw new \InvalidArgumentException('Model belum menyatakan dukungan level reasoning yang dipilih. Gunakan Otomatis atau pilih model yang mendukung.');
        }

        $record = [
            'provider_name' => mb_substr($providerName ?: 'openai-compatible', 0, 60),
            'base_url' => mb_substr($baseUrl ?: 'https://api.openai.com/v1', 0, 255),
            'model_id' => mb_substr($modelId ?: 'gpt-4o-mini', 0, 100),
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'timeout_seconds' => $timeoutSeconds,
            'streaming_enabled' => $streamingEnabled ? 1 : 0,
            'tools_enabled' => $toolsEnabled ? 1 : 0,
            'updated_by' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Simpan ke file server terproteksi (termasuk api_key jika diisi)
        $filePayload = $record + ['api_key' => $effectiveApiKey,'reasoning_effort'=>$reasoningEffort];
        (new AtomicJsonStore($this->configFile))->mutate(static function (array &$saved) use ($filePayload) { $saved = $filePayload; });

        // Simpan juga metadata non-rahasia ke Database A bila tabel tersedia
        try {
            $db = db_connect();
            if ($db->tableExists('ai_provider_configs')) {
                $existing = $db->table('ai_provider_configs')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
                if ($existing) {
                    $db->table('ai_provider_configs')->where('id', (int) $existing['id'])->update($record);
                } else {
                    $db->table('ai_provider_configs')->insert($record);
                }
            }
        } catch (Throwable) {
        }

        return $this->getActiveConfig(false);
    }

    /**
     * Meminta LLM (OpenAI-compatible / Ollama / Gemini / DeepSeek / Groq) untuk menyusun rencana SQL
     * atau menginterpretasikan hasil query dalam format JSON terstruktur.
     *
     * @param list<array{role: string, content: string}> $messages
     * @return array{used_remote: bool, content: ?string, parsed_json: ?array<string, mixed>, model: string, error: ?string}
     */
    public function chatCompletion(array $messages): array
    {
        $cfg = $this->getActiveConfig(true);
        $apiKey = (string) ($cfg['api_key'] ?? '');
        $isLocalOllama = $this->isLoopback((string) $cfg['base_url']);

        if ($apiKey === '' && !$isLocalOllama) {
            return [
                'used_remote' => false,
                'content' => null,
                'parsed_json' => null,
                'model' => (string) $cfg['model_id'],
                'error' => 'provider_not_configured',
            ];
        }

        $this->validateBaseUrl((string) $cfg['base_url']);
        $payload = [
            'model' => $cfg['model_id'],
            'messages' => $messages,
            'temperature' => $cfg['temperature'],
            'max_tokens' => $cfg['max_tokens'],
            'stream'=>false,
        ];
        if($cfg['reasoning_effort']!=='auto'){
            try{$caps=$this->reasoningCapabilities($cfg);}
            catch(Throwable){return $this->failure($cfg,'provider_capabilities_unavailable');}
            if(!(new AiReasoningPolicy())->supports($caps,$cfg['reasoning_effort']))return $this->failure($cfg,'provider_reasoning_unsupported');
            $payload['reasoning_effort']=$cfg['reasoning_effort'];
            // Reasoning providers can reject sampling parameters even on an OpenAI-compatible route.
            unset($payload['temperature']);
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        if ($apiKey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }

        try {
            $endpoint = rtrim((string) $cfg['base_url'], '/') . '/chat/completions';
            $response=$this->request('POST',$endpoint,$headers,$payload,max(5,(int)$cfg['timeout_seconds']));
            $rawBody=$response['body'];$statusCode=$response['status'];$curlErr=$response['error'];

            if ($rawBody === false || $statusCode < 200 || $statusCode >= 300) {
                $this->recordHealth($cfg, 'unavailable');
                return [
                    'used_remote' => false,
                    'content' => null,
                    'parsed_json' => null,
                    'model' => (string) $cfg['model_id'],
                    'error' => $curlErr !== '' ? $curlErr : ('HTTP ' . $statusCode),
                ];
            }

            $body = json_decode((string) $rawBody, true);
            $content = $body['choices'][0]['message']['content'] ?? '';
            if(is_array($content))$content=implode("\n",array_column(array_filter($content,static fn($part)=>is_array($part)&&($part['type']??null)==='text'&&is_string($part['text']??null)),'text'));
            if(!is_string($content))$content='';
            // Provider thinking is never a final answer, planner JSON, history or telemetry.
            $content=preg_replace('/^\s*(?:<think>.*?<\/think>\s*)+/si','',$content)??'';
            if(preg_match('/^\s*<think>/i',$content))$content='';
            $usage=$this->usage(is_array($body['usage']??null)?$body['usage']:[]);
            if(trim($content)===''||($body['choices'][0]['finish_reason']??'')==='length')return $this->failure($cfg,'provider_output_incomplete')+['usage'=>$usage];
            $this->recordHealth($cfg, 'verified');
            $parsedJson = $this->extractJsonFromContent($content);

            return [
                'used_remote' => true,
                'content' => $content,
                'parsed_json' => $parsedJson,
                'model' => (string) ($body['model'] ?? $cfg['model_id']),
                'error' => null,
                'usage' => $usage,
                'reasoning_effort'=>$cfg['reasoning_effort'],
            ];
        } catch (Throwable $e) {
            $this->recordHealth($cfg, 'unavailable');
            return [
                'used_remote' => false,
                'content' => null,
                'parsed_json' => null,
                'model' => (string) $cfg['model_id'],
                'error' => $e->getMessage(),
            ];
        }
    }

    private function failure(array $cfg,string $code): array
    {
        return ['used_remote'=>false,'content'=>null,'parsed_json'=>null,'model'=>(string)$cfg['model_id'],'error'=>$code,'reasoning_effort'=>$cfg['reasoning_effort']];
    }

    private function usage(array $raw): array
    {
        $result=[];
        foreach(['prompt_tokens','completion_tokens','total_tokens'] as $key)if(isset($raw[$key])&&is_numeric($raw[$key])&&$raw[$key]>=0)$result[$key]=(int)$raw[$key];
        $reasoning=$raw['completion_tokens_details']['reasoning_tokens']??null;
        if(is_numeric($reasoning)&&$reasoning>=0)$result['reasoning_tokens']=(int)$reasoning;
        return $result;
    }

    private function reasoningCapabilities(array $cfg): array
    {
        $path=dirname($this->configFile).'/provider_capabilities.json';$cached=(new AtomicJsonStore($path))->read();
        $fingerprint=$this->capabilityFingerprint($cfg,(string)($cfg['api_key']??''));
        if(($cached['fingerprint']??'')===$fingerprint&&($cached['expires_at']??0)>time())return $cached['capabilities'];
        $this->validateBaseUrl($cfg['base_url']);$headers=['Accept: application/json'];
        if(!empty($cfg['api_key']))$headers[]='Authorization: Bearer '.$cfg['api_key'];
        try{$response=$this->request('GET',rtrim($cfg['base_url'],'/').'/models',$headers,null,8);}
        catch(Throwable){throw new \InvalidArgumentException('Kemampuan model belum dapat diperiksa. Periksa koneksi provider atau gunakan Otomatis.');}
        $body=json_decode((string)$response['body'],true);
        if($response['status']!==200||!is_array($body['data']??null))throw new \InvalidArgumentException('Kemampuan model belum dapat diperiksa. Periksa koneksi provider atau gunakan Otomatis.');
        $model=[];
        foreach($body['data'] as $row)if(is_array($row)&&($row['id']??null)===$cfg['model_id']){$model=$row;break;}
        $caps=(new AiReasoningPolicy())->capabilities($model);
        (new AtomicJsonStore($path))->mutate(static function(array &$saved)use($fingerprint,$caps){$saved=['fingerprint'=>$fingerprint,'expires_at'=>time()+600,'capabilities'=>$caps];});
        return $caps;
    }

    private function capabilityFingerprint(array $cfg,string $apiKey): string
    {
        return hash('sha256',json_encode([$cfg['base_url'],$cfg['model_id'],$apiKey],JSON_THROW_ON_ERROR));
    }
    private function healthFingerprint(array $cfg,string $apiKey): string
    {
        return hash('sha256',json_encode([$cfg['base_url'],$cfg['model_id'],$apiKey,$cfg['reasoning_effort']??'auto'],JSON_THROW_ON_ERROR));
    }

    private function request(string $method,string $url,array $headers,?array $payload,int $timeout): array
    {
        if($this->transport!==null)return ($this->transport)($method,$url,$headers,$payload,$timeout);
        $ch=curl_init($url);if($ch===false)throw new \RuntimeException('cURL tidak tersedia.');
        try{
            curl_setopt_array($ch,[CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HTTPHEADER=>$headers,
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>min(8,$timeout)]);
            if($method==='POST')curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)]);
            $body=curl_exec($ch);
            return ['body'=>$body,'status'=>(int)curl_getinfo($ch,CURLINFO_HTTP_CODE),'error'=>curl_error($ch)];
        }finally{curl_close($ch);}
    }

    /**
     * @return array<string, mixed>|null
     */
    public function isLoopback(string $url): bool
    {
        return in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), ['localhost', '127.0.0.1', '[::1]'], true);
    }

    public function validateBaseUrl(string $url): void
    {
        $p = parse_url($url);
        if (!filter_var($url, FILTER_VALIDATE_URL) || !$p || isset($p['user'], $p['pass']) || isset($p['user']) || isset($p['query']) || isset($p['fragment']) ||
            !(($p['scheme'] ?? '') === 'https' || (($p['scheme'] ?? '') === 'http' && $this->isLoopback($url)))) {
            throw new \InvalidArgumentException('Provider remote wajib HTTPS; HTTP hanya untuk loopback lokal, tanpa credential/query pada URL.');
        }
    }

    private function recordHealth(array $cfg, string $status): void
    {
        try {
            $health = ['fingerprint' => $this->healthFingerprint($cfg,(string)($cfg['api_key']??'')), 'status' => $status, 'at' => date('c')];
            (new AtomicJsonStore(dirname($this->configFile) . '/provider_health.json'))->mutate(static function (array &$saved) use ($health) { $saved = $health; });
        } catch (Throwable) {
            service('auditLogger')->record('ai.health.storage_failed');
        }
    }

    private function extractJsonFromContent(string $content): ?array
    {
        $trimmed = trim($content);
        if ($trimmed === '') {
            return null;
        }
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $trimmed, $m)) {
            $trimmed = $m[1];
        } elseif (preg_match('/(\{.*\})/s', $trimmed, $m)) {
            $trimmed = $m[1];
        }
        $decoded = json_decode($trimmed, true);
        return is_array($decoded) ? $decoded : null;
    }
}
