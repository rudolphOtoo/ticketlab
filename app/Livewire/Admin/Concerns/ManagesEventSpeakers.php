<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use App\Livewire\Concerns\UploadsImages;
use App\Models\EventSpeaker;
use App\Support\MediaUrl;

trait ManagesEventSpeakers
{
    use UploadsImages;

    public ?int $organizationId = null;

    public ?string $flashMessage = null;

    public bool $isSpeakerModalOpen = false;

    public ?int $editingSpeakerId = null;

    public string $speaker_name = '';

    public string $speaker_title_role = '';

    public string $speaker_topic = '';

    public string $speaker_bio = '';

    /** The photo shown in the form: the speaker's current photo or a pasted https link. */
    public string $speaker_photo_path = '';

    public bool $speaker_is_featured = false;

    public int $speaker_sort_order = 0;

    public function openCreateSpeakerModal(): void
    {
        $this->discardPendingImage('speaker_photo');
        $this->resetValidation();
        $this->editingSpeakerId = null;
        $this->speaker_name = '';
        $this->speaker_title_role = '';
        $this->speaker_topic = '';
        $this->speaker_bio = '';
        $this->speaker_photo_path = '';
        $this->speaker_is_featured = false;
        $this->speaker_sort_order = 0;
        $this->isSpeakerModalOpen = true;
    }

    public function editSpeaker(int $id): void
    {
        $this->discardPendingImage('speaker_photo');
        $this->resetValidation();
        $speaker = $this->findScopedSpeaker($id);

        if ($speaker === null) {
            return;
        }

        $this->fill([
            'editingSpeakerId' => $speaker->id,
            'speaker_name' => $speaker->name,
            'speaker_title_role' => $speaker->title_role ?? '',
            'speaker_topic' => $speaker->topic ?? '',
            'speaker_bio' => $speaker->bio ?? '',
            'speaker_photo_path' => $speaker->photo_path ?? '',
            'speaker_is_featured' => $speaker->is_featured,
            'speaker_sort_order' => $speaker->sort_order,
            'isSpeakerModalOpen' => true,
        ]);
    }

    public function saveSpeaker(): void
    {
        $this->validate([
            'speaker_name' => ['required', 'string', 'max:100'],
            'speaker_title_role' => ['nullable', 'string', 'max:100'],
            'speaker_topic' => ['nullable', 'string', 'max:150'],
            'speaker_bio' => ['nullable', 'string', 'max:1000'],
            'speaker_photo_path' => ['nullable', 'string', 'max:2048'],
            'speaker_is_featured' => ['boolean'],
            'speaker_sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $event = $this->getCurrentEvent();

        if ($event === null) {
            return;
        }

        $current = $this->editingSpeakerId === null
            ? null
            : $this->findScopedSpeaker($this->editingSpeakerId)?->photo_path;
        $photo = $this->chosenSpeakerPhoto($current);

        if ($photo === false) {
            $this->addError('speaker_photo_path', 'Paste an https image link, or upload a photo.');

            return;
        }

        $this->speaker_photo_path = $photo ?? '';
        $this->flashMessage = $this->persistSpeaker($event->id, $current) ?? $this->flashMessage;

        $this->pendingImageSaved('speaker_photo');
        $this->isSpeakerModalOpen = false;
        $this->editingSpeakerId = null;
    }

    /**
     * The photo to save: a checked upload wins; otherwise the field, which may only keep the speaker's current photo
     * or name a new https link. False when it names anything else: the field is editable in the browser, so a stored
     * path typed into it (another organisation's image, say) is refused rather than saved or later deleted.
     */
    private function chosenSpeakerPhoto(?string $current): string|false|null
    {
        if ($this->pendingImage('speaker_photo') !== null) {
            return $this->pendingImage('speaker_photo');
        }

        $field = trim($this->speaker_photo_path);

        return match (true) {
            $field === '' => null,
            $field === $current, MediaUrl::isAcceptableLink($field) => $field,
            default => false,
        };
    }

    protected function imageUploadFolder(string $field): ?string
    {
        $event = $field === 'speaker_photo' ? $this->getCurrentEvent() : null;

        return $event === null ? null : MediaUrl::eventDirectory($event->organization_id, $event->id, 'speakers');
    }

    /**
     * Create or update the speaker behind the modal and return the confirmation to flash. $previousPhoto is the
     * speaker's stored photo (from the database, never from the form), deleted once the replacement is saved.
     */
    private function persistSpeaker(int $eventId, ?string $previousPhoto): ?string
    {
        $attributes = [
            'name' => $this->speaker_name,
            'title_role' => $this->speaker_title_role,
            'topic' => $this->speaker_topic,
            'bio' => $this->speaker_bio,
            'photo_path' => $this->speaker_photo_path !== '' ? $this->speaker_photo_path : null,
            'is_featured' => $this->speaker_is_featured,
            'sort_order' => $this->speaker_sort_order,
        ];

        if ($this->editingSpeakerId === null) {
            EventSpeaker::create(['organization_id' => $this->organizationId, 'event_id' => $eventId, ...$attributes]);

            return "Speaker '{$this->speaker_name}' added.";
        }

        $speaker = $this->findScopedSpeaker($this->editingSpeakerId);

        if ($speaker === null) {
            return null;
        }

        $speaker->update($attributes);

        // Only clean up the old upload once the replacement is actually saved on the speaker.
        if ($previousPhoto !== $speaker->photo_path) {
            MediaUrl::deleteStored($previousPhoto);
        }

        return "Speaker '{$speaker->name}' updated.";
    }

    public function closeSpeakerModal(): void
    {
        $this->discardPendingImage('speaker_photo');
        $this->isSpeakerModalOpen = false;
        $this->editingSpeakerId = null;
    }

    public function deleteSpeaker(int $id): void
    {
        $speaker = $this->findScopedSpeaker($id);

        if ($speaker !== null) {
            $name = $speaker->name;
            MediaUrl::deleteStored($speaker->photo_path);
            $speaker->delete();
            $this->flashMessage = "Speaker '{$name}' was removed.";
        }
    }

    protected function findScopedSpeaker(int $id): ?EventSpeaker
    {
        if ($this->organizationId === null) {
            return null;
        }

        return EventSpeaker::query()->where('organization_id', $this->organizationId)->whereKey($id)->first();
    }
}
