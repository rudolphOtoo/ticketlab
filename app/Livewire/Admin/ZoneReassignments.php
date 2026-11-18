<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Enums\OrderStatus;
use App\Livewire\Admin\Concerns\RequiresOrganizationWideAdmin;
use App\Models\Attendee;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Passes given a zone after payment, and what each zone is owed for them.
 *
 * Assigning a zone never moves money that has already settled, so this lists every affected order with its
 * per-zone share for manual settlement between zones, plus the organisation's audit trail of those changes.
 */
#[Layout('layouts.admin')]
#[Title('Zone Reassignments')]
class ZoneReassignments extends Component
{
    use RequiresOrganizationWideAdmin;

    public function render(): View
    {
        $user = Auth::user();

        if (!$user instanceof User || $user->organization_id === null || !$user->isAdmin()) {
            abort(403);
        }

        $rows = self::settlementRows($user->organization_id);

        return view('livewire.admin.zone-reassignments', [
            'label' => $user->zoneLabel(),
            'rows' => $rows,
            'totals' => collect($rows)
                ->groupBy('zone')
                ->map(static fn($zoneRows): array => [
                    'orders' => $zoneRows->pluck('reference')->unique()->count(),
                    'passes' => $zoneRows->sum('passes'),
                    'owed' => round($zoneRows->where('settled_to_zone', false)->sum('amount'), 2),
                ])
                ->sortKeys()
                ->all(),
            'audit' => AuditLog::query()
                ->where('organization_id', $user->organization_id)
                ->where('action', 'attendee.zone_assigned')
                ->with('user:id,name')
                ->latest('id')
                ->limit(100)
                ->get(),
        ]);
    }

    /**
     * One row per order and assigned zone. The amount is that zone's share of the ticket value (net of fees),
     * split evenly across the order's passes.
     *
     * @return list<array{reference: string, buyer: string, zone: string, passes: int, amount: float, settled_to: string, settled_to_zone: bool}>
     */
    public static function settlementRows(int $organizationId): array
    {
        $passes = Attendee::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('zone_assigned_at')
            ->whereHas('order', static fn(Builder $order) => $order->where('payment_status', OrderStatus::Paid->value))
            ->with([
                'subdivision:id,name,paystack_subaccount_code',
                'order' => static fn($order) => $order->withCount('attendees'),
            ])
            ->get();

        $rows = $passes
            ->groupBy(static fn(Attendee $pass): string => $pass->order_id . ':' . ($pass->subdivision_id ?? 0))
            ->map(static fn($group): array => self::settlementRow($group->firstOrFail(), $group->count()))
            ->values()
            ->all();

        usort(
            $rows,
            static fn(array $a, array $b): int => [$a['zone'], $a['reference']] <=> [$b['zone'], $b['reference']],
        );

        return $rows;
    }

    /**
     * @return array{reference: string, buyer: string, zone: string, passes: int, amount: float, settled_to: string, settled_to_zone: bool}
     */
    private static function settlementRow(Attendee $first, int $passes): array
    {
        $order = $first->order;
        $settledTo = $order->payout_subaccount_code;
        $share = $order->attendees_count > 0 ? ((float) $order->net_amount * $passes) / $order->attendees_count : 0.0;

        return [
            'reference' => $order->reference,
            'buyer' => $order->customer_name,
            'zone' => $first->subdivision->name ?? '—',
            'passes' => $passes,
            'amount' => round($share, 2),
            'settled_to' => $settledTo ?? 'Main account',
            'settled_to_zone' => $settledTo !== null && $settledTo === $first->subdivision?->paystack_subaccount_code,
        ];
    }
}
