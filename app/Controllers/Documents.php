<?php

namespace App\Controllers;

use App\Services\SampleInvoice;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;
use Dompdf\Dompdf;
use Dompdf\Options;

final class Documents extends BaseController
{
    private function data(string $format): array
    {
        if (!in_array($format, ['a4', 'struk58', 'struk80'], true)) {
            throw PageNotFoundException::forPageNotFound();
        }
        return ['format' => $format, 'invoice' => (new SampleInvoice())->data()];
    }

    public function preview(string $format): ResponseInterface
    {
        return $this->response->setHeader('Cache-Control', 'private, no-store')->setBody(view('documents/standalone', $this->data($format)));
    }

    public function pdf(string $format): ResponseInterface
    {
        $data = $this->data($format);
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('documents/pdf', $data), 'UTF-8');
        if ($format === 'a4') {
            $pdf->setPaper('A4');
        } else {
            $pdf->setPaper([0, 0, ($format === 'struk58' ? 58 : 80) * 72 / 25.4, 220 * 72 / 25.4]);
        }
        try {
            $content = (new \App\Services\ExportGuard())->run('invoice', $format, count($data['invoice']['lines']), static function () use ($pdf) { $pdf->render(); return $pdf->output(); });
        } catch (\LengthException) {
            return $this->response->setStatusCode(422)->setJSON(['message' => 'Dokumen melebihi batas item.']);
        } catch (\OverflowException $e) {
            return $this->response->setStatusCode(429)->setHeader('Retry-After', $e->getMessage())->setJSON(['message' => 'Batas ekspor tercapai.']);
        } catch (\RuntimeException) {
            return $this->response->setStatusCode(503)->setHeader('Retry-After', '10')->setJSON(['message' => 'Ekspor sedang sibuk.']);
        }
        return $this->response->setContentType('application/pdf')
            ->setHeader('Content-Disposition', 'attachment; filename="contoh-' . $format . '.pdf"')
            ->setHeader('Cache-Control', 'private, no-store')
            ->setHeader('X-Content-Type-Options', 'nosniff')->setBody($content);
    }
}
