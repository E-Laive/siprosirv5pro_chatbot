<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
class RevokeSessions extends BaseCommand
{
    protected $group = 'App';
    protected $name = 'app:revoke-sessions';
    protected $description = 'Cabut semua sesi satu pengguna melalui security version.';
    public function run(array $params)
    {
        $id = $params[0] ?? '';
        if (!ctype_digit((string) $id) || !(new \App\Models\UserModel())->find((int) $id)) { CLI::error('ID pengguna tidak valid.'); return EXIT_ERROR; }
        (new \App\Models\UserModel())->builder()->where('id', (int) $id)->set('security_version', 'security_version + 1', false)->update();
        service('auditLogger')->record('session.revoked', ['user_id' => (int) $id]);
        CLI::write('Seluruh sesi pengguna dicabut.', 'green');
    }
}
