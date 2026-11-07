<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashflowExporter
{
    private const RUPIAH_FORMAT = '"Rp" #,##0;-"Rp" #,##0';

    /**
     * @param  Collection<int, array<string, mixed>>  $ledger
     * @param  array<string, mixed>  $stats
     */
    public function stream(Collection $ledger, array $stats, CarbonImmutable $from, CarbonImmutable $to, string $filename): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;

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

    /**
     * @param  array<string, mixed>  $stats
     */
    private function writeSummarySheet(Spreadsheet $spreadsheet, array $stats, CarbonImmutable $from, CarbonImmutable $to): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Summary');

        $sheet->setCellValue('A1', 'Brread! — Financial Summary');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);

        $sheet->setCellValue('A2', "Period: {$from->format('d M Y')} – {$to->format('d M Y')}");
        $sheet->getStyle('A2')->getFont()->setItalic(true);

        $rows = [
            ['Opening Balance', $stats['opening_balance']],
            ['Total Income', $stats['total_income']],
            ['Total Expenses', $stats['total_expenses']],
            ['Net Profit / Loss', $stats['net']],
            ['Closing Balance', $stats['closing_balance']],
        ];

        $row = 4;
        foreach ($rows as [$label, $value]) {
            $this->writeMoneyRow($sheet, $row, $label, $value);
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'Transactions');
        $sheet->setCellValue("B{$row}", $stats['transaction_count']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);

        $sheet->getStyle('B7')->getFont()->setBold(true)->setColor(
            new Color($stats['net'] >= 0 ? 'FF1B7A3D' : 'FFB3261E')
        );

        $row += 2;
        $sheet->setCellValue("A{$row}", 'Expenses by Category');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        foreach ($stats['expenses_by_category'] as $category => $amount) {
            $sheet->setCellValueExplicit("A{$row}", $category, DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", $amount);
            $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode(self::RUPIAH_FORMAT);
            $row++;
        }

        foreach (['A', 'B'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }

    private function writeMoneyRow(Worksheet $sheet, int $row, string $label, float $value): void
    {
        $sheet->setCellValue("A{$row}", $label);
        $sheet->setCellValue("B{$row}", $value);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode(self::RUPIAH_FORMAT);
    }

    /**
     * Descriptions and categories are written as explicit strings: staff type them freely,
     * and a value starting with "=" would otherwise run as a formula when the file is opened.
     *
     * @param  Collection<int, array<string, mixed>>  $ledger
     */
    private function writeLedgerSheet(Spreadsheet $spreadsheet, Collection $ledger): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Cashflow');

        $headers = ['Date', 'Type', 'Description', 'Category', 'Amount', 'Balance After'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('A62D42');
        $sheet->getStyle('A1:F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = 2;
        foreach ($ledger as $entry) {
            $sheet->setCellValueExplicit("A{$row}", $entry['date']->format('Y-m-d H:i'), DataType::TYPE_STRING);
            $sheet->setCellValue("B{$row}", $entry['type'] === 'in' ? 'Income' : 'Expense');
            $sheet->setCellValueExplicit("C{$row}", $entry['description'], DataType::TYPE_STRING);
            $sheet->setCellValueExplicit("D{$row}", $entry['category'], DataType::TYPE_STRING);
            $sheet->setCellValue("E{$row}", $entry['type'] === 'in' ? $entry['amount'] : -$entry['amount']);
            $sheet->setCellValue("F{$row}", $entry['balance_after']);
            $sheet->getStyle("E{$row}:F{$row}")->getNumberFormat()->setFormatCode(self::RUPIAH_FORMAT);
            $row++;
        }

        $sheet->getStyle('A1:F'.($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E0D9D4');
        $sheet->freezePane('A2');

        foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
    }
}
