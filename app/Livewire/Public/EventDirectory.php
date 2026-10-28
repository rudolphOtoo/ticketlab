<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.public')]
#[Title('Explore Events & Gatherings')]
class EventDirectory extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $filter = 'all';

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filter = 'all';
    }

    public function render(): View
    {
        $query = Event::query()
            ->with(['organization', 'ticketTypes'])
            ->where('status', 'published')
            ->where('is_public', true);

        if (trim($this->search) !== '') {
            $term = '%' . trim($this->search) . '%';
            $query->where(function (Builder $builder) use ($term): void {
                $builder
                    ->where('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('venue', 'like', $term)
                    ->orWhereHas('organization', function (Builder $orgQuery) use ($term): void {
                        $orgQuery->where('name', 'like', $term);
                    });
            });
        }

        if ($this->filter === 'upcoming') {
            $query->where(function (Builder $builder): void {
                $builder->whereNull('start_at')->orWhere('start_at', '>=', now()->startOfDay());
            });
        } elseif ($this->filter === 'free') {
            $query->whereHas('ticketTypes', function (Builder $builder): void {
                $builder->where('price', '<=', 0);
            });
        } elseif ($this->filter === 'paid') {
            $query->whereHas('ticketTypes', function (Builder $builder): void {
                $builder->where('price', '>', 0);
            });
        }

        /** @var Collection<int, Event> $events */
        $events = $query->orderByRaw('CASE WHEN start_at IS NULL THEN 1 ELSE 0 END, start_at ASC')->latest('id')->get();

        return view('livewire.public.event-directory', [
            'events' => $events,
        ]);
    }
}
