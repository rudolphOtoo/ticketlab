@php
    $inputClass = 'w-full rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-input)] px-3.5 py-2 text-xs font-medium text-[var(--text-primary)] shadow-[var(--shadow-inset)] focus:border-[var(--border-focused)] focus:outline-hidden focus:ring-2 focus:ring-[var(--border-focused)]/20 disabled:opacity-60';
    $cardClass = 'rounded-2xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] p-4 shadow-xs sm:p-5';
    $labelClass = 'text-[10px] font-semibold uppercase tracking-wider text-[var(--text-tertiary)]';
    $roleLabel = $roleLabels[$member->role] ?? ($member->isSuperAdmin() ? 'Platform admin' : ($member->role === 'org_admin' ? 'Admin' : 'Attendee'));
    $canManage = ! $isSelf && ! $member->isSuperAdmin() && $member->organization_id !== null;
@endphp

<div class="space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-center gap-3.5">
            <div class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-[var(--bg-subtle)] text-sm font-bold text-[var(--text-primary)]">{{ $member->initials() }}</div>
            <div class="min-w-0">
                <a href="{{ route('admin.users') }}" wire:navigate class="text-[11px] font-semibold text-[var(--text-tertiary)] hover:text-[var(--text-primary)]">&larr; Team &amp; Staff</a>
                <h1 class="truncate text-xl font-bold tracking-tight sm:text-2xl">{{ $member->name }}{{ $isSelf ? ' (you)' : '' }}</h1>
                <p class="truncate text-xs text-[var(--text-secondary)] sm:text-sm">{{ $member->email }}</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-[11px] font-semibold">
            <span class="rounded-full bg-[var(--bg-subtle)] px-2.5 py-1 text-[var(--text-primary)]">{{ $roleLabel }}</span>
            <span class="rounded-full bg-[var(--bg-subtle)] px-2.5 py-1 text-[var(--text-secondary)]">{{ $member->subdivision?->name ?? 'All zones' }}</span>
        </div>
    </div>

    @if ($flashMessage)
        <div class="flex items-center justify-between rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-3 text-xs font-medium text-emerald-800 dark:text-emerald-300">
            <span>{{ $flashMessage }}</span>
            <button type="button" wire:click="$set('flashMessage', null)" class="cursor-pointer" aria-label="Dismiss">&times;</button>
        </div>
    @endif

    @error('action')
        <div class="rounded-2xl border border-rose-500/20 bg-rose-500/10 px-4 py-3 text-xs font-medium text-rose-700 dark:text-rose-300">{{ $message }}</div>
    @enderror

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3 sm:gap-5">
        <div class="flex flex-col gap-4 lg:col-span-2 sm:gap-5">
            {{-- Identity --}}
            <section class="{{ $cardClass }}">
                <h2 class="text-sm font-semibold">Identity</h2>
                <dl class="mt-4 grid grid-cols-1 gap-4 text-xs sm:grid-cols-2">
                    <div>
                        <dt class="{{ $labelClass }}">Full name</dt>
                        <dd class="mt-1 font-medium">{{ $member->name }}</dd>
                    </div>
                    <div>
                        <dt class="{{ $labelClass }}">Email address</dt>
                        <dd class="mt-1 break-all font-medium">{{ $member->email }}</dd>
                    </div>
                    <div>
                        <dt class="{{ $labelClass }}">Email verification</dt>
                        <dd class="mt-1 font-medium">
                            @if ($member->email_verified_at)
                                <span class="text-[var(--status-success)]">Verified</span>
                                <span class="text-[var(--text-tertiary)]">· {{ $member->email_verified_at->format('M j, Y') }}</span>
                            @else
                                <span class="text-[var(--status-warning)]">Not verified</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="{{ $labelClass }}">Two-factor sign-in</dt>
                        <dd class="mt-1 font-medium">{{ $member->two_factor_confirmed_at ? 'Enabled' : 'Off' }}</dd>
                    </div>
                    <div>
                        <dt class="{{ $labelClass }}">Account created</dt>
                        <dd class="mt-1 font-medium">{{ $member->created_at?->format('M j, Y · g:i A') ?? 'Unknown' }}</dd>
                    </div>
                    <div>
                        <dt class="{{ $labelClass }}">Organization</dt>
                        <dd class="mt-1 font-medium">{{ $member->organization?->name ?? 'None' }}</dd>
                    </div>
                </dl>
            </section>

            {{-- Access --}}
            <section class="{{ $cardClass }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-semibold">Access &amp; permissions</h2>
                        <p class="mt-0.5 text-xs text-[var(--text-secondary)]">Role decides which screens they can open; the zone limits which records they see.</p>
                    </div>
                </div>

                @if ($canManage)
                    <form wire:submit="updateAccess" class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="space-y-1.5">
                            <label for="role" class="{{ $labelClass }}">Role</label>
                            <select id="role" wire:model="role" class="{{ $inputClass }}">
                                @foreach ($roleLabels as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('role') <p class="text-[11px] text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="subdivisionId" class="{{ $labelClass }}">Zone</label>
                            <select id="subdivisionId" wire:model="subdivisionId" class="{{ $inputClass }}" @disabled($actor->subdivision_id !== null)>
                                @if ($actor->subdivision_id === null)
                                    <option value="">All zones (organization-wide)</option>
                                @endif
                                @foreach ($zones as $zone)
                                    <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                                @endforeach
                            </select>
                            @error('subdivisionId') <p class="text-[11px] text-rose-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="sm:col-span-2">
                            <button type="submit" class="inline-flex cursor-pointer items-center gap-2 rounded-xl bg-[var(--color-primary)] px-4 py-2 text-xs font-semibold text-[var(--color-primary-fg)] shadow-xs transition hover:opacity-95 press-scale">
                                Save access
                            </button>
                        </div>
                    </form>
                @else
                    <dl class="mt-4 grid grid-cols-1 gap-4 text-xs sm:grid-cols-2">
                        <div>
                            <dt class="{{ $labelClass }}">Role</dt>
                            <dd class="mt-1 font-medium">{{ $roleLabel }}</dd>
                        </div>
                        <div>
                            <dt class="{{ $labelClass }}">Zone</dt>
                            <dd class="mt-1 font-medium">{{ $member->subdivision?->name ?? 'All zones' }}</dd>
                        </div>
                    </dl>
                    <p class="mt-4 rounded-xl bg-[var(--bg-subtle)] px-3.5 py-2.5 text-[11px] text-[var(--text-secondary)]">
                        {{ $isSelf ? 'You cannot change your own role or zone. Ask another admin to do it.' : 'This account cannot be managed from your team screen.' }}
                    </p>
                @endif
            </section>

            {{-- Check-in history --}}
            <section class="{{ $cardClass }}">
                <h2 class="text-sm font-semibold">Recent gate check-ins</h2>
                <p class="mt-0.5 text-xs text-[var(--text-secondary)]">Passes this person scanned in{{ $actor->subdivision ? ' for ' . $actor->subdivision->name : '' }}.</p>

                <div class="mt-3 divide-y divide-[var(--border-hairline)] text-xs">
                    @forelse ($recentCheckIns as $checkIn)
                        <div wire:key="check-in-{{ $checkIn->id }}" class="flex items-center justify-between gap-3 py-2.5">
                            <div class="min-w-0">
                                <p class="truncate font-medium">{{ $checkIn->name }}</p>
                                <p class="truncate text-[10px] text-[var(--text-tertiary)]">{{ $checkIn->subdivision?->name ?? 'No zone' }} · {{ $checkIn->ticketType?->name ?? 'Pass' }}</p>
                            </div>
                            <span class="shrink-0 text-[10px] text-[var(--text-tertiary)]">{{ $checkIn->checked_in_at?->diffForHumans() }}</span>
                        </div>
                    @empty
                        <p class="py-4 text-center text-[var(--text-tertiary)]">No check-ins recorded.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="flex flex-col gap-4 sm:gap-5">
            {{-- Activity --}}
            <section class="{{ $cardClass }}">
                <h2 class="text-sm font-semibold">Activity</h2>
                <dl class="mt-3 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl bg-[var(--bg-subtle)] px-2 py-3">
                        <dd class="font-mono text-lg font-bold">{{ number_format($checkInCount) }}</dd>
                        <dt class="mt-0.5 text-[10px] text-[var(--text-tertiary)]">Check-ins</dt>
                    </div>
                    <div class="rounded-xl bg-[var(--bg-subtle)] px-2 py-3">
                        <dd class="font-mono text-lg font-bold">{{ number_format($orderCount) }}</dd>
                        <dt class="mt-0.5 text-[10px] text-[var(--text-tertiary)]">Orders</dt>
                    </div>
                    <div class="rounded-xl bg-[var(--bg-subtle)] px-2 py-3">
                        <dd class="font-mono text-lg font-bold">{{ number_format($passCount) }}</dd>
                        <dt class="mt-0.5 text-[10px] text-[var(--text-tertiary)]">Passes</dt>
                    </div>
                </dl>
            </section>

            {{-- Sessions --}}
            <section class="{{ $cardClass }}">
                <h2 class="text-sm font-semibold">Sign-in sessions</h2>

                @if (! $sessionsTracked)
                    <p class="mt-3 text-xs text-[var(--text-tertiary)]">Last activity isn't tracked with the current session driver.</p>
                @else
                    <p class="mt-0.5 text-xs text-[var(--text-secondary)]">
                        Last active: {{ $sessions->isEmpty() ? 'No active sessions' : \Illuminate\Support\Carbon::createFromTimestamp($sessions->first()->last_activity)->diffForHumans() }}
                    </p>
                    <div class="mt-3 divide-y divide-[var(--border-hairline)] text-xs">
                        @foreach ($sessions as $index => $session)
                            <div wire:key="session-{{ $index }}" class="py-2.5">
                                <p class="truncate font-medium" title="{{ $session->user_agent }}">{{ \Illuminate\Support\Str::limit($session->user_agent ?? 'Unknown device', 48) }}</p>
                                <p class="text-[10px] text-[var(--text-tertiary)]">{{ $session->ip_address ?? 'Unknown IP' }} · {{ \Illuminate\Support\Carbon::createFromTimestamp($session->last_activity)->diffForHumans() }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            {{-- Account actions --}}
            @if ($canManage)
                <section class="{{ $cardClass }} space-y-2.5">
                    <h2 class="text-sm font-semibold">Account actions</h2>

                    <button type="button" wire:click="sendPasswordReset" class="w-full cursor-pointer rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-4 py-2 text-xs font-semibold transition hover:bg-[var(--bg-subtle)]">
                        Email a password reset link
                    </button>

                    <button type="button" wire:click="signOutEverywhere" wire:confirm="Sign {{ $member->name }} out of every device?" class="w-full cursor-pointer rounded-xl border border-[var(--border-hairline)] bg-[var(--bg-surface)] px-4 py-2 text-xs font-semibold transition hover:bg-[var(--bg-subtle)]">
                        Sign out of all devices
                    </button>

                    <button type="button" wire:click="removeMember" wire:confirm="Remove {{ $member->name }}? They will lose access immediately." class="w-full cursor-pointer rounded-xl px-4 py-2 text-xs font-semibold text-rose-600 transition hover:bg-rose-500/10">
                        Remove from team
                    </button>
                </section>
            @endif
        </div>
    </div>
</div>
