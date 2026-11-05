<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Streams RFC 4180 CSV straight to the response body, one row at a time, so exports of any size run in
 * constant memory. The first row's keys become the header line.
 *
 * Every text cell is neutralised against spreadsheet formula injection: attendee names and questionnaire
 * answers are typed by the public, and a cell starting with `=`, `+`, `-` or `@` would run as a formula
 * when an admin opens the file.
 */
final class CsvWriter
{
    private bool $headerWritten = false;

    /**
     * @param resource $stream
     */
    private function __construct(
        private $stream,
    ) {}

    /**
     * Open a writer on the response output, prefixed with a UTF-8 byte order mark so spreadsheet apps
     * read accented names correctly.
     */
    public static function toOutput(): self
    {
        $stream = fopen('php://output', 'wb');

        if ($stream === false) {
            throw new \RuntimeException('Unable to open the CSV output stream.');
        }

        fwrite($stream, "\u{FEFF}");

        return new self($stream);
    }

    /**
     * @param array<string, string|int|float|null> $row
     */
    public function addRow(array $row): void
    {
        if (!$this->headerWritten) {
            $this->write(array_keys($row));
            $this->headerWritten = true;
        }

        $this->write(array_values($row));
    }

    public function close(): void
    {
        fclose($this->stream);
    }

    /**
     * Prefix text that a spreadsheet would evaluate as a formula so it is shown as plain text instead.
     */
    public static function safe(string|int|float|null $value): string
    {
        // Decimal values in exports are money, so they are always written with two places.
        if (is_float($value)) {
            return number_format($value, 2, '.', '');
        }

        if (!is_string($value)) {
            return (string) $value;
        }

        return preg_match('/^[=+\-@\t\r\n]/', $value) === 1 ? "'" . $value : $value;
    }

    /**
     * @param list<string|int|float|null> $cells
     */
    private function write(array $cells): void
    {
        fputcsv($this->stream, array_map(self::safe(...), $cells), ',', '"', '');
    }
}
