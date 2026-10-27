<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Event;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.ops')]
#[Title('Marketing Studio')]
class MarketingStudio extends Component
{
    public string $aspectRatio = '1:1';

    public string $eventId = '';

    public string $logoUrl = '';

    public string $primaryColor = '#0f766e';

    public string $secondaryColor = '#111827';

    public string $qrForeground = '#111827';

    public string $qrBackground = '#ffffff';

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        // Prevent door ushers from accessing marketing studio
        if (property_exists($user, 'role') && $user->role === 'usher') {
            abort(403);
        }

        // Tenant/authorization enforcement - require organization context for non-superadmins
        if (! ($user->role === 'superadmin')) {
            if (! $user->organization_id && ! $user->organization) {
                abort(403);
            }
        }

        $organization = $user->organization;
        if ($organization && $organization->logo_url) {
            $this->logoUrl = $organization->logo_url;
        }

        // Basic color validation
        $this->primaryColor = $this->normalizeHexColor($this->primaryColor, '#0f766e');
        $this->secondaryColor = $this->normalizeHexColor($this->secondaryColor, '#111827');
        $this->qrForeground = $this->normalizeHexColor($this->qrForeground, '#111827');
        $this->qrBackground = $this->normalizeHexColor($this->qrBackground, '#ffffff');
    }

    public function updatedPrimaryColor(string $value): void
    {
        $this->primaryColor = $this->normalizeHexColor($value, '#0f766e');
    }

    public function updatedSecondaryColor(string $value): void
    {
        $this->secondaryColor = $this->normalizeHexColor($value, '#111827');
    }

    public function updatedQrForeground(string $value): void
    {
        $this->qrForeground = $this->normalizeHexColor($value, '#111827');
    }

    public function updatedQrBackground(string $value): void
    {
        $this->qrBackground = $this->normalizeHexColor($value, '#ffffff');
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
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }
        if (property_exists($user, 'role') && $user->role === 'usher') {
            abort(403);
        }

        if (! ($user->role === 'superadmin')) {
            if (! $user->organization_id && ! $user->organization) {
                abort(403);
            }
        }

        $organization = $user?->organization;

        $events = Event::query()
            ->when($organization?->id, fn ($query, $orgId) => $query->where('organization_id', $orgId))
            ->when(! $organization && $user?->role === 'superadmin', fn ($query) => $query)
            ->orderByDesc('start_at')
            ->get();

        // If no organization and not superadmin, restrict
        if (! $organization && ! ($user?->role === 'superadmin')) {
            $events = collect();
        }

        $selectedEvent = null;
        if ($this->eventId) {
            $selectedEvent = $events->firstWhere('id', (int) $this->eventId);
            if ($selectedEvent && $organization?->id && $selectedEvent->organization_id !== $organization->id && $user->role !== 'superadmin') {
                abort(403);
            }
        } elseif ($events->isNotEmpty()) {
            $selectedEvent = $events->first();
            $this->eventId = (string) $selectedEvent->id;
        }

        $this->primaryColor = $this->normalizeHexColor($this->primaryColor, '#0f766e');
        $this->secondaryColor = $this->normalizeHexColor($this->secondaryColor, '#111827');
        $this->qrForeground = $this->normalizeHexColor($this->qrForeground, '#111827');
        $this->qrBackground = $this->normalizeHexColor($this->qrBackground, '#ffffff');

        return view('livewire.admin.marketing-studio', [
            'organization' => $organization,
            'events' => $events,
            'selectedEvent' => $selectedEvent,
        ]);
    }
}
