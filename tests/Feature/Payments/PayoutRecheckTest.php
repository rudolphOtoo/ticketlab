<?php

declare(strict_types=1);

use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\User;
use App\Notifications\PayoutAccountRejected;
use App\Services\Payment\PaystackSubaccountResolver;
use Illuminate\Console\Scheduling\Schedule;
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

test('the re-check runs daily on the scheduler', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(static fn($event): bool => str_contains((string) $event->command, 'payouts:recheck-subaccounts'));

    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 3 * * *');
});

test('a code paystack now rejects stops sales for that zone and alerts the organisation admins once', function (): void {
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

    // Already broken: the next day's re-check does not email again.
    $this->artisan('payouts:recheck-subaccounts')->assertSuccessful();
    Notification::assertSentToTimes($this->orgAdmin, PayoutAccountRejected::class, 1);
});

test('a paystack network error never blocks sales or alerts anyone', function (): void {
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
