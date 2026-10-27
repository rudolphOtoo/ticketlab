<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Concerns;

use App\Models\EventFaq;

trait ManagesEventFaqs
{
    public ?int $organizationId = null;

    public ?string $flashMessage = null;

    public bool $isFaqModalOpen = false;

    public ?int $editingFaqId = null;

    public string $faq_question = '';

    public string $faq_answer = '';

    public string $faq_category = 'General';

    public bool $faq_is_published = true;

    public int $faq_sort_order = 0;

    public function openCreateFaqModal(): void
    {
        $this->resetValidation();
        $this->editingFaqId = null;
        $this->faq_question = '';
        $this->faq_answer = '';
        $this->faq_category = 'General';
        $this->faq_is_published = true;
        $this->faq_sort_order = 0;
        $this->isFaqModalOpen = true;
    }

    public function editFaq(int $id): void
    {
        $this->resetValidation();
        $faq = $this->findScopedFaq($id);

        if ($faq === null) {
            return;
        }

        $this->editingFaqId = $faq->id;
        $this->faq_question = $faq->question;
        $this->faq_answer = $faq->answer;
        $this->faq_category = $faq->category ?? 'General';
        $this->faq_is_published = (bool) $faq->is_published;
        $this->faq_sort_order = (int) $faq->sort_order;
        $this->isFaqModalOpen = true;
    }

    public function saveFaq(): void
    {
        $this->validate([
            'faq_question' => ['required', 'string', 'max:250'],
            'faq_answer' => ['required', 'string', 'max:1500'],
            'faq_category' => ['required', 'string', 'max:50'],
            'faq_is_published' => ['boolean'],
            'faq_sort_order' => ['required', 'integer', 'min:0'],
        ]);

        $event = $this->getCurrentEvent();

        if ($event === null) {
            return;
        }

        if ($this->editingFaqId !== null) {
            $faq = $this->findScopedFaq($this->editingFaqId);

            if ($faq !== null) {
                $faq->update([
                    'question' => $this->faq_question,
                    'answer' => $this->faq_answer,
                    'category' => $this->faq_category,
                    'is_published' => $this->faq_is_published,
                    'sort_order' => $this->faq_sort_order,
                ]);

                $this->flashMessage = 'FAQ item updated.';
            }
        } else {
            EventFaq::create([
                'organization_id' => $this->organizationId,
                'event_id' => $event->id,
                'question' => $this->faq_question,
                'answer' => $this->faq_answer,
                'category' => $this->faq_category,
                'is_published' => $this->faq_is_published,
                'sort_order' => $this->faq_sort_order,
            ]);

            $this->flashMessage = 'New FAQ item created.';
        }

        $this->isFaqModalOpen = false;
        $this->editingFaqId = null;
    }

    public function deleteFaq(int $id): void
    {
        $faq = $this->findScopedFaq($id);

        if ($faq !== null) {
            $faq->delete();
            $this->flashMessage = 'FAQ item removed.';
        }
    }

    protected function findScopedFaq(int $id): ?EventFaq
    {
        if ($this->organizationId === null) {
            return null;
        }

        return EventFaq::query()
            ->where('organization_id', $this->organizationId)
            ->whereKey($id)
            ->first();
    }
}
