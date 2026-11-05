<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashflowExporter
{
    public function stream(Collection $ledger, array $stats, CarbonImmutable $from, CarbonImmutable $to, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet();

        $this->writeSummarySheet($spreadsheet, $stats, $from, $to);
        $this->writeLedgerSheet($spreadsheet, $ledger);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }

    private function writeSummarySheet(Spreadsheet $spreadsheet, array $stats, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Summary');

        $sheet->setCellValue('A1', 'Brread! — Financial Summary');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $sheet->setCellValue('A2', "Period: {$from->format('d M Y')} – {$to->format('d M Y')}");
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $rows = [
            ['Total Income', $stats['total_income']],
            ['Total Expenses', $stats['total_expenses']],
            ['Net Profit / Loss', $stats['net']],
            ['Transactions', $stats['transaction_count']],
        ];

        $row = 4;
        foreach ($rows as [$label, $value]) {
            $sheet->setCellValue("A{$row}", $label);
            $sheet->setCellValue("B{$row}", $value);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            if ($label !== 'Transactions') {
                $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
            }
            $row++;
        }

        $sheet->getStyle("B".($row - 2))->getFont()->setBold(true)->setColor(
            new Color($stats['net'] >= 0 ? 'FF1B7A3D' : 'FFB3261E')
        );

        foreach (['A', 'B'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }

    private function writeLedgerSheet(Spreadsheet $spreadsheet, Collection $ledger): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Cashflow');

        $headers = ['Date', 'Type', 'Description', 'Category', 'Amount', 'Balance After'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFFFF');
        $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FFC74659');
        $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = 2;
        foreach ($ledger as $entry) {
            $sheet->setCellValue("A{$row}", $entry['date']->format('Y-m-d H:i'));
            $sheet->setCellValue("B{$row}", $entry['type'] === 'in' ? 'Income' : 'Expense');
            $sheet->setCellValue("C{$row}", $entry['description']);
            $sheet->setCellValue("D{$row}", $entry['category']);
            $sheet->setCellValue("E{$row}", $entry['type'] === 'in' ? $entry['amount'] : -$entry['amount']);
            $sheet->setCellValue("F{$row}", $entry['balance_after']);
            $sheet->getStyle("E{$row}:F{$row}")->getNumberFormat()->setFormatCode('"Rp" #,##0');
            $row++;
        }

        $sheet->getStyle("A1:F".($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('FFE0D9D4');

        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
    }
}
