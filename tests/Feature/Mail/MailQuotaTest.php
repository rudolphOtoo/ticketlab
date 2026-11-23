<?php

declare(strict_types=1);

use App\Enums\MailCategory;
use App\Jobs\Middleware\MailQuotaGate;
use App\Jobs\SendEnquiryEmail;
use App\Jobs\SendEventReminders;
use App\Livewire\Admin\BroadcastHub;
use App\Livewire\Platform\PlatformDashboard;
use App\Mail\ZoneChoiceRequestMail;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use App\Notifications\OrganizationAdminInvitation;
use App\Services\Mail\BroadcastSchedule;
use App\Services\Mail\MailQuota;
use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * Brevo's free plan sends 300 emails a day across every kind. Each kind may only use its share (tickets 100%,
 * invitations and alerts 90%, zone choice 70%, broadcasts 50%), so ticket emails always find room, and anything
 * without room waits for the next day.
 */

function quota(): MailQuota
{
    return app(MailQuota::class);
}

function sent_today(int $count): void
{
    DB::table('mail_quota_days')->updateOrInsert(['day' => quota()->today()], ['sent' => $count, 'other' => $count]);
}

/** A stand-in for a queued job, recording what the gate did with it. */
function gated_job(): object
{
    return new class {
        public ?int $releasedFor = null;

        public bool $handled = false;

        public function release(int $delay): void
        {
            $this->releasedFor = $delay;
        }
    };
}

it('lets each kind of email use only its share of the daily cap', function (
    MailCategory $category,
    int $ceiling,
): void {
    sent_today($ceiling - 1);

    expect(quota()->reserve($category))->toBeTrue()->and(quota()->reserve($category))->toBeFalse();
})->with([
    'tickets: all 300' => [MailCategory::Ticket, 300],
    'invitations and alerts: 270' => [MailCategory::Transactional, 270],
    'zone choice: 210' => [MailCategory::ZoneChoice, 210],
    'broadcasts: 150' => [MailCategory::Broadcast, 150],
]);

it('takes the cap from BREVO_DAILY_LIMIT (Starter has no cap: set a large number)', function (): void {
    config(['mail.quota.daily_limit' => 20_000]);
    sent_today(9_999);

    expect(quota()->reserve(MailCategory::Broadcast))
        ->toBeTrue()
        ->and(quota()->remaining(MailCategory::Ticket))
        ->toBe(20_000 - 10_000);
});

it('starts from zero on the next day (Brevo resets daily, with no rollover)', function (): void {
    sent_today(300);
    expect(quota()->reserve(MailCategory::Ticket))->toBeFalse();

    $this->travelTo(quota()->nextReset()->addMinute());

    expect(quota()->reserve(MailCategory::Ticket))->toBeTrue()->and(quota()->sentToday())->toBe(1);
});

it('puts a queued email back until after midnight when its kind has no room, and sends it when there is', function (): void {
    sent_today(150);
    $job = gated_job();

    new MailQuotaGate(MailCategory::Broadcast)->handle($job, static function (object $job): void {
        $job->handled = true;
    });

    expect($job->handled)
        ->toBeFalse()
        ->and($job->releasedFor)
        ->toBeGreaterThanOrEqual((int) now()->diffInSeconds(quota()->nextReset(), true) - 1)
        ->and(quota()->snapshot()['deferred'])
        ->toBe(1);

    // A ticket-priority email still goes out the same day.
    $ticket = gated_job();
    new MailQuotaGate(MailCategory::Ticket)->handle($ticket, static function (object $job): void {
        $job->handled = true;
    });

    expect($ticket->handled)->toBeTrue()->and(quota()->sentToday())->toBe(151);
});

it('gives the slot back when a send fails', function (): void {
    expect(static fn() => new MailQuotaGate(
        MailCategory::Transactional,
    )->handle(gated_job(), static function (): never {
        throw new RuntimeException('SMTP refused');
    }))
        ->toThrow(RuntimeException::class, 'SMTP refused');

    expect(quota()->sentToday())->toBe(0);
});

it('puts every queued email behind the gate, and waiting never counts as a failed try', function (): void {
    $org = Organization::factory()->create();
    $order = Order::factory()->create(['organization_id' => $org->id]);
    $admin = User::factory()->create(['organization_id' => $org->id]);
    $gates = static fn(array $middleware): array => array_map(static fn(object $gate): string => $gate
        instanceof MailQuotaGate
            ? 'gate'
            : $gate::class, $middleware);

    expect($gates(new SendEventReminders(1, 'a@example.com', 'A')->middleware()))
        ->toBe(['gate'])
        ->and($gates(new SendEnquiryEmail($org->id, [
            'name' => 'n',
            'email' => 'e@example.com',
            'subject' => 's',
            'message' => 'm',
        ])->middleware()))
        ->toBe(['gate'])
        ->and($gates(new ZoneChoiceRequestMail($order)->middleware()))
        ->toBe(['gate'])
        ->and($gates(new OrganizationAdminInvitation($org)->middleware($admin, 'mail')))
        ->toBe(['gate']);

    // retryUntil is what stops Laravel counting tries; a broadcast delayed to a later day keeps its full window.
    $later = new SendEventReminders(1, 'a@example.com', 'A')->delay(now()->addDays(2));

    expect($later->retryUntil()->getTimestamp())
        ->toBe(now()->addDays(5)->getTimestamp())
        ->and($later->maxExceptions)
        ->toBe(3);
});

it('counts emails Laravel sends itself without holding them back, and counts gated ones once', function (): void {
    sent_today(300);

    Mail::raw('Reset your password', static fn($message) => $message->to('someone@example.com'));

    expect(quota()->sentToday())->toBe(301)->and(quota()->snapshot()['by_category']['other'])->toBe(301);

    $org = Organization::factory()->create(['settings' => ['contact_email' => 'org@example.com']]);
    $this->travelTo(quota()->nextReset()->addMinute());

    // Runs through the queue (sync in tests), so through the gate.
    SendEnquiryEmail::dispatch($org->id, [
        'name' => 'n',
        'email' => 'e@example.com',
        'subject' => 's',
        'message' => 'm',
    ]);

    expect(quota()->snapshot()['by_category'])
        ->toMatchArray(['transactional' => 1, 'other' => 0])
        ->and(quota()->sentToday())
        ->toBe(1);
});

describe('broadcasts larger than a day\'s share', function (): void {
    it('are spread over the following days, starting after the day\'s deferred ticket emails', function (): void {
        sent_today(100);
        $plan = BroadcastSchedule::plan(400, quota());
        $reset = quota()->nextReset();

        expect($plan->today)
            ->toBe(50)
            ->and($plan->perDay)
            ->toBe(150)
            ->and($plan->extraDays())
            ->toBe(3)
            ->and($plan->sendAt(0)->lessThan($reset))
            ->toBeTrue()
            ->and($plan->sendAt(49)->lessThan($reset))
            ->toBeTrue()
            ->and($plan->sendAt(50)->equalTo($reset->addMinutes(45)))
            ->toBeTrue()
            ->and($plan->sendAt(199)->lessThan($reset->addDay()))
            ->toBeTrue()
            ->and($plan->sendAt(200)->equalTo($reset->addDay()->addMinutes(45)))
            ->toBeTrue()
            ->and($plan->sendAt(399)->lessThan($reset->addDays(3)))
            ->toBeTrue()
            ->and($plan->summary())
            ->toBe('50 are emailed today, then up to 150 a day over the next 3 days (daily email limit).');
    });

    it('tell the admin how the send is spread', function (): void {
        Bus::fake();
        sent_today(150);

        $org = Organization::factory()->create();
        $admin = User::factory()->create()->assignMembership('admin', $org->id);
        $event = Event::factory()->create(['organization_id' => $org->id]);
        $tier = TicketType::factory()->create(['event_id' => $event->id]);
        Attendee::factory()->count(3)->create(['organization_id' => $org->id, 'ticket_type_id' => $tier->id]);

        Livewire::actingAs($admin)
            ->test(BroadcastHub::class)
            ->set('subject', 'Gates open at 8')
            ->set('body', 'See you tomorrow!')
            ->call('send')
            ->assertHasNoErrors()
            ->assertSee('0 are emailed today, then up to 150 a day over the next 1 day');

        Bus::assertBatched(static fn(PendingBatch $batch): bool => $batch->jobs->every(
            static fn(SendEventReminders $job): bool => $job->delay >= quota()->nextReset(),
        ));
    });
});

it('shows today\'s count, the waiting ticket emails and the deferrals on the platform dashboard', function (): void {
    sent_today(250);
    quota()->recordDeferral();
    $superAdmin = User::factory()->create()->assignMembership('super_admin', null);

    Livewire::actingAs($superAdmin)
        ->test(PlatformDashboard::class)
        ->assertSeeHtml('data-mail-health')
        ->assertSee('Emails today')
        ->assertSeeInOrder(['250', '/ 300'])
        ->assertSee('0 ticket emails waiting')
        ->assertSee('1 moved to tomorrow');
});
