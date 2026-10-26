<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Attendee;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

#[Layout('layouts.ops')]
#[Title('Attendee Roster & Gate Check-Ins')]
class AttendeeRoster extends Component
{
    use WithPagination;

    public string $search = '';

    public string $zoneFilter = '';

    public string $statusFilter = 'all';

    public string $tierFilter = '';

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
        $query = Attendee::query();

        // Enforce zone scoping if user is scoped to a specific subdivision
        if ($user && $user->subdivision_id !== null) {
            $query->where('subdivision_id', $user->subdivision_id);
        }

        $attendee = $query->find($attendeeId);
        if (! $attendee) {
            return;
        }

        if ($attendee->isCheckedIn()) {
            $attendee->update([
                'checked_in_at' => null,
                'checked_in_by' => null,
            ]);
        } else {
            $attendee->update([
                'checked_in_at' => now(),
                'checked_in_by' => $user ? $user->id : null,
            ]);
        }
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
        $fileName = 'attendee_roster_'.now()->format('Y_m_d_His').'.csv';

        return response()->streamDownload(
            function () use ($user) {
                $writer = SimpleExcelWriter::create('php://output', 'csv');

                $this->buildQuery($user)->chunk(500, static function ($attendees) use ($writer) {
                    foreach ($attendees as $att) {
                        $writer->addRow([
                            'ID' => $att->id,
                            'Name' => $att->name,
                            'Email' => $att->email,
                            'Phone' => $att->phone ?? 'N/A',
                            'Zone' => $att->subdivision ? $att->subdivision->name : 'General',
                            'Ticket Tier' => $att->ticketType ? $att->ticketType->name : 'N/A',
                            'QR Code' => $att->qr_code_hash,
                            'Checked In At' => $att->checked_in_at ? $att->checked_in_at->format('Y-m-d H:i:s') : 'Pending',
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
     * @return Builder<Attendee>
     */
    private function buildQuery(?User $user): Builder
    {
        $query = Attendee::with(['subdivision', 'ticketType', 'checkedInByUser']);

        // 1. Enforce subdivision scoping for scoped stewards
        if ($user && $user->subdivision_id !== null) {
            $query->where('subdivision_id', $user->subdivision_id);
        } elseif ($this->zoneFilter !== '') {
            $query->where('subdivision_id', (int) $this->zoneFilter);
        }

        // 2. Status filter
        if ($this->statusFilter === 'checked_in') {
            $query->whereNotNull('checked_in_at');
        } elseif ($this->statusFilter === 'pending') {
            $query->whereNull('checked_in_at');
        }

        // 3. Ticket tier filter
        if ($this->tierFilter !== '') {
            $query->where('ticket_type_id', (int) $this->tierFilter);
        }

        // 4. Search filter
        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
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

        /** @var LengthAwarePaginator<Attendee> $attendees */
        $attendees = $this->buildQuery($user)->paginate($this->perPage);

        // Calculate summary counters for filtered view
        $baseQuery = Attendee::query();
        if ($user && $user->subdivision_id !== null) {
            $baseQuery->where('subdivision_id', $user->subdivision_id);
        }

        $totalCount = (clone $baseQuery)->count();
        $checkedInCount = (clone $baseQuery)->whereNotNull('checked_in_at')->count();
        $pendingCount = $totalCount - $checkedInCount;

        $subdivisions = Subdivision::all();
        $ticketTypes = TicketType::all();

        $selectedAttendee = $this->selectedAttendeeId
            ? Attendee::with(['subdivision', 'ticketType', 'order'])->find($this->selectedAttendeeId)
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
            'scopedZoneName' => $user && $user->subdivision ? $user->subdivision->name : null,
        ]);
    }
}
