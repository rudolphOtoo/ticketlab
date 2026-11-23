<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\RequiresOrganizationWideAdmin;
use App\Livewire\Concerns\UploadsImages;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\Events\EventLifecycle;
use App\Services\Payment\PayoutReadiness;
use App\Support\ActiveEvent;
use App\Support\DedicatedSite;
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

#[Layout('layouts.admin')]
#[Title('Event Lifecycle Manager')]
class EventManager extends Component
{
    use RequiresOrganizationWideAdmin;
    use UploadsImages;

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

    public ?string $existingBannerUrl = null;

    public ?string $bannerUrlInput = null;

    public ?string $flashMessage = null;

    /** The event whose delete dialog is open; it asks for the event's name. */
    #[Locked]
    public ?int $deletingEventId = null;

    public string $deleteConfirmation = '';

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
        $this->discardPendingImage('banner');
        $this->resetValidation();
        $this->reset([
            'editingEventId',
            'title',
            'slug',
            'slugTouched',
            'description',
            'venue',
            'bannerUrlInput',
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

        $this->discardPendingImage('banner');
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
            'bannerUrlInput' => MediaUrl::isExternal($event->banner_path) ? $event->banner_path : null,
            'existingBannerUrl' => $event->bannerUrl(),
            'isModalOpen' => true,
        ]);
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->editingEventId = null;
        $this->discardPendingImage('banner');
        $this->bannerUrlInput = null;
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
            // Pasted links must be https; uploads arrive already checked through acceptImageUpload().
            'bannerUrlInput' => ['nullable', 'url:https', 'max:2048'],
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

        if ($this->publishBlocked($event)) {
            return;
        }

        $event->fill($attributes)->save();

        $this->saveBanner($event);

        // Only an organisation's first event becomes the working event; later ones never switch the admin away.
        if ($this->editingEventId === null && $this->events()->count() === 1) {
            ActiveEvent::set($event);
        }

        $this->flashMessage = $this->editingEventId === null
            ? "'{$event->title}' was created."
            : "'{$event->title}' was updated.";

        $this->closeModal();
    }

    /**
     * A published event with paid tickets must be able to pay every zone; drafts can be prepared freely.
     */
    private function publishBlocked(Event $event): bool
    {
        if ($this->status !== 'published' || !$event->exists) {
            return false;
        }

        $reason = app(PayoutReadiness::class)->blockingReason($event);

        if ($reason !== null) {
            $this->addError('status', $reason);
        }

        return $reason !== null;
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

    /**
     * Pick the headline event on the organisation's public site; every other published event stays listed.
     */
    public function featureEvent(int $eventId): void
    {
        $event = $this
            ->events()
            ->whereKey($eventId)
            ->where(Event::publiclyVisible(...))
            ->first();

        if ($event === null) {
            $this->flashMessage = 'Only published events can be featured on the public site.';

            return;
        }

        $organization = Organization::query()->findOrFail($event->organization_id);
        $organization->update(['settings' => [...($organization->settings ?? []), 'featured_event_id' => $event->id]]);

        $this->flashMessage = "'{$event->title}' is now the featured event on your public site.";
    }

    public function togglePublished(int $eventId): void
    {
        $event = $this->events()->whereKey($eventId)->first();

        if ($event === null) {
            return;
        }

        if ($event->status !== 'published') {
            $reason = app(PayoutReadiness::class)->blockingReason($event);

            if ($reason !== null) {
                $this->flashMessage = $reason;

                return;
            }
        }

        $event->update(['status' => $event->status === 'published' ? 'draft' : 'published']);
        $this->flashMessage = "'{$event->title}' is now {$event->status}.";
    }

    public function archiveEvent(int $eventId, EventLifecycle $lifecycle): void
    {
        $event = $this->events()->whereKey($eventId)->first();

        if ($event === null) {
            return;
        }

        $lifecycle->archive($event, $this->actor());
        $this->flashMessage =
            "'{$event->title}' is archived: hidden from the public site and no longer on sale. "
            . 'Its passes, ticket pages and reports keep working.';
    }

    public function unarchiveEvent(int $eventId, EventLifecycle $lifecycle): void
    {
        $event = $this->events()->whereKey($eventId)->first();

        if ($event === null) {
            return;
        }

        $lifecycle->unarchive($event, $this->actor());
        $this->flashMessage = "'{$event->title}' is back as {$event->status}.";
    }

    /**
     * Opens the delete dialog, or explains straight away why this event can't be deleted.
     */
    public function confirmDelete(int $eventId, EventLifecycle $lifecycle): void
    {
        $event = $this->events()->whereKey($eventId)->first();

        if ($event === null) {
            return;
        }

        $blocker = $lifecycle->deleteBlocker($event);

        if ($blocker !== null) {
            $this->flashMessage = $blocker;

            return;
        }

        $this->resetValidation('deleteConfirmation');
        $this->deleteConfirmation = '';
        $this->deletingEventId = $event->id;
    }

    public function cancelDelete(): void
    {
        $this->deletingEventId = null;
        $this->deleteConfirmation = '';
    }

    /**
     * Deletes the event once its name has been typed exactly. Refused whenever it has orders, seat holds or passes.
     */
    public function deleteEvent(int $eventId, EventLifecycle $lifecycle): void
    {
        $event = $this->events()->whereKey($eventId)->first();

        if ($event === null) {
            return;
        }

        $blocker = $lifecycle->deleteBlocker($event);

        if ($blocker !== null) {
            $this->cancelDelete();
            $this->flashMessage = $blocker;

            return;
        }

        $this->resetValidation('deleteConfirmation');

        if (trim($this->deleteConfirmation) !== $event->title) {
            $this->addError('deleteConfirmation', 'Type the event name exactly as shown to delete it.');

            return;
        }

        $blocker = $lifecycle->delete($event, $this->actor());
        $this->cancelDelete();
        $this->flashMessage = $blocker ?? "'{$event->title}' was deleted.";
    }

    private function actor(): User
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            abort(403);
        }

        return $user;
    }

    /**
     * An uploaded banner takes precedence over a pasted link. The banner it replaces is deleted from Cloudinary.
     */
    private function saveBanner(Event $event): void
    {
        $previousBanner = $event->banner_path;
        $newBanner = $this->pendingImage('banner') ?? trim($this->bannerUrlInput ?? '');

        if ($newBanner === '' || $newBanner === $previousBanner) {
            return;
        }

        $event->update(['banner_path' => $newBanner]);
        $this->pendingImageSaved('banner');
        MediaUrl::deleteStored($previousBanner);
    }

    /**
     * Banners of a new event go to the organisation's folder (the event has no id yet); edits to the event's own.
     */
    protected function imageUploadFolder(string $field): ?string
    {
        if ($field !== 'banner') {
            return null;
        }

        $organizationId = $this->organizationId();

        return $this->editingEventId === null
            ? MediaUrl::organizationDirectory($organizationId, 'banners')
            : MediaUrl::eventDirectory($organizationId, $this->editingEventId, 'banners');
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
            // Archived events go to the end of the list.
            ->orderByRaw('archived_at is not null')
            ->orderByDesc('start_at')
            ->get();

        return view('livewire.admin.event-manager', [
            'events' => $events,
            'activeEventId' => ActiveEvent::id($organizationId),
            'featuredEventId' => DedicatedSite::featuredEventId(Organization::query()->findOrFail($organizationId)),
        ]);
    }
}
