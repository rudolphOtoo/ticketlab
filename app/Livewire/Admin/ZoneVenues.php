<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Event;
use App\Models\EventZone;
use App\Models\Subdivision;
use App\Models\User;
use App\Support\ActiveEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Where and when each zone of the working event meets.
 *
 * Organisation admins set every zone; a zone admin edits only their own zone's venue details. Both rules are
 * enforced on the server, in save(), not by what the screen shows.
 */
#[Layout('layouts.admin')]
#[Title('Zone Venues')]
class ZoneVenues extends Component
{
    /** @var array<int|string, array{venue: string, address: string, map_url: string, start_at: string, end_at: string}> */
    public array $forms = [];

    public ?string $flashMessage = null;

    public function mount(): void
    {
        $event = $this->event();

        if ($event === null) {
            return;
        }

        $saved = EventZone::query()
            ->where('organization_id', $event->organization_id)
            ->where('event_id', $event->id)
            ->get()
            ->keyBy('subdivision_id');

        foreach ($this->editableZones()->get() as $zone) {
            $details = $saved->get($zone->id);
            $this->forms[$zone->id] = [
                'venue' => $details->venue ?? '',
                'address' => $details->address ?? '',
                'map_url' => $details->map_url ?? '',
                'start_at' => $details?->start_at?->format('Y-m-d\TH:i') ?? '',
                'end_at' => $details?->end_at?->format('Y-m-d\TH:i') ?? '',
            ];
        }
    }

    public function save(int $zoneId): void
    {
        $event = $this->event();
        // Re-checked on every save: a zone admin can never write another zone, whatever the request carries.
        $zone = $this->editableZones()->whereKey($zoneId)->first();

        if ($event === null || $zone === null) {
            abort(403, 'You can only edit your own ' . strtolower($this->actor()->zoneLabel()) . '.');
        }

        $validated = $this->validate([
            "forms.{$zoneId}.venue" => ['nullable', 'string', 'max:150'],
            "forms.{$zoneId}.address" => ['nullable', 'string', 'max:255'],
            "forms.{$zoneId}.map_url" => ['nullable', 'url:http,https', 'max:2048'],
            "forms.{$zoneId}.start_at" => ['nullable', 'date'],
            "forms.{$zoneId}.end_at" => ['nullable', 'date', "after_or_equal:forms.{$zoneId}.start_at"],
        ])['forms'][$zoneId];

        EventZone::query()->updateOrCreate(['event_id' => $event->id, 'subdivision_id' => $zone->id], [
            'organization_id' => $event->organization_id,
            'venue' => self::blankToNull($validated['venue'] ?? null),
            'address' => self::blankToNull($validated['address'] ?? null),
            'map_url' => self::blankToNull($validated['map_url'] ?? null),
            'start_at' => self::blankToNull($validated['start_at'] ?? null),
            'end_at' => self::blankToNull($validated['end_at'] ?? null),
        ]);

        $this->flashMessage = "{$zone->name} venue saved.";
    }

    public function render(): View
    {
        $user = $this->actor();

        return view('livewire.admin.zone-venues', [
            'event' => $this->event(),
            'zones' => $this->editableZones()->get(),
            'label' => $user->zoneLabel(),
            'isZoneAdmin' => $user->subdivision_id !== null,
        ]);
    }

    /**
     * The organisation admin edits every zone; a zone admin only their own.
     *
     * @return Builder<Subdivision>
     */
    private function editableZones(): Builder
    {
        $user = $this->actor();

        return Subdivision::query()
            ->where('organization_id', $user->organization_id)
            ->when($user->subdivision_id, static fn(Builder $query, int $zoneId) => $query->whereKey($zoneId))
            ->orderBy('name');
    }

    private function event(): ?Event
    {
        return ActiveEvent::resolve($this->actor()->organization_id);
    }

    private function actor(): User
    {
        $user = Auth::user();

        if (!$user instanceof User || $user->organization_id === null || !$user->isAdmin()) {
            abort(403);
        }

        return $user;
    }

    private static function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : trim($value);
    }
}
