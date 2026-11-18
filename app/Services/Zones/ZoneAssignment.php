<?php

declare(strict_types=1);

namespace App\Services\Zones;

use App\Enums\OrderStatus;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Gives a zone to paid passes that were sold before a zone was required.
 *
 * Only passes without a zone are ever assigned here; a pass that already has a zone is never moved. Assigning a zone
 * changes which gate admits the pass and how reports attribute it. It never moves money: the order's
 * payout_subaccount_code still records where the payment actually settled, so zones are settled manually.
 */
final class ZoneAssignment
{
    public const string VIA_ADMIN = 'admin';

    public const string VIA_BUYER = 'buyer';

    /**
     * Paid passes of one organisation that still have no zone.
     *
     * @return Builder<Attendee>
     */
    public static function zonelessPaidPasses(int $organizationId): Builder
    {
        return Attendee::query()
            ->where('organization_id', $organizationId)
            ->whereNull('subdivision_id')
            ->whereHas('order', static fn(Builder $order) => $order->where('payment_status', OrderStatus::Paid->value));
    }

    /**
     * An organisation-wide admin assigns a zone to some of their organisation's zoneless passes.
     *
     * @param list<int> $attendeeIds
     *
     * @return int How many passes were assigned (passes that already had a zone are skipped).
     */
    public function assignByAdmin(User $actor, array $attendeeIds, int $zoneId): int
    {
        if ($actor->organization_id === null || $actor->subdivision_id !== null || !$actor->isAdmin()) {
            abort(403, 'Only organisation admins can assign ' . strtolower($actor->zonesLabel()) . '.');
        }

        $zone = $this->zone($actor->organization_id, $zoneId);

        return DB::transaction(function () use ($actor, $attendeeIds, $zone): int {
            $passes = self::zonelessPaidPasses($zone->organization_id)->whereKey($attendeeIds)->lockForUpdate()->get();

            foreach ($passes as $pass) {
                $this->assign($pass, $zone, self::VIA_ADMIN, $actor->id);
            }

            $orderIds = array_map(static fn(Attendee $pass): int => $pass->order_id, $passes->all());
            $this->settleOrderZones(array_values(array_unique($orderIds)), $zone);

            return $passes->count();
        });
    }

    /**
     * The buyer picks their zone once, from the ticket page, for every pass on the order.
     *
     * @return bool False when the order already has zoned passes (the choice is locked after the first pick).
     */
    public function chooseByBuyer(Order $order, int $zoneId): bool
    {
        $zone = $this->zone($order->organization_id, $zoneId);

        return DB::transaction(function () use ($order, $zone): bool {
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->first();

            if ($locked === null || $locked->payment_status !== OrderStatus::Paid) {
                return false;
            }

            $passes = Attendee::query()->where('order_id', $locked->id)->lockForUpdate()->get();

            // Locked once chosen: the choice is all-or-nothing for the order, and only while no pass has a zone.
            if (
                $passes->isEmpty()
                || $passes->contains(static fn(Attendee $pass): bool => $pass->subdivision_id !== null)
            ) {
                return false;
            }

            foreach ($passes as $pass) {
                $this->assign($pass, $zone, self::VIA_BUYER, null);
            }

            $this->settleOrderZones([$locked->id], $zone);

            return true;
        });
    }

    private function assign(Attendee $pass, Subdivision $zone, string $via, ?int $userId): void
    {
        $pass->forceFill([
            'subdivision_id' => $zone->id,
            'zone_assigned_at' => now(),
            'zone_assigned_by' => $userId,
            'zone_assigned_via' => $via,
        ])->save();

        AuditLog::query()->create([
            'organization_id' => $pass->organization_id,
            'user_id' => $userId,
            'action' => 'attendee.zone_assigned',
            'subject_type' => 'attendee',
            'subject_id' => $pass->id,
            'details' => [
                'order_id' => $pass->order_id,
                'from_subdivision_id' => null,
                'to_subdivision_id' => $zone->id,
                'to_zone' => $zone->name,
                'via' => $via,
            ],
        ]);

        Log::info('Zone assigned to a paid pass after payment.', [
            'organization_id' => $pass->organization_id,
            'attendee_id' => $pass->id,
            'order_id' => $pass->order_id,
            'subdivision_id' => $zone->id,
            'via' => $via,
            'user_id' => $userId,
        ]);
    }

    /**
     * An order whose passes now all sit in one zone is attributed to that zone in reports. Its
     * payout_subaccount_code is left alone: that records where the money actually settled.
     *
     * @param list<int> $orderIds
     */
    private function settleOrderZones(array $orderIds, Subdivision $zone): void
    {
        foreach ($orderIds as $orderId) {
            $zones = Attendee::query()->where('order_id', $orderId)->distinct()->pluck('subdivision_id');

            if ($zones->count() === 1 && $zones->first() === $zone->id) {
                Order::query()
                    ->whereKey($orderId)
                    ->whereNull('subdivision_id')
                    ->update(['subdivision_id' => $zone->id]);
            }
        }
    }

    private function zone(int $organizationId, int $zoneId): Subdivision
    {
        return (
            Subdivision::query()->where('organization_id', $organizationId)->whereKey($zoneId)->first() ?? abort(
                422,
                'That zone does not belong to this organisation.',
            )
        );
    }
}
