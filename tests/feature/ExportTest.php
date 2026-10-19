<?php

namespace Tests\Feature;

use App\Services\CatalogData;
use App\Services\ReportExporter;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class ExportTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use \Tests\Support\AuthenticatedSession;

    protected $migrate = true;
    protected $refresh = true;
    protected $namespace = 'App';

    private function authenticated(): self
    {
        return $this->asUser(['auth_user_id' => 1, 'auth_display_name' => 'Test', 'auth_role' => 'Administrator']);
    }

    public function testExportRequiresAuthentication(): void
    {
        $result = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->get('/ekspor/produk/excel');
        $result->assertStatus(401);
        $result->assertJSONFragment(['success' => false]);
    }

    public function testAllCatalogReportsDownloadAsRealExcelAndPdf(): void
    {
        foreach (['produk', 'pelanggan', 'transaksi', 'pengguna'] as $dataset) {
            foreach (['excel' => 'PK', 'pdf' => '%PDF-'] as $format => $signature) {
                $result = $this->authenticated()->get("/ekspor/{$dataset}/{$format}");
                $result->assertStatus(200);
                $this->assertStringStartsWith($signature, $result->response()->getBody());
                $this->assertStringContainsString('attachment;', $result->response()->getHeaderLine('Content-Disposition'));
                $this->assertStringContainsString('no-store', $result->response()->getHeaderLine('Cache-Control'));
                $expectedMime = $format === 'excel' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf';
                $this->assertStringStartsWith($expectedMime, $result->response()->getHeaderLine('Content-Type'));
            }
        }
    }

    public function testExcelFilterPreservesNumericCellsAndHeader(): void
    {
        $result = $this->authenticated()->get('/ekspor/produk/excel?q=susu&status=Menipis');
        $result->assertStatus(200);
        $book = $this->readExcel($result->response()->getBody());
        try {
            $sheet = $book->getActiveSheet();
            $this->assertSame('PRD-002', $sheet->getCell('A6')->getValue());
            $this->assertSame(21500, $sheet->getCell('E6')->getValue());
            $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('E6')->getDataType());
            $this->assertSame(6, $sheet->getHighestDataRow());
            $this->assertSame('A6', $sheet->getFreezePane());
            $this->assertStringContainsString('Data contoh', $sheet->getCell('A2')->getValue());
        } finally { $book->disconnectWorksheets(); }
    }

    public function testUserRoleFilterExportsOnlyMatchingExamples(): void
    {
        $result = $this->authenticated()->get('/ekspor/pengguna/excel?role=Kasir');
        $result->assertStatus(200);
        $book = $this->readExcel($result->response()->getBody());
        try {
            $sheet = $book->getActiveSheet();
            $this->assertSame(7, $sheet->getHighestDataRow());
            $this->assertSame('Kasir', $sheet->getCell('C6')->getValue());
            $this->assertSame('Kasir', $sheet->getCell('C7')->getValue());
        } finally { $book->disconnectWorksheets(); }
    }

    public function testEmptyExportIsValidAndExplainsNoResults(): void
    {
        $result = $this->authenticated()->get('/ekspor/produk/excel?q=tidak-ada');
        $result->assertStatus(200);
        $book = $this->readExcel($result->response()->getBody());
        try { $this->assertSame('Tidak ada data yang sesuai filter.', $book->getActiveSheet()->getCell('A6')->getValue()); }
        finally { $book->disconnectWorksheets(); }
        $pdf = $this->authenticated()->get('/ekspor/produk/pdf?q=tidak-ada');
        $pdf->assertStatus(200);
        $this->assertStringStartsWith('%PDF-', $pdf->response()->getBody());
    }

    public function testUnknownReportIsRejected(): void
    {
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->authenticated()->get('/ekspor/rahasia/excel');
    }

    public function testUnknownFormatIsRejected(): void
    {
        $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
        $this->authenticated()->get('/ekspor/produk/csv');
    }

    public function testMalformedFilterIsRejected(): void
    {
        $this->authenticated()->get('/ekspor/produk/excel?q[]=x')->assertStatus(400);
    }

    public function testTextStartingWithEqualsCannotBecomeAnExcelFormula(): void
    {
        $report = (new CatalogData())->report('produk');
        $report['rows'][0]['name'] = '=HYPERLINK("https://example.invalid")';
        $book = $this->readExcel((new ReportExporter())->excel($report));
        try {
            $cell = $book->getActiveSheet()->getCell('B6');
            $this->assertSame(DataType::TYPE_STRING, $cell->getDataType());
            $this->assertSame($report['rows'][0]['name'], $cell->getValue());
        } finally { $book->disconnectWorksheets(); }
    }

    public function testTransactionExportTotalMatchesCatalog(): void
    {
        $report = (new CatalogData())->report('transaksi');
        $this->assertSame(3325000, array_sum(array_column($report['rows'], 'total')));
        $book = $this->readExcel((new ReportExporter())->excel($report));
        try {
            $sheet = $book->getActiveSheet();
            $this->assertSame(3325000, array_sum(array_map(fn ($row) => $sheet->getCell('G' . $row)->getValue(), range(6, 9))));
        } finally { $book->disconnectWorksheets(); }
    }

    private function readExcel(string $bytes): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $file = tempnam(WRITEPATH . 'cache', 'export-test-');
        try {
            file_put_contents($file, $bytes);
            return IOFactory::load($file);
        } finally { unlink($file); }
    }
}
