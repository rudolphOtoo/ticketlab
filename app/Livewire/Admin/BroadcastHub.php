<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Jobs\SendEventReminders;
use App\Models\Attendee;
use App\Models\Broadcast;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\User;
use App\Support\ActiveEvent;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.admin')]
#[Title('Email Broadcast Hub')]
class BroadcastHub extends Component
{
    /** Broadcasts an organisation may queue per hour, so a compromised admin cannot turn the platform into a spam relay. */
    public const int HOURLY_LIMIT = 10;

    public string $subject = '';

    public string $body = '';

    public string $audience = 'all';

    public ?string $flashMessage = null;

    public function mount(): void
    {
        $this->actor();
    }

    public function send(): void
    {
        $actor = $this->actor();
        $event = $this->event($actor);

        $this->validate([
            'subject' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::in(Broadcast::AUDIENCES)],
        ]);

        if ($event === null) {
            throw ValidationException::withMessages(['subject' => 'Select an active event before broadcasting.']);
        }

        $limiterKey = "broadcasts:{$event->organization_id}";

        if (RateLimiter::tooManyAttempts($limiterKey, self::HOURLY_LIMIT)) {
            throw ValidationException::withMessages([
                'subject' =>
                    'Broadcast limit reached. Try again in ' . RateLimiter::availableIn($limiterKey) . ' seconds.',
            ]);
        }

        $recipients = $this->recipients($actor, $event)->get();

        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages(['audience' => 'No ticket holders match this audience yet.']);
        }

        RateLimiter::hit($limiterKey, 3600);

        $broadcast = Broadcast::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'user_id' => $actor->id,
            'subject' => $this->subject,
            'body' => $this->body,
            'audience' => $this->audience,
            'recipients_count' => $recipients->count(),
        ]);

        $jobs = $recipients->map(
            static fn(Attendee $recipient): SendEventReminders => new SendEventReminders(
                $broadcast->id,
                $recipient->email,
                $recipient->name,
            ),
        )->all();

        $batch = Bus::batch($jobs)->name("Broadcast: {$this->subject}")->allowFailures()->dispatch();

        $broadcast->update(['batch_id' => $batch->id]);

        $this->reset(['subject', 'body']);
        $this->flashMessage = "Broadcast queued to {$broadcast->recipients_count} recipients.";
    }

    public function cancelBroadcast(int $broadcastId): void
    {
        $broadcast = $this->broadcasts($this->actor())->whereKey($broadcastId)->first();
        $batch = $broadcast?->batch();

        if ($batch !== null && !$batch->finished()) {
            $batch->cancel();
            $this->flashMessage = "Broadcast '{$broadcast->subject}' was cancelled.";
        }
    }

    private function actor(): User
    {
        $user = Auth::user();

        if (!$user instanceof User || $user->organization_id === null) {
            abort(403, 'Broadcasts are sent from inside an organization.');
        }

        return $user;
    }

    private function event(User $actor): ?Event
    {
        return ActiveEvent::resolve($actor->organization_id);
    }

    /**
     * One row per unique email among the event's ticket holders, inside the actor's tenant and zone.
     *
     * @return Builder<Attendee>
     */
    private function recipients(User $actor, Event $event): Builder
    {
        return Attendee::query()
            ->selectRaw('MIN(email) as email, MIN(name) as name')
            ->where('organization_id', $event->organization_id)
            ->whereIn('ticket_type_id', TicketType::query()->select('id')->where('event_id', $event->id))
            ->when($actor->subdivision_id, static fn(Builder $query, int $zoneId) => $query->where(
                'subdivision_id',
                $zoneId,
            ))
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->when($this->audience === 'checked_in', static fn(Builder $query) => $query->whereNotNull('checked_in_at'))
            ->when($this->audience === 'pending', static fn(Builder $query) => $query->whereNull('checked_in_at'))
            ->groupByRaw('LOWER(email)');
    }

    /**
     * @return Builder<Broadcast>
     */
    private function broadcasts(User $actor): Builder
    {
        return Broadcast::query()->where('organization_id', $actor->organization_id);
    }

    public function render(): View
    {
        $actor = $this->actor();
        $event = $this->event($actor);

        $history = $this->broadcasts($actor)->with(['event', 'sender'])->latest('id')->limit(15)->get();
        $batches = $history
            ->mapWithKeys(static fn(Broadcast $broadcast): array => [$broadcast->id => $broadcast->batch()])
            ->filter(static fn(?Batch $batch): bool => $batch !== null);

        return view('livewire.admin.broadcast-hub', [
            'event' => $event,
            'recipientCount' => $event === null
                ? 0
                : DB::query()->fromSub($this->recipients($actor, $event), 'recipients')->count(),
            'history' => $history,
            'batches' => $batches,
        ]);
    }
}
