<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen antialiased bg-[var(--bg-canvas)] text-[var(--text-primary)] transition-colors duration-200">
        <div class="flex min-h-svh flex-col items-center justify-center p-6 md:p-10">
            <div class="flex w-full max-w-sm flex-col gap-6">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-2" wire:navigate>
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-[var(--bg-surface)] border border-[var(--border-hairline)] shadow-xs">
                        <x-app-logo-icon class="size-6 text-[var(--text-primary)]" />
                    </span>
                    <span class="text-xs font-semibold tracking-widest uppercase text-[var(--text-secondary)]">TicketLab Ops</span>
                </a>

                <div class="flex flex-col gap-6 rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-6 md:p-8 shadow-xs">
                    {{ $slot }}
                </div>
            </div>
        </div>
    </body>
</html>
