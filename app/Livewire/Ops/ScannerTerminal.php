<?php

declare(strict_types=1);

namespace App\Livewire\Ops;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Subdivision;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.ops')]
#[Title('Gate Door Scanner Terminal')]
class ScannerTerminal extends Component
{
    public ?int $eventId = null;
    public string $manualSearch = '';
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
        // Trim and clean incoming hash
        $cleanHash = trim($qrHash);

        $attendee = Attendee::with(['ticketType', 'subdivision'])->where('qr_code_hash', $cleanHash)->first();

        if (!$attendee) {
            $this->scanState = 'invalid';
            $this->scanResult = [
                'status' => 'INVALID',
                'message' => 'Unrecognized or forged ticket credential. Direct guest to Cash Desk.',
            ];
            $this->dispatch('play-error-sound');
            return;
        }

        if ($attendee->checked_in_at !== null) {
            $this->scanState = 'duplicate';
            $this->scanResult = [
                'status' => 'DUPLICATE',
                'name' => $attendee->name,
                'tier' => $attendee->ticketType?->name ?? 'General Admission',
                'zone' => $attendee->subdivision?->name ?? 'Main Arena',
                'scanned_at' => $attendee->checked_in_at->format('h:i:s A'),
                'message' => 'Pass was already scanned earlier today.',
            ];
            $this->dispatch('play-warning-sound');
            return;
        }

        // Stamp check-in
        $attendee->update([
            'checked_in_at' => now(),
            'checked_in_by' => auth()->id(),
        ]);

        $this->scanState = 'success';
        $this->scanResult = [
            'status' => 'VERIFIED',
            'name' => $attendee->name,
            'tier' => $attendee->ticketType?->name ?? 'General Admission',
            'zone' => $attendee->subdivision?->name ?? 'Main Arena',
            'scanned_at' => now()->format('h:i:s A'),
            'message' => 'Admitted to event. Gate clearance granted.',
        ];

        $this->dispatch('play-success-sound');
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
        $event = $this->eventId ? Event::find($this->eventId) : Event::latest()->first();

        $activeSubdivision = auth()->user()?->subdivision_id
            ? Subdivision::find(auth()->user()->subdivision_id)
            : Subdivision::first();

        // Calculate gate turnout counts
        $totalCheckedIn = Attendee::whereNotNull('checked_in_at')->count();
        $totalExpected = Attendee::count();
        $turnoutPercent = $totalExpected > 0 ? (int) round(($totalCheckedIn / $totalExpected) * 100) : 0;

        return view('livewire.ops.scanner-terminal', [
            'event' => $event,
            'activeSubdivision' => $activeSubdivision,
            'totalCheckedIn' => $totalCheckedIn,
            'totalExpected' => $totalExpected > 0 ? $totalExpected : 1,
            'turnoutPercent' => $turnoutPercent,
        ]);
    }
}
