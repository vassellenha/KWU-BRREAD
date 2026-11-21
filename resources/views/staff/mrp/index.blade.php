@extends('layouts.staff')

@section('title', 'Stock — MRP')

@section('content')

    @php
        $field = 'rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary';
    @endphp

    <x-staff.page-header icon="inventory_2" title="Stock & Materials">
        Ingredient stock for the Builder. Levels drop automatically as customer orders come in.
        <x-slot:actions>
            <a href="{{ route('staff.mrp.movements') }}" class="press-scale inline-flex items-center gap-1.5 px-space-md py-space-xs rounded-full bg-surface-container-lowest border border-outline-variant/40 font-label-lg text-label-lg text-on-surface-variant hover:text-on-surface transition-colors duration-200">
                <span class="material-symbols-outlined text-[18px]">history</span>
                View movement log
            </a>
        </x-slot:actions>
    </x-staff.page-header>

    {{-- Everything below is one "save all" form: change as many rows as you like, then save them together. --}}
    <div x-data="batchForm({ url: @js(route('staff.mrp.bulk')), shared: '#shared-note' })">
        @include('partials.batch-hint')

        {{-- STAT SUMMARY --}}

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-space-md mb-space-lg">
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">category</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Ingredients</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums" data-stat="total_ingredients">{{ $stats['total_ingredients'] }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border {{ $stats['low_stock'] > 0 ? 'border-error/40' : 'border-outline-variant/30' }} p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] {{ $stats['low_stock'] > 0 ? 'text-error' : 'text-on-surface-variant' }}">warning</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Low Stock</span>
            </div>
            <p class="font-headline-sm text-headline-sm tabular-nums {{ $stats['low_stock'] > 0 ? 'text-error' : 'text-on-surface' }}" data-stat="low_stock">{{ $stats['low_stock'] }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">inventory_2</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Units in Stock</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums" data-stat="total_units">{{ number_format($stats['total_units'], 0, ',', '.') }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">payments</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Stock Value</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums" data-stat="stock_value">Rp {{ number_format($stats['stock_value'], 0, ',', '.') }}</p>
        </div>
    </div>

        <div class="mb-space-md flex flex-wrap items-end gap-space-sm">
            <div class="min-w-[220px] flex-1">
                <label for="shared-note" class="block font-label-md text-label-md text-on-surface-variant mb-1">Note for every row (optional)</label>
                <input id="shared-note" type="text" maxlength="255" data-batch-shared="shared_note" placeholder="e.g. weekly restock" class="w-full {{ $field }} placeholder:text-on-surface-variant/60">
            </div>
            <p class="max-w-md font-body-sm text-body-sm text-on-surface-variant">Fill in a quantity on each ingredient to change. A note on a row replaces the shared note for that row.</p>
        </div>

        <p id="grid-help" class="sr-only">Use the arrow keys to move between cells. Up and Down change row, Left and Right change cell. Shift plus Up or Down changes a number. Press Control S to save all changes.</p>

        <div x-grid-nav class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 relative overflow-x-auto">
            <table class="w-full min-w-[1100px] text-left" aria-describedby="grid-help">
                <thead>
                    <tr class="border-b border-outline-variant/30 bg-surface-container/50">
                        <th scope="col" class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Ingredient</th>
                        <th scope="col" class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Stock</th>
                        <th scope="col" class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Trend</th>
                        <th scope="col" class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Status</th>
                        <th scope="col" class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Direction</th>
                        <th scope="col" class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Quantity</th>
                        <th scope="col" class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Cost, Rp</th>
                        <th scope="col" class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Note</th>
                        <th scope="col" class="px-space-md py-space-sm"><span class="sr-only">Revert</span></th>
                    </tr>
                </thead>
                <tbody>
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
                            $itemName = $component->name; // inside a component slot, $component is the stepper itself
                        @endphp
                        <tr
                            x-data="{ direction: 'in' }"
                            data-grid-row
                            data-batch-row="{{ $component->id }}"
                            data-batch-trigger="quantity"
                            data-version="{{ $component->stockVersion() }}"
                            data-flag-classes="{{ json_encode(['low' => 'bg-error-container/10']) }}"
                            class="border-b border-outline-variant/20 last:border-0 {{ $low ? 'bg-error-container/10' : '' }}"
                        >
                            <td class="px-space-md py-space-sm" data-batch-title>
                                <div class="flex items-center gap-space-sm">
                                    @if ($component->hex_color)
                                        <span class="w-5 h-5 rounded-full border border-outline-variant/40 shrink-0" style="background-color: {{ $component->hex_color }}"></span>
                                    @endif
                                    <div class="min-w-0">
                                        <span class="font-label-lg text-label-lg text-on-surface">{{ $component->name }}</span>
                                        <span class="block font-body-sm text-body-sm text-on-surface-variant capitalize">{{ str_replace('_', ' ', $component->category) }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="px-space-md py-space-sm">
                                <span class="font-body-md text-body-md text-on-surface tabular-nums" data-batch-cell="stock">{{ $component->stock_quantity }} {{ $component->unit }}</span>
                                <span class="block font-body-sm text-body-sm text-on-surface-variant tabular-nums">reorder at {{ $component->reorder_point }}</span>
                            </td>
                            <td class="px-space-md py-space-sm">
                                <svg width="{{ $sw }}" height="{{ $sh }}" viewBox="0 0 {{ $sw }} {{ $sh }}" class="overflow-visible" aria-hidden="true">
                                    <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="{{ $low ? 'text-error' : 'text-tertiary' }}" />
                                </svg>
                            </td>
                            <td class="px-space-md py-space-sm">
                                <span data-show="low" @if (! $low) hidden @endif class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-error-container text-on-error-container font-label-md text-label-md">
                                    <span class="material-symbols-outlined text-[14px]">warning</span>
                                    Low stock
                                </span>
                                <span data-hide="low" @if ($low) hidden @endif class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-tertiary-fixed text-on-tertiary-fixed-variant font-label-md text-label-md">
                                    <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                    OK
                                </span>
                            </td>
                            <td class="px-space-md py-space-sm align-top">
                                <select data-batch-field="direction" data-cell="direction" x-model="direction" aria-label="Direction for {{ $component->name }}" class="{{ $field }}">
                                    <option value="in">Stock in (restock)</option>
                                    <option value="out">Stock out (waste / correction)</option>
                                </select>
                            </td>
                            <td class="px-space-md py-space-sm align-top">
                                <x-staff.stepper :label="'quantity of '.$itemName">
                                    <input type="text" inputmode="numeric" data-filter="integer" maxlength="6" data-batch-field="quantity" data-cell="quantity" data-stepper="1" data-min="0" data-max="100000" aria-label="Quantity for {{ $itemName }}" placeholder="0" class="w-24 tabular-nums {{ $field }} placeholder:text-on-surface-variant/60">
                                </x-staff.stepper>
                            </td>
                            <td class="px-space-md py-space-sm align-top">
                                <input x-show="direction === 'in'" type="text" inputmode="numeric" data-filter="integer" maxlength="10" data-batch-field="cost" data-cell="cost" :disabled="direction !== 'in'" aria-label="Purchase cost for {{ $component->name }}, in rupiah (optional)" placeholder="Logged in Accounting" class="w-44 tabular-nums {{ $field }} placeholder:text-on-surface-variant/60">
                            </td>
                            <td class="px-space-md py-space-sm align-top">
                                <input type="text" maxlength="255" data-batch-field="note" data-cell="note" aria-label="Note for {{ $component->name }}" placeholder="uses the shared note" class="w-52 {{ $field }} placeholder:text-on-surface-variant/60">
                            </td>
                            <td class="px-space-md py-space-sm align-top text-right">
                                <button type="button" data-batch-revert @click="revertRow($el.closest('[data-batch-row]'))" aria-label="Revert {{ $component->name }}" class="press-scale items-center gap-1 px-space-sm py-1 rounded bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-container-high transition-colors duration-200">
                                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">undo</span>
                                    Revert
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @include('partials.batch-bar')
    </div>

@endsection
