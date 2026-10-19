<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
final class ImportAiHistory extends BaseCommand
{
    protected $group = 'App';
    protected $name = 'ai:import-history';
    protected $description = 'Impor riwayat file AI ke database aplikasi dengan backup dan UID idempotent.';
    public function run(array $params)
    {
        try { CLI::write(json_encode(service('conversationService')->importFiles(), JSON_PRETTY_PRINT)); return EXIT_SUCCESS; }
        catch (\Throwable $e) { CLI::error($e->getMessage()); return EXIT_ERROR; }
    }
}
