<?php

namespace App\Services;

/** Only explicitly allowed, non-secret fields reach the audit log. */
final class AuditLogger
{
    public function record(string $event, array $context = []): void
    {
        $allowed = array_intersect_key($context, array_flip(['user_id', 'reason', 'resource', 'format', 'rows', 'status', 'retry_after', 'ai_calls', 'ai_duration_ms', 'ai_failed_calls', 'ai_total_tokens']));
        foreach ($allowed as $key => $value) {
            if (!is_scalar($value) && $value !== null) unset($allowed[$key]);
            elseif (is_string($value)) $allowed[$key] = mb_substr($value, 0, 160);
        }
        service('logger')->log('notice', 'security_event {event}', ['event' => json_encode(['event' => $event, 'at' => gmdate('c'), 'context' => $allowed], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)]);
    }
}
