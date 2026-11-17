@props(['icon', 'title', 'back' => null, 'backLabel' => 'Back'])

{{-- One header for every staff page: icon tile, title, subtitle, optional back link and action slot. --}}
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-space-sm mb-space-lg">
    <div class="flex items-center gap-space-sm min-w-0">
        @if ($back)
            <a href="{{ $back }}" aria-label="{{ $backLabel }}" class="press-scale flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-surface-container-lowest border border-outline-variant/40 text-on-surface-variant hover:text-primary hover:border-primary/40 transition-colors">
                <span class="material-symbols-outlined text-[22px]">arrow_back</span>
            </a>
        @endif
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary-container/40 text-primary">
            <span class="material-symbols-outlined text-[28px]">{{ $icon }}</span>
        </span>
        <div class="min-w-0">
            <h1 class="font-headline-md text-headline-md text-on-surface font-bold leading-tight">{{ $title }}</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-0.5">{{ $slot }}</p>
        </div>
    </div>
    @isset($actions)
        <div class="shrink-0">{{ $actions }}</div>
    @endisset
</div>
