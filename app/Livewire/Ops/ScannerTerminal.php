<?php

declare(strict_types=1);

namespace App\Livewire\Ops;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Support\ActiveEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.ops')]
#[Title('Gate Door Scanner Terminal')]
class ScannerTerminal extends Component
{
    #[Locked]
    public ?int $eventId = null;

    public string $manualSearch = '';

    /** @var array<string, string>|null */
    public ?array $scanResult = null;

    public string $scanState = 'idle'; // 'idle', 'success', 'duplicate', 'invalid'

    public function mount(?int $eventId = null): void
    {
        $this->eventId = $eventId;
    }

    /**
     * Validate an optical QR code hash scanned by the camera.
     */
    public function validatePass(string $qrHash): void
    {
        $cleanHash = trim($qrHash);

        if ($cleanHash === '') {
            return;
        }

        $result = DB::transaction(function () use ($cleanHash) {
            $query = Attendee::with(['ticketType', 'subdivision'])->where('qr_code_hash', $cleanHash)->lockForUpdate();

            if (auth()->check() && auth()->user()?->organization_id) {
                $query->where('organization_id', auth()->user()->organization_id);
            }

            if ($this->eventId) {
                $query->whereHas('ticketType', fn($q) => $q->where('event_id', $this->eventId));
            }

            $attendee = $query->first();

            if (!$attendee) {
                return [
                    'state' => 'invalid',
                    'payload' => [
                        'status' => 'INVALID',
                        'message' => 'Unrecognized or forged ticket credential. Direct guest to Cash Desk.',
                    ],
                ];
            }

            if ($attendee->checked_in_at !== null) {
                return [
                    'state' => 'duplicate',
                    'payload' => [
                        'status' => 'DUPLICATE',
                        'name' => $attendee->name,
                        'tier' => $attendee->ticketType->name,
                        'zone' => $attendee->subdivision->name ?? 'Main Arena',
                        'scanned_at' => $attendee->checked_in_at->format('h:i:s A'),
                        'message' => 'Pass was already scanned earlier today.',
                    ],
                ];
            }

            // Stamp check-in
            $attendee->update([
                'checked_in_at' => now(),
                'checked_in_by' => auth()->id(),
            ]);

            return [
                'state' => 'success',
                'payload' => [
                    'status' => 'VERIFIED',
                    'name' => $attendee->name,
                    'tier' => $attendee->ticketType->name,
                    'zone' => $attendee->subdivision->name ?? 'Main Arena',
                    'scanned_at' => now()->format('h:i:s A'),
                    'message' => 'Admitted to event. Gate clearance granted.',
                ],
            ];
        });

        $this->scanState = $result['state'];
        $this->scanResult = $result['payload'];

        $this->dispatch(match ($result['state']) {
            'success' => 'play-success-sound',
            'duplicate' => 'play-warning-sound',
            default => 'play-error-sound',
        });
    }

    /**
     * Clear the result overlay and resume scanning.
     */
    public function resetScanner(): void
    {
        $this->scanState = 'idle';
        $this->scanResult = null;
        $this->manualSearch = '';
    }

    public function render(): View
    {
        $user = auth()->user();
        $orgId = $user?->organization_id;
        $events = $this->forOrganization(Event::query(), $orgId);
        $event = match (true) {
            $this->eventId !== null => $events->find($this->eventId),
            $orgId !== null => ActiveEvent::resolve($orgId),
            default => $events->latest()->first(),
        };

        $activeSubdivision = $user?->subdivision_id
            ? Subdivision::query()->find($user->subdivision_id)
            : $this->forOrganization(Subdivision::query(), $orgId)->first();

        // Gate turnout covers only passes for the event on this terminal; with no event there is nothing to count.
        $totalCheckedIn = 0;
        $totalExpected = 0;

        if ($event !== null) {
            $attendees = $this->forOrganization(
                Attendee::query(),
                $orgId,
            )->whereIn('ticket_type_id', TicketType::query()->select('id')->where('event_id', $event->id));
            $totalCheckedIn = (clone $attendees)->whereNotNull('checked_in_at')->count();
            $totalExpected = $attendees->count();
        }
        $turnoutPercent = $totalExpected > 0 ? (int) round(($totalCheckedIn / $totalExpected) * 100) : 0;

        return view('livewire.ops.scanner-terminal', [
            'event' => $event,
            'activeSubdivision' => $activeSubdivision,
            'totalCheckedIn' => $totalCheckedIn,
            'totalExpected' => $totalExpected > 0 ? $totalExpected : 1,
            'turnoutPercent' => $turnoutPercent,
        ]);
    }

    /**
     * Constrain a query to the operator's organisation; superadmins without one see every tenant.
     *
     * @template TModel of Model
     *
     * @param Builder<TModel> $query
     * @return Builder<TModel>
     */
    private function forOrganization(Builder $query, ?int $orgId): Builder
    {
        return $orgId === null ? $query : $query->where('organization_id', $orgId);
    }
}
