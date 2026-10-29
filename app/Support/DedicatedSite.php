<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use App\Models\Organization;
use App\Models\PlatformSetting;

/**
 * Resolves the single organisation (and its headline event) served when the platform runs in Dedicated mode.
 */
final readonly class DedicatedSite
{
    public function __construct(
        public Organization $organization,
        public ?Event $event,
    ) {}

    public static function resolve(): ?self
    {
        if (!PlatformSetting::isDedicatedMode()) {
            return null;
        }

        $slug = PlatformSetting::getHomeOrgSlug();
        $organization = $slug === null ? null : Organization::query()->where('slug', $slug)->first();

        if ($organization === null) {
            return null;
        }

        $relations = ['ticketTypes', 'speakers', 'schedules', 'faqs'];
        $events = Event::query()->where('organization_id', $organization->id)->with($relations)->latest('id');

        $event = (clone $events)->where('status', 'published')->first() ?? $events->first();

        return new self($organization, $event);
    }

    /**
     * @return array{organization: Organization, event: Event|null}
     */
    public function viewData(): array
    {
        return ['organization' => $this->organization, 'event' => $this->event];
    }
}
