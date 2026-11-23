<?php

declare(strict_types=1);

namespace App\Livewire\Platform;

use App\Livewire\Concerns\UploadsImages;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Subdivision;
use App\Models\User;
use App\Notifications\OrganizationAdminInvitation;
use App\Services\Media\CloudinaryClient;
use App\Services\Media\CloudinaryImage;
use App\Services\Payment\PaystackSubaccountResolver;
use App\Support\MediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Super admin onboarding: one step creates the organisation, its zones (each with its Paystack payout
 * subaccount) and the organisation's first admin, who is emailed an invitation to set a password.
 */
#[Layout('layouts.platform')]
#[Title('Onboard an organisation')]
class OnboardOrganization extends Component
{
    use UploadsImages;

    public string $name = '';

    public string $slug = '';

    public bool $slugTouched = false;

    public string $subdivisionLabel = 'Zone';

    public string $contactEmail = '';

    public string $contactPhone = '';

    public string $logoUrl = '';

    /** Names this form's onboarding upload folder until the organisation (and its own folder) exists. */
    #[Locked]
    public string $uploadToken = '';

    public string $brandColor = '#18181b';

    public string $timezone = 'Africa/Accra';

    /** @var list<array{name: string, subaccount: string}> */
    public array $zones = [['name' => '', 'subaccount' => '']];

    public string $adminName = '';

    public string $adminEmail = '';

    public function mount(): void
    {
        self::superAdmin();
        $this->uploadToken = Str::lower(Str::random(24));
    }

    public function updatedName(string $value): void
    {
        if (!$this->slugTouched) {
            $this->slug = Str::slug($value);
        }
    }

    public function updatedSlug(string $value): void
    {
        $this->slugTouched = true;
        $this->slug = Str::slug($value);
    }

    public function addZone(): void
    {
        $this->zones[] = ['name' => '', 'subaccount' => ''];
    }

    public function removeZone(int $index): void
    {
        $this->zones = array_values(array_filter(
            $this->zones,
            static fn(int $key): bool => $key !== $index,
            ARRAY_FILTER_USE_KEY,
        ));
    }

    public function save(PaystackSubaccountResolver $subaccounts): void
    {
        $actor = self::superAdmin();
        $this->slug = Str::slug($this->slug !== '' ? $this->slug : $this->name);
        $this->zones = array_values(array_filter(
            $this->zones,
            static fn(array $zone): bool => trim($zone['name']) !== '' || trim($zone['subaccount']) !== '',
        ));

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required',
                'string',
                'max:80',
                'alpha_dash',
                Rule::notIn(Organization::RESERVED_SLUGS),
                Rule::unique(Organization::class, 'slug'),
            ],
            'subdivisionLabel' => ['required', 'string', 'max:30'],
            'contactEmail' => ['required', 'email', 'max:255'],
            'contactPhone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'logoUrl' => ['nullable', 'url:https', 'max:2048'],
            'brandColor' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'timezone' => ['required', 'timezone:all'],
            'zones' => ['required', 'array', 'min:1', 'max:50'],
            'zones.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'zones.*.subaccount' => ['nullable', 'string', 'max:64', 'regex:/^ACCT_[a-z0-9]+$/i', 'distinct'],
            'adminName' => ['required', 'string', 'max:255'],
            'adminEmail' => ['required', 'email', 'max:255', Rule::unique(User::class, 'email')],
        ], [
            'zones.required' => 'Add at least one ' . strtolower($this->subdivisionLabel) . '.',
            'zones.*.subaccount.regex' => 'Paystack subaccount codes look like ACCT_xxxxxxxxxxxx.',
            'slug.not_in' => 'That address is used by TicketLab itself. Choose another.',
        ]);

        $this->verifySubaccounts($subaccounts);

        $admin = null;
        $organization = DB::transaction(function () use ($actor, &$admin): Organization {
            return $this->persist($actor, $admin);
        });

        $this->moveLogoIntoOrganization($organization);

        // Sent after commit, so the link never points at an account that was rolled back.
        $admin?->notify(new OrganizationAdminInvitation($organization));

        session()->flash(
            'platform_status',
            "{$organization->name} is set up. An invitation was emailed to {$this->adminEmail}.",
        );
        $this->redirectRoute('platform.organizations', navigate: true);
    }

    /**
     * Creates the organisation, its zones, its uninvited admin and the audit entry. Runs inside one transaction.
     *
     * @param-out User $admin
     */
    private function persist(User $actor, ?User &$admin): Organization
    {
        $organization = Organization::query()->create([
            'name' => trim($this->name),
            'slug' => $this->slug,
            'subdivision_label' => trim($this->subdivisionLabel),
            'logo_url' => $this->logoUrl !== '' ? $this->logoUrl : null,
            'settings' => [
                'currency' => 'GHS',
                'timezone' => $this->timezone,
                'contact_email' => $this->contactEmail,
                'contact_phone' => $this->contactPhone !== '' ? $this->contactPhone : null,
                'brand_color' => $this->brandColor,
            ],
        ]);

        // An uploaded logo wins over a pasted link (moved into the organisation's folder after commit).
        if ($this->pendingImage('logo') !== null) {
            $organization->forceFill(['logo_url' => $this->pendingImage('logo')])->save();
        }

        foreach ($this->zones as $zone) {
            $created = Subdivision::query()->create([
                'organization_id' => $organization->id,
                'name' => trim($zone['name']),
                'slug' => Str::slug($zone['name']),
                'paystack_subaccount_code' => trim($zone['subaccount']) !== '' ? trim($zone['subaccount']) : null,
            ]);

            // verifySubaccounts() confirmed every code with Paystack before this transaction started.
            if ($created->paystack_subaccount_code !== null) {
                app(PaystackSubaccountResolver::class)->markVerified($created);
            }
        }

        // No password: the invitation link sets one. Privileges only ever via assignMembership().
        $admin = User::query()->create([
            'name' => trim($this->adminName),
            'email' => $this->adminEmail,
            'password' => null,
        ]);
        $admin->assignMembership('admin', $organization->id);

        AuditLog::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $actor->id,
            'action' => 'organization.onboarded',
            'subject_type' => 'organization',
            'subject_id' => $organization->id,
            'details' => ['zones' => count($this->zones), 'admin_email' => $admin->email],
        ]);

        return $organization;
    }

    protected function imageUploadFolder(string $field): ?string
    {
        self::superAdmin();

        return $field === 'logo' ? MediaUrl::onboardingDirectory($this->uploadToken) : null;
    }

    /**
     * The logo was uploaded before the organisation existed; it now moves into orgs/{id}/logos. If Cloudinary
     * refuses, the logo keeps working from the onboarding folder and the move is logged for a later retry.
     */
    private function moveLogoIntoOrganization(Organization $organization): void
    {
        $image = CloudinaryImage::fromStored($this->pendingImage('logo'));
        $this->pendingImageSaved('logo');

        if ($image === null) {
            return;
        }

        $target = MediaUrl::organizationDirectory($organization->id, 'logos') . '/' . basename($image->publicId);

        try {
            $version = app(CloudinaryClient::class)->rename($image->publicId, $target);
            $organization->forceFill(['logo_url' => new CloudinaryImage($target, $version)->toStored()])->save();
        } catch (Throwable $exception) {
            Log::warning('Onboarding logo stayed in the onboarding folder; it still displays.', [
                'organization_id' => $organization->id,
                'public_id' => $image->publicId,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    public function render(): View
    {
        return view('livewire.platform.onboard-organization');
    }

    /**
     * Every code is checked against the live Paystack account for the active key before anything is saved,
     * so a zone is never created with a subaccount that would make its checkouts fail.
     */
    private function verifySubaccounts(PaystackSubaccountResolver $subaccounts): void
    {
        $errors = [];

        foreach ($this->zones as $index => $zone) {
            $code = trim($zone['subaccount']);

            if ($code === '') {
                continue;
            }

            $reason = $subaccounts->rejectionReason($subaccounts->inspect($code));

            if ($reason !== null) {
                $errors["zones.{$index}.subaccount"] = $reason;
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
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
