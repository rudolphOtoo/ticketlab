<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventFormField;
use App\Models\Organization;
use App\Support\ActiveEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.admin')]
#[Title('Data Export & Sovereignty Central')]
class DataExportCentral extends Component
{
    #[Locked]
    public ?int $organizationId = null;

    public ?int $selectedEventId = null;

    public function mount(?int $eventId = null): void
    {
        $user = auth()->user();
        $this->organizationId = $user?->organization_id;

        if ($this->organizationId !== null) {
            $event = null;
            if ($eventId !== null) {
                $event = Event::query()->where('organization_id', $this->organizationId)->whereKey($eventId)->first();
            }

            $event ??= ActiveEvent::resolve($this->organizationId);

            $this->selectedEventId = $event?->id;
        }
    }

    public function exportAttendeeCsv(): StreamedResponse
    {
        if ($this->organizationId === null) {
            abort(403, 'Unauthorized organization context.');
        }

        $orgId = $this->organizationId;
        $eventId = $this->selectedEventId;

        $event = $eventId === null
            ? null
            : Event::query()->where('organization_id', $orgId)->whereKey($eventId)->first();

        // An event id outside this organisation falls back to an organisation-wide export.
        $eventId = $event?->id;
        $customFormFields = $this->formFieldsFor($event);

        $fileName = 'attendees_' . ($event !== null ? $event->slug : 'all') . '_' . now()->format('Y_m_d_His') . '.csv';

        return response()->streamDownload(
            static function () use ($orgId, $eventId, $customFormFields): void {
                $writer = SimpleExcelWriter::create('php://output', 'csv');

                $query = Attendee::query()
                    ->with(['ticketType', 'order', 'subdivision'])
                    ->whereHas('ticketType.event', static function (Builder $q) use ($orgId, $eventId): void {
                        $q->where('organization_id', $orgId);
                        if ($eventId !== null) {
                            $q->where('id', $eventId);
                        }
                    });

                $query->chunk(500, static function ($attendees) use ($writer, $customFormFields): void {
                    foreach ($attendees as $att) {
                        $row = [
                            'Attendee ID' => $att->id,
                            'Name' => $att->name,
                            'Email' => $att->email,
                            'Phone' => $att->phone,
                            'Ticket Tier' => $att->ticketType->name,
                            'Price Paid (GHS)' => number_format((float) $att->ticketType->price, 2),
                            'Order Reference' => $att->order->reference,
                            'Status' => $att->checked_in_at !== null ? 'Checked-In' : 'Pending',
                            'Checked In At' => $att->checked_in_at?->format('Y-m-d H:i:s') ?? 'N/A',
                        ];

                        $customValues = is_array($att->custom_fields) ? $att->custom_fields : [];

                        foreach ($customFormFields as $field) {
                            $val = $customValues[$field->field_key] ?? null;
                            if (is_array($val)) {
                                $val = implode(', ', $val);
                            }
                            $row[$field->label] = $val ?? 'N/A';
                        }

                        $writer->addRow($row);
                    }
                });

                $writer->close();
            },
            $fileName,
            ['Content-Type' => 'text/csv'],
        );
    }

    public function exportJsonBackup(): StreamedResponse
    {
        if ($this->organizationId === null) {
            abort(403, 'Unauthorized organization context.');
        }

        $orgId = $this->organizationId;
        $org = Organization::query()->findOrFail($orgId);

        $orgName = $org->slug;
        $fileName = $orgName . '_complete_backup_' . now()->format('Y_m_d_His') . '.json';

        return response()->streamDownload(
            static function () use ($orgId, $org): void {
                $events = Event::query()
                    ->where('organization_id', $orgId)
                    ->with(['ticketTypes', 'speakers', 'schedules', 'faqs', 'formFields'])
                    ->get();

                $payload = [
                    'generator' => 'TicketLab Data Sovereignty Engine v1.0',
                    'exported_at' => now()->toIso8601String(),
                    'organization' => [
                        'id' => $org->id,
                        'name' => $org->name,
                        'slug' => $org->slug,
                        'created_at' => $org->created_at?->toIso8601String(),
                    ],
                    'events' => $events->map(static fn($ev): array => [
                        'id' => $ev->id,
                        'title' => $ev->title,
                        'slug' => $ev->slug,
                        'description' => $ev->description,
                        'venue' => $ev->venue,
                        'start_at' => $ev->start_at->toIso8601String(),
                        'end_at' => $ev->end_at->toIso8601String(),
                        'status' => $ev->status,
                        'customization' => $ev->customization,
                        'ticket_types' => $ev->ticketTypes->map(static fn($t) => [
                            'name' => $t->name,
                            'price' => (float) $t->price,
                            'total_capacity' => $t->total_capacity,
                            'available_quantity' => $t->available_quantity,
                            'perks' => $t->perks,
                        ])->toArray(),
                        'speakers' => $ev->speakers->map(static fn($s) => [
                            'name' => $s->name,
                            'title_role' => $s->title_role,
                            'topic' => $s->topic,
                            'bio' => $s->bio,
                            'is_featured' => $s->is_featured,
                        ])->toArray(),
                        'schedules' => $ev->schedules->map(static fn($sc) => [
                            'day_label' => $sc->day_label,
                            'session_title' => $sc->session_title,
                            'start_time' => $sc->start_time,
                            'end_time' => $sc->end_time,
                            'venue_hall' => $sc->venue_hall,
                        ])->toArray(),
                        'faqs' => $ev->faqs->map(static fn($f) => [
                            'category' => $f->category,
                            'question' => $f->question,
                            'answer' => $f->answer,
                        ])->toArray(),
                        'form_fields' => $ev->formFields->map(static fn($ff) => [
                            'label' => $ff->label,
                            'field_key' => $ff->field_key,
                            'input_type' => $ff->input_type,
                            'is_required' => $ff->is_required,
                        ])->toArray(),
                    ])->toArray(),
                ];

                echo (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            },
            $fileName,
            ['Content-Type' => 'application/json'],
        );
    }

    public function render(): View
    {
        $events = Collection::empty();

        if ($this->organizationId !== null) {
            $events = Event::query()->where('organization_id', $this->organizationId)->orderByDesc('id')->get();
        }

        $selectedEvent = null;
        if ($this->selectedEventId !== null && $this->organizationId !== null) {
            $selectedEvent = Event::query()
                ->where('organization_id', $this->organizationId)
                ->whereKey($this->selectedEventId)
                ->first();
        }

        return view('livewire.admin.data-export-central', [
            'events' => $events,
            'selectedEvent' => $selectedEvent,
        ]);
    }

    /**
     * @return Collection<int, EventFormField>
     */
    private function formFieldsFor(?Event $event): Collection
    {
        return $event === null ? new Collection() : $event->formFields()->get();
    }
}
