<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use App\Models\EventSpeaker;

trait ManagesEventSpeakers
{
    public ?int $organizationId = null;

    public ?string $flashMessage = null;

    public bool $isSpeakerModalOpen = false;

    public ?int $editingSpeakerId = null;

    public string $speaker_name = '';

    public string $speaker_title_role = '';

    public string $speaker_topic = '';

    public string $speaker_bio = '';

    public string $speaker_photo_path = '';

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

        $this->editingSpeakerId = $speaker->id;
        $this->speaker_name = $speaker->name;
        $this->speaker_title_role = $speaker->title_role ?? '';
        $this->speaker_topic = $speaker->topic ?? '';
        $this->speaker_bio = $speaker->bio ?? '';
        $this->speaker_photo_path = $speaker->photo_path ?? '';
        $this->speaker_is_featured = (bool) $speaker->is_featured;
        $this->speaker_sort_order = (int) $speaker->sort_order;
        $this->isSpeakerModalOpen = true;
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

        if ($this->editingSpeakerId !== null) {
            $speaker = $this->findScopedSpeaker($this->editingSpeakerId);

            if ($speaker !== null) {
                $speaker->update([
                    'name' => $this->speaker_name,
                    'title_role' => $this->speaker_title_role,
                    'topic' => $this->speaker_topic,
                    'bio' => $this->speaker_bio,
                    'photo_path' => $this->speaker_photo_path,
                    'is_featured' => $this->speaker_is_featured,
                    'sort_order' => $this->speaker_sort_order,
                ]);

                $this->flashMessage = "Speaker '{$speaker->name}' updated.";
            }
        } else {
            EventSpeaker::create([
                'organization_id' => $this->organizationId,
                'event_id' => $event->id,
                'name' => $this->speaker_name,
                'title_role' => $this->speaker_title_role,
                'topic' => $this->speaker_topic,
                'bio' => $this->speaker_bio,
                'photo_path' => $this->speaker_photo_path,
                'is_featured' => $this->speaker_is_featured,
                'sort_order' => $this->speaker_sort_order,
            ]);

            $this->flashMessage = "Speaker '{$this->speaker_name}' added.";
        }

        $this->isSpeakerModalOpen = false;
        $this->editingSpeakerId = null;
    }

    public function deleteSpeaker(int $id): void
    {
        $speaker = $this->findScopedSpeaker($id);

        if ($speaker !== null) {
            $name = $speaker->name;
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
