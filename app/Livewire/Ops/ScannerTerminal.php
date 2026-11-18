<?php

declare(strict_types=1);

namespace App\Livewire\Ops;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Support\ActiveEvent;
use App\Support\ZoneVenue;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

    /** @var array<string, string|null>|null */
    public ?array $scanResult = null;

    public string $scanState = 'idle'; // 'idle', 'success', 'duplicate', 'wrong_zone', 'invalid'

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

        // The zone rule and the check-in stamp run in one locked transaction, so no request can slip between them.
        $result = DB::transaction(fn(): array => $this->checkIn($cleanHash));

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

        // Only zone-bound staff have a gate zone; organisation admins scan for every zone.
        $activeSubdivision = $user?->subdivision_id
            ? $this->forOrganization(Subdivision::query(), $orgId)->find($user->subdivision_id)
            : null;

        // Gate turnout covers only passes for the event on this terminal; with no event there is nothing to count.
        $totalCheckedIn = 0;
        $totalExpected = 0;

        if ($event !== null) {
            // Zone-bound operators track their own zone's turnout, though the gate admits every zone's passes.
            $attendees = $this
                ->forOrganization(Attendee::query(), $orgId)
                ->whereIn('ticket_type_id', TicketType::query()->select('id')->where('event_id', $event->id))
                ->when($user?->subdivision_id, static fn($q, int $zone) => $q->where('subdivision_id', $zone));
            $totalCheckedIn = (clone $attendees)->whereNotNull('checked_in_at')->count();
            $totalExpected = $attendees->count();
        }
        $turnoutPercent = $totalExpected > 0 ? (int) round(($totalCheckedIn / $totalExpected) * 100) : 0;

        return view('livewire.ops.scanner-terminal', [
            'event' => $event,
            'activeSubdivision' => $activeSubdivision,
            'zoneLabel' => $this->zoneLabel($orgId),
            'totalCheckedIn' => $totalCheckedIn,
            'totalExpected' => $totalExpected > 0 ? $totalExpected : 1,
            'turnoutPercent' => $turnoutPercent,
        ]);
    }

    /**
     * @return array{state: string, payload: array<string, string|null>}
     */
    private function checkIn(string $hash): array
    {
        $user = auth()->user();
        $orgId = $user?->organization_id;

        // Fail-closed: Cannot validate passes without resolved organization context.
        if ($user === null || $orgId === null) {
            return self::result('invalid', 'INVALID', 'Scanner terminal not bound to an active event or organization.');
        }

        $attendee = $this->lockedPass($hash, $orgId, $this->eventId ?? ActiveEvent::id($orgId));

        if ($attendee === null) {
            return self::result(
                'invalid',
                'INVALID',
                'Unrecognized or forged ticket credential. Direct guest to Cash Desk.',
            );
        }

        $zoneLabel = $this->zoneLabel($orgId);
        $gateZoneId = $user->subdivision_id;

        // A zone gate admits only its own zone's passes. Checked here, inside the locked check-in itself,
        // so a crafted Livewire call is refused exactly like a camera scan.
        if ($gateZoneId !== null && $attendee->subdivision_id !== $gateZoneId) {
            Log::warning('Wrong-zone pass refused at the gate.', [
                'organization_id' => $orgId,
                'gate_subdivision_id' => $gateZoneId,
                'pass_subdivision_id' => $attendee->subdivision_id,
                'attendee_id' => $attendee->id,
                'user_id' => $user->id,
            ]);

            return self::passResult(
                'wrong_zone',
                'WRONG ZONE',
                $attendee,
                $zoneLabel,
                self::wrongZoneMessage($attendee, $zoneLabel),
            );
        }

        if ($attendee->checked_in_at !== null) {
            return self::passResult(
                'duplicate',
                'DUPLICATE',
                $attendee,
                $zoneLabel,
                'Pass was already scanned earlier today.',
            );
        }

        $attendee->update(['checked_in_at' => now(), 'checked_in_by' => $user->id]);

        return self::passResult(
            'success',
            'VERIFIED',
            $attendee,
            $zoneLabel,
            $gateZoneId === null
                ? 'Admitted. This pass belongs to ' . self::passZone($attendee, $zoneLabel) . '.'
                : 'Admitted to event. Gate clearance granted.',
        );
    }

    private function lockedPass(string $hash, int $orgId, ?int $eventId): ?Attendee
    {
        return $this
            ->forOrganization(Attendee::query(), $orgId)
            ->with(['ticketType.event', 'subdivision'])
            ->where('qr_code_hash', $hash)
            ->when($eventId, static fn(
                Builder $query,
                int $event,
            ) => $query->whereHas('ticketType', static fn(Builder $tier) => $tier->where('event_id', $event)))
            ->lockForUpdate()
            ->first();
    }

    private static function wrongZoneMessage(Attendee $attendee, string $zoneLabel): string
    {
        $word = strtoupper($zoneLabel);

        return $attendee->subdivision_id === null
            ? "WRONG {$word}: this pass is not assigned to a "
            . strtolower($zoneLabel)
            . '. Send the guest to an organisation admin.'
            : "WRONG {$word}: this pass is for " . self::passZone($attendee, $zoneLabel) . '. Not admitted.';
    }

    private static function passZone(Attendee $attendee, string $zoneLabel): string
    {
        return $attendee->subdivision->name ?? 'No ' . strtolower($zoneLabel);
    }

    /**
     * @return array{state: string, payload: array<string, string|null>}
     */
    private static function passResult(
        string $state,
        string $status,
        Attendee $attendee,
        string $zoneLabel,
        string $message,
    ): array {
        $result = self::result($state, $status, $message);
        $result['payload'] += [
            'name' => $attendee->name,
            'tier' => $attendee->ticketType->name,
            'zone' => self::passZone($attendee, $zoneLabel),
            'venue' => ZoneVenue::for($attendee->ticketType->event, $attendee->subdivision)->place(),
            'scanned_at' => self::scanTime($state, $attendee),
        ];

        return $result;
    }

    /**
     * A duplicate reports when the pass was first admitted; every other result reports this scan.
     */
    private static function scanTime(string $state, Attendee $attendee): string
    {
        $time =
            $state === 'duplicate' && $attendee->checked_in_at !== null
                ? $attendee->checked_in_at
                : CarbonImmutable::now();

        return $time->format('h:i:s A');
    }

    /**
     * @return array{state: string, payload: array<string, string|null>}
     */
    private static function result(string $state, string $status, string $message): array
    {
        return ['state' => $state, 'payload' => ['status' => $status, 'message' => $message]];
    }

    /**
     * The organisation's own word for a zone ("Zone", "Branch", "Campus"…).
     */
    private function zoneLabel(?int $orgId): string
    {
        $label = $orgId === null ? null : Organization::query()->whereKey($orgId)->value('subdivision_label');

        return is_string($label) && trim($label) !== '' ? $label : 'Zone';
    }

    /**
     * Constrain a query to the operator's organisation; users without one match no tenant rows (fail-closed).
     *
     * @template TModel of Model
     *
     * @param Builder<TModel> $query
     * @return Builder<TModel>
     */
    private function forOrganization(Builder $query, ?int $orgId): Builder
    {
        // Fail closed: a user without an organisation matches no tenant rows rather than all of them.
        return $query->where('organization_id', $orgId);
    }
}
