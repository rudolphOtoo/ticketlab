<?php

declare(strict_types=1);

use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\TicketType;
use App\Services\Reservation\TicketReservationService;
use App\Services\Reservation\TicketSoldOutException;

/*
 * The cron now releases expired holds every 5 minutes, not every minute. Checkout must not show a tier as sold out in
 * between: when stock is short, it gives other buyers' expired holds back itself, under the tier's lock.
 */

beforeEach(function (): void {
    $this->org = Organization::factory()->create();
    $this->zone = payable_zone($this->org);
    $event = Event::factory()->create(['organization_id' => $this->org->id, 'status' => 'published']);
    $this->tier = TicketType::factory()->create([
        'event_id' => $event->id,
        'price' => 50,
        'total_capacity' => 2,
        'available_quantity' => 2,
        'is_active' => true,
    ]);
    $this->hold = fn(string $session, int $quantity = 2): Reservation => app(
        TicketReservationService::class,
    )->holdOrReuse($this->tier->fresh(), $quantity, $session, $this->zone->id);
});

it('gives seats of an expired hold to the next buyer without waiting for the cron', function (): void {
    $first = ($this->hold)('first-buyer');
    expect($this->tier->fresh()->available_quantity)->toBe(0);

    // Still held: sold out.
    expect(fn() => ($this->hold)('second-buyer'))->toThrow(TicketSoldOutException::class);

    // 15 minutes later the first hold has lapsed, and no cron has run since.
    $this->travel(16)->minutes();
    $second = ($this->hold)('second-buyer');

    expect($second->quantity)
        ->toBe(2)
        ->and($first->fresh()->status)
        ->toBe(ReservationStatus::Expired)
        ->and($this->tier->fresh()->available_quantity)
        ->toBe(0);
});

it('leaves holds that are still running alone', function (): void {
    ($this->hold)('first-buyer', 1);
    $this->travel(5)->minutes();
    $running = ($this->hold)('second-buyer', 1);

    expect(fn() => ($this->hold)('third-buyer', 1))
        ->toThrow(TicketSoldOutException::class)
        ->and($running->fresh()->status)
        ->toBe(ReservationStatus::Active);
});
