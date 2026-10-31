<div class="space-y-6">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-[var(--border-hairline)] pb-5">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[var(--text-primary)]">Bespoke Portal CMS</h1>
            <p class="mt-1 text-sm text-[var(--text-secondary)]">
                Manage navigation tabs, brand narrative, highlights, and custom sub-pages for your attendee-facing bespoke portal.
            </p>
        </div>

        @if ($previewUrl)
            <a
                href="{{ $previewUrl }}"
                target="_blank"
                rel="noreferrer noopener"
                class="inline-flex items-center gap-2 rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-subtle)] px-4 py-2 text-xs font-semibold text-[var(--text-primary)] transition hover:border-[var(--border-focused)] hover:bg-[var(--bg-surface)] press-scale"
            >
                <span>View Live Portal</span>
                <svg class="size-3.5 text-[var(--text-tertiary)]" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                </svg>
            </a>
        @endif
    </header>

    @if ($flashMessage)
        <div class="flex items-center justify-between rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-400">
            <span>{{ $flashMessage }}</span>
            <button type="button" wire:click="$set('flashMessage', null)" class="text-emerald-400/80 hover:text-emerald-400">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    {{-- Tabs Bar --}}
    <div class="flex flex-wrap items-center gap-1 border-b border-[var(--border-hairline)] pb-2 text-xs font-semibold">
        <button
            type="button"
            wire:click="$set('activeTab', 'nav')"
            @class([
                'rounded-lg px-3.5 py-2 transition cursor-pointer press-scale',
                'bg-[var(--bg-subtle)] text-[var(--text-primary)] shadow-xs' => $activeTab === 'nav',
                'text-[var(--text-secondary)] hover:text-[var(--text-primary)]' => $activeTab !== 'nav',
            ])
        >Navigation Flow</button>

        <button
            type="button"
            wire:click="$set('activeTab', 'about')"
            @class([
                'rounded-lg px-3.5 py-2 transition cursor-pointer press-scale',
                'bg-[var(--bg-subtle)] text-[var(--text-primary)] shadow-xs' => $activeTab === 'about',
                'text-[var(--text-secondary)] hover:text-[var(--text-primary)]' => $activeTab !== 'about',
            ])
        >About Page</button>

        <button
            type="button"
            wire:click="$set('activeTab', 'highlights')"
            @class([
                'rounded-lg px-3.5 py-2 transition cursor-pointer press-scale',
                'bg-[var(--bg-subtle)] text-[var(--text-primary)] shadow-xs' => $activeTab === 'highlights',
                'text-[var(--text-secondary)] hover:text-[var(--text-primary)]' => $activeTab !== 'highlights',
            ])
        >Highlights Page</button>

        <button
            type="button"
            wire:click="$set('activeTab', 'gallery')"
            @class([
                'rounded-lg px-3.5 py-2 transition cursor-pointer press-scale',
                'bg-[var(--bg-subtle)] text-[var(--text-primary)] shadow-xs' => $activeTab === 'gallery',
                'text-[var(--text-secondary)] hover:text-[var(--text-primary)]' => $activeTab !== 'gallery',
            ])
        >Moments & Gallery</button>

        <button
            type="button"
            wire:click="$set('activeTab', 'partnership')"
            @class([
                'rounded-lg px-3.5 py-2 transition cursor-pointer press-scale',
                'bg-[var(--bg-subtle)] text-[var(--text-primary)] shadow-xs' => $activeTab === 'partnership',
                'text-[var(--text-secondary)] hover:text-[var(--text-primary)]' => $activeTab !== 'partnership',
            ])
        >Partnership</button>

        <button
            type="button"
            wire:click="$set('activeTab', 'contact')"
            @class([
                'rounded-lg px-3.5 py-2 transition cursor-pointer press-scale',
                'bg-[var(--bg-subtle)] text-[var(--text-primary)] shadow-xs' => $activeTab === 'contact',
                'text-[var(--text-secondary)] hover:text-[var(--text-primary)]' => $activeTab !== 'contact',
            ])
        >Contact & Details</button>
    </div>

    {{-- Tab 1: Navigation Flow --}}
    @if ($activeTab === 'nav')
        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 space-y-6">
            <div>
                <h2 class="text-base font-bold text-[var(--text-primary)]">Portal Navigation Bar</h2>
                <p class="text-xs text-[var(--text-secondary)]">Enable or rename the tabs displayed in your portal's floating navigation bar.</p>
            </div>

            <form wire:submit="saveNavigation" class="space-y-4">
                <div class="divide-y divide-[var(--border-hairline)]">
                    {{-- About --}}
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" wire:model="nav_about_enabled" id="nav_about_enabled" class="size-4 rounded border-[var(--border-hairline)] text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            <label for="nav_about_enabled" class="text-xs font-semibold text-[var(--text-primary)]">About Sub-Page</label>
                        </div>
                        <input type="text" wire:model="nav_about_label" class="w-48 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" placeholder="About">
                    </div>

                    {{-- Speakers --}}
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" wire:model="nav_speakers_enabled" id="nav_speakers_enabled" class="size-4 rounded border-[var(--border-hairline)] text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            <label for="nav_speakers_enabled" class="text-xs font-semibold text-[var(--text-primary)]">Speakers Sub-Page</label>
                        </div>
                        <input type="text" wire:model="nav_speakers_label" class="w-48 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" placeholder="Speakers">
                    </div>

                    {{-- Packages --}}
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" wire:model="nav_packages_enabled" id="nav_packages_enabled" class="size-4 rounded border-[var(--border-hairline)] text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            <label for="nav_packages_enabled" class="text-xs font-semibold text-[var(--text-primary)]">Packages Sub-Page</label>
                        </div>
                        <input type="text" wire:model="nav_packages_label" class="w-48 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" placeholder="Packages">
                    </div>

                    {{-- Highlights --}}
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" wire:model="nav_highlights_enabled" id="nav_highlights_enabled" class="size-4 rounded border-[var(--border-hairline)] text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            <label for="nav_highlights_enabled" class="text-xs font-semibold text-[var(--text-primary)]">Highlights Sub-Page</label>
                        </div>
                        <input type="text" wire:model="nav_highlights_label" class="w-48 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" placeholder="Highlights">
                    </div>

                    {{-- Gallery --}}
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" wire:model="nav_gallery_enabled" id="nav_gallery_enabled" class="size-4 rounded border-[var(--border-hairline)] text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            <label for="nav_gallery_enabled" class="text-xs font-semibold text-[var(--text-primary)]">Gallery Sub-Page</label>
                        </div>
                        <input type="text" wire:model="nav_gallery_label" class="w-48 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" placeholder="Gallery">
                    </div>

                    {{-- Partnership --}}
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" wire:model="nav_partnership_enabled" id="nav_partnership_enabled" class="size-4 rounded border-[var(--border-hairline)] text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            <label for="nav_partnership_enabled" class="text-xs font-semibold text-[var(--text-primary)]">Partnership Sub-Page</label>
                        </div>
                        <input type="text" wire:model="nav_partnership_label" class="w-48 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" placeholder="Partnership">
                    </div>

                    {{-- FAQs --}}
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" wire:model="nav_faq_enabled" id="nav_faq_enabled" class="size-4 rounded border-[var(--border-hairline)] text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            <label for="nav_faq_enabled" class="text-xs font-semibold text-[var(--text-primary)]">FAQs Sub-Page</label>
                        </div>
                        <input type="text" wire:model="nav_faq_label" class="w-48 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" placeholder="FAQs">
                    </div>

                    {{-- Contact --}}
                    <div class="flex items-center justify-between py-3">
                        <div class="flex items-center gap-3">
                            <input type="checkbox" wire:model="nav_contact_enabled" id="nav_contact_enabled" class="size-4 rounded border-[var(--border-hairline)] text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                            <label for="nav_contact_enabled" class="text-xs font-semibold text-[var(--text-primary)]">Contact Sub-Page</label>
                        </div>
                        <input type="text" wire:model="nav_contact_label" class="w-48 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3 py-1.5 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" placeholder="Contact">
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[var(--color-primary)] px-5 py-2.5 text-xs font-bold text-[var(--color-primary-fg)] transition hover:opacity-90 press-scale">
                        <span wire:loading.remove wire:target="saveNavigation">Save Navigation</span>
                        <span wire:loading wire:target="saveNavigation">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Tab 2: About Page --}}
    @if ($activeTab === 'about')
        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 space-y-6">
            <div>
                <h2 class="text-base font-bold text-[var(--text-primary)]">About Sub-Page</h2>
                <p class="text-xs text-[var(--text-secondary)]">Customize the story and background narrative on your bespoke /page/about.</p>
            </div>

            <form wire:submit="saveAboutPage" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-[var(--text-primary)]">Page Heading</label>
                    <input type="text" wire:model="about_heading" class="mt-1.5 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" placeholder="About Kingdom Summit">
                    @error('about_heading') <span class="text-[11px] text-rose-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[var(--text-primary)]">Subheading / Lead</label>
                    <input type="text" wire:model="about_lead" class="mt-1.5 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" placeholder="A Purposefully Curated Experience">
                    @error('about_lead') <span class="text-[11px] text-rose-500">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[var(--text-primary)]">Story / Mission Narrative</label>
                    <textarea rows="5" wire:model="about_story" class="mt-1.5 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" placeholder="Write the history, mission, or vision for this gathering..."></textarea>
                    @error('about_story') <span class="text-[11px] text-rose-500">{{ $message }}</span> @enderror
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[var(--color-primary)] px-5 py-2.5 text-xs font-bold text-[var(--color-primary-fg)] transition hover:opacity-90 press-scale">
                        <span wire:loading.remove wire:target="saveAboutPage">Save About Page</span>
                        <span wire:loading wire:target="saveAboutPage">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Tab 3: Highlights Page --}}
    @if ($activeTab === 'highlights')
        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 space-y-6">
            <div>
                <h2 class="text-base font-bold text-[var(--text-primary)]">Highlights Sub-Page</h2>
                <p class="text-xs text-[var(--text-secondary)]">Customize the stats and summary displayed on /page/highlights (leave stat empty to use live counts).</p>
            </div>

            <form wire:submit="saveHighlightsPage" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Page Heading</label>
                        <input type="text" wire:model="highlights_heading" class="mt-1.5 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Subheading / Lead</label>
                        <input type="text" wire:model="highlights_lead" class="mt-1.5 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden">
                    </div>
                </div>

                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)] pt-2">Custom Highlight Metric Cards</h3>
                    <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] p-3 space-y-2">
                            <label class="block text-[11px] text-[var(--text-secondary)]">Card 1 Value & Label</label>
                            <input type="text" wire:model="stat1_value" placeholder="1,200+" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2.5 py-1.5 text-xs text-[var(--text-primary)]">
                            <input type="text" wire:model="stat1_label" placeholder="Attendees" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2.5 py-1.5 text-xs text-[var(--text-primary)]">
                        </div>

                        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] p-3 space-y-2">
                            <label class="block text-[11px] text-[var(--text-secondary)]">Card 2 Value & Label</label>
                            <input type="text" wire:model="stat2_value" placeholder="24" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2.5 py-1.5 text-xs text-[var(--text-primary)]">
                            <input type="text" wire:model="stat2_label" placeholder="Speakers" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2.5 py-1.5 text-xs text-[var(--text-primary)]">
                        </div>

                        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] p-3 space-y-2">
                            <label class="block text-[11px] text-[var(--text-secondary)]">Card 3 Value & Label</label>
                            <input type="text" wire:model="stat3_value" placeholder="3" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2.5 py-1.5 text-xs text-[var(--text-primary)]">
                            <input type="text" wire:model="stat3_label" placeholder="Days" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2.5 py-1.5 text-xs text-[var(--text-primary)]">
                        </div>

                        <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] p-3 space-y-2">
                            <label class="block text-[11px] text-[var(--text-secondary)]">Card 4 Value & Label</label>
                            <input type="text" wire:model="stat4_value" placeholder="100%" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2.5 py-1.5 text-xs text-[var(--text-primary)]">
                            <input type="text" wire:model="stat4_label" placeholder="Impact" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2.5 py-1.5 text-xs text-[var(--text-primary)]">
                        </div>
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[var(--color-primary)] px-5 py-2.5 text-xs font-bold text-[var(--color-primary-fg)] transition hover:opacity-90 press-scale">
                        <span wire:loading.remove wire:target="saveHighlightsPage">Save Highlights</span>
                        <span wire:loading wire:target="saveHighlightsPage">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Tab 4: Partnership Page --}}
    @if ($activeTab === 'partnership')
        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 space-y-6">
            <div>
                <h2 class="text-base font-bold text-[var(--text-primary)]">Partnership Sub-Page</h2>
                <p class="text-xs text-[var(--text-secondary)]">Enable sponsorship packages and customize tiers for corporate or community partners.</p>
            </div>

            <form wire:submit="savePartnershipPage" class="space-y-4">
                <div class="flex items-center gap-3 pb-2">
                    <input type="checkbox" wire:model="partnership_enabled" id="partnership_enabled" class="size-4 rounded border-[var(--border-hairline)] text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                    <label for="partnership_enabled" class="text-xs font-semibold text-[var(--text-primary)]">Enable Partnership Sub-Page on Portal</label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Page Heading</label>
                        <input type="text" wire:model="partnership_heading" class="mt-1.5 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Subheading / Lead</label>
                        <input type="text" wire:model="partnership_lead" class="mt-1.5 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden">
                    </div>
                </div>

                <div class="space-y-3 pt-2">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-[var(--text-tertiary)]">Sponsorship Tiers</h3>

                    <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] p-4 space-y-2">
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Tier 1</label>
                        <input type="text" wire:model="partner1_name" placeholder="Community Partner" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-1.5 text-xs text-[var(--text-primary)]">
                        <textarea rows="2" wire:model="partner1_body" placeholder="Tier description and perks..." class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-1.5 text-xs text-[var(--text-primary)]"></textarea>
                    </div>

                    <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] p-4 space-y-2">
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Tier 2</label>
                        <input type="text" wire:model="partner2_name" placeholder="Session Sponsor" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-1.5 text-xs text-[var(--text-primary)]">
                        <textarea rows="2" wire:model="partner2_body" placeholder="Tier description and perks..." class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-1.5 text-xs text-[var(--text-primary)]"></textarea>
                    </div>

                    <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] p-4 space-y-2">
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Tier 3</label>
                        <input type="text" wire:model="partner3_name" placeholder="Headline Partner" class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-1.5 text-xs text-[var(--text-primary)]">
                        <textarea rows="2" wire:model="partner3_body" placeholder="Tier description and perks..." class="w-full rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3 py-1.5 text-xs text-[var(--text-primary)]"></textarea>
                    </div>
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[var(--color-primary)] px-5 py-2.5 text-xs font-bold text-[var(--color-primary-fg)] transition hover:opacity-90 press-scale">
                        <span wire:loading.remove wire:target="savePartnershipPage">Save Partnership</span>
                        <span wire:loading wire:target="savePartnershipPage">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Tab: Gallery Sub-Page & Media Showcase --}}
    @if ($activeTab === 'gallery')
        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-[var(--border-hairline)]">
                <div>
                    <h2 class="text-base font-bold text-[var(--text-primary)]">Moments & Image Gallery</h2>
                    <p class="text-xs text-[var(--text-secondary)]">Manage the visual showcase for your bespoke portal. Support for direct links (Unsplash, CDN, cloud storage) or direct file uploads.</p>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="gallery_enabled" wire:model="gallery_enabled" class="size-4 rounded border-[var(--border-hairline)] text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                    <label for="gallery_enabled" class="text-xs font-semibold text-[var(--text-primary)] cursor-pointer">Enable Gallery Page & Reel</label>
                </div>
            </div>

            <form wire:submit="saveGalleryPage" class="space-y-6">
                <!-- Page Headings -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Section Heading</label>
                        <input type="text" wire:model="gallery_heading" class="mt-1.5 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" placeholder="Moments & Highlights">
                        @error('gallery_heading') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Subtitle / Narrative Lead</label>
                        <input type="text" wire:model="gallery_lead" class="mt-1.5 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden" placeholder="Capturing memories, energy, and milestones...">
                        @error('gallery_lead') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Add New Image Box (Add Perks Style Interactive Studio) -->
                <div class="rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] p-4 sm:p-5 space-y-4">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="p-1 rounded-md bg-[var(--color-primary)]/10 text-[var(--color-primary)]">
                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                            </span>
                            <span class="text-xs font-bold text-[var(--text-primary)]">Add Image to Gallery</span>
                        </div>

                        <!-- Source Switcher: Link URL vs Upload -->
                        <div class="inline-flex rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-0.5 text-[11px] font-semibold">
                            <button
                                type="button"
                                wire:click="$set('new_image_source_mode', 'link')"
                                class="px-2.5 py-1 rounded-md transition cursor-pointer {{ $new_image_source_mode === 'link' ? 'bg-[var(--color-primary)] text-[var(--color-primary-fg)] shadow-2xs' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]' }}"
                            >
                                Direct Link / URL
                            </button>
                            <button
                                type="button"
                                wire:click="$set('new_image_source_mode', 'upload')"
                                class="px-2.5 py-1 rounded-md transition cursor-pointer {{ $new_image_source_mode === 'upload' ? 'bg-[var(--color-primary)] text-[var(--color-primary-fg)] shadow-2xs' : 'text-[var(--text-secondary)] hover:text-[var(--text-primary)]' }}"
                            >
                                Upload File
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <!-- Source Input -->
                        @if ($new_image_source_mode === 'link')
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Image URL (Unsplash, Cloud CDN, or Direct Link) *</label>
                                <input
                                    type="url"
                                    wire:model="new_image_url"
                                    placeholder="https://images.unsplash.com/photo-... or your storage URL"
                                    class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden"
                                />
                                @error('new_image_url') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                        @else
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Select Image File (Max 4MB: WebP, PNG, JPG) *</label>
                                <input
                                    type="file"
                                    wire:model="new_image_file"
                                    accept="image/*"
                                    class="w-full text-xs text-[var(--text-secondary)] file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-[var(--color-primary)] file:text-[var(--color-primary-fg)] cursor-pointer"
                                />
                                <div wire:loading wire:target="new_image_file" class="text-[11px] text-[var(--color-primary)] mt-1">Uploading image...</div>
                                @error('new_image_file') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                            </div>
                        @endif

                        <div>
                            <label class="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Title / Caption Label</label>
                            <input
                                type="text"
                                wire:model="new_image_title"
                                placeholder="e.g. Opening Keynote Speech"
                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden"
                            />
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold text-[var(--text-secondary)] mb-1">Short Description / Subtitle</label>
                            <input
                                type="text"
                                wire:model="new_image_caption"
                                placeholder="e.g. Perez Dome Main Auditorium"
                                class="w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden"
                            />
                        </div>
                    </div>

                    <!-- Category Tag Chip Selection (Tag Chip System) -->
                    <div class="space-y-1.5 pt-1">
                        <label class="block text-[11px] font-semibold text-[var(--text-secondary)]">Category Tag</label>
                        <div class="flex flex-wrap items-center gap-1.5">
                            @foreach (['Keynote', 'Atmosphere', 'Audience', 'VIP', 'Stage', 'Networking', 'Panels'] as $presetTag)
                                <button
                                    type="button"
                                    wire:click="$set('new_image_tag', '{{ $presetTag }}')"
                                    class="px-2.5 py-1 rounded-lg text-[11px] font-semibold transition cursor-pointer active:scale-[0.96] {{ $new_image_tag === $presetTag ? 'bg-[var(--color-primary)] text-[var(--color-primary-fg)] shadow-2xs' : 'border border-[var(--border-hairline)] bg-[var(--bg-surface)] text-[var(--text-secondary)] hover:text-[var(--text-primary)]' }}"
                                >
                                    {{ $presetTag }}
                                </button>
                            @endforeach

                            <input
                                type="text"
                                wire:model="new_image_tag"
                                placeholder="Or type custom tag..."
                                class="w-40 rounded-lg border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-2.5 py-1 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="button"
                            wire:click="addGalleryImage"
                            class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-[var(--color-primary)] text-[var(--color-primary-fg)] text-xs font-bold transition hover:opacity-95 active:scale-[0.97] cursor-pointer"
                        >
                            <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span>Add Photo to Reel</span>
                        </button>
                    </div>
                </div>

                <!-- Configured Images Grid Preview -->
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-[var(--text-secondary)]">
                            Curated Gallery Items ({{ count($gallery_images) }})
                        </h3>
                        <span class="text-[10px] text-[var(--text-tertiary)]">Images display in responsive masonry layout</span>
                    </div>

                    @if (empty($gallery_images))
                        <div class="rounded-2xl border border-dashed border-[var(--border-hairline)] p-8 text-center text-xs text-[var(--text-secondary)]">
                            No images added to the gallery yet. Paste a link or upload photos above.
                        </div>
                    @else
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3.5">
                            @foreach ($gallery_images as $index => $img)
                                <div class="group relative rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] overflow-hidden shadow-2xs transition-all duration-200 hover:translate-y-[-2px] hover:shadow-md">
                                    <div class="aspect-4/3 w-full overflow-hidden bg-black/10">
                                        <img
                                            src="{{ $img['url'] }}"
                                            alt="{{ $img['title'] }}"
                                            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                            loading="lazy"
                                        />
                                    </div>

                                    <div class="p-2.5 space-y-1">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-[var(--bg-subtle)] text-[var(--text-secondary)] truncate">
                                                {{ $img['tag'] }}
                                            </span>
                                            <button
                                                type="button"
                                                wire:click="removeGalleryImage({{ $index }})"
                                                class="text-[var(--text-tertiary)] hover:text-rose-500 transition cursor-pointer p-0.5"
                                                title="Remove Photo"
                                            >
                                                <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </div>
                                        <h4 class="text-xs font-bold text-[var(--text-primary)] truncate">{{ $img['title'] }}</h4>
                                        @if (!empty($img['caption']))
                                            <p class="text-[10px] text-[var(--text-secondary)] line-clamp-1">{{ $img['caption'] }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="pt-4 flex justify-end border-t border-[var(--border-hairline)]">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[var(--color-primary)] px-5 py-2.5 text-xs font-bold text-[var(--color-primary-fg)] transition hover:opacity-90 active:scale-[0.97] cursor-pointer">
                        <span wire:loading.remove wire:target="saveGalleryPage">Save Gallery & Publish</span>
                        <span wire:loading wire:target="saveGalleryPage">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- Tab 5: Contact Page & Socials --}}
    @if ($activeTab === 'contact')
        <div class="rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 space-y-6">
            <div>
                <h2 class="text-base font-bold text-[var(--text-primary)]">Contact & Support Details</h2>
                <p class="text-xs text-[var(--text-secondary)]">Public contact details displayed on your bespoke /page/contact and footer callouts.</p>
            </div>

            <form wire:submit="saveContactPage" class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Contact Page Heading</label>
                        <input type="text" wire:model="contact_heading" class="mt-1.5 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Contact Page Lead</label>
                        <input type="text" wire:model="contact_lead" class="mt-1.5 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Public Support Email</label>
                        <input type="email" wire:model="contact_email" placeholder="contact@example.com" class="mt-1.5 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-[var(--text-primary)]">Support Phone / WhatsApp</label>
                        <input type="text" wire:model="contact_phone" placeholder="+233 24 000 0000" class="mt-1.5 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-[var(--text-primary)]">Venue / Physical Address</label>
                    <input type="text" wire:model="contact_address" placeholder="Perez Dome, Dzorwulu, Accra, Ghana" class="mt-1.5 w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-canvas)] px-3.5 py-2 text-xs text-[var(--text-primary)] focus:border-[var(--color-primary)] focus:outline-hidden">
                </div>

                <div class="pt-4 flex justify-end">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-[var(--color-primary)] px-5 py-2.5 text-xs font-bold text-[var(--color-primary-fg)] transition hover:opacity-90 press-scale">
                        <span wire:loading.remove wire:target="saveContactPage">Save Contact Details</span>
                        <span wire:loading wire:target="saveContactPage">Saving...</span>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>

