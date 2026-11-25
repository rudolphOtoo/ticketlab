<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\User;
use App\Notifications\PayoutAccountRejected;
use App\Services\Payment\PaystackSubaccountResolver;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    $this->org = Organization::factory()->create();
    $this->zone = Subdivision::factory()->create([
        'organization_id' => $this->org->id,
        'name' => 'Kumasi',
        'paystack_subaccount_code' => 'ACCT_kumasi1',
    ]);
    app(PaystackSubaccountResolver::class)->markVerified($this->zone);

    $this->orgAdmin = User::factory()->create()->assignMembership('admin', $this->org->id);
    $this->zoneAdmin = User::factory()->create()->assignMembership('admin', $this->org->id, $this->zone->id);
});

test('the re-check runs on every scheduler call, and only zones due for a check are taken', function (): void {
    $task = app(App\Services\Scheduler\Tasks\RecheckPayoutSubaccounts::class);

    // Every call (cron-job.org, every 5 minutes): interval_hours decides which zones are due.
    expect(App\Services\Scheduler\SchedulerRunner::TASKS)->toContain($task::class)->and($task->isDue())->toBeFalse();

    $this->travel(21)->hours();

    expect($task->isDue())->toBeTrue();
});

test('a code paystack now rejects stops sales for that zone and alerts the organisation admins once', function (): void {
    // Last checked when it was saved; due again after the re-check interval.
    $this->travel(21)->hours();
    Http::fake(['api.paystack.co/subaccount/*' => Http::response([
        'status' => false,
        'message' => 'Subaccount not found',
    ], 404)]);

    $this->artisan('payouts:recheck-subaccounts')->expectsOutputToContain('rejected 1')->assertSuccessful();

    expect(app(PaystackSubaccountResolver::class)->forSubdivision($this->zone->fresh()))
        ->toBeNull()
        ->and($this->zone->fresh()->payout_check_error)
        ->toContain('does not recognise');
    Notification::assertSentTo($this->orgAdmin, PayoutAccountRejected::class);
    Notification::assertNotSentTo($this->zoneAdmin, PayoutAccountRejected::class);

    // Already broken: the next due re-check does not email again.
    $this->travel(21)->hours();
    $this->artisan('payouts:recheck-subaccounts')->expectsOutputToContain('rejected 1')->assertSuccessful();
    Notification::assertSentToTimes($this->orgAdmin, PayoutAccountRejected::class, 1);
});

test('a paystack network error never blocks sales or alerts anyone', function (): void {
    $this->travel(21)->hours();
    Http::fake(['api.paystack.co/subaccount/*' => static fn() => throw new ConnectionException('timed out')]);

    $this->artisan('payouts:recheck-subaccounts')->expectsOutputToContain('unreachable 1')->assertSuccessful();

    expect(app(PaystackSubaccountResolver::class)->forSubdivision($this->zone->fresh()))->toBe('ACCT_kumasi1');
    Notification::assertNothingSent();
});

test('a code that is still valid stays verified with a fresh check time', function (): void {
    $this->travel(2)->days();
    Http::fake(['api.paystack.co/subaccount/*' => Http::response([
        'status' => true,
        'data' => ['active' => true, 'currency' => 'GHS'],
    ])]);

    $this->artisan('payouts:recheck-subaccounts')->expectsOutputToContain('Verified 1')->assertSuccessful();

    expect($this->zone->fresh()->payout_checked_at->isToday())
        ->toBeTrue()
        ->and(app(PaystackSubaccountResolver::class)->forSubdivision($this->zone->fresh()))
        ->toBe('ACCT_kumasi1');
    Notification::assertNothingSent();
});

test('only zones not checked recently are re-checked, at most a batch per run, oldest check first', function (): void {
    config(['scheduler.payout_recheck.batch_size' => 2]);
    Http::fake(['api.paystack.co/subaccount/*' => Http::response([
        'status' => true,
        'data' => ['active' => true, 'currency' => 'GHS'],
    ])]);
    // The beforeEach zone was checked just now: not due. Three more zones were last checked a day or more ago.
    foreach ([30, 48, 26] as $hoursAgo) {
        $zone = Subdivision::factory()->create([
            'organization_id' => $this->org->id,
            'paystack_subaccount_code' => 'ACCT_old' . $hoursAgo,
        ]);
        $zone->forceFill(['payout_checked_at' => now()->subHours($hoursAgo)])->save();
    }

    $this->artisan('payouts:recheck-subaccounts')->expectsOutputToContain('Verified 2')->assertSuccessful();

    $lookups = Http::recorded()->map(static fn(array $pair): string => basename($pair[0]->url()))->all();
    expect($lookups)->toBe(['ACCT_old48', 'ACCT_old30']);
});
