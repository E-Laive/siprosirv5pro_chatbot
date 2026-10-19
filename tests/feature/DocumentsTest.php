<?php

namespace Tests\Feature;

use App\Services\SampleInvoice;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

final class DocumentsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use \Tests\Support\AuthenticatedSession;

    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';

    public function testDocumentDownloadsRequireAuthentication(): void
    {
        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('/dokumen/struk58/pdf')->assertStatus(401);
    }

    public function testEveryPaperFormatRendersAndExportsTheSameSample(): void
    {
        foreach (['a4', 'struk58', 'struk80'] as $format) {
            $session = ['auth_user_id' => 1, 'auth_display_name' => 'Test', 'auth_role' => 'Administrator'];
            $result = $this->asUser($session)->get('/dokumen/' . $format);
            $result->assertStatus(200);
            $result->assertSee('DOKUMEN CONTOH');
            $result->assertSee('INV-260811-041');
            $this->assertStringContainsString('Rp785.000', $result->response()->getBody());
            $pdf = $this->asUser($session)->get('/dokumen/' . $format . '/pdf');
            $pdf->assertStatus(200);
            $this->assertStringStartsWith('%PDF-', $pdf->response()->getBody());
            $this->assertStringContainsString('no-store', $pdf->response()->getHeaderLine('Cache-Control'));
            $verificationPath = ROOTPATH . 'build/verification';
            if (!is_dir($verificationPath)) {
                mkdir($verificationPath, 0775, true);
            }
            file_put_contents($verificationPath . '/document-' . $format . '.pdf', $pdf->response()->getBody());
        }
    }

    public function testDocumentTotalsReconcile(): void
    {
        $invoice = (new SampleInvoice())->data();
        $this->assertSame(4, count($invoice['lines']));
        $this->assertSame(775000, array_sum(array_column($invoice['lines'], 'subtotal')));
        $this->assertSame($invoice['total'], $invoice['subtotal'] + $invoice['service']);
        $this->assertSame(15000, $invoice['change']);
    }

    public function testUnknownPaperCannotRenderArbitraryViews(): void
    {
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->asUser(['auth_user_id' => 1])->get('/dokumen/secret');
    }
}
