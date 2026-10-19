<?php

namespace Tests\Feature;

use App\Models\UserModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class AuthenticationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use \Tests\Support\AuthenticatedSession;

    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';

    public function testFirstVisitRedirectsToLoginWithCliProvisioning(): void
    {
        $result = $this->get('/');

        $result->assertRedirectTo('/login');
    }

    public function testDashboardRequiresAuthentication(): void
    {
        $result = $this->get('/dashboard');

        $result->assertRedirectTo('/login');
    }

    public function testValidLoginCreatesSessionAndReturnsDashboardRedirect(): void
    {
        $model = new UserModel();
        $model->insert([
            'username' => 'admin.test',
            'display_name' => 'Administrator Test',
            'password_hash' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'role' => 'Administrator',
            'is_active' => true,
        ]);

        $token = csrf_hash();
        $result = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('/login', [
                csrf_token() => $token,
                'username' => 'admin.test',
                'password' => 'TestPassword123!',
            ]);

        $result->assertStatus(200);
        $result->assertJSONFragment(['success' => true]);
        $this->assertNotNull(session('auth_user_id'));
    }

    public function testInvalidLoginUsesGenericMessage(): void
    {
        $model = new UserModel();
        $model->insert([
            'username' => 'admin.test',
            'display_name' => 'Administrator Test',
            'password_hash' => password_hash('TestPassword123!', PASSWORD_DEFAULT),
            'role' => 'Administrator',
            'is_active' => true,
        ]);

        $token = csrf_hash();
        $result = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post('/login', [
                csrf_token() => $token,
                'username' => 'tidak.ada',
                'password' => 'PasswordYangSalah',
            ]);

        $result->assertStatus(401);
        $result->assertJSONFragment([
            'success' => false,
            'message' => 'Username atau password tidak sesuai.',
        ]);
    }

    public function testAuthenticatedDashboardRendersCompleteShell(): void
    {
        $result = $this->asUser([
            'auth_user_id' => 1,
            'auth_display_name' => 'Administrator Test',
            'auth_role' => 'Administrator',
        ])->get('/dashboard');

        $result->assertStatus(200);
        $result->assertSee('SIPROSIR');
        $result->assertSee('Dashboard');
        $result->assertSee('AI ASISTAN');
        $result->assertDontSee('Fondasi');
        $result->assertDontSee('Template Halaman');
    }

    public function testSpaRequestReturnsOnlyPagePayload(): void
    {
        $result = $this->asUser([
            'auth_user_id' => 1,
            'auth_display_name' => 'Administrator Test',
            'auth_role' => 'Administrator',
        ])->withHeaders([
            'X-Requested-With' => 'XMLHttpRequest',
            'X-SPA-Request' => '1',
        ])->get('/katalog/button-badge');

        $result->assertStatus(200);
        $result->assertJSONFragment([
            'success' => true,
            'activeMenu' => 'button-badge',
            'pageTitle' => 'Button & Badge',
        ]);
    }

    public function testTableCatalogContainsFourExampleRows(): void
    {
        $result = $this->asUser([
            'auth_user_id' => 1,
            'auth_display_name' => 'Administrator Test',
            'auth_role' => 'Administrator',
        ])->get('/katalog/tabel-data');

        $result->assertStatus(200);
        $result->assertSee('PRD-001');
        $result->assertSee('PRD-002');
        $result->assertSee('PRD-003');
        $result->assertSee('PRD-004');
        $result->assertSee('Pelanggan prioritas');
        $result->assertSee('Ringkasan transaksi');
        $result->assertSee('INV-260811-041');
        $result->assertSee('INV-260811-044');
    }

    public function testEveryCatalogTableViewContainsFourExampleRows(): void
    {
        $tableViews = ['/katalog/tabel-data', '/katalog/template-crud'];

        foreach ($tableViews as $view) {
            $result = $this->asUser(['auth_user_id' => 1, 'auth_display_name' => 'Test', 'auth_role' => 'Administrator'])->get($view);
            $result->assertStatus(200);
            $contents = $result->getBody();
            $this->assertGreaterThan(0, preg_match_all('/<tbody>(.*?)<\/tbody>/s', $contents, $matches), $view);

            foreach ($matches[1] as $tableBody) {
                $this->assertSame(4, substr_count($tableBody, '<tr'), $view);
            }
        }
    }

    public function testTableCatalogContainsThreeProfessionalVariants(): void
    {
        $result = $this->asUser(['auth_user_id' => 1, 'auth_display_name' => 'Test', 'auth_role' => 'Administrator'])->get('/katalog/tabel-data');
        $result->assertStatus(200);
        $contents = $result->getBody();

        $this->assertSame(3, substr_count($contents, '<table'));
        $this->assertStringContainsString('STANDARD INTERACTIVE TABLE', $contents);
        $this->assertStringContainsString('MODERN TABLE', $contents);
        $this->assertStringContainsString('PROFESSIONAL DENSE TABLE', $contents);
        $this->assertStringContainsString('transaction-summary-strip', $contents);
    }

    public function testFeedbackCatalogContainsTopRightAlertTemplates(): void
    {
        $result = $this->asUser([
            'auth_user_id' => 1,
            'auth_display_name' => 'Administrator Test',
            'auth_role' => 'Administrator',
        ])->get('/katalog/feedback');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertStringContainsString('data-alert-toast="success"', $body);
        $this->assertStringContainsString('data-alert-toast="error"', $body);
        $this->assertStringContainsString('data-alert-confirm', $body);
        $this->assertStringContainsString('catalogAlertStack', $body);
    }

    public function testSharedStylesDefineThemeSafeDisabledStates(): void
    {
        $appCss = file_get_contents(FCPATH . 'assets/css/app.css');
        $catalogCss = file_get_contents(FCPATH . 'assets/css/catalog.css');

        $this->assertIsString($appCss);
        $this->assertIsString($catalogCss);
        $this->assertStringContainsString('--disabled-surface:', $appCss);
        $this->assertStringContainsString('--disabled-ink:', $appCss);
        $this->assertStringContainsString('.btn:disabled', $appCss);
        $this->assertStringContainsString('.form-control:disabled', $appCss);
        $this->assertStringContainsString('.command-box:disabled', $appCss);
        $this->assertStringContainsString('.page-item.disabled > .page-link', $catalogCss);
    }

    public function testButtonCatalogContainsOutlineButtonAndBadgeVariants(): void
    {
        $result = $this->asUser([
            'auth_user_id' => 1,
            'auth_display_name' => 'Administrator Test',
            'auth_role' => 'Administrator',
        ])->get('/katalog/button-badge');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertSame(8, substr_count($body, 'btn btn-demo-outline '));
        $this->assertSame(7, substr_count($body, 'status-chip outline '));
        $this->assertStringContainsString('Outline berdasarkan status', $body);
        $this->assertStringContainsString('Outline badge', $body);
    }

    public function testButtonCatalogContainsTextAndButtonGroupCombinations(): void
    {
        $result = $this->asUser([
            'auth_user_id' => 1,
            'auth_display_name' => 'Administrator Test',
            'auth_role' => 'Administrator',
        ])->get('/katalog/button-badge');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertSame(4, substr_count($body, 'class="combination-example"'));
        $this->assertStringContainsString('Text lalu button', $body);
        $this->assertStringContainsString('Button lalu text', $body);
        $this->assertStringContainsString('Button, text, button', $body);
        $this->assertStringContainsString('Text dengan dua button', $body);
        $this->assertStringContainsString('quantity-group', $body);
    }

    public function testFormCatalogContainsReusableFormGroupPatterns(): void
    {
        $result = $this->asUser([
            'auth_user_id' => 1,
            'auth_display_name' => 'Administrator Test',
            'auth_role' => 'Administrator',
        ])->get('/katalog/elemen-form');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertStringContainsString('FORM GROUP', $body);
        $this->assertStringContainsString('SECTION GROUP', $body);
        $this->assertStringContainsString('INLINE GROUP', $body);
        $this->assertSame(9, substr_count($body, 'class="field-group'));
        $this->assertStringContainsString('field-label-row', $body);
        $this->assertStringContainsString('form-section-panel', $body);
        $this->assertStringContainsString('inline-form-panel', $body);
    }

    public function testCardCatalogContainsSixIconPositionVariants(): void
    {
        $result = $this->asUser([
            'auth_user_id' => 1,
            'auth_display_name' => 'Administrator Test',
            'auth_role' => 'Administrator',
        ])->get('/katalog/card-media');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertSame(6, substr_count($body, 'class="position-card '));
        $this->assertStringContainsString('data-icon-position="top-left"', $body);
        $this->assertStringContainsString('data-icon-position="top-right"', $body);
        $this->assertStringContainsString('data-icon-position="side-left"', $body);
        $this->assertStringContainsString('data-icon-position="side-right"', $body);
        $this->assertStringContainsString('data-icon-position="floating"', $body);
        $this->assertStringContainsString('data-icon-position="footer"', $body);
    }

    public function testSidebarContainsOnlyAiNavigationAndKeepsCompactSupport(): void
    {
        $result = $this->asUser([
            'auth_user_id' => 1,
            'auth_display_name' => 'Administrator Test',
            'auth_role' => 'Administrator',
        ])->get('/dashboard');

        $result->assertStatus(200);
        $this->assertStringNotContainsString('data-flyout-title=', $result->getBody());
        $this->assertStringContainsString('data-menu="asisten-ai"', $result->getBody());

        $appCss = (string) file_get_contents(FCPATH . 'assets/css/app.css');
        $appJs = (string) file_get_contents(FCPATH . 'assets/js/app.js');
        $this->assertStringContainsString('body.sidebar-mini .nav-group .sidebar-submenu', $appCss);
        $this->assertStringNotContainsString('body.sidebar-mini .nav-group:hover .sidebar-submenu', $appCss);
        $this->assertStringContainsString('mini-flyout-open', $appJs);
        $this->assertStringContainsString('updateMiniFlyoutPositions', $appJs);
        $this->assertStringContainsString('MINI_FLYOUT_OPEN_DELAY = 120', $appJs);
        $this->assertStringContainsString('MINI_FLYOUT_CLOSE_DELAY = 180', $appJs);
        $this->assertStringContainsString('pointerenter', $appJs);
        $this->assertStringContainsString('pinnedMiniGroup', $appJs);
        $this->assertStringContainsString('--sidebar-width: 268px', $appCss);
        $this->assertStringContainsString('--sidebar-mini: 76px', $appCss);
        $this->assertStringContainsString('height: var(--topbar-height)', $appCss);
        $this->assertStringContainsString('font-size: var(--text-body)', $appCss);
    }

    public function testLocalInterVariableFontsAreBundledAndLoadedByTheShell(): void
    {
        $normalFont = FCPATH . 'assets/fonts/inter/InterVariable.woff2';
        $italicFont = FCPATH . 'assets/fonts/inter/InterVariable-Italic.woff2';
        $license = FCPATH . 'assets/fonts/inter/LICENSE.txt';
        $fontCss = (string) file_get_contents(FCPATH . 'assets/css/fonts.css');

        $this->assertFileExists($normalFont);
        $this->assertFileExists($italicFont);
        $this->assertFileExists($license);
        $this->assertSame('wOF2', file_get_contents($normalFont, false, null, 0, 4));
        $this->assertSame('wOF2', file_get_contents($italicFont, false, null, 0, 4));
        $this->assertStringContainsString('font-weight: 100 900', $fontCss);
        $this->assertStringContainsString('InterVariable.woff2', $fontCss);
        $this->assertStringContainsString('InterVariable-Italic.woff2', $fontCss);
        $this->assertStringContainsString('SIL OPEN FONT LICENSE', strtoupper((string) file_get_contents($license)));

        $result = $this->asUser([
            'auth_user_id' => 1,
            'auth_display_name' => 'Administrator Test',
            'auth_role' => 'Administrator',
        ])->get('/dashboard');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertStringContainsString('rel="preload"', $body);
        $this->assertStringContainsString('assets/fonts/inter/InterVariable.woff2', $body);
        $this->assertStringContainsString('assets/css/fonts.css', $body);
    }

    public function testShellAppearsImmediatelyAndSupportsReducedMotion(): void
    {
        $result = $this->asUser([
            'auth_user_id' => 1,
            'auth_display_name' => 'Administrator Test',
            'auth_role' => 'Administrator',
        ])->get('/dashboard');

        $result->assertStatus(200);
        $this->assertStringContainsString('class="app-body"', $result->getBody());
        $this->assertStringNotContainsString('class="app-body shell-entering"', $result->getBody());

        $appCss = (string) file_get_contents(FCPATH . 'assets/css/app.css');
        $appJs = (string) file_get_contents(FCPATH . 'assets/js/app.js');
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $appCss);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $appJs);
    }

    public function testProfessionalAccountMenuContainsOnlyFunctionalActions(): void
    {
        $result = $this->asUser([
            'auth_user_id' => 1,
            'auth_display_name' => 'Administrator Test',
            'auth_role' => 'Administrator',
        ])->get('/dashboard');

        $result->assertStatus(200);
        $body = $result->getBody();
        $this->assertStringContainsString('aria-label="Buka menu akun"', $body);
        $this->assertStringContainsString('class="user-status-dot"', $body);
        $this->assertStringContainsString('AKUN AKTIF', $body);
        $this->assertStringContainsString('Sesi aktif', $body);
        $this->assertStringContainsString('id="profileThemeSettingsAction"', $body);
        $this->assertStringContainsString('id="themeSettingsModal"', $body);
        $this->assertStringContainsString('value="system"', $body);
        $this->assertStringContainsString('value="custom"', $body);
        $this->assertSame(4, substr_count($body, 'data-theme-region='));
        $this->assertStringContainsString('Reset ke Sistem', $body);
        $this->assertStringContainsString('id="themeApplyButton"', $body);
        $this->assertStringContainsString('class="profile-logout-form"', $body);

        $appCss = (string) file_get_contents(FCPATH . 'assets/css/app.css');
        $appJs = (string) file_get_contents(FCPATH . 'assets/js/app.js');
        $themeInitJs = (string) file_get_contents(FCPATH . 'assets/js/theme-init.js');
        $this->assertStringContainsString('--account-danger:', $appCss);
        $this->assertStringContainsString('.profile-meta-grid', $appCss);
        $this->assertStringContainsString('themeManager?.save(modalThemeConfig())', $appJs);
        $this->assertStringContainsString('themeResetButton', $appJs);
        $this->assertStringContainsString('kerangka-theme-config-v1', $themeInitJs);
        $this->assertStringContainsString('kerangka-sidebar-theme', $themeInitJs);
        $this->assertStringContainsString('root.dataset.themeMode', $themeInitJs);
        $this->assertStringContainsString('html[data-sidebar-theme="light"]', $appCss);
        $this->assertStringContainsString('html[data-sidebar-theme="dark"]', $appCss);
        $this->assertStringContainsString('html[data-topbar-theme="dark"]', $appCss);
        $this->assertStringContainsString('html[data-footer-theme="dark"]', $appCss);
        $this->assertStringContainsString('assets/js/theme-init.js', $body);
    }
}
