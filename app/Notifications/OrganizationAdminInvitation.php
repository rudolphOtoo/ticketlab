<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\MailCategory;
use App\Jobs\Concerns\WaitsForMailQuota;
use App\Jobs\Middleware\MailQuotaGate;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Invites an organisation's first admin to set their password.
 *
 * The link is signed and expires after INVITATION_DAYS. It works only while the account has no password, so it
 * cannot be reused to take over the account once the admin has signed up.
 */
class OrganizationAdminInvitation extends Notification implements ShouldQueue
{
    use Queueable;
    use WaitsForMailQuota;

    public const int INVITATION_DAYS = 7;

    public function __construct(
        public Organization $organization,
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

    /**
     * @return list<object>
     */
    public function middleware(User $notifiable, string $channel): array
    {
        return $channel === 'mail' ? [new MailQuotaGate(MailCategory::Transactional)] : [];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return new MailMessage()
            ->subject("You're invited to manage {$this->organization->name} on TicketLab")
            ->greeting("Hello {$notifiable->name},")
            ->line(
                "{$this->organization->name} now sells its event tickets on TicketLab, and you are its organisation admin.",
            )
            ->line(
                'Set your password to open the admin dashboard, where you manage events, ticket prices, zones and your team.',
            )
            ->action('Set my password', self::acceptUrl($notifiable))
            ->line(
                'This link works once and expires in '
                . self::INVITATION_DAYS
                . ' days. If it has expired, ask the TicketLab team to resend it.',
            );
    }

    public static function acceptUrl(User $user): string
    {
        return URL::temporarySignedRoute('invitations.accept', now()->addDays(self::INVITATION_DAYS), [
            'user' => $user->id,
        ]);
    }
}
