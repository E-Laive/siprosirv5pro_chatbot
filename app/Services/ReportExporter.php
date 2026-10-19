<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class ReportExporter
{
    private function csvText(mixed $value): string
    {
        $text = (string) $value;
        return preg_match('/^[\x00-\x20]*[=+\-@]/', $text) || preg_match('/^[\t\r\n]/', $text) ? "'" . $text : $text;
    }
    public function csv(array $report): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            throw new \RuntimeException('Stream ekspor CSV tidak tersedia.');
        }

        try {
            // UTF-8 BOM agar Excel membaca karakter UTF-8 dengan benar
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, [$this->csvText($report['title'])], escape: "");
            fputcsv($stream, [$this->csvText($report['note'] ?? 'Laporan Analisis Data')], escape: "");
            fputcsv($stream, [$this->csvText($this->filterLabel($report))], escape: "");
            fputcsv($stream, [], escape: "");

            $headers = [];
            foreach ($report['columns'] as $key => [$label, $type]) {
                $headers[] = $this->csvText($label);
            }
            fputcsv($stream, $headers, escape: "");

            foreach ($report['rows'] as $row) {
                $line = [];
                foreach ($report['columns'] as $key => [$label, $type]) {
                    $val = $row[$key] ?? '';
                    if (in_array($type, ['number', 'currency'], true) && is_numeric($val)) {
                        $line[] = $val;
                    } else {
                        $str = (string) $val;
                        $str = $this->csvText($str);
                        $line[] = $str;
                    }
                }
                fputcsv($stream, $line, escape: "");
            }

            rewind($stream);
            return (string) stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
    }

    public function excel(array $report): string
    {
        $book = new Spreadsheet();
        $book->getProperties()->setCreator('AI ASISTAN')->setTitle($report['title']);
        $book->getDefaultStyle()->getFont()->setName('Calibri')->setSize(11);
        $sheet = $book->getActiveSheet()->setTitle('Laporan');
        $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($report['columns'])));
        $sheet->mergeCells("A1:{$lastColumn}1")->setCellValueExplicit('A1', $report['title'], DataType::TYPE_STRING);
        $sheet->mergeCells("A2:{$lastColumn}2")->setCellValueExplicit('A2', $report['note'], DataType::TYPE_STRING);
        $sheet->mergeCells("A3:{$lastColumn}3")->setCellValueExplicit('A3', $this->filterLabel($report), DataType::TYPE_STRING);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(18);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $index = 1;
        foreach ($report['columns'] as $key => [$label, $type]) {
            $column = Coordinate::stringFromColumnIndex($index++);
            $sheet->setCellValueExplicit($column . '5', $label, DataType::TYPE_STRING);
            $sheet->getColumnDimension($column)->setWidth($type === 'currency' ? 20 : 26);
            foreach ($report['rows'] as $offset => $row) {
                $val = $row[$key] ?? '';
                $isNumericType = in_array($type, ['number', 'currency'], true) && is_numeric($val);
                $sheet->setCellValueExplicit($column . ($offset + 6), $val, $isNumericType ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
            }
            $lastRow = max(6, count($report['rows']) + 5);
            if ($type === 'currency') {
                $sheet->getStyle("{$column}6:{$column}{$lastRow}")->getNumberFormat()->setFormatCode('"Rp" #,##0;[Red]-"Rp" #,##0');
            }
        }
        $sheet->getStyle("A5:{$lastColumn}5")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF176B87');
        $sheet->getStyle("A5:{$lastColumn}5")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getRowDimension(5)->setRowHeight(24);
        $sheet->freezePane('A6')->setAutoFilter("A5:{$lastColumn}" . max(5, count($report['rows']) + 5));
        if ($report['rows'] === []) {
            $sheet->setCellValue('A6', 'Tidak ada data yang sesuai filter.');
        }
        $writer = new Xlsx($book);
        $writer->setPreCalculateFormulas(false);
        ob_start();
        try {
            $writer->save('php://output');
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
            $book->disconnectWorksheets();
        }
    }

    public function pdf(array $report): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml(view('exports/table', ['report' => $report, 'filterLabel' => $this->filterLabel($report)]), 'UTF-8');
        $pdf->setPaper('A4', count($report['columns']) > 5 ? 'landscape' : 'portrait');
        $pdf->render();
        return $pdf->output();
    }

    private function filterLabel(array $report): string
    {
        if (empty($report['filters'])) {
            return 'Filter: semua data';
        }
        $filters = [];
        foreach ($report['filters'] as $label => $value) {
            $filters[] = $label . ': ' . $value;
        }
        return implode(' | ', $filters);
    }
}
