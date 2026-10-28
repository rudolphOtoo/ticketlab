<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\ManagesEventFaqs;
use App\Livewire\Admin\Concerns\ManagesEventFormFields;
use App\Livewire\Admin\Concerns\ManagesEventSchedules;
use App\Livewire\Admin\Concerns\ManagesEventSpeakers;
use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.ops')]
#[Title('Event Customizer Studio')]
class EventCustomizer extends Component
{
    use ManagesEventFaqs;
    use ManagesEventFormFields;
    use ManagesEventSchedules;
    use ManagesEventSpeakers;

    #[Locked]
    public ?int $organizationId = null;

    public ?int $selectedEventId = null;

    public string $activeTab = 'brand';

    public ?string $flashMessage = null;

    // Brand, Theme & Hero Configuration
    public string $title = '';

    public string $venue = '';

    public ?string $start_at = null;

    public ?string $end_at = null;

    public string $headline = '';

    public string $tagline = '';

    public string $theme_preset = 'serene';

    public string $accent_hex = '#18181b';

    public string $banner_path = '';

    public bool $show_countdown = true;

    public bool $show_speakers = true;

    public bool $show_schedule = true;

    public bool $show_faqs = true;

    public bool $is_public = true;

    public function mount(?int $eventId = null): void
    {
        $user = auth()->user();
        $this->organizationId = $user?->organization_id;

        $event = null;

        if ($eventId !== null && $this->organizationId !== null) {
            $event = Event::query()->where('organization_id', $this->organizationId)->whereKey($eventId)->first();
        }

        if ($event === null && $this->organizationId !== null) {
            $event = Event::query()->where('organization_id', $this->organizationId)->latest('id')->first();
        }

        if ($event !== null) {
            $this->selectedEventId = $event->id;
            $this->loadEventData($event);
        }
    }

    public function updatedSelectedEventId(int $eventId): void
    {
        $event = $this->getCurrentEvent();

        if ($event !== null) {
            $this->loadEventData($event);
        }
    }

    protected function loadEventData(Event $event): void
    {
        $this->title = $event->title;
        $this->venue = $event->venue ?? '';
        $this->start_at = $event->start_at?->format('Y-m-d\TH:i');
        $this->end_at = $event->end_at?->format('Y-m-d\TH:i');
        $this->banner_path = $event->banner_path ?? '';

        $custom = is_array($event->customization) ? $event->customization : [];
        $this->headline = (string) ($custom['headline'] ?? $event->title);
        $this->tagline = (string) ($custom['tagline'] ?? $event->description ?? '');
        $this->theme_preset = (string) ($custom['theme_preset'] ?? 'serene');
        $this->accent_hex = (string) ($custom['accent_hex'] ?? '#18181b');
        $this->show_countdown = (bool) ($custom['show_countdown'] ?? true);
        $this->show_speakers = (bool) ($custom['show_speakers'] ?? true);
        $this->show_schedule = (bool) ($custom['show_schedule'] ?? true);
        $this->show_faqs = (bool) ($custom['show_faqs'] ?? true);
        $this->is_public = (bool) ($event->is_public ?? true);
    }

    public function saveBrandAndHero(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'venue' => ['nullable', 'string', 'max:150'],
            'start_at' => ['nullable', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'headline' => ['required', 'string', 'max:200'],
            'tagline' => ['nullable', 'string', 'max:500'],
            'theme_preset' => ['required', 'string', 'in:serene,royal,emerald,amber,custom'],
            'accent_hex' => ['required', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'banner_path' => ['nullable', 'string', 'max:2048'],
            'show_countdown' => ['boolean'],
            'show_speakers' => ['boolean'],
            'show_schedule' => ['boolean'],
            'show_faqs' => ['boolean'],
            'is_public' => ['boolean'],
        ]);

        $event = $this->getCurrentEvent();

        if ($event === null) {
            throw ValidationException::withMessages([
                'title' => 'An active event must exist before saving visual styles.',
            ]);
        }

        $customization = [
            'headline' => $this->headline,
            'tagline' => $this->tagline,
            'theme_preset' => $this->theme_preset,
            'accent_hex' => $this->accent_hex,
            'show_countdown' => $this->show_countdown,
            'show_speakers' => $this->show_speakers,
            'show_schedule' => $this->show_schedule,
            'show_faqs' => $this->show_faqs,
        ];

        $event->update([
            'title' => $this->title,
            'venue' => $this->venue,
            'start_at' => filled($this->start_at) ? $this->start_at : null,
            'end_at' => filled($this->end_at) ? $this->end_at : null,
            'banner_path' => $this->banner_path,
            'is_public' => $this->is_public,
            'customization' => $customization,
        ]);

        $this->flashMessage = 'Event branding and hero configuration saved successfully.';
    }

    protected function getCurrentEvent(): ?Event
    {
        if ($this->organizationId === null || $this->selectedEventId === null) {
            return null;
        }

        return Event::query()
            ->where('organization_id', $this->organizationId)
            ->whereKey($this->selectedEventId)
            ->first();
    }

    public function render(): View
    {
        $events = Collection::empty();

        if ($this->organizationId !== null) {
            $events = Event::query()->where('organization_id', $this->organizationId)->orderByDesc('id')->get();
        }

        $currentEvent = $this->getCurrentEvent();

        $speakers = Collection::empty();
        $schedules = Collection::empty();
        $formFields = Collection::empty();
        $faqs = Collection::empty();

        if ($currentEvent !== null) {
            $speakers = $currentEvent->speakers()->get();
            $schedules = $currentEvent->schedules()->get();
            $formFields = $currentEvent->formFields()->get();
            $faqs = $currentEvent->faqs()->get();
        }

        return view('livewire.admin.event-customizer', [
            'events' => $events,
            'currentEvent' => $currentEvent,
            'speakers' => $speakers,
            'schedules' => $schedules,
            'formFields' => $formFields,
            'faqs' => $faqs,
            'isDedicatedMode' => \App\Models\PlatformSetting::isDedicatedMode(),
        ]);
    }
}
