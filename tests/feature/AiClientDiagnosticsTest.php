<?php
namespace Tests\Feature;
use App\Services\AI\AiClientDiagnostics;
use CodeIgniter\Test\CIUnitTestCase;

final class AiClientDiagnosticsTest extends CIUnitTestCase
{
    public function testProductionRemovesSqlFromResponseHistoryAndContextWithoutChangingStoredInput(): void
    {
        $message = ['metadata' => ['queries' => [['sql' => 'PRIVATE_SQL', 'success' => false]], 'sql_diagnostics' => [['sql' => 'PRIVATE_SQL', 'error' => 'PRIVATE_SQL']], 'sql_planning_attempts' => [['sql' => 'PRIVATE_SQL']]], 'blocks' => [['type' => 'text', 'content' => 'Hasil belum lengkap.']]];
        $conversation = ['context' => ['last_queries' => [['sql' => 'PRIVATE_SQL']], 'last_rows' => [['amount' => 10]]], 'messages' => [$message]];
        $payload = $message + ['context' => $conversation['context'], 'conversation' => $conversation];
        $out = (new AiClientDiagnostics())->present($payload, true);
        $this->assertStringNotContainsString('PRIVATE_SQL', json_encode($out));
        $this->assertFalse($out['metadata']['show_query_audit']);
        $this->assertTrue($out['metadata']['query_failure_present']);
        $this->assertEquals([['amount' => 10]], $out['conversation']['context']['last_rows']);
        $this->assertSame('PRIVATE_SQL', $payload['metadata']['queries'][0]['sql']);
        $this->assertEquals($out, (new AiClientDiagnostics())->present($out, true));
    }
    public function testDevelopmentRetainsQueryDiagnostics(): void
    {
        $payload = ['metadata' => ['queries' => [['sql' => 'SELECT id_produk FROM tbldatabarang']]]];
        $this->assertSame($payload, (new AiClientDiagnostics())->present($payload, false));
    }
}
