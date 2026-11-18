<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\MediaUrl;
use Carbon\CarbonImmutable;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

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

    /**
     * First URL segments the platform itself uses. An organisation slug can never be one of these, or its public
     * site at /{slug} would be unreachable; routes/public.php builds its pattern from this list.
     */
    public const array RESERVED_SLUGS = [
        'ops',
        'admin',
        'api',
        'livewire',
        'up',
        'dashboard',
        'settings',
        'tickets',
        'events',
        'platform',
        'login',
        'logout',
        'register',
        'invitations',
        'forgot-password',
        'reset-password',
        'two-factor-challenge',
        'user',
        'email',
        'storage',
        'build',
        'about-us',
        'speakers',
        'conference-packages',
        'highlights',
        'gallery',
        'partnership',
        'faq',
        'contact',
    ];

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
     * The logo's URL: an uploaded logo (as a WebP copy of the given size once generated), or a pasted https or
     * same-site link. Anything else (javascript:, data:, protocol-relative) is never rendered.
     */
    public function logoUrl(string $size = 'thumb'): ?string
    {
        $logo = $this->logo_url;

        if (!is_string($logo) || trim($logo) === '') {
            return null;
        }

        if (MediaUrl::isExternal($logo)) {
            return preg_match('#^(https://|/(?!/))#i', $logo) === 1 ? $logo : null;
        }

        return MediaUrl::resolve($logo, $size);
    }

    /**
     * Badge letters for the organisation: the first letter of its first two words ("Perez Dome & …" → "PD").
     * Words that start with a symbol, such as "&", are skipped.
     */
    public function initials(): string
    {
        $words = preg_split('/\s+/u', trim($this->name), -1, PREG_SPLIT_NO_EMPTY);
        $letters = array_filter(
            is_array($words) ? $words : [],
            static fn(string $word): bool => preg_match('/^\p{L}/u', $word) === 1,
        );

        return Str::upper(implode('', array_map(
            static fn(string $word): string => Str::substr($word, 0, 1),
            array_slice(array_values($letters), 0, 2),
        )));
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
     * True only for the one organisation the dedicated site serves at "/". Every other organisation keeps its
     * own /{org_slug} URLs even in dedicated mode, so its pages never link into the home organisation's site.
     */
    public function isDedicatedHome(): bool
    {
        return PlatformSetting::isDedicatedMode() && PlatformSetting::getHomeOrgSlug() === $this->slug;
    }

    /**
     * This organisation's public home page.
     */
    public function homeUrl(): string
    {
        return $this->isDedicatedHome() ? url('/') : route('public.tenant.home', ['org_slug' => $this->slug]);
    }

    /**
     * One of this organisation's marketing sub-pages (about, speakers, packages, highlights, gallery, partnership,
     * faq, contact).
     */
    public function pageUrl(string $page): string
    {
        return (
            $this->isDedicatedHome() && Route::has('dedicated.' . $page)
                ? route('dedicated.' . $page)
                : route('public.tenant.page', ['org_slug' => $this->slug, 'page' => $page])
        );
    }

    /**
     * Resolves the navigation links based on bespoke CMS configuration.
     *
     * @return list<array{key: string, label: string, url: string, active: bool}>
     */
    public function bespokeNavLinks(): array
    {
        $settings = $this->bespokeSettings();
        $navConfig = (array) ($settings['nav'] ?? []);
        $links = [];

        foreach ($navConfig as $key => $item) {
            if (!($item['enabled'] ?? true)) {
                continue;
            }

            $label = (string) ($item['label'] ?? ucfirst($key));
            $url = $this->pageUrl((string) $key);

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
