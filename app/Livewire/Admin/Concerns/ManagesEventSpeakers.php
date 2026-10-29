<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use App\Models\EventSpeaker;
use App\Support\MediaUrl;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

trait ManagesEventSpeakers
{
    use WithFileUploads;

    public ?int $organizationId = null;

    public ?string $flashMessage = null;

    public bool $isSpeakerModalOpen = false;

    public ?int $editingSpeakerId = null;

    public string $speaker_name = '';

    public string $speaker_title_role = '';

    public string $speaker_topic = '';

    public string $speaker_bio = '';

    public string $speaker_photo_path = '';

    public ?TemporaryUploadedFile $speaker_photo = null;

    public bool $speaker_is_featured = false;

    public int $speaker_sort_order = 0;

    public function openCreateSpeakerModal(): void
    {
        $this->resetValidation();
        $this->editingSpeakerId = null;
        $this->speaker_name = '';
        $this->speaker_title_role = '';
        $this->speaker_topic = '';
        $this->speaker_bio = '';
        $this->speaker_photo_path = '';
        $this->speaker_photo = null;
        $this->speaker_is_featured = false;
        $this->speaker_sort_order = 0;
        $this->isSpeakerModalOpen = true;
    }

    public function editSpeaker(int $id): void
    {
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
            'speaker_photo' => null,
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
            'speaker_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'speaker_is_featured' => ['boolean'],
            'speaker_sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $event = $this->getCurrentEvent();

        if ($event === null) {
            return;
        }

        $previousPhoto = null;

        // A fresh upload wins over a pasted URL; uploads live on the public disk under speakers/.
        if ($this->speaker_photo !== null) {
            $previousPhoto = $this->speaker_photo_path;
            $this->speaker_photo_path = MediaUrl::store($this->speaker_photo, "speakers/{$this->organizationId}");
        }

        $this->flashMessage = $this->persistSpeaker($event->id, $previousPhoto) ?? $this->flashMessage;

        $this->isSpeakerModalOpen = false;
        $this->editingSpeakerId = null;
        $this->speaker_photo = null;
    }

    /**
     * Create or update the speaker behind the modal and return the confirmation to flash.
     */
    private function persistSpeaker(int $eventId, ?string $previousPhoto): ?string
    {
        $attributes = [
            'name' => $this->speaker_name,
            'title_role' => $this->speaker_title_role,
            'topic' => $this->speaker_topic,
            'bio' => $this->speaker_bio,
            'photo_path' => $this->speaker_photo_path,
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
        if ($previousPhoto !== $this->speaker_photo_path) {
            MediaUrl::deleteStored($previousPhoto);
        }

        return "Speaker '{$speaker->name}' updated.";
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
