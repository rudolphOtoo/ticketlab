<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Enums\OrganizationStatus;
use App\Livewire\Concerns\UploadsImages;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PlatformAuditLog;
use App\Models\Subdivision;
use App\Models\User;
use App\Services\Payment\PaystackSubaccountResolver;
use App\Services\Platform\OrganizationLifecycle;
use App\Support\MediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Super admin edits an organisation's identity and adds zones.
 *
 * The web address (slug) is not editable here: it is printed on tickets and shared links, so changing it is a
 * separate, deliberate decision. Existing zones and their payout accounts are managed by the organisation admin.
 */
#[Layout('layouts.platform')]
#[Title('Edit organisation')]
class EditOrganization extends Component
{
    use UploadsImages;

    #[Locked]
    public int $organizationId;

    public string $name = '';

    public string $subdivisionLabel = 'Zone';

    public string $contactEmail = '';

    public string $contactPhone = '';

    public string $logoUrl = '';

    public string $brandColor = '#18181b';

    public string $timezone = 'Africa/Accra';

    public string $newZoneName = '';

    public string $newZoneSubaccount = '';

    public ?string $flashMessage = null;

    /** Why the organisation is being suspended, archived or reactivated: required, and kept in the audit trail. */
    public string $statusReason = '';

    public string $deleteReason = '';

    /** The organisation's web address, typed to confirm a delete. */
    public string $deleteConfirmation = '';

    public function mount(Organization $organization): void
    {
        self::superAdmin();
        $settings = $organization->settings ?? [];

        $this->organizationId = $organization->id;
        $this->name = $organization->name;
        $this->subdivisionLabel = $organization->subdivision_label;
        $this->contactEmail = is_string($settings['contact_email'] ?? null) ? $settings['contact_email'] : '';
        $this->contactPhone = is_string($settings['contact_phone'] ?? null) ? $settings['contact_phone'] : '';
        // The URL field shows pasted links only; an uploaded logo is shown as a preview with a Remove button.
        $this->logoUrl = MediaUrl::isExternal($organization->logo_url) ? (string) $organization->logo_url : '';
        $this->brandColor = is_string($settings['brand_color'] ?? null) ? $settings['brand_color'] : '#18181b';
        $this->timezone = is_string($settings['timezone'] ?? null) ? $settings['timezone'] : 'Africa/Accra';
    }

    public function save(): void
    {
        $actor = self::superAdmin();

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'subdivisionLabel' => ['required', 'string', 'max:30'],
            'contactEmail' => ['required', 'email', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'logoUrl' => ['nullable', 'url:https', 'max:2048'],
            'brandColor' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'timezone' => ['required', 'timezone:all'],
        ]);

        $organization = $this->organization();
        $before = $organization->only(['name', 'subdivision_label', 'logo_url']);

        $previousLogo = $organization->logo_url;
        $logo = $this->nextLogo($organization, $previousLogo);

        $organization->forceFill([
            'name' => trim($this->name),
            'subdivision_label' => trim($this->subdivisionLabel),
            'logo_url' => $logo,
            'settings' => [
                ...($organization->settings ?? []),
                'contact_email' => $this->contactEmail,
                'contact_phone' => $this->contactPhone !== '' ? $this->contactPhone : null,
                'brand_color' => $this->brandColor,
                'timezone' => $this->timezone,
            ],
        ])->save();

        // A replaced uploaded logo is deleted from Cloudinary; pasted links are never touched.
        if ($previousLogo !== $logo) {
            MediaUrl::deleteStored($previousLogo);
        }

        $this->pendingImageSaved('logo');
        $this->audit($actor, 'organization.updated', ['before' => $before]);
        $this->flashMessage = "{$organization->name} was updated.";
    }

    public function removeLogo(): void
    {
        $actor = self::superAdmin();
        $organization = $this->organization();

        MediaUrl::deleteStored($organization->logo_url);
        $organization->forceFill(['logo_url' => null])->save();
        $this->logoUrl = '';
        $this->discardPendingImage('logo');

        $this->audit($actor, 'organization.logo_removed', []);
        $this->flashMessage = 'The logo was removed.';
    }

    protected function imageUploadFolder(string $field): ?string
    {
        self::superAdmin();

        return $field === 'logo' ? MediaUrl::organizationDirectory($this->organizationId, 'logos') : null;
    }

    /**
     * A new upload wins; otherwise a pasted link; otherwise the current uploaded logo stays.
     */
    private function nextLogo(Organization $organization, ?string $current): ?string
    {
        if ($this->pendingImage('logo') !== null) {
            return $this->pendingImage('logo');
        }

        if ($this->logoUrl !== '') {
            return $this->logoUrl;
        }

        return MediaUrl::isExternal($current) ? null : $current;
    }

    /**
     * Adds a zone. A subaccount code, when given, is verified with Paystack first and stored as verified.
     */
    public function addZone(PaystackSubaccountResolver $subaccounts): void
    {
        $actor = self::superAdmin();
        $organization = $this->organization();

        $this->validate(
            [
                'newZoneName' => [
                    'required',
                    'string',
                    'max:100',
                    // Zone names are matched ignoring case (e.g. by tenancy:move-event), so "accra" duplicates "Accra".
                    static function (string $attribute, mixed $value, \Closure $fail) use ($organization): void {
                        $taken = Subdivision::query()
                            ->where('organization_id', $organization->id)
                            ->whereRaw('lower(name) = ?', [mb_strtolower(trim((string) $value))])
                            ->exists();

                        if ($taken) {
                            $fail('This organisation already has a zone with that name.');
                        }
                    },
                ],
                'newZoneSubaccount' => ['nullable', 'string', 'max:64', 'regex:/^ACCT_[a-z0-9]+$/i'],
            ],
            ['newZoneSubaccount.regex' => 'Paystack subaccount codes look like ACCT_xxxxxxxxxxxx.'],
        );

        $code = trim($this->newZoneSubaccount);
        $inspection = $code === '' ? null : $subaccounts->inspect($code);
        $reason = $inspection === null ? null : $subaccounts->rejectionReason($inspection);

        if ($reason !== null) {
            $this->addError('newZoneSubaccount', $reason);

            return;
        }

        $zone = DB::transaction(function () use ($organization, $code, $subaccounts, $actor): Subdivision {
            $zone = Subdivision::query()->create([
                'organization_id' => $organization->id,
                'name' => trim($this->newZoneName),
                'slug' => $this->uniqueZoneSlug($organization, $this->newZoneName),
                'paystack_subaccount_code' => $code !== '' ? $code : null,
            ]);

            if ($code !== '') {
                $subaccounts->markVerified($zone);
            }

            $this->audit($actor, 'organization.zone_added', ['zone' => $zone->name, 'has_subaccount' => $code !== '']);

            return $zone;
        });

        $this->reset(['newZoneName', 'newZoneSubaccount']);
        $this->flashMessage =
            "{$zone->name} was added"
            . ($code === '' ? ' (no payout account yet: paid sales stay paused for it).' : '.');
    }

    /**
     * Suspend, archive or reactivate the organisation, with a reason.
     */
    public function changeStatus(string $to, OrganizationLifecycle $lifecycle): void
    {
        $actor = self::superAdmin();
        $status = OrganizationStatus::tryFrom($to) ?? abort(422);

        $this->validate(['statusReason' => ['required', 'string', 'min:5', 'max:1000']], [
            'statusReason.required' => 'Give a reason: it is kept in the audit trail.',
        ]);

        $organization = $this->organization();
        $reason = trim($this->statusReason);

        match ($status) {
            OrganizationStatus::Active => $lifecycle->reactivate($organization, $actor, $reason),
            OrganizationStatus::Suspended => $lifecycle->suspend($organization, $actor, $reason),
            OrganizationStatus::Archived => $lifecycle->archive($organization, $actor, $reason),
        };

        $this->statusReason = '';
        $this->flashMessage = match ($status) {
            OrganizationStatus::Active
                => "{$organization->name} is active again: its pages, sales and sign-ins are back.",
            OrganizationStatus::Suspended => "{$organization->name} is suspended: its pages are unavailable, sales are "
                . 'stopped and its users are signed out. Nothing was deleted.',
            OrganizationStatus::Archived => "{$organization->name} is archived: like suspended, and hidden from the "
                . 'organisation list. Nothing was deleted.',
        };
    }

    /**
     * Deletes an organisation that never took an order or a payment, once its web address is typed exactly.
     */
    public function deleteOrganization(OrganizationLifecycle $lifecycle): void
    {
        $actor = self::superAdmin();
        $organization = $this->organization();

        $this->validate([
            'deleteReason' => ['required', 'string', 'min:5', 'max:1000'],
            'deleteConfirmation' => ['required', 'in:' . $organization->slug],
        ], [
            'deleteReason.required' => 'Give a reason: it is kept in the audit trail.',
            'deleteConfirmation.required' => 'Type the web address to confirm.',
            'deleteConfirmation.in' => 'Type the web address exactly as shown to confirm.',
        ]);

        $blocker = $lifecycle->delete($organization, $actor, trim($this->deleteReason));

        if ($blocker !== null) {
            $this->addError('deleteConfirmation', $blocker);

            return;
        }

        session()->flash(
            'platform_status',
            "{$organization->name} was deleted. Its web address /{$organization->slug} stays reserved.",
        );
        $this->redirectRoute('platform.organizations', navigate: true);
    }

    public function render(PaystackSubaccountResolver $subaccounts, OrganizationLifecycle $lifecycle): View
    {
        $organization = $this->organization();

        return view('livewire.platform.edit-organization', [
            'deleteBlocker' => $lifecycle->deleteBlocker($organization),
            'history' => PlatformAuditLog::query()
                ->where('organization_id', $organization->id)
                ->with('user:id,name')
                ->latest('id')
                ->limit(20)
                ->get(),
            'organization' => $organization,
            'zones' => Subdivision::query()->where('organization_id', $organization->id)->orderBy('name')->get(),
            'subaccounts' => $subaccounts,
        ]);
    }

    private function organization(): Organization
    {
        return Organization::query()->findOrFail($this->organizationId);
    }

    private function uniqueZoneSlug(Organization $organization, string $name): string
    {
        $base = Str::slug($name) !== '' ? Str::slug($name) : 'zone';
        $slug = $base;

        for (
            $n = 2;
            Subdivision::query()->where('organization_id', $organization->id)->where('slug', $slug)->exists();
            $n++
        ) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    /**
     * @param array<string, mixed> $details
     */
    private function audit(User $actor, string $action, array $details): void
    {
        AuditLog::query()->create([
            'organization_id' => $this->organizationId,
            'user_id' => $actor->id,
            'action' => $action,
            'subject_type' => 'organization',
            'subject_id' => $this->organizationId,
            'details' => $details,
        ]);
    }

    private static function superAdmin(): User
    {
        $user = Auth::user();

        if (!$user instanceof User || !$user->isSuperAdmin()) {
            abort(403, 'Platform Super Admin clearance required.');
        }

        return $user;
    }
}
