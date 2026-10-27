<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.ops')]
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
                $event = Event::query()
                    ->where('organization_id', $this->organizationId)
                    ->whereKey($eventId)
                    ->first();
            }

            if ($event === null) {
                $event = Event::query()
                    ->where('organization_id', $this->organizationId)
                    ->latest('id')
                    ->first();
            }

            $this->selectedEventId = $event?->id;
        }
    }

    public function exportAttendeeCsv(): StreamedResponse
    {
        $orgId = $this->organizationId;
        $eventId = $this->selectedEventId;

        $event = null;
        $customFormFields = Collection::empty();

        if ($eventId !== null && $orgId !== null) {
            $event = Event::query()
                ->where('organization_id', $orgId)
                ->whereKey($eventId)
                ->first();

            if ($event !== null) {
                $customFormFields = $event->formFields()->get();
            }
        }

        $fileName = 'attendees_' . ($event?->slug ?? 'all') . '_' . now()->format('Y_m_d_His') . '.csv';

        return response()->streamDownload(
            function () use ($orgId, $eventId, $customFormFields): void {
                $writer = SimpleExcelWriter::create('php://output', 'csv');

                $query = Attendee::query()
                    ->with(['ticketType', 'order', 'subdivision'])
                    ->whereHas('ticketType.event', function (Builder $q) use ($orgId, $eventId): void {
                        if ($orgId !== null) {
                            $q->where('organization_id', $orgId);
                        }
                        if ($eventId !== null) {
                            $q->where('id', $eventId);
                        }
                    });

                $query->chunk(500, function ($attendees) use ($writer, $customFormFields): void {
                    foreach ($attendees as $att) {
                        $row = [
                            'Attendee ID' => $att->id,
                            'Name' => $att->name,
                            'Email' => $att->email,
                            'Phone' => $att->phone ?? 'N/A',
                            'Ticket Tier' => $att->ticketType?->name ?? 'N/A',
                            'Price Paid (GHS)' => $att->ticketType ? number_format((float) $att->ticketType->price, 2) : '0.00',
                            'Order Reference' => $att->order?->reference ?? 'N/A',
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
            ['Content-Type' => 'text/csv']
        );
    }

    public function exportJsonBackup(): StreamedResponse
    {
        $orgId = $this->organizationId;
        $org = Organization::query()->find($orgId);

        $orgName = $org?->slug ?? 'organization';
        $fileName = $orgName . '_complete_backup_' . now()->format('Y_m_d_His') . '.json';

        return response()->streamDownload(
            function () use ($orgId, $org): void {
                $events = Collection::empty();

                if ($orgId !== null) {
                    $events = Event::query()
                        ->where('organization_id', $orgId)
                        ->with(['ticketTypes', 'speakers', 'schedules', 'faqs', 'formFields'])
                        ->get();
                }

                $payload = [
                    'generator' => 'TicketLab Data Sovereignty Engine v1.0',
                    'exported_at' => now()->toIso8601String(),
                    'organization' => [
                        'id' => $org?->id,
                        'name' => $org?->name,
                        'slug' => $org?->slug,
                        'created_at' => $org?->created_at?->toIso8601String(),
                    ],
                    'events' => $events->map(function ($ev): array {
                        return [
                            'id' => $ev->id,
                            'title' => $ev->title,
                            'slug' => $ev->slug,
                            'description' => $ev->description,
                            'venue' => $ev->venue,
                            'start_at' => $ev->start_at?->toIso8601String(),
                            'end_at' => $ev->end_at?->toIso8601String(),
                            'status' => $ev->status,
                            'customization' => $ev->customization,
                            'ticket_types' => $ev->ticketTypes->map(fn($t) => [
                                'name' => $t->name,
                                'price' => (float) $t->price,
                                'total_capacity' => $t->total_capacity,
                                'available_quantity' => $t->available_quantity,
                                'perks' => $t->perks,
                            ])->toArray(),
                            'speakers' => $ev->speakers->map(fn($s) => [
                                'name' => $s->name,
                                'title_role' => $s->title_role,
                                'topic' => $s->topic,
                                'bio' => $s->bio,
                                'is_featured' => $s->is_featured,
                            ])->toArray(),
                            'schedules' => $ev->schedules->map(fn($sc) => [
                                'day_label' => $sc->day_label,
                                'session_title' => $sc->session_title,
                                'start_time' => $sc->start_time,
                                'end_time' => $sc->end_time,
                                'venue_hall' => $sc->venue_hall,
                            ])->toArray(),
                            'faqs' => $ev->faqs->map(fn($f) => [
                                'category' => $f->category,
                                'question' => $f->question,
                                'answer' => $f->answer,
                            ])->toArray(),
                            'form_fields' => $ev->formFields->map(fn($ff) => [
                                'label' => $ff->label,
                                'field_key' => $ff->field_key,
                                'input_type' => $ff->input_type,
                                'is_required' => $ff->is_required,
                            ])->toArray(),
                        ];
                    })->toArray(),
                ];

                echo (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            },
            $fileName,
            ['Content-Type' => 'application/json']
        );
    }

    public function render(): View
    {
        $events = Collection::empty();

        if ($this->organizationId !== null) {
            $events = Event::query()
                ->where('organization_id', $this->organizationId)
                ->orderByDesc('id')
                ->get();
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
}
