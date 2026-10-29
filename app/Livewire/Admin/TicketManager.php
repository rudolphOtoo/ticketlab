<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Event;
use App\Models\TicketType;
use App\Support\ActiveEvent;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Ticket Tiers & Price Manager')]
class TicketManager extends Component
{
    #[Locked]
    public ?int $organizationId = null;

    public ?int $selectedEventId = null;

    public bool $isModalOpen = false;

    public ?int $editingTicketTypeId = null;

    public string $name = '';

    public string $description = '';

    public string|float $price = '0.00';

    public int $total_capacity = 100;

    public ?string $sales_start_at = null;

    public ?string $sales_end_at = null;

    public bool $is_active = true;

    /**
     * @var list<string>
     */
    public array $perks = [];

    public string $newPerkInput = '';

    public string $search = '';

    public ?string $flashMessage = null;

    public function mount(?int $eventId = null): void
    {
        $user = auth()->user();
        $this->organizationId = $user?->organization_id;

        $event = null;

        if ($eventId !== null && $this->organizationId !== null) {
            $event = Event::query()->where('organization_id', $this->organizationId)->whereKey($eventId)->first();
        }

        $event ??= ActiveEvent::resolve($this->organizationId);

        $this->selectedEventId = $event?->id;
    }

    public function updatedSelectedEventId(): void
    {
        $event = $this->getCurrentEvent();

        if ($event !== null) {
            ActiveEvent::set($event);
        }
    }

    public function openCreateModal(): void
    {
        $this->resetValidation();
        $this->editingTicketTypeId = null;
        $this->name = '';
        $this->description = '';
        $this->price = '0.00';
        $this->total_capacity = 100;
        $this->sales_start_at = now()->format('Y-m-d\TH:i');
        $this->sales_end_at = now()->addMonth()->format('Y-m-d\TH:i');
        $this->is_active = true;
        $this->perks = [];
        $this->newPerkInput = '';
        $this->isModalOpen = true;
    }

    public function editTicketType(int $id): void
    {
        $this->resetValidation();
        $ticketType = $this->findScopedTicketType($id);

        if ($ticketType === null) {
            return;
        }

        $this->editingTicketTypeId = $ticketType->id;
        $this->name = $ticketType->name;
        $this->description = $ticketType->description ?? '';
        $this->price = $ticketType->price;
        $this->total_capacity = $ticketType->total_capacity;
        $this->sales_start_at = $ticketType->sales_start_at?->format('Y-m-d\TH:i');
        $this->sales_end_at = $ticketType->sales_end_at?->format('Y-m-d\TH:i');
        $this->is_active = $ticketType->is_active;
        $this->perks = $ticketType->perks ?? [];
        $this->newPerkInput = '';
        $this->isModalOpen = true;
    }

    public function closeModal(): void
    {
        $this->isModalOpen = false;
        $this->editingTicketTypeId = null;
    }

    public function addPerk(): void
    {
        $trimmed = trim($this->newPerkInput);

        if ($trimmed !== '' && !in_array($trimmed, $this->perks, true)) {
            $this->perks[] = $trimmed;
            $this->newPerkInput = '';
        }
    }

    public function removePerk(int $index): void
    {
        if (array_key_exists($index, $this->perks)) {
            $this->perks = array_values(array_filter(
                $this->perks,
                static fn(int $key): bool => $key !== $index,
                ARRAY_FILTER_USE_KEY,
            ));
        }
    }

    public function toggleActive(int $id): void
    {
        $ticketType = $this->findScopedTicketType($id);

        if ($ticketType === null) {
            return;
        }

        $ticketType->is_active = !$ticketType->is_active;
        $ticketType->save();

        $status = $ticketType->is_active ? 'opened' : 'paused';
        $this->flashMessage = "Sales for '{$ticketType->name}' have been {$status}.";
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'total_capacity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'sales_start_at' => ['nullable', 'date'],
            'sales_end_at' => ['nullable', 'date', 'after_or_equal:sales_start_at'],
            'is_active' => ['boolean'],
            'perks' => ['array'],
            'perks.*' => ['string', 'max:120'],
        ]);

        $event = $this->getCurrentEvent();

        if ($event === null) {
            throw ValidationException::withMessages([
                'selectedEventId' => 'An active event must be selected before creating ticket tiers.',
            ]);
        }

        DB::transaction(fn() => $this->editingTicketTypeId === null
            ? $this->createTicketType($event)
            : $this->updateTicketType($this->editingTicketTypeId));

        $this->closeModal();
    }

    /**
     * Form values shared by create and update; capacity bookkeeping is handled by the callers.
     *
     * @return array<string, mixed>
     */
    private function tierAttributes(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'price' => (float) $this->price,
            'total_capacity' => $this->total_capacity,
            'sales_start_at' => filled($this->sales_start_at) ? $this->sales_start_at : null,
            'sales_end_at' => filled($this->sales_end_at) ? $this->sales_end_at : null,
            'is_active' => $this->is_active,
            'perks' => $this->perks,
        ];
    }

    private function createTicketType(Event $event): void
    {
        $ticketType = TicketType::create([
            ...$this->tierAttributes(),
            'event_id' => $event->id,
            'available_quantity' => $this->total_capacity,
        ]);

        $this->flashMessage = "New ticket tier '{$ticketType->name}' created successfully.";
    }

    /**
     * Must run inside a transaction: the tier row is locked so concurrent sales cannot race the capacity maths.
     */
    private function updateTicketType(int $ticketTypeId): void
    {
        $ticketType = TicketType::query()
            ->whereHas('event', function ($query): void {
                $query->where('organization_id', $this->organizationId);
            })
            ->whereKey($ticketTypeId)
            ->lockForUpdate()
            ->first();

        if ($ticketType === null) {
            return;
        }

        $soldTickets = max(0, $ticketType->total_capacity - $ticketType->available_quantity);

        if ($this->total_capacity < $soldTickets) {
            throw ValidationException::withMessages([
                'total_capacity' => "Cannot reduce capacity below currently sold tickets ({$soldTickets}).",
            ]);
        }

        $capacityDiff = $this->total_capacity - $ticketType->total_capacity;

        $ticketType->update([
            ...$this->tierAttributes(),
            'available_quantity' => max(0, $ticketType->available_quantity + $capacityDiff),
        ]);

        $this->flashMessage = "Ticket tier '{$ticketType->name}' updated successfully.";
    }

    public function deleteTicketType(int $id): void
    {
        $ticketType = $this->findScopedTicketType($id);

        if ($ticketType === null) {
            return;
        }

        if ($ticketType->attendees()->exists() || $ticketType->reservations()->exists()) {
            $this->flashMessage = "Cannot delete '{$ticketType->name}' because tickets or reservations are linked to it. You can pause sales instead.";
            return;
        }

        $name = $ticketType->name;
        $ticketType->delete();

        $this->flashMessage = "Ticket tier '{$name}' was deleted.";
    }

    protected function findScopedTicketType(int $id): ?TicketType
    {
        if ($this->organizationId === null) {
            return null;
        }

        return TicketType::query()
            ->whereHas('event', function ($query): void {
                $query->where('organization_id', $this->organizationId);
            })
            ->whereKey($id)
            ->first();
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

        $ticketTypes = Collection::empty();
        $currentEvent = $this->getCurrentEvent();

        if ($currentEvent !== null) {
            $query = TicketType::query()->where('event_id', $currentEvent->id);

            if ($this->search !== '') {
                $query->where('name', 'like', '%' . $this->search . '%');
            }

            $ticketTypes = $query->withCount('attendees')->orderBy('price')->get();
        }

        return view('livewire.admin.ticket-manager', [
            'events' => $events,
            'currentEvent' => $currentEvent,
            'ticketTypes' => $ticketTypes,
        ]);
    }
}
