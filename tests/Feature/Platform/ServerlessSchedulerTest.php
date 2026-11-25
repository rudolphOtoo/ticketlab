<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use App\Enums\OrganizationStatus;
use App\Enums\ReservationStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Subdivision;
use App\Models\TicketType;
use App\Services\Scheduler\SchedulerRunner;
use App\Services\Scheduler\SchedulerTask;
use App\Services\Scheduler\TaskResult;
use App\Support\Scheduler\SchedulerBudget;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/*
 | /api/run-scheduler runs every due task in this request (SchedulerRunner): idle answers at once, busy runs take one
 | lock and one deadline. The endpoint's auth, throttle and maintenance answers are in SchedulerEndpointTest.
 */

beforeEach(function (): void {
    config([
        'services.scheduler.token' => str_repeat('t', 64),
        'services.scheduler.per_minute' => 100,
    ]);
    // The factory's zones count as checked, so only what a test creates is due.
    Subdivision::query()->update(['payout_checked_at' => now()]);
});

function run_scheduler(object $test): Illuminate\Testing\TestResponse
{
    // Factories add zones whose payout account was never re-checked; only the payout tests want that work.
    Subdivision::query()->whereNull('payout_checked_at')->update(['payout_checked_at' => now()]);

    return $test->getJson('api/run-scheduler', ['Authorization' => 'Bearer ' . str_repeat('t', 64)])->assertOk();
}

/** A test task: due while $due, and $work runs inside it. */
function fake_task(string $name, bool $due = true, ?Closure $work = null): SchedulerTask
{
    return new class($name, $due, $work) implements SchedulerTask {
        public bool $ran = false;

        public function __construct(
            private readonly string $name,
            private readonly bool $due,
            private readonly ?Closure $work,
        ) {}

        public function name(): string
        {
            return $this->name;
        }

        public function isDue(): bool
        {
            return $this->due && !$this->ran;
        }

        public function run(CarbonImmutable $deadline): TaskResult
        {
            $this->ran = true;

            return $this->work === null ? new TaskResult(['done' => 1]) : ($this->work)($deadline);
        }
    };
}

function expired_hold(Organization $organization): Reservation
{
    $zone = payable_zone($organization);
    $zone->forceFill(['payout_checked_at' => now()])->save();
    $event = Event::factory()->create(['organization_id' => $organization->id]);
    $tier = TicketType::factory()->create(['event_id' => $event->id]);

    return Reservation::factory()
        ->expired()
        ->create([
            'organization_id' => $organization->id,
            'ticket_type_id' => $tier->id,
            'subdivision_id' => $zone->id,
        ]);
}

test('an idle call answers "idle": no lock, no worker, no process, no cookies', function () {
    Process::fake();
    $worker = Mockery::mock(Illuminate\Queue\Worker::class);
    $worker->shouldNotReceive('runNextJob');
    app()->instance('queue.worker', $worker);

    $response = run_scheduler($this)
        ->assertJsonPath('status', 'idle')
        ->assertJsonPath('ran', [])
        ->assertJsonPath('errors', 0)
        ->assertJsonPath('next_due', []);

    Process::assertNothingRan();
    expect($response->json('duration_ms'))
        ->toBeInt()
        ->and($response->headers->getCookies())
        ->toBe([])
        ->and(json_decode((string) $response->getContent())->counts)
        ->toBeObject();
});

test('a call with work runs only the due tasks, in process, and reports what it did', function () {
    Process::fake();
    $hold = expired_hold(Organization::factory()->create());

    run_scheduler($this)
        ->assertJsonPath('status', 'ran')
        ->assertJsonPath('ran', ['release_holds'])
        ->assertJsonPath('counts.release_holds.released', 1)
        ->assertJsonPath('errors', 0)
        ->assertJsonPath('next_due', []);

    Process::assertNothingRan();
    expect($hold->fresh()->status)
        ->toBe(ReservationStatus::Expired)
        // The run's deadline is gone again afterwards.
        ->and(SchedulerBudget::secondsLeft())
        ->toBeNull();
});

test('tasks run most important first', function () {
    expect(SchedulerRunner::TASKS)->toBe([
        App\Services\Scheduler\Tasks\ReleaseExpiredHolds::class,
        App\Services\Scheduler\Tasks\ReconcilePendingPayments::class,
        App\Services\Scheduler\Tasks\SendOwedTicketEmails::class,
        App\Services\Scheduler\Tasks\WorkQueues::class,
        App\Services\Scheduler\Tasks\RecheckPayoutSubaccounts::class,
        App\Services\Scheduler\Tasks\CleanUp::class,
    ]);

    $names = array_map(static fn(string $task): string => app($task)->name(), SchedulerRunner::TASKS);

    foreach ($names as $name) {
        expect(config("scheduler.{$name}.time_budget_seconds"))
            ->toBeInt()
            ->toBeGreaterThan(0)
            ->and(config("scheduler.{$name}.overrun_seconds"))
            ->toBeInt();
    }
});

test('each kind of work is detected only when it is due', function () {
    $runner = app(SchedulerRunner::class);
    expect($runner->due())->toBe([]);

    // A pending order is the webhook's and the buyer's for its first 10 minutes.
    $order = Order::factory()->create();
    Subdivision::query()->update(['payout_checked_at' => now()]);
    expect($runner->due())->toBe([]);
    $this->travel(11)->minutes();
    expect($runner->due())->toBe(['reconcile']);
    $order->update(['payment_status' => OrderStatus::Paid]);

    // An owed ticket email.
    $order->forceFill(['ticket_email_due_at' => now()])->save();
    expect($runner->due())->toBe(['ticket_emails']);
    $order->forceFill(['ticket_email_due_at' => now()->addMinutes(5)])->save();
    expect($runner->due())->toBe([]);

    // A queued job that is available now, but not one delayed to tomorrow.
    config(['queue.default' => 'database']);
    DB::table('jobs')->insert([
        'queue' => 'broadcasts',
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->addDay()->getTimestamp(),
        'created_at' => now()->getTimestamp(),
    ]);
    expect($runner->due())->toBe([]);
    DB::table('jobs')->update(['available_at' => now()->getTimestamp()]);
    expect($runner->due())->toBe(['worker']);
    DB::table('jobs')->delete();

    // A zone not re-checked for a day.
    Subdivision::query()->whereNotNull('paystack_subaccount_code')->limit(1)->update(['payout_checked_at' => null]);
    expect($runner->due())->toBe(['payout_recheck']);
    Subdivision::query()->update(['payout_checked_at' => now()]);

    // Cache rows that expired more than a day ago; recently expired ones (throttle counters) do not count.
    DB::table('cache')->insert(['key' => 'recent', 'value' => 'x', 'expiration' => now()->subMinute()->getTimestamp()]);
    expect($runner->due())->toBe([]);
    DB::table('cache')->insert(['key' => 'stale', 'value' => 'x', 'expiration' => now()->subDays(2)->getTimestamp()]);
    expect($runner->due())->toBe(['cleanup']);
});

test('suspended and archived organisations are skipped; their work runs once reactivated', function () {
    Http::fake();
    $organization = Organization::factory()->create();
    $organization->forceFill(['status' => OrganizationStatus::Suspended])->save();
    $hold = expired_hold($organization);
    Order::factory()->create([
        'organization_id' => $organization->id,
        'payment_status' => OrderStatus::Pending,
        'created_at' => now()->subHour(),
    ]);

    run_scheduler($this)->assertJsonPath('status', 'idle');

    $organization->forceFill(['status' => OrganizationStatus::Archived])->save();
    run_scheduler($this)->assertJsonPath('status', 'idle');
    expect($hold->fresh()->status)->toBe(ReservationStatus::Active)->and(Http::recorded())->toBeEmpty();

    $organization->forceFill(['status' => OrganizationStatus::Active])->save();
    run_scheduler($this)->assertJsonPath('counts.release_holds.released', 1);
});

describe('one run at a time', function (): void {
    test('a second call while a run holds the lock answers "locked" at once', function () {
        expired_hold(Organization::factory()->create());
        $lock = Cache::store('database')->lock(SchedulerRunner::LOCK, 65);
        expect($lock->get())->toBeTrue();

        run_scheduler($this)
            ->assertJsonPath('status', 'locked')
            ->assertJsonPath('ran', [])
            ->assertJsonPath('next_due', ['release_holds']);

        $lock->release();
        run_scheduler($this)->assertJsonPath('status', 'ran');
    });

    test('the lock of a killed run expires on its own just after the function limit', function () {
        expired_hold(Organization::factory()->create());
        // A run that was killed: it took the lock and never released it.
        expect(Cache::store('database')->lock(SchedulerRunner::LOCK, 65)->get())->toBeTrue();

        run_scheduler($this)->assertJsonPath('status', 'locked');

        $this->travel(config('scheduler.function_max_duration') + 6)->seconds();
        run_scheduler($this)->assertJsonPath('status', 'ran');
    });

    test('the lock is released after a run, even one whose task threw', function () {
        $runner = new SchedulerRunner([fake_task(
            'release_holds',
            work: static fn() => throw new RuntimeException('boom'),
        )]);

        expect($runner->run()['status'])
            ->toBe('ran')
            ->and(Cache::store('database')->lock(SchedulerRunner::LOCK, 1)->get())
            ->toBeTrue();
    });
});

describe('one deadline under the function limit', function (): void {
    test('every budget and maxDuration lives in config and vercel.json, in step', function () {
        $vercel = json_decode((string) file_get_contents(base_path('vercel.json')), true);
        $route = collect($vercel['routes'])->firstWhere('src', '^/api/run-scheduler$');

        expect($vercel['functions']['api/scheduler.php']['maxDuration'])
            ->toBe(config('scheduler.function_max_duration'))
            // Normal pages keep their own limit.
            ->and($vercel['functions']['api/index.php']['maxDuration'])
            ->toBe(60)
            ->and(config('scheduler.run_budget_seconds'))
            ->toBeLessThanOrEqual(config('scheduler.function_max_duration') - 10)
            ->and($route['dest'])
            ->toBe('/api/scheduler.php')
            ->and($route['headers']['cache-control'])
            ->toBe('no-store')
            ->and(file_exists(base_path('api/scheduler.php')))
            ->toBeTrue();
    });

    test('a task is given its budget, cut so its last item still ends by the deadline', function () {
        $this->freezeTime();
        config(['scheduler.reconcile.time_budget_seconds' => 12, 'scheduler.reconcile.overrun_seconds' => 8]);

        expect(SchedulerRunner::taskBudget('reconcile', CarbonImmutable::now()->addSeconds(48)))
            ->toBe(12)
            ->and(SchedulerRunner::taskBudget('reconcile', CarbonImmutable::now()->addSeconds(15)))
            ->toBe(7)
            ->and(SchedulerRunner::taskBudget('reconcile', CarbonImmutable::now()->addSeconds(8)))
            ->toBe(0);
    });

    test('once time runs short the remaining tasks are skipped and wait for the next call', function () {
        config(['scheduler.run_budget_seconds' => 48]);
        $slow = fake_task('release_holds', work: function (CarbonImmutable $deadline): TaskResult {
            // Takes its whole budget, as a long backlog would.
            $this->travelTo($deadline);

            return new TaskResult(['released' => 500]);
        });
        $slower = fake_task('reconcile', work: function (CarbonImmutable $deadline): TaskResult {
            $this->travelTo($deadline->addSeconds((int) config('scheduler.reconcile.overrun_seconds')));

            return new TaskResult(['skipped' => 25]);
        });
        $emails = fake_task('ticket_emails', work: function (CarbonImmutable $deadline): TaskResult {
            $this->travelTo($deadline->addSeconds((int) config('scheduler.ticket_emails.overrun_seconds')));

            return new TaskResult(['sent' => 20]);
        });
        $worker = fake_task('worker');
        $start = CarbonImmutable::now();

        $report = new SchedulerRunner([$slow, $slower, $emails, $worker])->run();

        // Each task uses its whole budget and overrun: holds 5 s, reconcile 12 + 8 s, emails 6 + 15 s = 46 s.
        // 2 s are left, too few for the worker (it needs its 10 s overrun plus 1 s), so it waits for the next call.
        expect($report['ran'])
            ->toBe(['release_holds', 'reconcile', 'ticket_emails'])
            ->and($report['skipped'])
            ->toBe(['worker'])
            ->and($report['next_due'])
            ->toBe(['worker'])
            ->and($worker->ran)
            ->toBeFalse()
            ->and(CarbonImmutable::now()->diffInSeconds($start, true))
            ->toBeLessThanOrEqual(48);
    });

    test('a slow Paystack stops reconcile at its budget; the rest of the orders wait for the next call', function () {
        config(['scheduler.reconcile.time_budget_seconds' => 15]);
        $organization = Organization::factory()->create();
        Order::factory()
            ->count(4)
            ->create([
                'organization_id' => $organization->id,
                'payment_status' => OrderStatus::Pending,
                'created_at' => now()->subHour(),
            ]);
        Http::fake(['api.paystack.co/transaction/verify/*' => function () {
            $this->travel(10)->seconds();

            return Http::response(['status' => true, 'data' => ['status' => 'ongoing']]);
        }]);

        run_scheduler($this)
            ->assertJsonPath('ran', ['reconcile'])
            ->assertJsonPath('counts.reconcile.skipped', 2)
            ->assertJsonPath('counts.reconcile.waiting', 2)
            ->assertJsonPath('next_due', ['reconcile']);

        Http::assertSentCount(2);
    });
});

test('a task that fails is logged with its name and counted; the other tasks still run', function () {
    Log::spy();
    $after = fake_task('ticket_emails');

    $report = new SchedulerRunner([
        fake_task('release_holds', work: static fn() => throw new RuntimeException('database went away')),
        $after,
    ])->run();

    expect($report['errors'])
        ->toBe(1)
        ->and($report['ran'])
        ->toBe(['release_holds', 'ticket_emails'])
        ->and($report['error_messages'])
        ->toBe(['release_holds: database went away'])
        ->and($after->ran)
        ->toBeTrue();
    Log::shouldHaveReceived('error')
        ->withArgs(static fn(string $message, array $context): bool => $context['task'] === 'release_holds')
        ->once();
});

test('the response counts errors but never carries their messages', function () {
    $organization = Organization::factory()->create();
    Order::factory()->create([
        'organization_id' => $organization->id,
        'payment_status' => OrderStatus::Pending,
        'created_at' => now()->subHour(),
    ]);
    // Paystack says paid, then fulfilment breaks unexpectedly: an error for this order, logged, counted.
    app()->instance(App\Services\Payment\PaystackGateway::class, Mockery::mock(App\Services\Payment\PaystackGateway::class, static function ($mock): void {
        $mock->shouldReceive('verify')->andReturn(['status' => 'success']);
        $mock->shouldReceive('handleChargeSuccess')->andThrow(new RuntimeException('secret detail'));
    }));

    $response = run_scheduler($this)->assertJsonPath('errors', 1)->assertJsonPath('ran', ['reconcile']);

    expect($response->json())
        ->not->toHaveKey('error_messages')->and((string) $response->getContent())
        ->not->toContain('secret detail');
});

describe('ticket emails owed', function (): void {
    beforeEach(function (): void {
        $this->org = Organization::factory()->create();
        $this->zone = payable_zone($this->org);
        $this->zone->forceFill(['payout_checked_at' => now()])->save();
        $this->event = Event::factory()->create(['organization_id' => $this->org->id, 'status' => 'published']);
        $this->tier = TicketType::factory()->create(['event_id' => $this->event->id, 'price' => 50]);

        // The production mailer: Brevo's HTTP API.
        config([
            'mail.default' => 'brevo',
            'mail.mailers.brevo.key' => 'xkeysib-test-key',
            'mail.quota.daily_limit' => 3,
        ]);
        Http::fake(['api.brevo.com/*' => Http::response(['messageId' => '<sent@brevo>'], 201)]);
    });

    function owed_ticket_emails(object $test, int $count): void
    {
        foreach (range(1, $count) as $n) {
            $order = Order::factory()
                ->paid()
                ->create([
                    'organization_id' => $test->org->id,
                    'subdivision_id' => $test->zone->id,
                    'customer_email' => "buyer{$n}@example.com",
                    'ticket_email_due_at' => now()->subMinute(),
                ]);
            Attendee::factory()->create([
                'organization_id' => $test->org->id,
                'order_id' => $order->id,
                'ticket_type_id' => $test->tier->id,
            ]);
        }
    }

    test('are sent through the configured mailer, within the ticket share of the daily cap', function () {
        // Daily limit 3, tickets may use 90%: 2 go now, the third waits for tomorrow's quota.
        owed_ticket_emails($this, 3);

        run_scheduler($this)
            ->assertJsonPath('ran', ['ticket_emails'])
            ->assertJsonPath('counts.ticket_emails.sent', 2)
            ->assertJsonPath('counts.ticket_emails.deferred', 1)
            ->assertJsonPath('next_due', []);

        Http::assertSentCount(2);
        Http::assertSent(
            static fn(Request $request): bool => $request->url() === 'https://api.brevo.com/v3/smtp/email'
            && $request->hasHeader('api-key', 'xkeysib-test-key'),
        );
        expect(Order::query()->whereNotNull('ticket_emailed_at')->count())->toBe(2);
    });

    test('a payment reconcile confirms is emailed in the same run, not after the response', function () {
        expect(SchedulerBudget::secondsLeft())->toBeNull();
        owed_ticket_emails($this, 1);
        $this->withoutDefer();

        // Inside a run, sendSoon leaves the send to the ticket_emails task (within the deadline).
        SchedulerBudget::start(48);

        try {
            app(App\Services\Mail\TicketEmailer::class)->sendSoon(Order::query()->firstOrFail());
        } finally {
            SchedulerBudget::clear();
        }

        Http::assertNothingSent();

        run_scheduler($this)->assertJsonPath('counts.ticket_emails.sent', 1);
    });
});

test('the worker takes queued jobs in process, every queue, and stops when they are empty', function () {
    config(['queue.default' => 'database']);
    $this->mock(App\Services\Sms\Contracts\SmsGatewayInterface::class);
    dispatch(static fn() => Cache::put('job-ran-on-default', true))->onQueue('default');
    dispatch(static fn() => Cache::put('job-ran-on-transactional', true))->onQueue('transactional');
    dispatch(static fn() => throw new RuntimeException('job broke'))->onQueue('broadcasts');
    Process::fake();

    run_scheduler($this)
        ->assertJsonPath('ran', ['worker'])
        ->assertJsonPath('counts.worker.processed', 2)
        ->assertJsonPath('counts.worker.failed', 1)
        ->assertJsonPath('errors', 1)
        ->assertJsonPath('next_due', []);

    Process::assertNothingRan();
    expect(Cache::get('job-ran-on-default'))
        ->toBeTrue()
        ->and(Cache::get('job-ran-on-transactional'))
        ->toBeTrue()
        ->and(DB::table('jobs')->count())
        ->toBe(0);
});

test('the worker works every queue that jobs are dispatched onto', function () {
    $dispatched = ['default'];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path())) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        preg_match_all(
            "/onQueue\\(\\s*'([^']+)'\\s*\\)|\\\$queue\\s*=\\s*'([^']+)'/",
            (string) file_get_contents($file->getPathname()),
            $matches,
        );
        $dispatched = [...$dispatched, ...array_filter($matches[1]), ...array_filter($matches[2])];
    }

    expect(array_values(array_diff(array_unique($dispatched), config('queue.worker_queues'))))
        ->toBe([])
        ->and($dispatched)
        ->toContain('transactional', 'broadcasts');
});

test('cleanup deletes cache rows expired over a day ago, and nothing else', function () {
    DB::table('cache')->insert([
        ['key' => 'stale', 'value' => 'x', 'expiration' => now()->subDays(2)->getTimestamp()],
        ['key' => 'recent', 'value' => 'x', 'expiration' => now()->subMinute()->getTimestamp()],
        ['key' => 'live', 'value' => 'x', 'expiration' => now()->addHour()->getTimestamp()],
    ]);
    DB::table('cache_locks')->insert([
        'key' => 'stale-lock',
        'owner' => 'x',
        'expiration' => now()->subDays(2)->getTimestamp(),
    ]);

    run_scheduler($this)->assertJsonPath('ran', ['cleanup'])->assertJsonPath('counts.cleanup.cache_rows', 2);

    expect(DB::table('cache')->orderBy('key')->pluck('key')->all())
        ->toBe(['live', 'recent'])
        ->and(DB::table('cache_locks')->where('key', 'stale-lock')->exists())
        ->toBeFalse();
});

test('scheduler:tick runs the same runner, and is the only scheduled command', function () {
    expired_hold(Organization::factory()->create());

    $this->artisan('scheduler:tick')->expectsOutputToContain('"status": "ran"')->assertSuccessful();

    $events = app(Schedule::class)->events();
    expect($events)
        ->toHaveCount(1)
        ->and((string) $events[0]->command)
        ->toContain('scheduler:tick')
        ->and($events[0]->expression)
        ->toBe('* * * * *');
});
