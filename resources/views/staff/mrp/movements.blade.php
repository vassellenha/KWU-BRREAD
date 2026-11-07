@extends('layouts.staff')

@section('title', 'Stock Movements')

@section('content')

    <div class="flex items-center gap-space-sm mb-space-lg">
        <a href="{{ route('staff.mrp.index') }}" aria-label="Back to stock" class="text-on-surface-variant hover:text-on-surface">
            <span class="material-symbols-outlined text-[22px]">arrow_back</span>
        </a>
        <div>
            <h1 class="font-headline-md text-headline-md text-on-surface">Stock Movement Log</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">Every restock, order consumption, and manual adjustment, newest first.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('staff.mrp.movements') }}" class="flex flex-wrap items-end gap-space-sm mb-space-md">
        <div>
            <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Ingredient</label>
            <select name="component_id" onchange="this.form.submit()" class="rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary min-w-[180px]">
                <option value="">All ingredients</option>
                @foreach ($components as $c)
                    <option value="{{ $c->id }}" {{ (string) ($filters['component_id'] ?? '') === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Type</label>
            <select name="type" onchange="this.form.submit()" class="rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface focus:outline-none focus:ring-2 focus:ring-primary min-w-[160px]">
                <option value="">All types</option>
                <option value="restock" {{ ($filters['type'] ?? '') === 'restock' ? 'selected' : '' }}>Restock</option>
                <option value="consumption" {{ ($filters['type'] ?? '') === 'consumption' ? 'selected' : '' }}>Consumption</option>
                <option value="adjustment" {{ ($filters['type'] ?? '') === 'adjustment' ? 'selected' : '' }}>Adjustment</option>
            </select>
        </div>
        @if (($filters['component_id'] ?? null) || ($filters['type'] ?? null))
            <a href="{{ route('staff.mrp.movements') }}" class="press-scale inline-flex items-center gap-1 px-space-md py-space-xs rounded-full bg-surface-container text-on-surface-variant font-label-md text-label-md hover:text-on-surface transition-colors duration-200">
                <span class="material-symbols-outlined text-[16px]">close</span>
                Clear filters
            </a>
        @endif
    </form>

    <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-x-auto">
        <table class="w-full min-w-[720px] text-left">
            <thead>
                <tr class="border-b border-outline-variant/30 bg-surface-container/50">
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Date</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Ingredient</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Type</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Change</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Balance After</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Reference</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($movements as $movement)
                    <tr class="border-b border-outline-variant/20 last:border-0">
                        <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant whitespace-nowrap">{{ $movement->created_at->format('d M Y, H:i') }}</td>
                        <td class="px-space-md py-space-sm font-label-lg text-label-lg text-on-surface">{{ $movement->component?->name ?? '—' }}</td>
                        <td class="px-space-md py-space-sm">
                            <span @class([
                                'inline-flex items-center px-2 py-0.5 rounded-full font-label-md text-label-md capitalize',
                                'bg-tertiary-fixed text-on-tertiary-fixed-variant' => $movement->type === 'restock',
                                'bg-secondary-fixed text-on-secondary-fixed-variant' => $movement->type === 'consumption',
                                'bg-surface-container text-on-surface-variant' => $movement->type === 'adjustment',
                            ])>{{ $movement->type }}</span>
                        </td>
                        <td class="px-space-md py-space-sm font-body-md text-body-md {{ $movement->quantity_change >= 0 ? 'text-tertiary' : 'text-error' }}">
                            {{ $movement->quantity_change >= 0 ? '+' : '' }}{{ $movement->quantity_change }}
                        </td>
                        <td class="px-space-md py-space-sm font-body-md text-body-md text-on-surface-variant">{{ $movement->balance_after }}</td>
                        <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant">
                            @if ($movement->order)
                                Order {{ $movement->order->order_number }}
                            @elseif ($movement->user)
                                {{ $movement->user->name }}{{ $movement->note ? ' — '.$movement->note : '' }}
                            @elseif ($movement->note)
                                {{ $movement->note }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-space-md py-space-xl text-center">
                            <div class="flex flex-col items-center gap-space-xs">
                                <span class="material-symbols-outlined text-[40px] text-on-surface-variant/40">history_toggle_off</span>
                                <p class="font-title-md text-title-md text-on-surface-variant">
                                    {{ ($filters['component_id'] ?? null) || ($filters['type'] ?? null) ? 'No movements match these filters' : 'No stock movements yet' }}
                                </p>
                                <p class="font-body-sm text-body-sm text-on-surface-variant/70 max-w-xs">
                                    {{ ($filters['component_id'] ?? null) || ($filters['type'] ?? null) ? 'Try clearing a filter to see more.' : 'Restocks, order consumption, and manual adjustments will show up here.' }}
                                </p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-space-md">
        {{ $movements->links() }}
    </div>

@endsection
