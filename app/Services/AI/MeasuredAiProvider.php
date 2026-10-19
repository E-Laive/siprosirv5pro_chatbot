<?php
namespace App\Services\AI;

/** Numerical telemetry only: no prompts, responses, endpoint credentials or transaction rows. */
final class MeasuredAiProvider implements AiProviderGateway
{
    private array $calls = [];
    public function __construct(private AiProviderGateway $inner) {}
    public function reset(): void { $this->calls = []; }
    public function getActiveConfig(bool $includeSecretKey = false): array { return $this->inner->getActiveConfig($includeSecretKey); }
    public function isLoopback(string $url): bool { return $this->inner->isLoopback($url); }
    public function chatCompletion(array $messages): array
    {
        $start = hrtime(true); $result = [];
        try { $result = $this->inner->chatCompletion($messages); return $result; }
        finally {
            $usage = [];
            foreach (['prompt_tokens','completion_tokens','total_tokens','reasoning_tokens'] as $key) if (isset($result['usage'][$key]) && is_numeric($result['usage'][$key]) && $result['usage'][$key] >= 0) $usage[$key] = (int) $result['usage'][$key];
            $this->calls[] = ['duration_ms' => round((hrtime(true) - $start) / 1e6, 2), 'success' => !empty($result['used_remote']) && empty($result['error']), 'usage' => $usage];
        }
    }
    public function metrics(): array
    {
        $total = array_column(array_column($this->calls, 'usage'), 'total_tokens');
        $reasoning=array_column(array_column($this->calls,'usage'),'reasoning_tokens');
        return ['calls' => count($this->calls), 'duration_ms' => round(array_sum(array_column($this->calls, 'duration_ms')), 2), 'failed_calls' => count(array_filter($this->calls, static fn ($c) => !$c['success'])), 'total_tokens' => count($total) === count($this->calls) && $total !== [] ? array_sum($total) : null,
            'reasoning_tokens'=>count($reasoning)===count($this->calls)&&$reasoning!==[]?array_sum($reasoning):null,'reasoning_token_reports'=>count($reasoning)];
    }
}
