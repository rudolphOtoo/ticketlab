<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Support\MediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.admin')]
#[Title('Bespoke Portal CMS & Navigation')]
class BespokeStudio extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $organizationId = null;

    public string $activeTab = 'nav';

    public ?string $flashMessage = null;

    // Navigation Flow
    public bool $nav_about_enabled = true;
    public string $nav_about_label = 'About';

    public bool $nav_speakers_enabled = true;
    public string $nav_speakers_label = 'Speakers';

    public bool $nav_packages_enabled = true;
    public string $nav_packages_label = 'Packages';

    public bool $nav_highlights_enabled = true;
    public string $nav_highlights_label = 'Highlights';

    public bool $nav_gallery_enabled = true;
    public string $nav_gallery_label = 'Gallery';

    public bool $nav_partnership_enabled = false;
    public string $nav_partnership_label = 'Partnership';

    public bool $nav_faq_enabled = true;
    public string $nav_faq_label = 'FAQs';

    public bool $nav_contact_enabled = true;
    public string $nav_contact_label = 'Contact';

    // About Sub-Page
    public string $about_heading = '';
    public string $about_lead = '';
    public string $about_story = '';

    // Highlights Sub-Page
    public string $highlights_heading = '';
    public string $highlights_lead = '';
    public string $stat1_value = '';
    public string $stat1_label = '';
    public string $stat2_value = '';
    public string $stat2_label = '';
    public string $stat3_value = '';
    public string $stat3_label = '';
    public string $stat4_value = '';
    public string $stat4_label = '';

    // Partnership Sub-Page
    public bool $partnership_enabled = false;
    public string $partnership_heading = '';
    public string $partnership_lead = '';
    public string $partner1_name = '';
    public string $partner1_body = '';
    public string $partner2_name = '';
    public string $partner2_body = '';
    public string $partner3_name = '';
    public string $partner3_body = '';

    // Contact Sub-Page
    public string $contact_heading = '';
    public string $contact_lead = '';
    public string $contact_email = '';
    public string $contact_phone = '';
    public string $contact_address = '';

    // Gallery Sub-Page
    public bool $gallery_enabled = true;
    public string $gallery_heading = '';
    public string $gallery_lead = '';

    /**
     * @var list<array{id: string, url: string, title: string, caption: string, tag: string}>
     */
    public array $gallery_images = [];

    public string $new_image_source_mode = 'link'; // 'link' or 'upload'
    public string $new_image_url = '';
    public ?TemporaryUploadedFile $new_image_file = null;
    public string $new_image_title = '';
    public string $new_image_caption = '';
    public string $new_image_tag = 'Atmosphere';
    public string $newCustomTagInput = '';

    public function mount(): void
    {
        $user = auth()->user();
        $this->organizationId = $user?->organization_id;

        $org = $this->organization();
        if ($org !== null) {
            $this->loadSettings($org);
        }
    }

    protected function organization(): ?Organization
    {
        if ($this->organizationId === null) {
            return null;
        }

        return Organization::query()->find($this->organizationId);
    }

    protected function loadSettings(Organization $org): void
    {
        $settings = $org->bespokeSettings();

        // Nav
        $nav = (array) ($settings['nav'] ?? []);
        $this->nav_about_enabled = (bool) ($nav['about']['enabled'] ?? true);
        $this->nav_about_label = (string) ($nav['about']['label'] ?? 'About');

        $this->nav_speakers_enabled = (bool) ($nav['speakers']['enabled'] ?? true);
        $this->nav_speakers_label = (string) ($nav['speakers']['label'] ?? 'Speakers');

        $this->nav_packages_enabled = (bool) ($nav['packages']['enabled'] ?? true);
        $this->nav_packages_label = (string) ($nav['packages']['label'] ?? 'Packages');

        $this->nav_highlights_enabled = (bool) ($nav['highlights']['enabled'] ?? true);
        $this->nav_highlights_label = (string) ($nav['highlights']['label'] ?? 'Highlights');

        $this->nav_gallery_enabled = (bool) ($nav['gallery']['enabled'] ?? true);
        $this->nav_gallery_label = (string) ($nav['gallery']['label'] ?? 'Gallery');

        $this->nav_partnership_enabled = (bool) ($nav['partnership']['enabled'] ?? false);
        $this->nav_partnership_label = (string) ($nav['partnership']['label'] ?? 'Partnership');

        $this->nav_faq_enabled = (bool) ($nav['faq']['enabled'] ?? true);
        $this->nav_faq_label = (string) ($nav['faq']['label'] ?? 'FAQs');

        $this->nav_contact_enabled = (bool) ($nav['contact']['enabled'] ?? true);
        $this->nav_contact_label = (string) ($nav['contact']['label'] ?? 'Contact');

        // About
        $about = (array) ($settings['about'] ?? []);
        $this->about_heading = (string) ($about['heading'] ?? 'About ' . $org->name);
        $this->about_lead = (string) ($about['lead'] ?? 'A Purposefully Curated Experience');
        $this->about_story = (string) ($about['story'] ?? '');

        // Highlights
        $highlights = (array) ($settings['highlights'] ?? []);
        $this->highlights_heading = (string) ($highlights['heading'] ?? 'Highlights');
        $this->highlights_lead = (string) ($highlights['lead'] ?? 'The program at a glance: the people, the sessions and the moments.');
        $stats = (array) ($highlights['stats'] ?? []);
        $this->stat1_value = (string) ($stats[0]['value'] ?? '');
        $this->stat1_label = (string) ($stats[0]['label'] ?? '');
        $this->stat2_value = (string) ($stats[1]['value'] ?? '');
        $this->stat2_label = (string) ($stats[1]['label'] ?? '');
        $this->stat3_value = (string) ($stats[2]['value'] ?? '');
        $this->stat3_label = (string) ($stats[2]['label'] ?? '');
        $this->stat4_value = (string) ($stats[3]['value'] ?? '');
        $this->stat4_label = (string) ($stats[3]['label'] ?? '');

        // Partnership
        $partnership = (array) ($settings['partnership'] ?? []);
        $this->partnership_enabled = (bool) ($partnership['enabled'] ?? false);
        $this->partnership_heading = (string) ($partnership['heading'] ?? 'Partner With Us');
        $this->partnership_lead = (string) ($partnership['lead'] ?? 'Put your brand in front of a purposeful, engaged audience.');
        $tiers = (array) ($partnership['tiers'] ?? []);
        $this->partner1_name = (string) ($tiers[0]['name'] ?? 'Community Partner');
        $this->partner1_body = (string) ($tiers[0]['body'] ?? 'Logo on the event site and complimentary passes.');
        $this->partner2_name = (string) ($tiers[1]['name'] ?? 'Session Sponsor');
        $this->partner2_body = (string) ($tiers[1]['body'] ?? 'Branded session, stage mention and partner table.');
        $this->partner3_name = (string) ($tiers[2]['name'] ?? 'Headline Partner');
        $this->partner3_body = (string) ($tiers[2]['body'] ?? 'Main-stage presence, keynote and VIP access.');

        // Contact
        $contact = (array) ($settings['contact'] ?? []);
        $this->contact_heading = (string) ($contact['heading'] ?? 'Get in Touch');
        $this->contact_lead = (string) ($contact['lead'] ?? 'Questions about passes, partnerships or the program?');
        $this->contact_email = (string) ($contact['email'] ?? '');
        $this->contact_phone = (string) ($contact['phone'] ?? '');
        $this->contact_address = (string) ($contact['address'] ?? '');

        // Gallery
        $gallery = (array) ($settings['gallery'] ?? []);
        $this->gallery_enabled = (bool) ($gallery['enabled'] ?? true);
        $this->gallery_heading = (string) ($gallery['heading'] ?? 'Moments & Gallery');
        $this->gallery_lead = (string) ($gallery['lead'] ?? 'Capturing the vibrant energy, keynote sessions, and electric connections.');
        $this->gallery_images = (array) ($gallery['images'] ?? []);
    }

    public function saveNavigation(): void
    {
        $this->validate([
            'nav_about_label' => ['required', 'string', 'max:50'],
            'nav_speakers_label' => ['required', 'string', 'max:50'],
            'nav_packages_label' => ['required', 'string', 'max:50'],
            'nav_highlights_label' => ['required', 'string', 'max:50'],
            'nav_gallery_label' => ['required', 'string', 'max:50'],
            'nav_partnership_label' => ['required', 'string', 'max:50'],
            'nav_faq_label' => ['required', 'string', 'max:50'],
            'nav_contact_label' => ['required', 'string', 'max:50'],
        ]);

        $this->persistBespokeKey('nav', [
            'about' => ['enabled' => $this->nav_about_enabled, 'label' => $this->nav_about_label],
            'speakers' => ['enabled' => $this->nav_speakers_enabled, 'label' => $this->nav_speakers_label],
            'packages' => ['enabled' => $this->nav_packages_enabled, 'label' => $this->nav_packages_label],
            'highlights' => ['enabled' => $this->nav_highlights_enabled, 'label' => $this->nav_highlights_label],
            'gallery' => ['enabled' => $this->nav_gallery_enabled, 'label' => $this->nav_gallery_label],
            'partnership' => ['enabled' => $this->nav_partnership_enabled, 'label' => $this->nav_partnership_label],
            'faq' => ['enabled' => $this->nav_faq_enabled, 'label' => $this->nav_faq_label],
            'contact' => ['enabled' => $this->nav_contact_enabled, 'label' => $this->nav_contact_label],
        ]);

        $this->flashMessage = 'Bespoke navigation settings saved successfully.';
    }

    public function saveAboutPage(): void
    {
        $this->validate([
            'about_heading' => ['required', 'string', 'max:150'],
            'about_lead' => ['nullable', 'string', 'max:255'],
            'about_story' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->persistBespokeKey('about', [
            'heading' => $this->about_heading,
            'lead' => $this->about_lead,
            'story' => $this->about_story,
        ]);

        $this->flashMessage = 'About sub-page content saved successfully.';
    }

    public function saveHighlightsPage(): void
    {
        $this->validate([
            'highlights_heading' => ['required', 'string', 'max:150'],
            'highlights_lead' => ['nullable', 'string', 'max:255'],
        ]);

        $stats = array_values(array_filter([
            filled($this->stat1_value) ? ['value' => $this->stat1_value, 'label' => $this->stat1_label] : null,
            filled($this->stat2_value) ? ['value' => $this->stat2_value, 'label' => $this->stat2_label] : null,
            filled($this->stat3_value) ? ['value' => $this->stat3_value, 'label' => $this->stat3_label] : null,
            filled($this->stat4_value) ? ['value' => $this->stat4_value, 'label' => $this->stat4_label] : null,
        ]));

        $this->persistBespokeKey('highlights', [
            'heading' => $this->highlights_heading,
            'lead' => $this->highlights_lead,
            'stats' => $stats,
        ]);

        $this->flashMessage = 'Highlights sub-page content saved successfully.';
    }

    public function savePartnershipPage(): void
    {
        $this->validate([
            'partnership_heading' => ['required', 'string', 'max:150'],
            'partnership_lead' => ['nullable', 'string', 'max:255'],
        ]);

        $tiers = array_values(array_filter([
            filled($this->partner1_name) ? ['name' => $this->partner1_name, 'body' => $this->partner1_body] : null,
            filled($this->partner2_name) ? ['name' => $this->partner2_name, 'body' => $this->partner2_body] : null,
            filled($this->partner3_name) ? ['name' => $this->partner3_name, 'body' => $this->partner3_body] : null,
        ]));

        $this->persistBespokeKey('partnership', [
            'enabled' => $this->partnership_enabled,
            'heading' => $this->partnership_heading,
            'lead' => $this->partnership_lead,
            'tiers' => $tiers,
        ]);

        $this->flashMessage = 'Partnership sub-page content saved successfully.';
    }

    public function saveContactPage(): void
    {
        $this->validate([
            'contact_heading' => ['required', 'string', 'max:150'],
            'contact_lead' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'contact_address' => ['nullable', 'string', 'max:255'],
        ]);

        $this->persistBespokeKey('contact', [
            'heading' => $this->contact_heading,
            'lead' => $this->contact_lead,
            'email' => $this->contact_email,
            'phone' => $this->contact_phone,
            'address' => $this->contact_address,
        ]);

        $this->flashMessage = 'Contact information and page saved successfully.';
    }

    public function addGalleryImage(): void
    {
        $imageUrl = '';

        if ($this->new_image_source_mode === 'upload') {
            $this->validate([
                'new_image_file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
                'new_image_title' => ['nullable', 'string', 'max:100'],
                'new_image_caption' => ['nullable', 'string', 'max:255'],
                'new_image_tag' => ['required', 'string', 'max:50'],
            ]);

            $imageUrl = MediaUrl::store($this->new_image_file, "gallery/{$this->organizationId}");
            $this->new_image_file = null;
        } else {
            $this->validate([
                'new_image_url' => ['required', 'url', 'max:2048'],
                'new_image_title' => ['nullable', 'string', 'max:100'],
                'new_image_caption' => ['nullable', 'string', 'max:255'],
                'new_image_tag' => ['required', 'string', 'max:50'],
            ]);

            $imageUrl = trim($this->new_image_url);
        }

        $this->gallery_images[] = [
            'id' => (string) str()->uuid(),
            'url' => $imageUrl,
            'title' => trim($this->new_image_title) !== '' ? trim($this->new_image_title) : 'Event Moment',
            'caption' => trim($this->new_image_caption),
            'tag' => trim($this->new_image_tag),
        ];

        $this->new_image_url = '';
        $this->new_image_title = '';
        $this->new_image_caption = '';
        $this->new_image_file = null;

        $this->flashMessage = 'Image added to gallery. Click "Save Gallery" to publish changes.';
    }

    public function removeGalleryImage(int $index): void
    {
        if (array_key_exists($index, $this->gallery_images)) {
            $removed = $this->gallery_images[$index];
            if (isset($removed['url']) && str_starts_with((string) $removed['url'], 'storage/')) {
                MediaUrl::deleteStored((string) $removed['url']);
            }

            $this->gallery_images = array_values(array_filter(
                $this->gallery_images,
                static fn(int $k): bool => $k !== $index,
                ARRAY_FILTER_USE_KEY,
            ));

            $this->flashMessage = 'Image removed from gallery.';
        }
    }

    public function saveGalleryPage(): void
    {
        $this->validate([
            'gallery_heading' => ['required', 'string', 'max:150'],
            'gallery_lead' => ['nullable', 'string', 'max:255'],
        ]);

        $this->persistBespokeKey('gallery', [
            'enabled' => $this->gallery_enabled,
            'heading' => $this->gallery_heading,
            'lead' => $this->gallery_lead,
            'images' => $this->gallery_images,
        ]);

        $this->flashMessage = 'Gallery sub-page and showcase saved successfully.';
    }

    protected function persistBespokeKey(string $key, array $data): void
    {
        $org = $this->organization();
        if ($org === null) {
            throw ValidationException::withMessages(['general' => 'Organization not found.']);
        }

        $settings = (array) ($org->settings ?? []);
        $bespoke = (array) ($settings['bespoke'] ?? []);
        $bespoke[$key] = $data;
        $settings['bespoke'] = $bespoke;

        $org->settings = $settings;
        $org->save();
    }

    public function render(): View
    {
        $org = $this->organization();
        $isDedicated = PlatformSetting::isDedicatedMode();
        $previewUrl = $org !== null
            ? ($isDedicated ? url('/') : url('/' . $org->slug))
            : url('/');

        return view('livewire.admin.bespoke-studio', [
            'organization' => $org,
            'isDedicated' => $isDedicated,
            'previewUrl' => $previewUrl,
        ]);
    }
}

