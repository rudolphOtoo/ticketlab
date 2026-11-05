<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Exports\AttendeeRoster;
use App\Exports\EventAnalyticsExport;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Support\ActiveEvent;
use App\Support\CsvWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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
        [$fileName, $roster] = $this->attendeeExport();

        return response()->streamDownload(
            static function () use ($roster): void {
                $writer = CsvWriter::toOutput();

                // 500 passes at a time, so the download runs in constant memory however large the roster is.
                foreach ($roster->query()->lazy(500) as $attendee) {
                    $writer->addRow($roster->row($attendee));
                }

                $writer->close();
            },
            $fileName . '.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * The Excel analytics report: the styled roster plus a dashboard with native revenue and check-in charts.
     */
    public function exportAnalyticsXlsx(): BinaryFileResponse
    {
        [$fileName, $roster, $scope] = $this->attendeeExport();

        return Excel::download(
            new EventAnalyticsExport($roster, $scope),
            str_replace('attendees_', 'event_analytics_', $fileName) . '.xlsx',
            \Maatwebsite\Excel\Excel::XLSX,
            ['Content-Type' => EventAnalyticsExport::CONTENT_TYPE],
        );
    }

    /**
     * Authorise the export and describe it: the download name (without extension), the roster in scope and a
     * human-readable summary of that scope.
     *
     * @return array{string, AttendeeRoster, string}
     */
    private function attendeeExport(): array
    {
        if ($this->organizationId === null) {
            abort(403, 'Unauthorized organization context.');
        }

        $orgId = $this->organizationId;
        $user = auth()->user();
        $zoneId = $user?->subdivision_id;

        $event = $this->selectedEventId === null
            ? null
            : Event::query()->where('organization_id', $orgId)->whereKey($this->selectedEventId)->first();

        // An event id outside this organisation falls back to an organisation-wide export.
        $fileName = 'attendees_' . ($event !== null ? $event->slug : 'all') . '_' . now()->format('Y_m_d_His');

        // Zone-bound admins always have a zone; everyone else exports every zone.
        $zoneName = $user?->subdivision->name ?? 'All zones';
        $scope = implode(' · ', [
            $event !== null ? $event->title : 'All events',
            $zoneName,
            'Generated ' . now()->format('j M Y, g:i A'),
        ]);

        return [$fileName, new AttendeeRoster($orgId, $zoneId, $event?->id), $scope];
    }

    public function exportJsonBackup(): StreamedResponse
    {
        if ($this->organizationId === null) {
            abort(403, 'Unauthorized organization context.');
        }

        // The backup spans every zone's configuration, so it belongs to organisation-wide admins only.
        $user = auth()->user();

        if (!$user instanceof User || $user->subdivision_id !== null) {
            abort(403, 'Full organization backups are restricted to organization-wide admins.');
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
}
