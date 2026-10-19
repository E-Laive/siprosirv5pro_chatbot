<?php
namespace App\Services\AI;

interface AiProviderGateway
{
    public function getActiveConfig(bool $includeSecretKey = false): array;
    public function chatCompletion(array $messages): array;
    public function isLoopback(string $url): bool;
}
