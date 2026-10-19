<?php
namespace App\Services\AI;

final class AnalysisException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly array $queries = [], public readonly array $planningAttempts = [], public readonly array $planDiagnostics = []) { parent::__construct($message); }
}
