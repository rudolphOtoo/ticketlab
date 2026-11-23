<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\RequiresOrganizationWideAdmin;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Services\Events\EventLifecycle;
use App\Support\CsvWriter;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Paid orders of a cancelled event, per zone, for refunding by hand. Refunds are not automatic yet (KANBAN B-2):
 * Paystack refunds are made in the Paystack dashboard, cash refunds at the desk.
 */
#[Layout('layouts.admin')]
#[Title('Refund list')]
class EventRefunds extends Component
{
    use RequiresOrganizationWideAdmin;

    #[Locked]
    public int $eventId;

    public function mount(int $event): void
    {
        $this->eventId = $this->event($event)->id;
    }

    public function exportCsv(EventLifecycle $lifecycle): StreamedResponse
    {
        $event = $this->event($this->eventId);
        $zones = $lifecycle->refundableOrders($event);

        return response()->streamDownload(
            static function () use ($zones): void {
                $writer = CsvWriter::toOutput();

                foreach ($zones as $zone) {
                    foreach ($zone['orders'] as $order) {
                        $writer->addRow(self::row($zone['zone'], $order));
                    }
                }

                $writer->close();
            },
            "refunds_{$event->slug}_" . now()->format('Y_m_d_His') . '.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * @return array<string, string|int|float|null>
     */
    private static function row(string $zone, Order $order): array
    {
        return [
            'Zone' => $zone,
            'Order' => $order->reference,
            'Buyer' => $order->customer_name,
            'Email' => $order->customer_email,
            'Phone' => $order->customer_phone,
            'Paid by' => $order->payment_method->label(),
            'Paid at' => (string) $order->paid_at?->format('Y-m-d H:i'),
            'Passes' => (int) $order->getAttribute('passes_count'),
            'Amount paid (GHS)' => number_format((float) $order->gross_amount, 2, '.', ''),
        ];
    }

    private function event(int $id): Event
    {
        $user = Auth::user();
        $organizationId = $user instanceof User ? $user->organization_id : null;

        return Event::query()->where('organization_id', $organizationId)->whereKey($id)->firstOrFail();
    }

    public function render(EventLifecycle $lifecycle): View
    {
        $event = $this->event($this->eventId);
        $zones = $lifecycle->refundableOrders($event);

        return view('livewire.admin.event-refunds', [
            'event' => $event,
            'zones' => $zones,
            'grandTotal' => round(array_sum(array_column($zones, 'total')), 2),
            'orderCount' => array_sum(array_map(static fn(array $zone): int => count($zone['orders']), $zones)),
        ]);
    }
}
