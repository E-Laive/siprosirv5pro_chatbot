<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\AuthenticatedSession;

final class ErrorGalleryTest extends CIUnitTestCase
{
    use DatabaseTestTrait, FeatureTestTrait, AuthenticatedSession;

    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';

    public function testPreviewRequiresAuthentication(): void
    {
        $this->withSession([])->withHeaders(['X-SPA-Request' => '1'])->get('/pratinjau-error?code=500')->assertStatus(401);
    }

    public function testEveryAllowedPreviewIsSafeAndKeepsTheSession(): void
    {
        $this->asUser(['auth_role' => 'Kasir']);
        $id = null;
        foreach (config('ErrorPages')->pages as $code => $page) {
            $result = $this->get('/pratinjau-error?code=' . $code);
            $result->assertStatus(200);
            $result->assertSee($page['title']);
            $result->assertSee('Mode pratinjau');
            $result->assertSee('Kembali ke galeri');
            $id ??= session('auth_user_id');
            $this->assertGreaterThan(0, (int) $id);
            $this->assertSame($id, session('auth_user_id'));
            $this->assertStringContainsString("script-src 'self'", $result->response()->getHeaderLine('Content-Security-Policy'));
        }
    }

    public function testUnexpectedCodesAndStructuredInputAreRejected(): void
    {
        $this->asUser(['auth_role' => 'Administrator']);
        foreach (['200', '501', '0500', '500extra', '500%3Cscript%3E', 'code[]=500'] as $code) {
            $query = str_starts_with($code, 'code[') ? $code : 'code=' . $code;
            try {
                $this->get('/pratinjau-error?' . $query);
                $this->fail('Unexpected error code was accepted');
            } catch (\CodeIgniter\Exceptions\PageNotFoundException $error) {
                $this->assertSame(404, $error->getCode());
            }
        }
    }

    public function testGalleryWorksAsFullPageAndSpaPayload(): void
    {
        $fullPage = $this->asUser(['auth_role' => 'Administrator'])->get('/katalog/galeri-error?code=503');
        $fullPage->assertStatus(200);
        $fullPage->assertSee('Buka halaman penuh');
        $result = $this->withHeaders(['X-SPA-Request' => '1'])->get('/katalog/galeri-error');
        $result->assertStatus(200);
        $payload = json_decode($result->response()->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('galeri-error', $payload['activeMenu']);
        $this->assertStringContainsString('data-error-gallery-form', $payload['content']);
    }

    public function testRealErrorsKeepStatusAndNeverExposeExceptionData(): void
    {
        try {
            $this->get('/halaman-tidak-ada-untuk-pengujian');
            $this->fail('Expected missing route to retain 404');
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $error) {
            $this->assertSame(404, $error->getCode());
        }
        foreach ([400, 403, 404, 500] as $code) {
            $html = view('errors/html/error_' . $code, ['message' => 'SECRET_EXCEPTION_MARKER', 'exception' => new \RuntimeException('SECRET_EXCEPTION_MARKER')]);
            $this->assertStringNotContainsString('SECRET_EXCEPTION_MARKER', $html);
            $this->assertStringNotContainsString('Mode pratinjau', $html);
            $this->assertStringContainsString('Kode ' . $code, $html);
        }
    }
}
