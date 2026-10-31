<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo_url
 * @property string $subdivision_label
 * @property array<string, mixed>|null $settings
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    protected static function newFactory(): OrganizationFactory
    {
        return OrganizationFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'logo_url',
        'subdivision_label',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    /**
     * @return HasMany<Subdivision, $this>
     */
    public function subdivisions(): HasMany
    {
        return $this->hasMany(Subdivision::class);
    }

    /**
     * @return HasMany<Event, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<Attendee, $this>
     */
    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    /**
     * @return HasMany<SessionLedger, $this>
     */
    public function sessionLedgers(): HasMany
    {
        return $this->hasMany(SessionLedger::class);
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Inbox for dedicated-site enquiries: the organisation's configured address, else the platform sender.
     */
    public function contactEmail(): string
    {
        $configured = $this->settings['bespoke']['contact']['email'] ?? $this->settings['contact_email'] ?? null;

        return is_string($configured) && filter_var($configured, FILTER_VALIDATE_EMAIL)
            ? $configured
            : (string) config('mail.from.address');
    }

    /**
     * Bespoke marketing portal configuration and CMS copy with robust fallbacks.
     *
     * @return array<string, mixed>
     */
    public function bespokeSettings(): array
    {
        $defaults = [
            'nav' => [
                'about' => ['enabled' => true, 'label' => 'About'],
                'speakers' => ['enabled' => true, 'label' => 'Speakers'],
                'packages' => ['enabled' => true, 'label' => 'Packages'],
                'highlights' => ['enabled' => true, 'label' => 'Highlights'],
                'gallery' => ['enabled' => true, 'label' => 'Gallery'],
                'partnership' => ['enabled' => false, 'label' => 'Partnership'],
                'faq' => ['enabled' => true, 'label' => 'FAQs'],
                'contact' => ['enabled' => true, 'label' => 'Contact'],
            ],
            'about' => [
                'heading' => 'About ' . $this->name,
                'lead' => 'A Purposefully Curated Experience',
                'story' => '',
            ],
            'highlights' => [
                'heading' => 'Highlights',
                'lead' => 'The program at a glance: the people, the sessions and the moments that make this gathering.',
                'stats' => [],
            ],
            'gallery' => [
                'enabled' => true,
                'heading' => 'Moments & Gallery',
                'lead' => 'Capturing the vibrant energy, keynote sessions, and electric connections.',
                'images' => [],
            ],
            'partnership' => [
                'enabled' => false,
                'heading' => 'Partner With Us',
                'lead' => 'Put your brand in front of a purposeful, engaged audience and help shape the experience.',
                'tiers' => [],
            ],
            'contact' => [
                'heading' => 'Get in Touch',
                'lead' => 'Questions about passes, partnerships or the program? Send us a message and our team will reply.',
                'email' => '',
                'phone' => '',
                'address' => '',
            ],
        ];

        $configured = (array) ($this->settings['bespoke'] ?? []);

        return array_replace_recursive($defaults, $configured);
    }

    /**
     * Resolves the navigation links based on bespoke CMS configuration.
     *
     * @return list<array{key: string, label: string, url: string, active: bool}>
     */
    public function bespokeNavLinks(bool $isMultiTenant = true): array
    {
        $settings = $this->bespokeSettings();
        $navConfig = (array) ($settings['nav'] ?? []);
        $links = [];

        foreach ($navConfig as $key => $item) {
            if (!($item['enabled'] ?? true)) {
                continue;
            }

            $label = (string) ($item['label'] ?? ucfirst($key));
            $url = $isMultiTenant
                ? route('public.tenant.page', ['org_slug' => $this->slug, 'page' => $key])
                : (Route::has('dedicated.' . $key) ? route('dedicated.' . $key) : url('/' . $key));

            $links[] = [
                'key' => $key,
                'label' => $label,
                'url' => $url,
                'active' => request()->is('*/page/' . $key) || request()->routeIs('dedicated.' . $key),
            ];
        }

        return $links;
    }
}
