<div class="flex items-start max-md:flex-col gap-6 md:gap-8">
    <div class="w-full md:w-52 shrink-0">
        <nav class="flex flex-col gap-1 text-xs font-medium">
            <a href="{{ route('profile.edit') }}"
               class="rounded-lg px-3 py-2 transition {{ request()->routeIs('profile.edit') ? 'bg-[var(--bg-subtle)] text-[var(--text-primary)] font-semibold' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' }}"
               wire:navigate>
                {{ __('Profile') }}
            </a>
            <a href="{{ route('security.edit') }}"
               class="rounded-lg px-3 py-2 transition {{ request()->routeIs('security.edit') ? 'bg-[var(--bg-subtle)] text-[var(--text-primary)] font-semibold' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' }}"
               wire:navigate>
                {{ __('Security') }}
            </a>
            <a href="{{ route('appearance.edit') }}"
               class="rounded-lg px-3 py-2 transition {{ request()->routeIs('appearance.edit') ? 'bg-[var(--bg-subtle)] text-[var(--text-primary)] font-semibold' : 'text-[var(--text-secondary)] hover:bg-[var(--bg-subtle)] hover:text-[var(--text-primary)]' }}"
               wire:navigate>
                {{ __('Appearance') }}
            </a>
        </nav>
    </div>

    <div class="flex-1 min-w-0 self-stretch">
        @if(!empty($heading))
            <h2 class="text-base font-bold text-[var(--text-primary)]">{{ $heading }}</h2>
        @endif
        @if(!empty($subheading))
            <p class="text-xs text-[var(--text-secondary)] mt-0.5">{{ $subheading }}</p>
        @endif

        <div class="mt-5 w-full max-w-lg">
            {{ $slot }}
        </div>
    </div>
</div>
