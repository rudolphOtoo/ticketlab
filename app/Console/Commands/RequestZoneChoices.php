<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Mail\ZoneChoiceRequestMail;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

/**
 * Emails each buyer whose paid passes have no zone a link to choose one on their ticket page.
 *
 * Dry run by default: it only lists who would be emailed. Each order is emailed once (recorded in its metadata)
 * unless --resend is given. Orders that are partly zoned cannot be self-served and are listed for an admin.
 */
class RequestZoneChoices extends Command
{
    protected $signature = 'passes:request-zone-choice
        {--organization= : Only this organisation (slug)}
        {--send : Actually queue the emails (otherwise a dry run)}
        {--resend : Also email orders that were already asked}';

    protected $description = 'Email buyers of paid passes with no zone a one-time "choose your zone" link (dry run by default)';

    public function handle(): int
    {
        $send = (bool) $this->option('send');
        $orders = $this->orders()->get();

        $selfService = $orders->filter(static fn(Order $order): bool => $order->attendees->every(
            static fn($pass): bool => $pass->subdivision_id === null,
        ));
        $partlyZoned = $orders->diff($selfService);

        $this->table([
            'Organisation',
            'Order',
            'Buyer email',
            'Passes',
            'Already asked',
        ], $selfService->map(static fn(Order $order): array => [
            $order->organization->slug,
            $order->reference,
            $order->customer_email,
            $order->attendees->count(),
            $order->metadata['zone_choice_requested_at'] ?? 'no',
        ])->all());

        if ($partlyZoned->isNotEmpty()) {
            $this->warn(
                'Partly zoned orders (assign the rest on the roster; buyers cannot self-serve): '
                    . $partlyZoned->pluck('reference')->implode(', '),
            );
        }

        $toEmail = $selfService->filter(
            fn(Order $order): bool => (
                (bool) $this->option('resend') || !array_key_exists('zone_choice_requested_at', $order->metadata ?? [])
            ),
        );

        if (!$send) {
            $this->info("Dry run: {$toEmail->count()} email(s) would be queued. Nothing was sent. Re-run with --send.");

            return self::SUCCESS;
        }

        foreach ($toEmail as $order) {
            Mail::to($order->customer_email, $order->customer_name)->queue(new ZoneChoiceRequestMail($order));
            $order->forceFill([
                'metadata' => [...($order->metadata ?? []), 'zone_choice_requested_at' => now()->toIso8601String()],
            ])->save();
        }

        $this->info("Queued {$toEmail->count()} email(s) on the 'emails' queue.");

        return self::SUCCESS;
    }

    /**
     * @return Builder<Order>
     */
    private function orders(): Builder
    {
        $slug = $this->option('organization');

        return Order::query()
            ->where('payment_status', OrderStatus::Paid->value)
            ->whereHas('attendees', static fn(Builder $pass) => $pass->whereNull('subdivision_id'))
            ->when(
                is_string($slug) && $slug !== '',
                static fn(Builder $query) => $query->whereHas('organization', static fn(Builder $org) => $org->where(
                    'slug',
                    $slug,
                )),
            )
            ->with(['organization', 'attendees.ticketType.event'])
            ->orderBy('id');
    }
}
