<?php

declare(strict_types=1);

use App\Livewire\Platform\PlatformDashboard;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\SchedulerRun;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Scheduler\SchedulerRunner;
use App\Services\Scheduler\SchedulerTask;
use App\Services\Scheduler\TaskResult;
use Carbon\CarbonImmutable;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/*
 | Every scheduler call leaves a scheduler_runs row; the platform health panel reads them.
 */

const RUNS_TEST_TOKEN = 'fedcba9876543210fedcba9876543210fedcba9876543210fedcba9876543210';

beforeEach(function (): void {
    config([
        'services.scheduler.token' => RUNS_TEST_TOKEN,
        'services.scheduler.per_minute' => 100,
    ]);
    Subdivision::query()->update(['payout_checked_at' => now()]);
});

function call_scheduler(object $test, string $query = ''): Illuminate\Testing\TestResponse
{
    Subdivision::query()->whereNull('payout_checked_at')->update(['payout_checked_at' => now()]);

    return $test->getJson(
        'api/run-scheduler' . $query,
        $query === '' ? ['Authorization' => 'Bearer ' . RUNS_TEST_TOKEN] : [],
    )->assertOk();
}

function due_hold(): Reservation
{
    $organization = Organization::factory()->create();
    $zone = payable_zone($organization);
    $tier = TicketType::factory()->create([
        'event_id' => App\Models\Event::factory()->create(['organization_id' => $organization->id])->id,
    ]);

    return Reservation::factory()
        ->expired()
        ->create([
            'organization_id' => $organization->id,
            'ticket_type_id' => $tier->id,
            'subdivision_id' => $zone->id,
        ]);
}

test('an idle call, a busy run and a locked call each leave one row', function () {
    call_scheduler($this);

    due_hold();
    $lock = Cache::store('database')->lock(SchedulerRunner::LOCK, 65);
    $lock->get();
    call_scheduler($this);
    $lock->release();

    call_scheduler($this);

    $runs = SchedulerRun::query()->orderBy('id')->get();
    expect($runs->pluck('status')->all())
        ->toBe(['idle', 'locked', 'ran'])
        ->and($runs[2]->ran)
        ->toBe(['release_holds'])
        ->and($runs[2]->counts)
        ->toBe(['release_holds' => ['released' => 1]])
        ->and($runs[2]->errors)
        ->toBe(0)
        ->and($runs[2]->finished_at)
        ->not
        ->toBeNull()
        ->and($runs[2]->duration_ms)
        ->toBeInt();
});

test('a busy run is recorded as running while it works, so a killed run stays visible', function () {
    $seen = null;
    $task = new class($seen) implements SchedulerTask {
        public function __construct(
            public ?string &$seen,
        ) {}

        public function name(): string
        {
            return 'release_holds';
        }

        public function isDue(): bool
        {
            return $this->seen === null;
        }

        public function run(CarbonImmutable $deadline): TaskResult
        {
            $this->seen = SchedulerRun::query()->latest('id')->value('status');

            return new TaskResult();
        }
    };

    new SchedulerRunner([$task])->run();

    expect($seen)->toBe('running')->and(SchedulerRun::query()->sole()->status)->toBe('ran');
});

test('errors are stored with their task, shortened, and never stop the run being recorded', function () {
    $failing = new class implements SchedulerTask {
        private bool $done = false;

        public function name(): string
        {
            return 'reconcile';
        }

        public function isDue(): bool
        {
            return !$this->done;
        }

        public function run(CarbonImmutable $deadline): TaskResult
        {
            $this->done = true;

            throw new RuntimeException(str_repeat('x', 1000));
        }
    };

    new SchedulerRunner([$failing])->run();

    $run = SchedulerRun::query()->sole();
    expect($run->status)
        ->toBe('ran')
        ->and($run->errors)
        ->toBe(1)
        ->and($run->error_messages)
        ->toHaveCount(1)
        ->and($run->error_messages[0])
        ->toStartWith('reconcile: xxx')
        ->and(mb_strlen($run->error_messages[0]))
        ->toBeLessThanOrEqual(303);
});

test('cleanup deletes runs older than 14 days and keeps the rest', function () {
    SchedulerRun::query()->create(['status' => 'idle', 'started_at' => now()->subDays(15)]);
    SchedulerRun::query()->create(['status' => 'idle', 'started_at' => now()->subDays(13)]);

    call_scheduler($this)->assertJsonPath('ran', ['cleanup'])->assertJsonPath('counts.cleanup.scheduler_runs', 1);

    // The 13-day-old run and this call's own row remain.
    expect(SchedulerRun::query()->where('started_at', '<', now()->subDays(14))->exists())
        ->toBeFalse()
        ->and(SchedulerRun::query()->count())
        ->toBe(2);
});

test('before the migration has run, the scheduler still works and logs a warning', function () {
    Schema::drop('scheduler_runs');
    Log::spy();

    call_scheduler($this)->assertJsonPath('status', 'idle');

    Log::shouldHaveReceived('warning')->withArgs(
        static fn(string $message): bool => str_contains($message, 'scheduler_runs'),
    );
});

test('the query token appears in no log line and no stored run, even when a task fails', function () {
    $logged = [];
    Event::listen(MessageLogged::class, static function (MessageLogged $event) use (&$logged): void {
        $logged[] = $event->message . json_encode($event->context);
    });
    // A reconcile that breaks part-way: its error is logged and stored.
    $order = App\Models\Order::factory()->create([
        'payment_status' => App\Enums\OrderStatus::Pending,
        'created_at' => now()->subHour(),
    ]);
    app()->instance(App\Services\Payment\PaystackGateway::class, Mockery::mock(App\Services\Payment\PaystackGateway::class, static function ($mock): void {
        $mock->shouldReceive('verify')->andReturn(['status' => 'success']);
        $mock->shouldReceive('handleChargeSuccess')->andThrow(
            new RuntimeException('failed while handling ' . request()->fullUrl()),
        );
    }));

    call_scheduler($this, '?token=' . RUNS_TEST_TOKEN)->assertJsonPath('errors', 1);

    $stored = SchedulerRun::query()->get()->toJson();
    expect($stored)
        ->toContain($order->reference)
        ->not->toContain(RUNS_TEST_TOKEN)->and(implode("\n", $logged))->toContain($order->reference)
        ->not->toContain(RUNS_TEST_TOKEN);
});

describe('platform health panel', function (): void {
    beforeEach(function (): void {
        $this->superAdmin = User::factory()->create()->assignMembership('super_admin', null);
    });

    test('shows when the scheduler last ran, without a warning while runs are recent', function () {
        SchedulerRun::query()->create(['status' => 'idle', 'started_at' => now()->subMinutes(4)]);

        Livewire::actingAs($this->superAdmin)
            ->test(PlatformDashboard::class)
            ->assertSeeHtml('data-scheduler-health')
            ->assertSeeText('Last run 4 minutes ago')
            ->assertSeeText('Last successful run: 4 minutes ago')
            ->assertDontSeeText('No successful scheduler run');
    });

    test('warns once no run has completed for 15 minutes; locked or unfinished runs do not count', function () {
        SchedulerRun::query()->create(['status' => 'ran', 'started_at' => now()->subMinutes(20)]);
        SchedulerRun::query()->create(['status' => 'running', 'started_at' => now()->subMinutes(10)]);
        SchedulerRun::query()->create(['status' => 'locked', 'started_at' => now()->subMinutes(2)]);

        Livewire::actingAs($this->superAdmin)
            ->test(PlatformDashboard::class)
            ->assertSeeText('Last run 2 minutes ago')
            ->assertSeeText('Last successful run: 20 minutes ago')
            ->assertSeeText('No successful scheduler run in the last 15 minutes');
    });

    test('warns when the scheduler has never run, and counts the last day\'s errors', function () {
        Livewire::actingAs($this->superAdmin)
            ->test(PlatformDashboard::class)
            ->assertSeeText('No run recorded yet')
            ->assertSeeText('No successful scheduler run in the last 15 minutes');

        SchedulerRun::query()->create(['status' => 'ran', 'started_at' => now()->subMinute(), 'errors' => 2]);
        SchedulerRun::query()->create(['status' => 'ran', 'started_at' => now()->subDays(2), 'errors' => 5]);

        Livewire::actingAs($this->superAdmin)
            ->test(PlatformDashboard::class)
            ->assertSeeText('2 errors in the last 24 hours')
            ->assertDontSeeText('No successful scheduler run');
    });
});
