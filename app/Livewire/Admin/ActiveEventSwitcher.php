<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Event;
use App\Models\User;
use App\Support\ActiveEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Header control that pins the admin's working event; every scoped screen re-reads it from the session.
 */
class ActiveEventSwitcher extends Component
{
    #[Locked]
    public string $returnUrl = '';

    public ?int $eventId = null;

    public function mount(): void
    {
        $this->returnUrl = request()->fullUrl();
        $this->eventId = ActiveEvent::id($this->organizationId());
    }

    public function updatedEventId(mixed $value): void
    {
        $organizationId = $this->organizationId();
        $event = $organizationId === null || !is_numeric($value)
            ? null
            : Event::query()
                ->where('organization_id', $organizationId)
                ->whereKey((int) $value)
                ->first();

        if ($event === null) {
            $this->eventId = ActiveEvent::id($organizationId);

            return;
        }

        ActiveEvent::set($event);

        // Reload the current screen so its components re-mount against the newly selected event.
        $this->redirect($this->returnUrl, navigate: true);
    }

    private function organizationId(): ?int
    {
        $user = Auth::user();

        return $user instanceof User ? $user->organization_id : null;
    }

    public function render(): View
    {
        $organizationId = $this->organizationId();

        $events = $organizationId === null
            ? collect()
            : Event::query()
                ->where('organization_id', $organizationId)
                ->orderByDesc('start_at')
                ->get(['id', 'title', 'status', 'start_at']);

        return view('livewire.admin.active-event-switcher', ['events' => $events]);
    }
}
