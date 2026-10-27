<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Event;
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
    public string $aspectRatio = '1:1';

    public string $eventId = '';

    public string $logoUrl = '';

    public string $primaryColor = '#0f766e';

    public string $secondaryColor = '#111827';

    public string $qrForeground = '#111827';

    public string $qrBackground = '#ffffff';

    public function mount(): void
    {
        $organization = $this->authorizedUser()->organization;
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

    /**
     * Defence in depth alongside the route middleware: ushers and users without
     * an organisation (other than superadmins) never reach the studio.
     */
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
        $organization = $this->authorizedUser()->organization;

        // Superadmins without an organisation see every event; everyone else is tenant-scoped.
        $events = Event::query()
            ->when($organization?->id, fn($query, $orgId) => $query->where('organization_id', $orgId))
            ->orderByDesc('start_at')
            ->get();

        $selectedEvent = null;
        if ($this->eventId) {
            // Only events already scoped to this tenant are selectable.
            $selectedEvent = $events->firstWhere('id', (int) $this->eventId);
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
