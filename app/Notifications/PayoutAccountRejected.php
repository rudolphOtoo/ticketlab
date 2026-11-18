<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Subdivision;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells organisation admins that a zone's Paystack subaccount stopped being valid, so its paid sales are paused.
 */
class PayoutAccountRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Subdivision $zone,
        public string $reason,
    ) {
        $this->onQueue('transactional');
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $label = trim($this->zone->organization->subdivision_label) !== ''
            ? $this->zone->organization->subdivision_label
            : 'Zone';

        return new MailMessage()
            ->subject("Action needed: {$this->zone->name} cannot take payments")
            ->greeting("Hello {$notifiable->name},")
            ->line(
                "Paystack no longer accepts the payout account for the {$this->zone->name} {$label}: {$this->reason}",
            )
            ->line(
                "Online sales for {$this->zone->name} are paused until a valid subaccount is entered. Other "
                . strtolower($label)
                . 's are not affected.',
            )
            ->action('Fix the payout account', route('admin.payouts'));
    }
}
