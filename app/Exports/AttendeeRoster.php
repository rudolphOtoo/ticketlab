<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Attendee;
use App\Models\EventFormField;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * The attendee roster behind every export format: which passes are in scope, the columns, and each row.
 *
 * Scope is the organisation, narrowed to one event when chosen and always to the admin's zone when zone-bound.
 * Each registration question becomes its own "Custom Field: …" column after the standard ones.
 */
final class AttendeeRoster
{
    /** @var list<string> */
    private const array STANDARD_COLUMNS = [
        'Attendee ID',
        'Name',
        'Email',
        'Phone',
        'Ticket Tier',
        'Zone',
        'Price Paid (GHS)',
        'Order Reference',
        'Status',
        'Checked In At',
    ];

    /** 1-based position of the money column among the standard columns, for number formatting. */
    public const int PRICE_COLUMN = 7;

    /** @var array<string, array{keys: array<int, string>, checkbox: bool}>|null */
    private ?array $customColumns = null;

    public function __construct(
        public readonly int $organizationId,
        public readonly ?int $zoneId,
        public readonly ?int $eventId,
    ) {}

    /**
     * @return Builder<Attendee>
     */
    public function query(): Builder
    {
        $orgId = $this->organizationId;
        $eventId = $this->eventId;

        return Attendee::query()
            ->with(['ticketType', 'order', 'subdivision'])
            ->when($this->zoneId, static fn(Builder $q, int $zone) => $q->where('subdivision_id', $zone))
            ->whereHas('ticketType.event', static function (Builder $q) use ($orgId, $eventId): void {
                $q->where('organization_id', $orgId);
                if ($eventId !== null) {
                    $q->where('id', $eventId);
                }
            });
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [...self::STANDARD_COLUMNS, ...array_keys($this->customColumns())];
    }

    /**
     * One attendee as a row keyed by heading, in heading order.
     *
     * @return array<string, string|int|float|null>
     */
    public function row(Attendee $attendee): array
    {
        $row = [
            'Attendee ID' => $attendee->id,
            'Name' => $attendee->name,
            'Email' => $attendee->email,
            'Phone' => $attendee->phone,
            'Ticket Tier' => $attendee->ticketType->name,
            'Zone' => $attendee->subdivision->name ?? 'No zone',
            'Price Paid (GHS)' => round((float) $attendee->ticketType->price, 2),
            'Order Reference' => $attendee->order->reference,
            'Status' => $attendee->checked_in_at !== null ? 'Checked-In' : 'Pending',
            'Checked In At' => self::checkedInAt($attendee->checked_in_at),
        ];

        foreach ($this->customColumns() as $header => $column) {
            $fieldKey = $column['keys'][$attendee->ticketType->event_id] ?? null;

            // Blank when this attendee's event never asked the question; "N/A" when it was left unanswered.
            $row[$header] = $fieldKey === null
                ? ''
                : self::answer($attendee->answerFor($fieldKey), $column['checkbox']);
        }

        return $row;
    }

    /**
     * One export column per registration question, headed "Custom Field: {label}" so it can never overwrite a
     * standard column. Across a multi-event export, questions sharing a label share a column; each column maps
     * event id => field key. Switched-off questions stay, because passes issued earlier still hold their answers.
     *
     * @return array<string, array{keys: array<int, string>, checkbox: bool}>
     */
    private function customColumns(): array
    {
        if ($this->customColumns !== null) {
            return $this->customColumns;
        }

        $fields = EventFormField::query()
            ->where('organization_id', $this->organizationId)
            ->when($this->eventId, static fn(Builder $q, int $id) => $q->where('event_id', $id))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['event_id', 'field_key', 'label', 'input_type']);

        $keys = [];
        $checkbox = [];

        foreach ($fields as $field) {
            $header = 'Custom Field: ' . trim($field->label);

            // Two questions with the same label on one event each keep their own column.
            if (array_key_exists($field->event_id, $keys[$header] ?? [])) {
                $header .= " ({$field->field_key})";
            }

            $keys[$header][$field->event_id] = $field->field_key;
            $checkbox[$header] = ($checkbox[$header] ?? true) && $field->field_type === 'checkbox';
        }

        $columns = [];

        foreach ($keys as $header => $byEvent) {
            $columns[$header] = ['keys' => $byEvent, 'checkbox' => $checkbox[$header] ?? false];
        }

        return $this->customColumns = $columns;
    }

    private static function checkedInAt(?CarbonInterface $at): string
    {
        return $at === null ? 'N/A' : $at->format('Y-m-d H:i:s');
    }

    /**
     * Questionnaire answers are stored as JSON: confirmations are booleans, multi-choice answers are lists and
     * unanswered questions are missing (an unticked confirmation box counts as "No").
     */
    private static function answer(mixed $value, bool $checkbox = false): string
    {
        return match (true) {
            is_bool($value) => $value ? 'Yes' : 'No',
            $value === null && $checkbox => 'No',
            is_array($value) => implode(', ', array_map(static fn(mixed $option): string => is_scalar($option)
                ? (string) $option
                : '', $value)),
            is_scalar($value) => (string) $value,
            default => 'N/A',
        };
    }
}
