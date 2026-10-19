<?php

namespace App\Controllers;

use App\Services\CatalogData;
use App\Services\ReportExporter;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;

final class Export extends BaseController
{
    public function download(string $dataset, string $format): ResponseInterface
    {
        if (!in_array($format, ['excel', 'pdf'], true)) {
            throw PageNotFoundException::forPageNotFound();
        }
        $filters = [];
        foreach (['q', 'status', 'role'] as $key) {
            $value = $this->request->getGet($key);
            if ($value !== null && (!is_string($value) || mb_strlen($value) > 200)) {
                return $this->response->setStatusCode(400)->setJSON(['message' => 'Filter ekspor tidak valid.']);
            }
            $filters[$key] = trim($value ?? '');
        }
        try {
            $report = (new CatalogData())->report($dataset, $filters['q'], $filters['status'], $filters['role']);
        } catch (InvalidArgumentException) {
            throw PageNotFoundException::forPageNotFound();
        }
        if (!service('accessPolicy')->allows('export.' . $dataset)) {
            return service('accessPolicy')->deny('export.' . $dataset, $this->request, $this->response);
        }
        $exporter = new ReportExporter();
        $extension = $format === 'excel' ? 'xlsx' : 'pdf';
        $mime = $format === 'excel' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf';
        try {
            $content = (new \App\Services\ExportGuard())->run($dataset, $format, count($report['rows']), fn() => $format === 'excel' ? $exporter->excel($report) : $exporter->pdf($report));
        } catch (\LengthException $e) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => $e->getMessage()]);
        } catch (\OverflowException $e) {
            return $this->response->setStatusCode(429)->setHeader('Retry-After', $e->getMessage())->setJSON(['success' => false, 'message' => 'Batas ekspor tercapai. Coba kembali sebentar lagi.']);
        } catch (\RuntimeException) {
            return $this->response->setStatusCode(503)->setHeader('Retry-After', '10')->setJSON(['success' => false, 'message' => 'Ekspor sedang sibuk atau layanan keamanan belum tersedia.']);
        }
        return $this->response->setContentType($mime)
            ->setHeader('Content-Disposition', 'attachment; filename="contoh-' . $dataset . '.' . $extension . '"')
            ->setHeader('Cache-Control', 'private, no-store, max-age=0')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setBody($content);
    }
}
