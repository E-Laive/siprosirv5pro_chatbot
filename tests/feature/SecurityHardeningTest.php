<?php
namespace Tests\Feature;
use App\Models\UserModel;
use App\Services\AuthService;
use App\Services\SetupService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthenticatedSession;
final class SecurityHardeningTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait, AuthenticatedSession;
    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';
    private function user(string $role = 'Administrator', bool $active = true): int
    {
        return (int)(new UserModel())->insert(['username' => 'security.user', 'display_name' => 'Security Test', 'password_hash' => AuthService::hashPassword('SecurityPassword123!'), 'role' => $role, 'is_active' => $active], true);
    }
    private function signed(int $id, array $extra = []): static
    {
        return $this->withSession($extra + ['auth_user_id' => $id, 'auth_version' => 1, 'auth_logged_in_at' => time(), 'auth_last_seen_at' => time()])->withHeaders(['X-SPA-Request' => '1']);
    }
    public function testWebSetupCannotCreateAccountFromQueryOrPost(): void
    {
        $this->post('/setup?username=security.admin&display_name=Valid%20Name&password=SecurityPassword123!&password_confirm=SecurityPassword123!', [csrf_token() => csrf_hash()])->assertStatus(403);
        $this->assertSame(0, (new UserModel())->countAllResults());
        $this->assertNull(session('_ci_old_input'));
        $this->get('/setup')->assertStatus(403);
    }
    public function testSetupServiceValidatesOnlyProvidedArrayAndCreatesOnce(): void
    {
        $service = new SetupService();
        try { $service->createAdministrator(['username' => [], 'display_name' => 'A', 'password' => 'short', 'password_confirm' => 'short']); $this->fail('Expected validation rejection'); } catch (\InvalidArgumentException) { $this->assertSame(0, (new UserModel())->countAllResults()); }
        $data = ['username' => 'security.admin', 'display_name' => 'Security Admin', 'password' => 'SecurityPassword123!', 'password_confirm' => 'SecurityPassword123!'];
        $id = $service->createAdministrator($data);
        $this->assertGreaterThan(0, $id);
        $this->assertTrue(password_verify($data['password'], (new UserModel())->find($id)['password_hash']));
        $this->expectException(\RuntimeException::class); $service->createAdministrator($data + ['username' => 'second.admin']);
    }
    public function testMissingDeletedDisabledAndRevokedSessionsAreRejected(): void
    {
        $this->signed(999999)->get('/dashboard')->assertStatus(401);
        $id = $this->user('Administrator', false);
        $this->signed($id)->get('/dashboard')->assertStatus(401);
        (new UserModel())->update($id, ['is_active' => true, 'security_version' => 2]);
        $this->signed($id)->get('/dashboard')->assertStatus(401);
        (new UserModel())->delete($id);
        $this->signed($id)->get('/dashboard')->assertStatus(401);
    }
    public function testIdleAndAbsoluteTimeoutAreServerEnforced(): void
    {
        $id = $this->user();
        $this->signed($id, ['auth_last_seen_at' => time()-1801])->get('/dashboard')->assertStatus(401);
        $this->signed($id, ['auth_logged_in_at' => time()-28801])->get('/dashboard')->assertStatus(401);
    }
    public function testRoleIsReloadedAndCashierCannotAccessUsers(): void
    {
        $id = $this->user('Kasir');
        $this->signed($id, ['auth_role' => 'Administrator'])->get('/ekspor/pengguna/excel')->assertStatus(403);
        $this->assertSame('Kasir', session('auth_role'));
        $this->signed($id)->get('/katalog/template-crud')->assertStatus(403);
        $this->signed($id)->get('/ekspor/produk/excel')->assertStatus(200);
    }
    public function testUnknownRoleHasNoPrivileges(): void
    {
        $this->assertFalse(service('accessPolicy')->allows('catalog.view', 'Unknown'));
        $this->assertFalse(service('accessPolicy')->allows('export.pengguna', 'Gudang'));
    }
    public function testLoginCannotUseQueryOnlyAndRejectsArrayInput(): void
    {
        $this->post('/login?username=security.user&password=SecurityPassword123!', [csrf_token() => csrf_hash()])->assertStatus(422);
        $this->post('/login', [csrf_token() => csrf_hash(), 'username' => ['x'], 'password' => ['x']])->assertStatus(422);
        $this->assertNull(session('auth_user_id'));
        $this->assertNull(session('_ci_old_input'));
    }
    public function testCsrfMissingAndStaleAreRejected(): void
    {
        foreach ([[], [csrf_token() => str_repeat('0',32)]] as $token) {
            try { $this->post('/login', $token + ['username'=>'security.user','password'=>'SecurityPassword123!']); $this->fail('Expected CSRF rejection'); } catch (\CodeIgniter\Security\Exceptions\SecurityException $e) { $this->assertSame(403,$e->getCode()); }
        }
    }
    public function testLoginRateLimitAndRetryAfter(): void
    {
        for ($i=0;$i<5;$i++) $this->post('/login',[csrf_token()=>csrf_hash(),'username'=>'not.present','password'=>'SecurityPassword123!'])->assertStatus(401);
        $limited=$this->post('/login',[csrf_token()=>csrf_hash(),'username'=>'not.present','password'=>'SecurityPassword123!']);
        $limited->assertStatus(429);
        $this->assertGreaterThan(0,(int)$limited->response()->getHeaderLine('Retry-After'));
    }
    public function testUnavailableLimiterFailsClosed(): void
    {
        \Config\Services::injectMock('securityRateLimiter',new \App\Services\SecurityRateLimiter(__FILE__));
        $this->post('/login',[csrf_token()=>csrf_hash(),'username'=>'not.present','password'=>'SecurityPassword123!'])->assertStatus(503);
    }
    public function testPasswordSuffixRemainsSignificant(): void
    {
        if (AuthService::algorithm() === PASSWORD_BCRYPT) { $this->expectException(\InvalidArgumentException::class); AuthService::hashPassword(str_repeat('a',73)); return; }
        $prefix = str_repeat('a',72); $hash=AuthService::hashPassword($prefix.'one');
        $this->assertTrue(password_verify($prefix.'one',$hash));
        $this->assertFalse(password_verify($prefix.'two',$hash));
    }
    public function testLogoutInvalidatesSession(): void
    {
        $id=$this->user(); $token=csrf_hash();
        $this->signed($id)->post('/logout',[csrf_token()=>$token])->assertRedirectTo('/login');
        $this->assertNull(session('auth_user_id'));
        $this->withSession([])->get('/dashboard')->assertStatus(401);
    }
    public function testAuthenticatedResponseHasCspAndNoStore(): void
    {
        $r=$this->signed($this->user())->get('/dashboard'); $r->assertStatus(200);
        $this->assertStringContainsString("script-src 'self'",$r->response()->getHeaderLine('Content-Security-Policy'));
        $this->assertStringContainsString("object-src 'none'",$r->response()->getHeaderLine('Content-Security-Policy'));
        $this->assertStringContainsString('no-store',$r->response()->getHeaderLine('Cache-Control'));
    }
    public function testExportRowAndConcurrencyLimits(): void
    {
        $id=$this->user(); config('AppSecurity')->maxExportRows=1;
        $this->signed($id)->get('/ekspor/produk/excel')->assertStatus(422);
        config('AppSecurity')->maxExportRows=5000; config('AppSecurity')->maxConcurrentExports=0;
        $this->signed($id)->get('/ekspor/produk/excel')->assertStatus(503);
        config('AppSecurity')->maxConcurrentExports=2;
    }
    public function testSensitiveAccountUpdateRevokesPreviousSession(): void
    {
        $id=$this->user(); (new UserModel())->update($id,['role'=>'Kasir']);
        $this->assertSame(2,(new UserModel())->find($id)['security_version']);
        $this->signed($id)->get('/dashboard')->assertStatus(401);
    }
    public function testAuditLoggerExcludesSecretsAndStructuredInput(): void
    {
        $logger=new class extends \Psr\Log\AbstractLogger {
            public array $events=[];
            public function log($level, \Stringable|string $message, array $context=[]): void { $this->events[]=[$level,$message,$context]; }
        };
        \Config\Services::injectMock('logger',$logger);
        service('auditLogger')->record('test.event',['user_id'=>1,'password'=>'SECRET_MARKER','password_confirm'=>'SECRET_MARKER','token'=>'SECRET_MARKER','reason'=>['password'=>'SECRET_MARKER']]);
        $encoded=json_encode($logger->events);
        $this->assertStringContainsString('test.event',$encoded);
        $this->assertStringNotContainsString('SECRET_MARKER',$encoded);
    }
    public function testLegacyBcryptSuffixCollisionCannotAuthenticate(): void
    {
        $prefix=str_repeat('a',72);
        (new UserModel())->insert(['username'=>'legacy.user','display_name'=>'Legacy Test','password_hash'=>password_hash($prefix.'one',PASSWORD_BCRYPT),'role'=>'Administrator','is_active'=>true]);
        $this->post('/login',[csrf_token()=>csrf_hash(),'username'=>'legacy.user','password'=>$prefix.'two'])->assertStatus(401);
        $this->assertNull(session('auth_user_id'));
    }
    public function testEveryCatalogPageHasAuthenticatedPayload(): void
    {
        $id=$this->user();
        foreach(config('TemplateCatalog')->groups as $group) foreach($group['items'] as $item) {
            $r=$this->signed($id)->get('/katalog/'.$item['slug']); $r->assertStatus(200);
            $data=json_decode($r->response()->getBody(),true);
            $this->assertIsArray($data,$item['slug']);
            $this->assertTrue($data['success']);
            $this->assertNotEmpty($data['content']);
        }
    }
    public function testCashierMenuOmitsUsersAndDirectAccessRenders403(): void
    {
        $id=$this->user('Kasir');
        $this->signed($id)->withHeaders([]);
        $r=$this->get('/dashboard'); $r->assertStatus(200);
        $this->assertStringNotContainsString('/katalog/template-crud',$r->response()->getBody());
        $this->signed($id)->withHeaders([])->get('/katalog/template-crud')->assertSee('Akses tidak tersedia');
    }
}
