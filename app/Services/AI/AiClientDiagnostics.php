<?php
namespace App\Services\AI;

/** Keep diagnostic SQL in server storage; production HTTP responses do not expose it. */
final class AiClientDiagnostics
{
    public function present(array $payload, ?bool $production = null): array
    {
        if (!($production ?? ENVIRONMENT === 'production')) return $payload;
        if (isset($payload['metadata'])) {
            $payload['metadata']['show_query_audit'] = false;
            $payload['metadata']['query_failure_present'] = !empty($payload['metadata']['query_failure_present']) || (bool) array_filter($payload['metadata']['queries'] ?? [], static fn ($q) => ($q['success'] ?? true) === false);
            $payload['metadata']['queries'] = [];
            unset($payload['metadata']['sql_planning_attempts'], $payload['metadata']['sql_diagnostics'], $payload['metadata']['plan_diagnostics']);
        }
        if (isset($payload['context'])) $payload['context']['last_queries'] = [];
        if (isset($payload['messages'])) foreach ($payload['messages'] as $i => $message) $payload['messages'][$i] = $this->present($message, true);
        if (isset($payload['conversation']) && is_array($payload['conversation'])) $payload['conversation'] = $this->present($payload['conversation'], true);
        return $payload;
    }
}
