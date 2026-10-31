<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Jobs\SendTicketEmail;
use App\Models\Attendee;
use App\Models\Order;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use App\Support\ActiveEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

    public ?string $flashMessage = null;

    // Manual Pass Issuance (Complimentary / VIP / Guest)
    public bool $isManualPassModalOpen = false;
    public string $manual_name = '';
    public string $manual_email = '';
    public string $manual_phone = '';
    public ?int $manual_ticket_type_id = null;
    public ?int $manual_subdivision_id = null;
    /** @var list<string> */
    public array $manual_tags = [];
    public string $newManualTagInput = '';

    // Edit Attendee Modal
    public bool $isEditModalOpen = false;
    public ?int $editingAttendeeId = null;
    public string $edit_name = '';
    public string $edit_email = '';
    public string $edit_phone = '';
    /** @var list<string> */
    public array $edit_tags = [];
    public string $newEditTagInput = '';

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

    public function openManualPassModal(): void
    {
        $this->resetValidation();
        $this->manual_name = '';
        $this->manual_email = '';
        $this->manual_phone = '';
        $this->manual_tags = ['Complimentary'];
        $this->newManualTagInput = '';

        $user = auth()->user();
        $eventId = ActiveEvent::id($user?->organization_id);
        $firstTier = TicketType::query()
            ->when($eventId, fn($q) => $q->where('event_id', $eventId))
            ->first();

        $this->manual_ticket_type_id = $firstTier?->id;
        $this->manual_subdivision_id = $user?->subdivision_id;
        $this->isManualPassModalOpen = true;
    }

    public function closeManualPassModal(): void
    {
        $this->isManualPassModalOpen = false;
    }

    public function addManualTag(): void
    {
        $trimmed = trim($this->newManualTagInput);
        if ($trimmed !== '' && !in_array($trimmed, $this->manual_tags, true)) {
            $this->manual_tags[] = $trimmed;
            $this->newManualTagInput = '';
        }
    }

    public function removeManualTag(int $index): void
    {
        if (array_key_exists($index, $this->manual_tags)) {
            $this->manual_tags = array_values(array_filter(
                $this->manual_tags,
                static fn(int $k): bool => $k !== $index,
                ARRAY_FILTER_USE_KEY,
            ));
        }
    }

    public function issueManualPass(): void
    {
        $user = auth()->user();
        $orgId = (int) $user?->organization_id;

        $this->validate([
            'manual_name' => ['required', 'string', 'max:100'],
            'manual_email' => ['required', 'email', 'max:150'],
            'manual_phone' => ['nullable', 'string', 'max:30'],
            'manual_ticket_type_id' => ['required', 'exists:ticket_types,id'],
            'manual_subdivision_id' => ['nullable', 'exists:subdivisions,id'],
            'manual_tags' => ['array'],
        ]);

        $ticketType = TicketType::query()
            ->whereHas('event', fn($q) => $q->where('organization_id', $orgId))
            ->whereKey($this->manual_ticket_type_id)
            ->first();

        if ($ticketType === null) {
            $this->addError('manual_ticket_type_id', 'Selected ticket category is invalid for this organization.');
            return;
        }

        DB::transaction(function () use ($user, $orgId, $ticketType): void {
            $ref = 'COMP-' . strtoupper(Str::random(10));

            $order = Order::create([
                'organization_id' => $orgId,
                'subdivision_id' => $this->manual_subdivision_id,
                'reference' => $ref,
                'customer_name' => $this->manual_name,
                'customer_email' => $this->manual_email,
                'customer_phone' => $this->manual_phone ?: '0000000000',
                'gross_amount' => 0.00,
                'platform_fee' => 0.00,
                'net_amount' => 0.00,
                'ticket_quantity' => 1,
                'payment_status' => OrderStatus::Paid,
                'payment_method' => PaymentMethod::Complimentary,
                'metadata' => [
                    'source' => 'admin_manual_issue',
                    'issued_by_user_id' => $user?->id,
                    'tags' => $this->manual_tags,
                ],
            ]);

            $qrHash = hash('sha256', implode('|', [
                $order->reference,
                (string) $order->id,
                '0',
                Str::uuid()->toString(),
                (string) now()->getTimestampMs(),
            ]));

            Attendee::create([
                'order_id' => $order->id,
                'organization_id' => $orgId,
                'subdivision_id' => $this->manual_subdivision_id,
                'ticket_type_id' => $ticketType->id,
                'name' => $this->manual_name,
                'email' => $this->manual_email,
                'phone' => $this->manual_phone ?: '0000000000',
                'qr_code_hash' => $qrHash,
                'checked_in_at' => null,
                'checked_in_by' => null,
                'custom_fields' => [
                    'tags' => $this->manual_tags,
                    'issued_by' => $user?->name,
                ],
            ]);

            if ($ticketType->available_quantity > 0) {
                $ticketType->decrement('available_quantity');
            }
        });

        $this->isManualPassModalOpen = false;
        $this->flashMessage = "Complimentary pass issued successfully for '{$this->manual_name}'.";
    }

    public function openEditAttendeeModal(int $attendeeId): void
    {
        $user = auth()->user();
        $attendee = $this->scopedAttendees($user)->whereKey($attendeeId)->first();

        if ($attendee === null) {
            return;
        }

        $this->resetValidation();
        $this->editingAttendeeId = $attendee->id;
        $this->edit_name = $attendee->name;
        $this->edit_email = $attendee->email;
        $this->edit_phone = $attendee->phone;

        $custom = (array) ($attendee->custom_fields ?? []);
        $this->edit_tags = (array) ($custom['tags'] ?? []);
        $this->newEditTagInput = '';
        $this->isEditModalOpen = true;
    }

    public function closeEditAttendeeModal(): void
    {
        $this->isEditModalOpen = false;
        $this->editingAttendeeId = null;
    }

    public function addEditTag(): void
    {
        $trimmed = trim($this->newEditTagInput);
        if ($trimmed !== '' && !in_array($trimmed, $this->edit_tags, true)) {
            $this->edit_tags[] = $trimmed;
            $this->newEditTagInput = '';
        }
    }

    public function removeEditTag(int $index): void
    {
        if (array_key_exists($index, $this->edit_tags)) {
            $this->edit_tags = array_values(array_filter(
                $this->edit_tags,
                static fn(int $k): bool => $k !== $index,
                ARRAY_FILTER_USE_KEY,
            ));
        }
    }

    public function updateAttendee(): void
    {
        $user = auth()->user();

        if ($this->editingAttendeeId === null) {
            return;
        }

        $attendee = $this->scopedAttendees($user)->whereKey($this->editingAttendeeId)->first();

        if ($attendee === null) {
            return;
        }

        $this->validate([
            'edit_name' => ['required', 'string', 'max:100'],
            'edit_email' => ['required', 'email', 'max:150'],
            'edit_phone' => ['nullable', 'string', 'max:30'],
            'edit_tags' => ['array'],
        ]);

        $custom = (array) ($attendee->custom_fields ?? []);
        $custom['tags'] = $this->edit_tags;

        $attendee->update([
            'name' => $this->edit_name,
            'email' => $this->edit_email,
            'phone' => $this->edit_phone ?: $attendee->phone,
            'custom_fields' => $custom,
        ]);

        $this->isEditModalOpen = false;
        $this->editingAttendeeId = null;
        $this->flashMessage = "Attendee '{$attendee->name}' details updated.";
    }

    public function resendPassEmail(int $attendeeId): void
    {
        $user = auth()->user();
        $attendee = $this->scopedAttendees($user)->whereKey($attendeeId)->first();

        if ($attendee === null) {
            return;
        }

        $limiterKey = "resend_pass:{$attendee->id}";

        if (RateLimiter::tooManyAttempts($limiterKey, 3)) {
            $this->flashMessage = "Email throttle active. Please wait " . RateLimiter::availableIn($limiterKey) . " seconds before sending again.";
            return;
        }

        RateLimiter::hit($limiterKey, 60);

        SendTicketEmail::dispatch($attendee->order_id);
        $this->flashMessage = "Ticket email dispatched to '{$attendee->email}'.";
    }

    public function voidPass(int $attendeeId): void
    {
        $user = auth()->user();
        $attendee = $this->scopedAttendees($user)->whereKey($attendeeId)->first();

        if ($attendee === null) {
            return;
        }

        $name = $attendee->name;

        DB::transaction(function () use ($attendee): void {
            if ($attendee->ticketType) {
                $attendee->ticketType->increment('available_quantity');
            }
            $attendee->delete();
        });

        $this->selectedAttendeeId = null;
        $this->flashMessage = "Pass for '{$name}' has been voided and seat restored.";
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
                            'Subdivision' => $att->subdivision ? $att->subdivision->name : 'General',
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
