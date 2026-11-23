<?php

declare(strict_types=1);

use App\Jobs\SendTicketEmail;
use App\Livewire\Public\EventShow;
use App\Livewire\Public\TicketShow;
use App\Mail\TicketIssuedMail;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\TicketType;
use App\Services\Mail\MailQuota;
use App\Services\Mail\TicketEmailer;
use App\Services\Payment\OrderFulfillmentService;
use App\Services\Payment\TicketPassPdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

/*
 * The ticket page and its PDF are the main way to get a ticket; the email is the backup. It is sent right after
 * payment is confirmed (after the response), the cron retries only what is still owed, and it takes its slot of the
 * daily cap first.
 */

beforeEach(function (): void {
    // Deferred work (after the response) runs at once in these tests.
    $this->withoutDefer();
    Mail::fake();

    $this->org = Organization::factory()->create();
    $this->zone = payable_zone($this->org);
    $this->event = Event::factory()->create([
        'organization_id' => $this->org->id,
        'status' => 'published',
        'slug' => 'summit-2027',
    ]);
    $this->tier = TicketType::factory()->create([
        'event_id' => $this->event->id,
        'price' => 50,
        'total_capacity' => 10,
        'available_quantity' => 10,
        'is_active' => true,
    ]);
});

function paid_order_with_passes(object $test, int $passes = 2): Order
{
    $order = Order::factory()
        ->paid()
        ->create([
            'organization_id' => $test->org->id,
            'subdivision_id' => $test->zone->id,
            'customer_email' => 'buyer@example.com',
        ]);
    Attendee::factory()
        ->count($passes)
        ->create(['organization_id' => $test->org->id, 'order_id' => $order->id, 'ticket_type_id' => $test->tier->id]);

    return $order;
}

/** Makes the next $times PDF renders throw, as a failing send would. */
function failing_sends(int $times): void
{
    $real = app(TicketPassPdf::class);
    $failures = $times;

    app()->instance(TicketPassPdf::class, new class($real, $failures) extends TicketPassPdf {
        public function __construct(
            private readonly TicketPassPdf $real,
            private int $failures,
        ) {}

        public function render(Order $order): Barryvdh\DomPDF\PDF
        {
            if ($this->failures-- > 0) {
                throw new RuntimeException('SMTP timeout');
            }

            return $this->real->render($order);
        }
    });
    app()->forgetInstance(TicketEmailer::class);
}

it('emails the passes right after payment is confirmed, exactly once, without the cron', function (): void {
    $component = Livewire::test(EventShow::class, ['org_slug' => $this->org->slug, 'event_slug' => 'summit-2027'])
        ->set('ticketTypeId', $this->tier->id)
        ->set('quantity', 1)
        ->set('customerName', 'Kofi Boateng')
        ->set('customerEmail', 'kofi@example.com')
        ->set('customerPhone', '0244998877')
        ->set('subdivisionId', $this->zone->id)
        ->checkoutAsShown()
        ->assertHasNoErrors();

    $order = Order::query()->where('reference', $component->get('orderReference'))->firstOrFail();
    $payment = [
        'reference' => $order->reference,
        'status' => 'success',
        'amount' => $order->gross_pesewas,
        'currency' => 'GHS',
        'channel' => 'mobile_money',
        ...paystack_routing($order),
    ];

    // The webhook and the buyer's return both confirm the same payment.
    $fulfillment = app(OrderFulfillmentService::class);
    $fulfillment->fulfill($order, $payment);
    $fulfillment->fulfill($order->fresh(), $payment);

    Mail::assertSent(TicketIssuedMail::class, 1);
    Mail::assertSent(TicketIssuedMail::class, static fn(TicketIssuedMail $mail): bool => $mail->hasTo(
        'kofi@example.com',
    ));

    $order->refresh();

    expect($order->ticket_emailed_at)
        ->not
        ->toBeNull()
        ->and($order->ticket_email_due_at)
        ->toBeNull()
        ->and($order->ticket_email_attempts)
        ->toBe(1)
        ->and(app(MailQuota::class)->snapshot()['by_category']['ticket'])
        ->toBe(1);
});

it('attaches one branded PDF with every pass', function (): void {
    $order = paid_order_with_passes($this);

    app(TicketEmailer::class)->sendSoon($order);

    Mail::assertSent(TicketIssuedMail::class, static function (TicketIssuedMail $mail) use ($order): bool {
        $attachments = $mail->attachments();

        return (
            $mail->hasTo('buyer@example.com')
            && count($attachments) === 1
            && str_starts_with((string) $attachments[0]->as, "tickets-{$order->reference}")
        );
    });
});

it('sends nothing for an order without passes, and stops owing it', function (): void {
    $order = Order::factory()->paid()->create(['organization_id' => $this->org->id]);

    app(TicketEmailer::class)->sendSoon($order);

    Mail::assertNothingSent();
    expect($order->fresh()->ticket_email_due_at)->toBeNull();
});

it('leaves a failed send to the cron, which retries it after a back-off and stops after five attempts', function (): void {
    failing_sends(99);
    $order = paid_order_with_passes($this);

    app(TicketEmailer::class)->sendSoon($order);

    $order->refresh();
    expect($order->ticket_emailed_at)
        ->toBeNull()
        ->and($order->ticket_email_attempts)
        ->toBe(1)
        ->and($order->ticket_email_error)
        ->toBe('SMTP timeout')
        ->and($order->ticket_email_due_at?->isFuture())
        ->toBeTrue()
        // A failed send gives its slot back.
        ->and(app(MailQuota::class)->sentToday())
        ->toBe(0);

    // Not due yet: the cron leaves it alone.
    $this->artisan('tickets:email-pending')->expectsOutputToContain('No ticket emails owed.')->assertSuccessful();

    foreach ([2, 10, 30, 120] as $minutes) {
        $this->travel($minutes)->minutes();
        $this->artisan('tickets:email-pending')->assertSuccessful();
    }

    $order->refresh();
    expect($order->ticket_email_attempts)
        ->toBe(TicketEmailer::MAX_ATTEMPTS)
        ->and($order->ticket_email_due_at)
        ->toBeNull()
        ->and(app(TicketEmailer::class)->counts())
        ->toBe(['waiting' => 0, 'gave_up' => 1]);
    Mail::assertNothingSent();
});

it('sends on the next cron run after one failure', function (): void {
    failing_sends(1);
    $order = paid_order_with_passes($this);

    app(TicketEmailer::class)->sendSoon($order);
    Mail::assertNothingSent();

    $this->travel(3)->minutes();
    $this->artisan('tickets:email-pending')->expectsOutputToContain('sent 1')->assertSuccessful();

    Mail::assertSent(TicketIssuedMail::class, 1);
    expect($order->fresh()->ticket_emailed_at)->not->toBeNull();
});

it('retries a send whose request was killed, once its claim has expired', function (): void {
    $order = paid_order_with_passes($this);
    // Claimed by a request that never finished.
    $order->forceFill([
        'ticket_email_due_at' => now()->addMinutes(TicketEmailer::CLAIM_MINUTES),
        'ticket_email_attempts' => 1,
    ])->save();

    expect(app(TicketEmailer::class)->attempt($order->id))->toBe('skipped');

    $this->travel(TicketEmailer::CLAIM_MINUTES + 1)->minutes();
    $this->artisan('tickets:email-pending')->assertSuccessful();

    Mail::assertSent(TicketIssuedMail::class, 1);
});

it('waits for the next day when the daily cap is used up, without counting it as a failed attempt', function (): void {
    config(['mail.quota.daily_limit' => 2]);
    DB::table('mail_quota_days')->insert(['day' => app(MailQuota::class)->today(), 'sent' => 2, 'other' => 2]);
    $order = paid_order_with_passes($this);

    app(TicketEmailer::class)->sendSoon($order);

    $order->refresh();
    Mail::assertNothingSent();
    expect($order->ticket_email_attempts)
        ->toBe(0)
        ->and($order->ticket_email_due_at?->greaterThanOrEqualTo(app(MailQuota::class)->nextReset()))
        ->toBeTrue()
        ->and(app(MailQuota::class)->snapshot()['deferred'])
        ->toBe(1);

    // The ticket page says so; the passes work now.
    Livewire::test(TicketShow::class, ['order' => $order])->assertSeeHtml('data-email-status="tomorrow"');

    $this->travelTo(app(MailQuota::class)->nextReset()->addMinutes(31));
    $this->artisan('tickets:email-pending')->expectsOutputToContain('sent 1')->assertSuccessful();

    Mail::assertSent(TicketIssuedMail::class, 1);
});

it('exits after one query when no ticket email is owed', function (): void {
    paid_order_with_passes($this);
    DB::enableQueryLog();

    $this->artisan('tickets:email-pending')->expectsOutputToContain('No ticket emails owed.')->assertSuccessful();

    expect(collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(static fn(string $sql): bool => str_contains($sql, '"orders"')))->toHaveCount(1);
});

it('still sends ticket-email jobs queued before this release', function (): void {
    $order = paid_order_with_passes($this);

    new SendTicketEmail($order->id)->handle(app(TicketEmailer::class));

    Mail::assertSent(TicketIssuedMail::class, 1);
});

describe('the ticket page', function (): void {
    it('puts the ticket link, the PDF and the email status first', function (): void {
        $order = paid_order_with_passes($this);
        app(TicketEmailer::class)->sendSoon($order);

        Livewire::test(TicketShow::class, ['order' => $order->fresh()])
            ->assertSee('This page is your ticket')
            ->assertSeeHtml('value="' . route('public.tickets.show', ['order' => $order->reference]) . '"')
            ->assertSeeHtml(route('public.tickets.pdf', ['order' => $order->reference]))
            ->assertSeeHtml('data-email-status="sent"')
            ->assertSee('Resend my ticket');
    });

    it('resends to the address on the order, and limits how often', function (): void {
        $order = paid_order_with_passes($this);
        app(TicketEmailer::class)->sendSoon($order);

        $page = Livewire::test(TicketShow::class, ['order' => $order->fresh()])
            ->call('resendTicket')
            ->assertSee('Sending your ticket again');

        Mail::assertSent(TicketIssuedMail::class, 2);

        // Straight away again: refused, the page still works.
        $page->call('resendTicket')->assertSee('We just sent it');
        Mail::assertSent(TicketIssuedMail::class, 2);

        // Three an hour at most.
        foreach ([1, 2] as $_) {
            $this->travel(TicketShow::RESEND_GAP_SECONDS + 1)->seconds();
            $page->call('resendTicket');
        }

        $this->travel(TicketShow::RESEND_GAP_SECONDS + 1)->seconds();
        $page->call('resendTicket')->assertSee('We just sent it');

        Mail::assertSent(TicketIssuedMail::class, 4);
        Mail::assertSent(TicketIssuedMail::class, static fn(TicketIssuedMail $mail): bool => $mail->hasTo(
            'buyer@example.com',
        ));
    });

    it('offers no resend for an unpaid order', function (): void {
        $order = Order::factory()->create(['organization_id' => $this->org->id]);

        Livewire::test(TicketShow::class, ['order' => $order])
            ->assertDontSee('Resend my ticket')
            ->call('resendTicket')
            ->assertSet('resendMessage', 'There is no ticket email to send for this order.');

        Mail::assertNothingSent();
    });
});
