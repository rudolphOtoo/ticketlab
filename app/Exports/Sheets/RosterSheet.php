<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Exports\AttendeeRoster;
use App\Exports\Concerns\StoresTextAsText;
use App\Models\Attendee;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomChunkSize;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 1: every pass in scope, one styled row each, with a frozen, filterable header and banded rows.
 *
 * @implements WithMapping<Attendee>
 */
final class RosterSheet implements
    FromQuery,
    WithMapping,
    WithHeadings,
    WithTitle,
    WithStyles,
    WithColumnFormatting,
    ShouldAutoSize,
    WithCustomChunkSize,
    WithCustomValueBinder,
    WithEvents
{
    use StoresTextAsText;

    public const string TITLE = 'Attendee Roster';

    public function __construct(
        private readonly AttendeeRoster $roster,
    ) {}

    public function query(): Builder
    {
        // Chunks are paged with LIMIT/OFFSET, so a unique order keeps every pass in exactly one chunk.
        return $this->roster->query()->orderBy('id');
    }

    public function chunkSize(): int
    {
        return 500;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return $this->roster->headings();
    }

    /**
     * @param Attendee $row
     *
     * @return list<string|int|float|null>
     */
    public function map($row): array
    {
        return array_values($this->roster->row($row));
    }

    public function title(): string
    {
        return self::TITLE;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '18181B']],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function columnFormats(): array
    {
        $price = Coordinate::stringFromColumnIndex(AttendeeRoster::PRICE_COLUMN);

        return [$price => NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => static function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastColumn = $sheet->getHighestColumn();
                $lastRow = max(2, $sheet->getHighestRow());

                $sheet->freezePane('A2');
                $sheet->setAutoFilter("A1:{$lastColumn}{$lastRow}");

                // One banding rule for the whole table instead of a style per row, so large rosters stay light.
                $band = new Conditional()
                    ->setConditionType(Conditional::CONDITION_EXPRESSION)
                    ->addCondition('MOD(ROW(),2)=0');
                // Excel reads a conditional fill from its end colour; set both so every viewer agrees.
                $fill = $band->getStyle()->getFill()->setFillType(Fill::FILL_SOLID);
                $fill->getStartColor()->setRGB('F4F4F5');
                $fill->getEndColor()->setRGB('F4F4F5');
                $sheet->getStyle("A2:{$lastColumn}{$lastRow}")->setConditionalStyles([$band]);
            },
        ];
    }
}
