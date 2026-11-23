<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Enums\OrganizationStatus;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PlatformAuditLog;
use App\Models\RetiredOrganizationSlug;
use App\Models\SessionLedger;
use App\Models\Subdivision;
use App\Models\User;
use App\Services\Media\CloudinaryClient;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * Platform super admins suspend, archive and reactivate organisations. Suspended and archived organisations keep every
 * record; their public pages are unavailable, nothing is sold, their users can't sign in and the scheduler skips them.
 * Only an organisation that never took an order or a payment can be deleted. Each change is recorded with who made it
 * and why, in the platform audit trail that outlives the organisation.
 */
final readonly class OrganizationLifecycle
{
    public function __construct(
        private CloudinaryClient $cloudinary,
    ) {}

    public function suspend(Organization $organization, User $actor, string $reason): void
    {
        $this->moveTo($organization, OrganizationStatus::Suspended, $actor, $reason);
    }

    /**
     * Like suspending, and hidden from the platform lists unless archived organisations are asked for.
     */
    public function archive(Organization $organization, User $actor, string $reason): void
    {
        $this->moveTo($organization, OrganizationStatus::Archived, $actor, $reason);
    }

    public function reactivate(Organization $organization, User $actor, string $reason): void
    {
        $this->moveTo($organization, OrganizationStatus::Active, $actor, $reason);
    }

    /**
     * Why the organisation can't be deleted, or null when it never took an order or a payment. Organisations that
     * did are suspended or archived instead: their orders, passes and payments are financial records.
     */
    public function deleteBlocker(Organization $organization): ?string
    {
        $tookMoney =
            Order::query()->where('organization_id', $organization->id)->exists()
            || Attendee::query()->where('organization_id', $organization->id)->exists()
            || SessionLedger::query()->where('organization_id', $organization->id)->exists();

        if ($tookMoney) {
            return (
                "{$organization->name} has orders or payments, so it can't be deleted. Suspend it to take it "
                . 'offline, or archive it to also hide it from the platform lists. Both keep every record.'
            );
        }

        return $organization->isDedicatedHome()
            ? "{$organization->name} is the dedicated site's home organisation. Choose another home first."
            : null;
    }

    /**
     * Deletes an organisation that never took an order or a payment: its zones, users, events (all empty) and its
     * Cloudinary folder. Its web address stays reserved. Returns the reason instead when it can't be deleted.
     */
    public function delete(Organization $organization, User $actor, string $reason): ?string
    {
        if (!$actor->isSuperAdmin()) {
            throw new InvalidArgumentException('Only platform super admins delete organisations.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required.');
        }

        $blocker = DB::transaction(function () use ($organization, $actor, $reason): ?string {
            // Locked, so nothing can be sold between the check and the delete.
            $locked = Organization::query()->whereKey($organization->id)->lockForUpdate()->first();

            if ($locked === null) {
                return null;
            }

            $blocker = $this->deleteBlocker($locked);

            if ($blocker !== null) {
                return $blocker;
            }

            $users = User::query()->where('organization_id', $locked->id);
            $this->audit($locked, $actor, 'organization.deleted', trim($reason), [
                'events' => Event::query()->where('organization_id', $locked->id)->count(),
                'zones' => Subdivision::query()->where('organization_id', $locked->id)->count(),
                'users' => (clone $users)->pluck('email')->all(),
            ]);
            RetiredOrganizationSlug::query()->create([
                'slug' => $locked->slug,
                'organization_name' => $locked->name,
                'retired_at' => now(),
            ]);

            // Its users have no other organisation; the database would only detach them.
            $users->delete();
            // Zones, events and everything under them (tiers, programme, holds), and the organisation's own audit trail.
            $locked->delete();

            return null;
        });

        if ($blocker === null) {
            $this->deleteImages($organization);
        }

        return $blocker;
    }

    /**
     * The database is already committed: a Cloudinary failure leaves only unused images behind, so it is logged.
     */
    private function deleteImages(Organization $organization): void
    {
        $folder = MediaUrl::organizationRoot($organization->id);

        if (!$this->cloudinary->isConfigured()) {
            return;
        }

        try {
            $this->cloudinary->destroyFolder($folder);
        } catch (Throwable $exception) {
            Log::warning('Deleted organisation\'s Cloudinary folder could not be removed.', [
                'organization_id' => $organization->id,
                'folder' => $folder,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $details
     */
    public function audit(
        Organization $organization,
        User $actor,
        string $action,
        string $reason,
        array $details = [],
    ): void {
        PlatformAuditLog::query()->create([
            'user_id' => $actor->id,
            'organization_id' => $organization->id,
            'organization_name' => $organization->name,
            'organization_slug' => $organization->slug,
            'action' => $action,
            'reason' => $reason,
            'details' => $details === [] ? null : $details,
        ]);
    }

    private function moveTo(Organization $organization, OrganizationStatus $status, User $actor, string $reason): void
    {
        if (!$actor->isSuperAdmin()) {
            throw new InvalidArgumentException('Only platform super admins change an organisation\'s status.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reason is required.');
        }

        $from = $organization->status;

        if ($from === $status) {
            return;
        }

        $organization->forceFill(['status' => $status, 'status_changed_at' => now()])->save();

        $this->audit(
            $organization,
            $actor,
            'organization.' . match ($status) {
                OrganizationStatus::Active => 'reactivated',
                OrganizationStatus::Suspended => 'suspended',
                OrganizationStatus::Archived => 'archived',
            },
            trim($reason),
            ['from' => $from->value, 'to' => $status->value],
        );
    }
}
