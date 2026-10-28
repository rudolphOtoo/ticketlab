<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.ops')]
#[Title('Marketing Studio')]
class MarketingStudio extends Component
{
    public ?int $selectedEventId = null;

    public string $selectedAspectRatio = '1:1';

    public string $flyerHeadline = '';

    public string $flyerTagline = '';

    public string $flyerVenue = '';

    public string $flyerDate = '';

    public ?int $featuredSpeakerId = null;

    public string $badgeText = 'OFFICIAL PASS';

    public string $accentColor = '#6366f1';

    public string $themePreset = 'amethyst';

    public bool $showQrCode = true;

    // Vector QR Studio State
    public string $qrFgColor = '#121316';

    public string $qrBgColor = '#ffffff';

    public int $qrMargin = 2;

    public function mount(): void
    {
        $this->authorizedUser();

        $event = $this->event();
        if ($event !== null) {
            $this->selectedEventId = $event->id;
            $this->flyerHeadline = $event->title;
            $customization = is_array($event->customization) ? $event->customization : [];
            $this->flyerTagline = (string) ($customization['tagline'] ?? 'Join us for a transformative experience.');
            $this->flyerVenue = (string) ($customization['venue_name'] ?? 'Main Convention Center');
            $this->flyerDate = $event->start_at ? $event->start_at->format('M d, Y • h:i A') : 'Upcoming Event';

            $firstSpeaker = $event->speakers()->orderBy('sort_order')->first();
            $this->featuredSpeakerId = $firstSpeaker?->id;
        } else {
            $this->flyerHeadline = 'Sample Event Title';
            $this->flyerTagline = 'Join us for a transformative experience.';
            $this->flyerVenue = 'Main Convention Center';
            $this->flyerDate = 'Upcoming Event';
        }

        $this->accentColor = $this->normalizeHexColor($this->accentColor, '#6366f1');
        $this->qrFgColor = $this->normalizeHexColor($this->qrFgColor, '#121316');
        $this->qrBgColor = $this->normalizeHexColor($this->qrBgColor, '#ffffff');
    }

    public function setAspectRatio(string $ratio): void
    {
        if (in_array($ratio, ['1:1', '9:16', '16:9'], strict: true)) {
            $this->selectedAspectRatio = $ratio;
        }
    }

    public function setPreset(string $preset): void
    {
        $this->themePreset = $preset;
        match ($preset) {
            'amethyst' => $this->accentColor = '#7c3aed',
            'emerald' => $this->accentColor = '#059669',
            'amber' => $this->accentColor = '#d97706',
            'rose' => $this->accentColor = '#e11d48',
            'slate' => $this->accentColor = '#0f172a',
            default => null,
        };
    }

    public function event(): ?Event
    {
        $user = $this->authorizedUser();
        $orgId = $user->organization_id;

        if ($orgId === null) {
            if ($user->isSuperAdmin()) {
                return Event::query()->latest('id')->first();
            }

            return null;
        }

        if ($this->selectedEventId !== null) {
            /** @var Event|null $event */
            $event = Event::query()->where('organization_id', $orgId)->whereKey($this->selectedEventId)->first();

            if ($event !== null) {
                return $event;
            }
        }

        return Event::query()->where('organization_id', $orgId)->latest('id')->first();
    }

    private function authorizedUser(): User
    {
        $user = Auth::user();

        if (!$user instanceof User) {
            abort(401);
        }

        if ($user->isUsher() || !$user->hasOrganizationAccess()) {
            abort(403);
        }

        return $user;
    }

    private function normalizeHexColor(string $color, string $fallback): string
    {
        $color = trim($color);
        if (preg_match('/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $color)) {
            return strtolower($color);
        }

        return $fallback;
    }

    public function render(): View
    {
        $user = $this->authorizedUser();
        $event = $this->event();
        $org = $event?->organization ?? ($user->organization_id ? Organization::find($user->organization_id) : null);

        $events = Event::query()
            ->when($org?->id, fn($query, $orgId) => $query->where('organization_id', $orgId))
            ->orderByDesc('start_at')
            ->get();

        $speakers = $event ? $event->speakers()->orderBy('sort_order')->get() : collect();
        $ticketTypes = $event ? $event->ticketTypes()->where('is_active', true)->get() : collect();
        $lowestTicketPrice = $ticketTypes->min('price');

        $publicUrl = $event && $org
            ? route('public.event.show', ['org_slug' => $org->slug, 'event_slug' => $event->slug])
            : url('/');

        $featuredSpeaker = $this->featuredSpeakerId !== null
            ? $speakers->firstWhere('id', $this->featuredSpeakerId)
            : null;

        return view('livewire.admin.marketing-studio', [
            'event' => $event,
            'events' => $events,
            'organization' => $org,
            'speakers' => $speakers,
            'ticketTypes' => $ticketTypes,
            'lowestTicketPrice' => $lowestTicketPrice,
            'publicUrl' => $publicUrl,
            'featuredSpeaker' => $featuredSpeaker,
        ]);
    }
}
