<?php
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
class SecurityCheck extends BaseCommand
{
    protected $group='App';
    protected $name='app:security-check';
    protected $description='Periksa baseline keamanan tanpa mencetak credential atau data pengguna.';
    public function run(array $params)
    {
        $app=config('App'); $db=config('Database');
        $checks=['production'=>ENVIRONMENT==='production','https_url'=>str_starts_with($app->baseURL,'https://'),'force_https'=>$app->forceGlobalSecureRequests,'secure_cookie'=>config('Cookie')->secure,'database_non_root'=>$db->default['username']!=='root' && $db->default['username']!=='','database_debug_off'=>!$db->default['DBDebug'],'argon2id'=>defined('PASSWORD_ARGON2ID'),'csrf_session'=>config('Security')->csrfProtection==='session'];
        try { $checks['security_version_column']=db_connect()->fieldExists('security_version','users'); } catch (\Throwable) { $checks['database_connection']=false; }
        try { service('securityRateLimiter')->check('healthcheck:'.bin2hex(random_bytes(8)),1,1); $checks['security_storage']=true; } catch (\Throwable) { $checks['security_storage']=false; }
        CLI::write(json_encode($checks,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        return in_array(false,$checks,true)?EXIT_ERROR:EXIT_SUCCESS;
    }
}
