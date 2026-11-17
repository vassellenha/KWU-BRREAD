@extends('layouts.staff')

@section('title', 'Security')

@section('content')

    @php
        $flag = function (?string $code): string {
            if (! $code || strlen($code) !== 2 || ! ctype_alpha($code)) {
                return '';
            }

            $code = strtoupper($code);

            return mb_chr(0x1F1E6 + ord($code[0]) - 65).mb_chr(0x1F1E6 + ord($code[1]) - 65);
        };

        $blockedIps = $blocks->pluck('id', 'ip');

        $tiles = [
            ['label' => 'Lockdowns (7 days)', 'icon' => 'shield', 'value' => number_format($stats['events'])],
            ['label' => 'Attempts (7 days)', 'icon' => 'bolt', 'value' => number_format($stats['attempts'])],
            ['label' => 'Addresses (7 days)', 'icon' => 'lan', 'value' => number_format($stats['addresses'])],
            ['label' => 'Top country (30 days)', 'icon' => 'public', 'value' => $stats['top_country'] ? trim($flag($stats['top_country']->country_code).' '.$stats['top_country']->country) : '-'],
        ];
    @endphp

    <x-staff.page-header icon="shield" title="Security">
        Every address that triggered the sign-in lockdown: where it came from, what it ran, and what it tried.
    </x-staff.page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-space-sm mb-space-lg">
        @foreach ($tiles as $tile)
            <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md min-w-0">
                <div class="flex items-center gap-space-xs mb-1">
                    <span class="material-symbols-outlined text-[18px] text-on-surface-variant">{{ $tile['icon'] }}</span>
                    <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">{{ $tile['label'] }}</span>
                </div>
                <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums truncate" title="{{ $tile['value'] }}">{{ $tile['value'] }}</p>
            </div>
        @endforeach
    </div>

    <section class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md mb-space-lg" aria-labelledby="blocked-heading">
        <div class="flex flex-wrap items-start justify-between gap-space-sm mb-space-sm">
            <div class="min-w-0">
                <h2 id="blocked-heading" class="font-title-md text-title-md text-on-surface flex items-center gap-space-xs">
                    <span class="material-symbols-outlined text-[20px] text-error">block</span>
                    Blocked addresses
                    <span class="font-label-md text-label-md text-on-surface-variant tabular-nums">({{ $blocks->count() }})</span>
                </h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant">A blocked address gets a "not welcome" page on every page of the site until you unblock it.</p>
            </div>
            <form method="POST" action="{{ route('staff.security.block') }}" class="flex flex-wrap items-center gap-space-xs">
                @csrf
                <label for="block-ip" class="sr-only">Address to block</label>
                <input id="block-ip" name="ip" required inputmode="decimal" autocomplete="off" spellcheck="false" placeholder="e.g. 203.0.113.7" value="{{ old('ip') }}" class="w-44 rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-mono text-[13px] text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                <label for="block-note" class="sr-only">Note</label>
                <input id="block-note" name="note" maxlength="120" autocomplete="off" placeholder="Note (optional)" value="{{ old('note') }}" class="w-44 rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                <button type="submit" class="press-scale inline-flex items-center gap-1 rounded-full bg-error px-space-md py-space-xs font-label-md text-label-md text-on-error">
                    <span class="material-symbols-outlined text-[18px]">block</span>
                    Block
                </button>
            </form>
        </div>

        @if ($blocks->isEmpty())
            <p class="font-body-sm text-body-sm text-on-surface-variant/80">No address is blocked.</p>
        @else
            <ul class="divide-y divide-outline-variant/20">
                @foreach ($blocks as $block)
                    <li class="flex flex-wrap items-center justify-between gap-space-sm py-space-xs">
                        <div class="min-w-0">
                            <code class="font-mono text-[13px] text-on-surface">{{ $block->ip }}</code>
                            @if ($block->note)
                                <span class="font-body-sm text-body-sm text-on-surface-variant"> &middot; {{ $block->note }}</span>
                            @endif
                            <span class="block font-body-sm text-body-sm text-on-surface-variant/80">
                                Since {{ $block->created_at->format('d M Y, H:i') }}{{ $block->blocker ? ' by '.$block->blocker->name : '' }}
                                &middot; turned away {{ number_format($block->hits) }} {{ str('time')->plural($block->hits) }}{{ $block->last_seen_at ? ', last '.$block->last_seen_at->diffForHumans() : '' }}
                            </span>
                        </div>
                        <form method="POST" action="{{ route('staff.security.unblock', $block) }}" data-confirm="{{ $block->ip }} will be able to open the site again." data-confirm-title="Unblock this address?" data-confirm-label="Unblock" data-cancel-label="Keep blocked">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="press-scale inline-flex items-center gap-1 rounded-full bg-surface-container px-space-md py-space-xs font-label-md text-label-md text-on-surface">
                                <span class="material-symbols-outlined text-[18px]">lock_open</span>
                                Unblock
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <div class="flex flex-wrap items-center justify-between gap-space-sm mb-space-md">
        <nav aria-label="Filter by reason" class="flex flex-wrap gap-space-xs">
            @foreach (['' => 'All'] + \App\Models\SecurityEvent::REASONS as $key => $label)
                <a
                    href="{{ route('staff.security.index', array_filter(['reason' => $key])) }}"
                    @class([
                        'press-scale inline-flex items-center px-space-md py-space-xs rounded-full font-label-md text-label-md transition-colors duration-200',
                        'bg-primary text-on-primary' => ($reason ?? '') === $key,
                        'bg-surface-container text-on-surface-variant hover:text-on-surface' => ($reason ?? '') !== $key,
                    ])
                >{{ $label }}</a>
            @endforeach
        </nav>
        <p class="font-body-sm text-body-sm text-on-surface-variant max-w-xl">
            Location is approximate (city level) and shows the VPN or proxy exit when one is used.
            Events are kept for {{ $keepDays }} days.
        </p>
    </div>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-x-auto">
        <table class="w-full min-w-[1120px] text-left">
            <thead>
                <tr class="border-b border-outline-variant/30 bg-surface-container/50">
                    @foreach (['Time', 'Address', 'Location', 'Browser / system', 'Device', 'Reason', 'Tried', 'Hits', 'Actions'] as $heading)
                        <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide whitespace-nowrap">{{ $heading }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($events as $event)
                    <tr class="border-b border-outline-variant/20 last:border-0 align-top">
                        <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant whitespace-nowrap">
                            {{ $event->created_at->format('d M Y, H:i') }}
                            <span class="block text-on-surface-variant/70">{{ $event->created_at->diffForHumans() }}</span>
                        </td>
                        <td class="px-space-md py-space-sm font-mono text-[13px] text-on-surface whitespace-nowrap">
                            {{ $event->ip }}
                            @if ($blockedIps->has($event->ip))
                                <span class="mt-1 flex w-fit items-center gap-1 rounded-full bg-error-container px-2 py-0.5 font-label-md text-label-md text-on-error-container">
                                    <span class="material-symbols-outlined text-[14px]">block</span>Blocked
                                </span>
                            @endif
                        </td>
                        <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface min-w-[180px]">
                            @if ($event->place())
                                <span>{{ trim($flag($event->country_code).' '.$event->place()) }}</span>
                            @else
                                <span class="text-on-surface-variant">Unknown</span>
                            @endif
                            @if ($event->isp)
                                <span class="block text-on-surface-variant">{{ $event->isp }}</span>
                            @endif
                        </td>
                        <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface min-w-[170px]">
                            {{ $event->browser ?? 'Unknown' }}
                            <span class="block text-on-surface-variant">{{ $event->os ?? 'Unknown OS' }}</span>
                        </td>
                        <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant">{{ $event->device ?? 'Unknown' }}</td>
                        <td class="px-space-md py-space-sm">
                            <span @class([
                                'inline-flex items-center px-2 py-0.5 rounded-full font-label-md text-label-md whitespace-nowrap',
                                'bg-error-container text-on-error-container' => $event->reason === 'payload',
                                'bg-secondary-fixed text-on-secondary-fixed-variant' => $event->reason !== 'payload',
                            ])>{{ \App\Models\SecurityEvent::REASONS[$event->reason] ?? $event->reason }}</span>
                        </td>
                        <td class="px-space-md py-space-sm max-w-[220px]">
                            @if ($event->username !== null && $event->username !== '')
                                <code class="block max-w-[200px] truncate font-mono text-[12px] text-on-surface" title="{{ $event->username }}">{{ $event->username }}</code>
                            @else
                                <span class="font-body-sm text-body-sm text-on-surface-variant">-</span>
                            @endif
                            <span class="block font-body-sm text-body-sm text-on-surface-variant/70">/{{ $event->path }}</span>
                        </td>
                        <td class="px-space-md py-space-sm font-body-md text-body-md text-on-surface tabular-nums">{{ number_format($event->hits) }}</td>
                        <td class="px-space-md py-space-sm">
                            <div class="flex items-center gap-space-xs">
                                @if ($blockedIps->has($event->ip))
                                    <form method="POST" action="{{ route('staff.security.unblock', $blockedIps[$event->ip]) }}" data-confirm="{{ $event->ip }} will be able to open the site again." data-confirm-title="Unblock this address?" data-confirm-label="Unblock" data-cancel-label="Keep blocked">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="press-scale inline-flex items-center gap-1 rounded-full bg-surface-container px-3 py-1 font-label-md text-label-md text-on-surface whitespace-nowrap">
                                            <span class="material-symbols-outlined text-[16px]">lock_open</span>Unblock
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('staff.security.block') }}" data-confirm="{{ $event->ip }} will get a blocked page on every page of the site until you unblock it." data-confirm-title="Block this address?" data-confirm-label="Block" data-cancel-label="Cancel" data-confirm-tone="danger">
                                        @csrf
                                        <input type="hidden" name="ip" value="{{ $event->ip }}">
                                        <button type="submit" class="press-scale inline-flex items-center gap-1 rounded-full bg-error px-3 py-1 font-label-md text-label-md text-on-error whitespace-nowrap">
                                            <span class="material-symbols-outlined text-[16px]">block</span>Block
                                        </button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('staff.security.forget') }}" data-confirm="Every record for {{ $event->ip }} will be deleted from this list. A block on it stays." data-confirm-title="Delete these records?" data-confirm-label="Delete" data-cancel-label="Cancel" data-confirm-tone="danger">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="ip" value="{{ $event->ip }}">
                                    <button type="submit" aria-label="Delete records for {{ $event->ip }}" title="Delete records for {{ $event->ip }}" class="press-scale flex h-8 w-8 items-center justify-center rounded-full text-on-surface-variant hover:bg-error-container hover:text-on-error-container">
                                        <span class="material-symbols-outlined text-[18px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-space-md py-space-xl text-center">
                            <div class="flex flex-col items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[40px] text-on-surface-variant/40">verified_user</span>
                                <p class="font-title-md text-title-md text-on-surface-variant">No lockdowns recorded</p>
                                <p class="font-body-sm text-body-sm text-on-surface-variant/70 max-w-xs">
                                    Addresses that hammer the sign-in forms or send attack payloads will show up here.
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-space-md">
        {{ $events->links() }}
    </div>

@endsection
