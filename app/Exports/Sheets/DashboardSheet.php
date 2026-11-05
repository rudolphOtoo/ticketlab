<?php

declare(strict_types=1);

namespace App\Exports\Sheets;

use App\Exports\Concerns\StoresTextAsText;
use App\Exports\EventAnalytics;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 2: headline metrics, revenue by zone and check-in status, each charted natively from the tables above it,
 * so the charts update if someone edits a figure in Excel.
 *
 * Layout (rows): 1 title, 2 scope, 4-9 key metrics, 11 zone header, 12.. one row per zone,
 * then a blank row, the status header and the two status rows.
 */
final class DashboardSheet implements
    FromArray,
    WithTitle,
    WithCharts,
    WithColumnWidths,
    WithCustomValueBinder,
    WithEvents
{
    use StoresTextAsText;

    public const string TITLE = 'Dashboard';

    private const int ZONE_HEADER_ROW = 11;

    public function __construct(
        private readonly EventAnalytics $analytics,
        private readonly string $scope,
    ) {}

    /**
     * @return list<list<string|int|float|null>>
     */
    public function array(): array
    {
        $rows = [
            ['Event Analytics Report'],
            [$this->scope],
            [null],
            ['Key metrics', 'Value'],
            ['Total revenue (GHS)', $this->analytics->revenue],
            ['Passes issued', $this->analytics->issued],
            ['Checked in', $this->analytics->checkedIn],
            ['Pending', $this->analytics->pending()],
            ['Check-in turnout', $this->analytics->turnout()],
            [null],
            ['Zone', 'Passes issued', 'Revenue (GHS)'],
        ];

        foreach ($this->analytics->zones as $zone) {
            $rows[] = [$zone['zone'], $zone['passes'], $zone['revenue']];
        }

        $rows[] = [null];
        $rows[] = ['Check-in status', 'Passes'];
        $rows[] = ['Checked in', $this->analytics->checkedIn];
        $rows[] = ['Pending', $this->analytics->pending()];

        return $rows;
    }

    public function title(): string
    {
        return self::TITLE;
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        return ['A' => 30, 'B' => 16, 'C' => 18];
    }

    /**
     * @return list<Chart>
     */
    public function charts(): array
    {
        [$firstZone, $lastZone] = $this->zoneRows();
        $statusFirst = $lastZone + 3;

        $revenue = self::chart('Revenue by zone (GHS)', DataSeries::TYPE_BARCHART, 'C', $firstZone, $lastZone);
        $revenue->setTopLeftPosition('E4');
        $revenue->setBottomRightPosition('M20');

        $status = self::chart('Check-in status', DataSeries::TYPE_DOUGHNUTCHART, 'B', $statusFirst, $statusFirst + 1);
        $status->setTopLeftPosition('E22');
        $status->setBottomRightPosition('M38');

        return [$revenue, $status];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        [$firstZone, $lastZone] = $this->zoneRows();
        $statusHeader = $lastZone + 2;

        return [
            AfterSheet::class => static function (AfterSheet $event) use ($firstZone, $lastZone, $statusHeader): void {
                $sheet = $event->sheet->getDelegate();

                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
                $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('71717A');

                foreach ([
                    'A4:B4',
                    'A' . self::ZONE_HEADER_ROW . ':C' . self::ZONE_HEADER_ROW,
                    "A{$statusHeader}:B{$statusHeader}",
                ] as $header) {
                    self::header($sheet, $header);
                }

                $sheet->getStyle('A5:A9')->getFont()->setBold(true);
                $sheet->getStyle('B5')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);
                $sheet->getStyle('B9')->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_0);
                $sheet
                    ->getStyle("C{$firstZone}:C{$lastZone}")
                    ->getNumberFormat()
                    ->setFormatCode(NumberFormat::FORMAT_NUMBER_COMMA_SEPARATED1);

                $sheet->setSelectedCell('A1');
            },
        ];
    }

    /**
     * First and last spreadsheet rows of the revenue-by-zone table.
     *
     * @return array{int, int}
     */
    private function zoneRows(): array
    {
        $first = self::ZONE_HEADER_ROW + 1;

        return [$first, $first + count($this->analytics->zones) - 1];
    }

    private static function header(Worksheet $sheet, string $range): void
    {
        $style = $sheet->getStyle($range);
        $style->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('18181B');
    }

    /**
     * One native chart over rows $first..$last of this sheet: labels from column A, values from $valueColumn.
     */
    private static function chart(string $title, string $type, string $valueColumn, int $first, int $last): Chart
    {
        $sheet = "'" . self::TITLE . "'!";
        $categories = "\$A\${$first}:\$A\${$last}";
        $values = "\${$valueColumn}\${$first}:\${$valueColumn}\${$last}";
        $points = $last - $first + 1;

        $series = new DataSeries(
            plotType: $type,
            plotGrouping: $type === DataSeries::TYPE_BARCHART ? DataSeries::GROUPING_CLUSTERED : null,
            plotOrder: [0],
            plotLabel: [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, null, null, 1, [$title])],
            plotCategory: [new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_STRING,
                $sheet . $categories,
                null,
                $points,
            )],
            plotValues: [new DataSeriesValues(
                DataSeriesValues::DATASERIES_TYPE_NUMBER,
                $sheet . $values,
                null,
                $points,
            )],
        );

        if ($type === DataSeries::TYPE_BARCHART) {
            $series->setPlotDirection(DataSeries::DIRECTION_COL);
        }

        $layout = new Layout();

        if ($type === DataSeries::TYPE_DOUGHNUTCHART) {
            $layout->setShowPercent(true);
        }

        return new Chart(
            name: Str::snake(preg_replace('/[^A-Za-z ]/', '', $title) ?? 'chart'),
            title: new Title($title),
            legend: new Legend(
                $type === DataSeries::TYPE_BARCHART ? Legend::POSITION_BOTTOM : Legend::POSITION_RIGHT,
                null,
                false,
            ),
            plotArea: new PlotArea($layout, [$series]),
        );
    }
}
