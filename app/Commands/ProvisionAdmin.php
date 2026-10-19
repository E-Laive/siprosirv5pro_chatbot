<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
class ProvisionAdmin extends BaseCommand
{
    protected $group = 'App';
    protected $name = 'app:provision-admin';
    protected $description = 'Provision administrator pertama secara lokal; password tidak masuk argumen shell.';
    public function run(array $params)
    {
        if ($params !== []) { CLI::error('Gunakan prompt interaktif tanpa argumen password.'); return EXIT_ERROR; }
        $name = CLI::prompt('Nama pengelola');
        $username = CLI::prompt('Username');
        try {
            $password = $this->secret('Password (minimal 12 karakter)');
            $confirm = $this->secret('Ulangi password');
            $id = (new \App\Services\SetupService())->createAdministrator(['display_name' => $name, 'username' => $username, 'password' => $password, 'password_confirm' => $confirm]);
            CLI::write('Administrator pertama berhasil dibuat (ID ' . $id . ').', 'green');
        } catch (\Throwable $e) { CLI::error($e instanceof \InvalidArgumentException || $e instanceof \RuntimeException ? $e->getMessage() : 'Provisioning gagal. Periksa log server tanpa mencetak secret.'); return EXIT_ERROR; }
    }
    private function secret(string $prompt): string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Read-Host keeps input hidden; the secret returns through a private pipe, never an argument.
            $command = 'powershell.exe -NoProfile -Command "$s=Read-Host -AsSecureString -Prompt \'' . $prompt . '\'; $b=[Runtime.InteropServices.Marshal]::SecureStringToBSTR($s); try {[Console]::Write([Runtime.InteropServices.Marshal]::PtrToStringBSTR($b))} finally {[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($b)}"';
            $value = shell_exec($command);
            if ($value === null) throw new \RuntimeException('Prompt password tersembunyi tidak tersedia.');
            return rtrim($value, "\r\n");
        }
        if (!function_exists('shell_exec') || trim((string) shell_exec('stty -g 2>/dev/null')) === '') throw new \RuntimeException('Terminal interaktif diperlukan.');
        $mode = trim((string) shell_exec('stty -g')); fwrite(STDOUT, $prompt . ': '); shell_exec('stty -echo');
        try { return rtrim((string) fgets(STDIN), "\r\n"); } finally { shell_exec('stty ' . escapeshellarg($mode)); fwrite(STDOUT, PHP_EOL); }
    }
}
