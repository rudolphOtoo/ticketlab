<?php

declare(strict_types=1);

namespace App\Support;

use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes an Excel workbook row by row to a file, in constant memory, with the same row shape as CsvWriter:
 * the first row's keys become a bold, frozen header line.
 *
 * Text is always written as a text cell, never a formula, so attendee names and questionnaire answers typed
 * by the public cannot run as formulas when an admin opens the file. Whole numbers and amounts stay numeric
 * so the sheet can be summed and sorted.
 */
final class XlsxWriter
{
    public const string CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    private bool $headerWritten = false;

    private readonly Style $header;

    private readonly Style $amount;

    private function __construct(
        private readonly Writer $writer,
    ) {
        $this->header = new Style(fontBold: true);
        $this->amount = new Style(format: '#,##0.00');
    }

    public static function toFile(string $path, string $sheetName): self
    {
        $writer = new Writer();
        $writer->openToFile($path);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName($sheetName);
        $sheet->setSheetView(new SheetView()->withFreezeRow(2));

        return new self($writer);
    }

    /**
     * @param array<string, string|int|float|null> $row
     */
    public function addRow(array $row): void
    {
        if (!$this->headerWritten) {
            $this->writer->addRow(new Row(array_map(
                fn(string $label): Cell => new StringCell($label, $this->header),
                array_keys($row),
            )));
            $this->headerWritten = true;
        }

        $this->writer->addRow(new Row(array_map($this->cell(...), array_values($row))));
    }

    public function close(): void
    {
        $this->writer->close();
    }

    private function cell(string|int|float|null $value): Cell
    {
        return match (true) {
            $value === null => new EmptyCell(null),
            is_string($value) => new StringCell($value),
            is_float($value) => new NumericCell($value, $this->amount),
            default => new NumericCell($value),
        };
    }
}
