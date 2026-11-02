<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Broadcast;
use App\Models\User;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Queue health. Tenant admins see and retry only their own broadcast batches; the raw failed-job table
 * (which carries other tenants' payloads) and the global retry/flush controls are reserved for platform super admins.
 */
#[Layout('layouts.admin')]
#[Title('Queue Monitor')]
class QueueMonitor extends Component
{
    public ?string $flashMessage = null;

    public function mount(): void
    {
        $this->actor();
    }

    public function retryBatch(int $broadcastId): void
    {
        $broadcast = $this->broadcasts($this->actor())->whereKey($broadcastId)->first();

        if ($broadcast?->batch_id === null) {
            return;
        }

        Artisan::call('queue:retry-batch', ['id' => [$broadcast->batch_id]]);
        $this->flashMessage = "Failed deliveries for '{$broadcast->subject}' were pushed back onto the queue.";
    }

    public function retryJob(string $uuid): void
    {
        $this->requireSuperAdmin();

        $exitCode = Artisan::call('queue:retry', ['id' => [$uuid]]);
        $this->flashMessage = $exitCode === 0 ? 'Job pushed back onto the queue.' : 'That job could not be retried.';
    }

    public function forgetJob(string $uuid): void
    {
        $this->requireSuperAdmin();

        Artisan::call('queue:forget', ['id' => $uuid]);
        $this->flashMessage = 'Failed job removed.';
    }

    public function flushFailed(): void
    {
        $this->requireSuperAdmin();

        Artisan::call('queue:flush');
        $this->flashMessage = 'All failed jobs were cleared.';
    }

    private function actor(): User
    {
        $user = Auth::user();

        if (!$user instanceof User || !$user->hasOrganizationAccess()) {
            abort(403);
        }

        return $user;
    }

    private function requireSuperAdmin(): void
    {
        if (!$this->actor()->isSuperAdmin()) {
            abort(403, 'Global queue controls are restricted to platform administrators.');
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Broadcast>
     */
    private function broadcasts(User $actor)
    {
        return Broadcast::query()->when(!$actor->isSuperAdmin(), static fn($query) => $query->where(
            'organization_id',
            $actor->organization_id,
        ));
    }

    /**
     * @param Collection<int, \stdClass> $jobs
     *
     * @return Collection<string, int>
     */
    private static function countByJob(Collection $jobs): Collection
    {
        return $jobs
            ->map(static fn(\stdClass $job): string => self::jobName((string) $job->payload))
            ->countBy()
            ->sortDesc();
    }

    private static function jobName(string $payload): string
    {
        $decoded = json_decode($payload, true);

        return class_basename(is_array($decoded) ? (string) ($decoded['displayName'] ?? 'Unknown') : 'Unknown');
    }

    public function render(): View
    {
        $actor = $this->actor();
        $isSuperAdmin = $actor->isSuperAdmin();

        $broadcasts = $this->broadcasts($actor)->with('event')->latest('id')->limit(20)->get();
        $batches = $broadcasts
            ->mapWithKeys(static fn(Broadcast $broadcast): array => [$broadcast->id => $broadcast->batch()])
            ->filter(static fn(?Batch $batch): bool => $batch !== null);

        $failedJobs = $isSuperAdmin
            ? DB::table('failed_jobs')
                ->latest('failed_at')
                ->limit(50)
                ->get(['uuid', 'queue', 'payload', 'exception', 'failed_at'])
                ->map(static fn(\stdClass $job): array => [
                    'uuid' => (string) $job->uuid,
                    'queue' => (string) $job->queue,
                    'failed_at' => (string) $job->failed_at,
                    'name' => self::jobName((string) $job->payload),
                    'error' => Str::limit(Str::before((string) $job->exception, "\n"), 160),
                ])
            : collect();

        return view('livewire.admin.queue-monitor', [
            'isSuperAdmin' => $isSuperAdmin,
            'pendingCount' => DB::table('jobs')->count(),
            'failedCount' => DB::table('failed_jobs')->count(),
            'pendingByJob' => $isSuperAdmin ? self::countByJob(DB::table('jobs')->get(['payload'])) : collect(),
            'failedJobs' => $failedJobs,
            'broadcasts' => $broadcasts,
            'batches' => $batches,
        ]);
    }
}
