<?php

declare(strict_types=1);

namespace App\Exports;

use App\Exports\Sheets\DashboardSheet;
use App\Exports\Sheets\RosterSheet;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithProperties;

/**
 * The Excel analytics report: the styled attendee roster plus a dashboard of headline figures and native charts.
 * Both sheets share one scope, so a zone admin's charts only ever describe their own zone.
 */
final readonly class EventAnalyticsExport implements Export, WithMultipleSheets, WithProperties
{
    public const string CONTENT_TYPE = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(
        private AttendeeRoster $roster,
        private string $scope,
    ) {}

    /**
     * @return list<Export>
     */
    public function sheets(): array
    {
        return [new RosterSheet($this->roster), new DashboardSheet(EventAnalytics::for($this->roster), $this->scope)];
    }

    /**
     * @return array<string, string>
     */
    public function properties(): array
    {
        return [
            'creator' => (string) config('app.name'),
            'title' => 'Event Analytics Report',
            'description' => $this->scope,
        ];
    }
}
