<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Attendee;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use App\Support\ActiveEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.admin')]
#[Title('Attendee Roster & Gate Check-Ins')]
class AttendeeRoster extends Component
{
    use WithPagination;

    public string $search = '';

    public string $zoneFilter = '';

    public string $statusFilter = 'all';

    public string $tierFilter = '';

    #[Locked]
    public int $perPage = 10;

    public ?int $selectedAttendeeId = null;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingZoneFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatingTierFilter(): void
    {
        $this->resetPage();
    }

    public function toggleCheckIn(int $attendeeId): void
    {
        $user = auth()->user();

        // Serialise concurrent toggles on the same pass so each one acts on the committed state.
        DB::transaction(function () use ($user, $attendeeId): void {
            $attendee = $this->scopedAttendees($user)->whereKey($attendeeId)->lockForUpdate()->first();

            if ($attendee === null) {
                return;
            }

            $checkingIn = !$attendee->isCheckedIn();

            $attendee->update([
                'checked_in_at' => $checkingIn ? now() : null,
                'checked_in_by' => $checkingIn ? $user?->id : null,
            ]);
        });
    }

    public function selectAttendee(int $attendeeId): void
    {
        $this->selectedAttendeeId = $attendeeId;
    }

    public function closeDetailModal(): void
    {
        $this->selectedAttendeeId = null;
    }

    public function exportCsv(): StreamedResponse
    {
        $user = auth()->user();
        $fileName = 'attendee_roster_' . now()->format('Y_m_d_His') . '.csv';

        return response()->streamDownload(
            function () use ($user) {
                $writer = SimpleExcelWriter::create('php://output', 'csv');

                $this->buildQuery($user)->chunk(500, static function ($attendees) use ($writer) {
                    foreach ($attendees as $att) {
                        $writer->addRow([
                            'ID' => $att->id,
                            'Name' => self::csvSafe($att->name),
                            'Email' => self::csvSafe($att->email),
                            'Phone' => self::csvSafe($att->phone),
                            'Zone' => $att->subdivision ? $att->subdivision->name : 'General',
                            'Ticket Tier' => $att->ticketType->name,
                            'QR Code' => $att->qr_code_hash,
                            'Checked In At' => $att->checked_in_at?->format('Y-m-d H:i:s') ?? 'Pending',
                            'Checked In By' => $att->checkedInByUser ? $att->checkedInByUser->name : 'N/A',
                        ]);
                    }
                });

                $writer->close();
            },
            $fileName,
            [
                'Content-Type' => 'text/csv',
            ],
        );
    }

    /**
     * Organisation tenant scope plus subdivision scope for zone-bound stewards, limited to the active event.
     *
     * @return Builder<Attendee>
     */
    private function scopedAttendees(?User $user): Builder
    {
        $eventId = ActiveEvent::id($user?->organization_id);

        return Attendee::query()
            ->when($user?->organization_id, static fn($q, $orgId) => $q->where('organization_id', $orgId))
            ->when($user?->subdivision_id, static fn($q, $subId) => $q->where('subdivision_id', $subId))
            ->when($eventId, static fn($q, int $id) => $q->whereIn('ticket_type_id', TicketType::query()
                ->select('id')
                ->where('event_id', $id)));
    }

    /**
     * @return Builder<Attendee>
     */
    private function buildQuery(?User $user): Builder
    {
        $query = $this->scopedAttendees($user)->with(['subdivision', 'ticketType', 'checkedInByUser']);

        // 1. Zone filter (only meaningful for users not already bound to a zone)
        if ($user?->subdivision_id === null && $this->zoneFilter !== '') {
            $query->where('subdivision_id', (int) $this->zoneFilter);
        }

        // 2. Status filter
        match ($this->statusFilter) {
            'checked_in' => $query->whereNotNull('checked_in_at'),
            'pending' => $query->whereNull('checked_in_at'),
            default => null,
        };

        // 3. Ticket tier filter
        if ($this->tierFilter !== '') {
            $query->where('ticket_type_id', (int) $this->tierFilter);
        }

        // 4. Search filter
        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(static function ($q) use ($term) {
                $q
                    ->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('qr_code_hash', 'like', $term);
            });
        }

        return $query->latest();
    }

    public function render(): View
    {
        $user = auth()->user();

        $attendees = $this->buildQuery($user)->paginate($this->perPage);

        // Calculate summary counters for filtered view
        $baseQuery = $this->scopedAttendees($user);

        $totalCount = (clone $baseQuery)->count();
        $checkedInCount = (clone $baseQuery)->whereNotNull('checked_in_at')->count();
        $pendingCount = $totalCount - $checkedInCount;

        $subdivisions = Subdivision::query()->when($user?->organization_id, static fn($q, $orgId) => $q->where(
            'organization_id',
            $orgId,
        ))->get();
        $activeEventId = ActiveEvent::id($user?->organization_id);
        $ticketTypes = TicketType::query()
            ->when($user?->organization_id, static fn($q, $orgId) => $q->whereHas('event', static fn($eq) => $eq->where(
                'organization_id',
                $orgId,
            )))
            ->when($activeEventId, static fn($q, int $id) => $q->where('event_id', $id))
            ->get();

        $selectedAttendee = $this->selectedAttendeeId
            ? $this
                ->scopedAttendees($user)
                ->with(['subdivision', 'ticketType', 'order'])
                ->find($this->selectedAttendeeId)
            : null;

        return view('livewire.admin.attendee-roster', [
            'attendees' => $attendees,
            'totalCount' => $totalCount,
            'checkedInCount' => $checkedInCount,
            'pendingCount' => $pendingCount,
            'subdivisions' => $subdivisions,
            'ticketTypes' => $ticketTypes,
            'selectedAttendee' => $selectedAttendee,
            'isUserScoped' => $user && $user->subdivision_id !== null,
            'scopedZoneName' => $user?->subdivision?->name,
        ]);
    }

    /**
     * Neutralise spreadsheet formula injection in buyer-supplied CSV cells.
     */
    private static function csvSafe(?string $value): string
    {
        if ($value === null || $value === '') {
            return 'N/A';
        }

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'" . $value : $value;
    }
}
