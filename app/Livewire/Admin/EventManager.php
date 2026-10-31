<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\User;
use App\Support\ActiveEvent;
use App\Support\MediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
#[Title('Event Lifecycle Manager')]
class EventManager extends Component
{
    use WithFileUploads;

    public bool $isModalOpen = false;

    #[Locked]
    public ?int $editingEventId = null;

    public string $title = '';

    public string $slug = '';

    /** Once the admin edits the slug by hand we stop deriving it from the title. */
    public bool $slugTouched = false;

    public string $description = '';

    public string $venue = '';

    public ?string $start_at = null;

    public ?string $end_at = null;

    public string $status = 'draft';

    public bool $is_public = true;

    public ?TemporaryUploadedFile $banner = null;

    public ?string $existingBannerUrl = null;

    public ?string $flashMessage = null;

    public function mount(): void
    {
        $this->organizationId();
    }

    public function updatedTitle(string $value): void
    {
        if (!$this->slugTouched) {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedSlug(string $value): void
    {
        $this->slugTouched = true;
        $this->slug = Str::slug($value);
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->reset([
            'editingEventId',
            'title',
            'slug',
            'slugTouched',
            'description',
            'venue',
            'banner',
            'existingBannerUrl',
        ]);
        $this->start_at = now()->addMonth()->setTime(9, 0)->format('Y-m-d\TH:i');
        $this->end_at = now()->addMonth()->setTime(17, 0)->format('Y-m-d\TH:i');
        $this->status = 'draft';
        $this->is_public = true;
        $this->isModalOpen = true;
    }

    public function editEvent(int $eventId): void
    {
        $event = $this->events()->whereKey($eventId)->first();

        if ($event === null) {
            return;
        }

        $this->resetValidation();
        $this->fill([
            'editingEventId' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'slugTouched' => true,
            'description' => $event->description ?? '',
            'venue' => $event->venue ?? '',
            'start_at' => $event->start_at->format('Y-m-d\TH:i'),
            'end_at' => $event->end_at->format('Y-m-d\TH:i'),
            'status' => $event->status,
            'is_public' => $event->is_public,
            'banner' => null,
            'existingBannerUrl' => $event->bannerUrl(),
            'isModalOpen' => true,
        ]);
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->editingEventId = null;
        $this->banner = null;
    }

    public function save(): void
    {
        $organizationId = $this->organizationId();
        $this->slug = Str::slug($this->slug !== '' ? $this->slug : $this->title);

        $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:160',
                'alpha_dash',
                Rule::unique(Event::class, 'slug')
                    ->where('organization_id', $organizationId)
                    ->ignore($this->editingEventId),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'venue' => ['nullable', 'string', 'max:150'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after_or_equal:start_at'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'is_public' => ['boolean'],
            'banner' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        $event = $this->editingEventId === null
            ? new Event(['organization_id' => $organizationId])
            : $this->events()->whereKey($this->editingEventId)->first();

        if ($event === null) {
            $this->closeModal();

            return;
        }

        $attributes = [
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description !== '' ? $this->description : null,
            'venue' => $this->venue !== '' ? $this->venue : null,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'status' => $this->status,
            'is_public' => $this->is_public,
        ];

        if ($this->banner !== null) {
            $previousBanner = $event->banner_path;
            $attributes['banner_path'] = MediaUrl::store($this->banner, "events/{$organizationId}/banners");
            MediaUrl::deleteStored($previousBanner);
        }

        $event->fill($attributes)->save();

        // A brand new organisation has nothing to scope to yet, so its first event becomes the working event.
        if ($this->editingEventId === null && ActiveEvent::id($organizationId) === $event->id) {
            ActiveEvent::set($event);
        }

        $this->flashMessage = $this->editingEventId === null
            ? "'{$event->title}' was created."
            : "'{$event->title}' was updated.";

        $this->closeModal();
    }

    public function activate(int $eventId): void
    {
        $event = $this->events()->whereKey($eventId)->first();

        if ($event === null) {
            return;
        }

        ActiveEvent::set($event);

        $this->redirectRoute('admin.events', navigate: true);
    }

    public function togglePublished(int $eventId): void
    {
        $event = $this->events()->whereKey($eventId)->first();

        if ($event === null) {
            return;
        }

        $event->update(['status' => $event->status === 'published' ? 'draft' : 'published']);
        $this->flashMessage = "'{$event->title}' is now {$event->status}.";
    }

    public function deleteEvent(int $eventId): void
    {
        $event = $this->events()->whereKey($eventId)->first();

        if ($event === null) {
            return;
        }

        $tierIds = $event->ticketTypes()->select('id');

        // Issued passes are financial records; an event that has sold anything can only be unpublished.
        if (Attendee::query()->whereIn('ticket_type_id', $tierIds)->exists()) {
            $this->flashMessage = "'{$event->title}' has issued passes and cannot be deleted. Move it to draft instead.";

            return;
        }

        MediaUrl::deleteStored($event->banner_path);
        $title = $event->title;
        $event->delete();

        $this->flashMessage = "'{$title}' was deleted.";
    }

    private function organizationId(): int
    {
        $user = Auth::user();

        if (!$user instanceof User || $user->organization_id === null) {
            abort(403, 'Events are managed inside an organization.');
        }

        return $user->organization_id;
    }

    /**
     * @return Builder<Event>
     */
    private function events(): Builder
    {
        return Event::query()->where('organization_id', $this->organizationId());
    }

    public function render(): View
    {
        $organizationId = $this->organizationId();

        $events = $this
            ->events()
            ->withCount('ticketTypes')
            ->withSum('ticketTypes as capacity_total', 'total_capacity')
            ->orderByDesc('start_at')
            ->get();

        return view('livewire.admin.event-manager', [
            'events' => $events,
            'activeEventId' => ActiveEvent::id($organizationId),
        ]);
    }
}
