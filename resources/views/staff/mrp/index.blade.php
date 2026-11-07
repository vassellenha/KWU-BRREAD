@extends('layouts.staff')

@section('title', 'Stock — MRP')

@section('content')

    <div class="flex items-center justify-between mb-space-lg flex-wrap gap-space-sm">
        <div>
            <h1 class="font-headline-md text-headline-md text-on-surface">Stock &amp; Materials</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">
                Ingredient stock for the Builder. Levels drop automatically as customer orders come in.
            </p>
        </div>
        <a href="{{ route('staff.mrp.movements') }}" class="press-scale inline-flex items-center gap-1.5 px-space-md py-space-xs rounded-full bg-surface-container-lowest border border-outline-variant/40 font-label-lg text-label-lg text-on-surface-variant hover:text-on-surface transition-colors duration-200">
            <span class="material-symbols-outlined text-[18px]">history</span>
            View movement log
        </a>
    </div>

    {{-- STAT SUMMARY --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-space-md mb-space-lg">
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">category</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Ingredients</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums">{{ $stats['total_ingredients'] }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border {{ $stats['low_stock'] > 0 ? 'border-error/40' : 'border-outline-variant/30' }} p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] {{ $stats['low_stock'] > 0 ? 'text-error' : 'text-on-surface-variant' }}">warning</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Low Stock</span>
            </div>
            <p class="font-headline-sm text-headline-sm tabular-nums {{ $stats['low_stock'] > 0 ? 'text-error' : 'text-on-surface' }}">{{ $stats['low_stock'] }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">inventory_2</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Units in Stock</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums">{{ number_format($stats['total_units'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">payments</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Stock Value</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums">Rp {{ number_format($stats['stock_value'], 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-x-auto">
        <table class="w-full min-w-[820px] text-left">
            <thead>
                <tr class="border-b border-outline-variant/30 bg-surface-container/50">
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Ingredient</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Category</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Stock</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Trend</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Reorder Point</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Status</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide text-right">Actions</th>
                </tr>
            </thead>
            <tbody x-data="{ openId: {{ old('_row') ? (int) old('_row') : 'null' }} }">
                @foreach ($components as $component)
                    @php
                        $series = $sparklines->get($component->id);
                        if ($series->count() < 2) {
                            $series = collect([$component->stock_quantity, $component->stock_quantity]);
                        }
                        $min = $series->min();
                        $max = $series->max();
                        $range = max(1, $max - $min);
                        $n = $series->count();
                        $sw = 72;
                        $sh = 24;
                        $points = $series->values()->map(function ($v, $i) use ($n, $sw, $sh, $min, $range) {
                            $x = $n > 1 ? round(($i / ($n - 1)) * $sw, 1) : $sw / 2;
                            $y = round($sh - (($v - $min) / $range) * $sh, 1);
                            return "{$x},{$y}";
                        })->implode(' ');
                        $low = $component->isLowStock();
                        $isRestoredRow = (int) old('_row') === $component->id;
                    @endphp
                    <tr
                        class="border-b border-outline-variant/20 last:border-0 {{ $low ? 'bg-error-container/10' : '' }}"
                    >
                        <td class="px-space-md py-space-sm">
                            <div class="flex items-center gap-space-sm">
                                @if ($component->hex_color)
                                    <span class="w-5 h-5 rounded-full border border-outline-variant/40 shrink-0" style="background-color: {{ $component->hex_color }}"></span>
                                @endif
                                <span class="font-label-lg text-label-lg text-on-surface">{{ $component->name }}</span>
                            </div>
                        </td>
                        <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant capitalize">{{ str_replace('_', ' ', $component->category) }}</td>
                        <td class="px-space-md py-space-sm font-body-md text-body-md text-on-surface tabular-nums">{{ $component->stock_quantity }} {{ $component->unit }}</td>
                        <td class="px-space-md py-space-sm">
                            <svg width="{{ $sw }}" height="{{ $sh }}" viewBox="0 0 {{ $sw }} {{ $sh }}" class="overflow-visible" aria-hidden="true">
                                <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="{{ $low ? 'text-error' : 'text-tertiary' }}" />
                            </svg>
                        </td>
                        <td class="px-space-md py-space-sm font-body-md text-body-md text-on-surface-variant tabular-nums">{{ $component->reorder_point }} {{ $component->unit }}</td>
                        <td class="px-space-md py-space-sm">
                            @if ($low)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-error-container text-on-error-container font-label-md text-label-md">
                                    <span class="material-symbols-outlined text-[14px]">warning</span>
                                    Low stock
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-tertiary-fixed text-on-tertiary-fixed-variant font-label-md text-label-md">
                                    <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                    OK
                                </span>
                            @endif
                        </td>
                        <td class="px-space-md py-space-sm text-right">
                            <button type="button" @click="openId = openId === {{ $component->id }} ? null : {{ $component->id }}" :aria-expanded="openId === {{ $component->id }}" class="press-scale inline-flex items-center gap-1 px-space-sm py-1 rounded bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-container-high transition-colors duration-200">
                                <span class="material-symbols-outlined text-[16px]">tune</span>
                                Adjust
                            </button>
                        </td>
                    </tr>
                    <tr x-show="openId === {{ $component->id }}" x-cloak>
                        <td colspan="7" class="px-space-md pb-space-md bg-surface-container/30">
                            <form
                                method="POST"
                                action="{{ route('staff.mrp.adjust', $component) }}"
                                x-data="{ direction: @js($isRestoredRow ? old('direction', 'in') : 'in') }"
                                class="flex flex-wrap items-end gap-space-sm pt-space-sm"
                            >
                                @csrf
                                <input type="hidden" name="_row" value="{{ $component->id }}">
                                <div>
                                    <label for="direction-{{ $component->id }}" class="block font-label-md text-label-md text-on-surface-variant mb-1">Direction</label>
                                    <select id="direction-{{ $component->id }}" name="direction" x-model="direction" class="rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary">
                                        <option value="in">Stock in (restock)</option>
                                        <option value="out">Stock out (waste / correction)</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="quantity-{{ $component->id }}" class="block font-label-md text-label-md text-on-surface-variant mb-1">Quantity</label>
                                    <input id="quantity-{{ $component->id }}" type="text" inputmode="numeric" data-filter="integer" name="quantity" maxlength="6" value="{{ $isRestoredRow ? old('quantity') : '' }}" required class="w-28 rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface tabular-nums focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                                <div x-show="direction === 'in'">
                                    <label for="cost-{{ $component->id }}" class="block font-label-md text-label-md text-on-surface-variant mb-1">Purchase cost, Rp (optional)</label>
                                    <input id="cost-{{ $component->id }}" type="text" inputmode="numeric" data-filter="integer" name="cost" maxlength="10" :disabled="direction !== 'in'" value="{{ $isRestoredRow ? old('cost') : '' }}" placeholder="Logged in Accounting" class="w-48 rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface tabular-nums placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                                <div class="flex-1 min-w-[180px]">
                                    <label for="note-{{ $component->id }}" class="block font-label-md text-label-md text-on-surface-variant mb-1">Note (optional)</label>
                                    <input id="note-{{ $component->id }}" type="text" name="note" maxlength="255" value="{{ $isRestoredRow ? old('note') : '' }}" placeholder="e.g. weekly restock" class="w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary">
                                </div>
                                <button type="submit" class="press-scale px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
                                    Save
                                </button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

@endsection
