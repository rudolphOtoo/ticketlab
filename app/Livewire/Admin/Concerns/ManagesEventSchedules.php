<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use App\Models\EventSchedule;

trait ManagesEventSchedules
{
    public ?int $organizationId = null;

    public ?string $flashMessage = null;

    public bool $isScheduleModalOpen = false;

    public ?int $editingScheduleId = null;

    public string $schedule_day_label = 'Day 1';

    public string $schedule_session_title = '';

    public string $schedule_speaker_name = '';

    public string $schedule_venue_hall = '';

    public string $schedule_start_time = '09:00';

    public string $schedule_end_time = '10:30';

    public string $schedule_description = '';

    public int $schedule_sort_order = 0;

    public function openCreateScheduleModal(): void
    {
        $this->resetValidation();
        $this->editingScheduleId = null;
        $this->schedule_day_label = 'Day 1';
        $this->schedule_session_title = '';
        $this->schedule_speaker_name = '';
        $this->schedule_venue_hall = '';
        $this->schedule_start_time = '09:00';
        $this->schedule_end_time = '10:30';
        $this->schedule_description = '';
        $this->schedule_sort_order = 0;
        $this->isScheduleModalOpen = true;
    }

    public function editSchedule(int $id): void
    {
        $this->resetValidation();
        $schedule = $this->findScopedSchedule($id);

        if ($schedule === null) {
            return;
        }

        $this->editingScheduleId = $schedule->id;
        $this->schedule_day_label = $schedule->day_label;
        $this->schedule_session_title = $schedule->session_title;
        $this->schedule_speaker_name = $schedule->speaker_name ?? '';
        $this->schedule_venue_hall = $schedule->venue_hall ?? '';
        $this->schedule_start_time = $schedule->start_time ?? '09:00';
        $this->schedule_end_time = $schedule->end_time ?? '10:30';
        $this->schedule_description = $schedule->description ?? '';
        $this->schedule_sort_order = (int) $schedule->sort_order;
        $this->isScheduleModalOpen = true;
    }

    public function saveSchedule(): void
    {
        $this->validate([
            'schedule_day_label' => ['required', 'string', 'max:50'],
            'schedule_session_title' => ['required', 'string', 'max:150'],
            'schedule_speaker_name' => ['nullable', 'string', 'max:100'],
            'schedule_venue_hall' => ['nullable', 'string', 'max:100'],
            'schedule_start_time' => ['required', 'string', 'max:20'],
            'schedule_end_time' => ['nullable', 'string', 'max:20'],
            'schedule_description' => ['nullable', 'string', 'max:500'],
            'schedule_sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $event = $this->getCurrentEvent();

        if ($event === null) {
            return;
        }

        if ($this->editingScheduleId !== null) {
            $schedule = $this->findScopedSchedule($this->editingScheduleId);

            if ($schedule !== null) {
                $schedule->update([
                    'day_label' => $this->schedule_day_label,
                    'session_title' => $this->schedule_session_title,
                    'speaker_name' => $this->schedule_speaker_name,
                    'venue_hall' => $this->schedule_venue_hall,
                    'start_time' => $this->schedule_start_time,
                    'end_time' => $this->schedule_end_time,
                    'description' => $this->schedule_description,
                    'sort_order' => $this->schedule_sort_order,
                ]);

                $this->flashMessage = "Program session '{$schedule->session_title}' updated.";
            }
        } else {
            EventSchedule::create([
                'organization_id' => $this->organizationId,
                'event_id' => $event->id,
                'day_label' => $this->schedule_day_label,
                'session_title' => $this->schedule_session_title,
                'speaker_name' => $this->schedule_speaker_name,
                'venue_hall' => $this->schedule_venue_hall,
                'start_time' => $this->schedule_start_time,
                'end_time' => $this->schedule_end_time,
                'description' => $this->schedule_description,
                'sort_order' => $this->schedule_sort_order,
            ]);

            $this->flashMessage = "Program session '{$this->schedule_session_title}' added.";
        }

        $this->isScheduleModalOpen = false;
        $this->editingScheduleId = null;
    }

    public function deleteSchedule(int $id): void
    {
        $schedule = $this->findScopedSchedule($id);

        if ($schedule !== null) {
            $title = $schedule->session_title;
            $schedule->delete();
            $this->flashMessage = "Session '{$title}' was removed.";
        }
    }

    protected function findScopedSchedule(int $id): ?EventSchedule
    {
        if ($this->organizationId === null) {
            return null;
        }

        return EventSchedule::query()
            ->where('organization_id', $this->organizationId)
            ->whereKey($id)
            ->first();
    }
}
