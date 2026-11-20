{{-- SIGNATURE COMBOS: the one-click creations of the builder --}}
@php
    $comboErrorBag = $errors->has('combo') || $errors->has('name') || $errors->has('bread_id') || $errors->has('flavor_id') || $errors->has('toppings') || $errors->has('toppings.*');
    $restoredCombo = old('_form') === 'combo-add' ? 'add' : (old('_form') === 'combo-edit' ? (int) old('_combo') : null);
    $selectedToppings = fn (?int $comboId, $combo = null) => collect(
        $restoredCombo === ($comboId ?? 'add') ? old('toppings', []) : ($combo?->toppings->pluck('id')->all() ?? [])
    )->map(fn ($id) => (int) $id)->all();
@endphp

<div x-data="{ showAddCombo: @js($restoredCombo === 'add'), openCombo: @js(is_int($restoredCombo) ? $restoredCombo : null) }" class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden mb-space-lg">
    <div class="flex items-center justify-between gap-space-sm p-space-md border-b border-outline-variant/30">
        <div class="flex items-center gap-space-sm min-w-0">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-container/40 text-primary">
                <span class="material-symbols-outlined text-[22px]">stars</span>
            </span>
            <div class="min-w-0">
                <h2 class="font-title-md text-title-md text-on-surface">Signature combos <span class="font-label-md text-label-md text-on-surface-variant tabular-nums">({{ $combos->count() }})</span></h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant">The one-click creations in the builder. A combo is a bread, a flavor and any toppings from the menu below, in the order shown here.</p>
            </div>
        </div>
        <button type="button" @click="showAddCombo = !showAddCombo" :aria-expanded="showAddCombo" class="press-scale shrink-0 inline-flex items-center gap-1.5 px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
            <span class="material-symbols-outlined text-[18px]" x-text="showAddCombo ? 'close' : 'add'">add</span>
            <span x-text="showAddCombo ? 'Cancel' : 'Add Combo'">Add Combo</span>
        </button>
    </div>

    @if ($comboErrorBag)
        <div class="px-space-md pt-space-sm" role="alert">
            @foreach (array_unique(array_merge($errors->get('combo'), $errors->get('name'), $errors->get('bread_id'), $errors->get('flavor_id'), $errors->get('toppings'), $errors->get('toppings.*'))) as $message)
                <p class="font-body-sm text-body-sm text-error">{{ $message }}</p>
            @endforeach
        </div>
    @endif

    <div x-show="showAddCombo" x-cloak class="p-space-md border-b border-outline-variant/30 bg-surface-container/30">
        @include('staff.menu.combo-form', ['formId' => 'combo-new', 'action' => route('staff.menu.combos.store'), 'method' => null, 'combo' => null, 'marker' => 'combo-add', 'comboId' => null])
    </div>

    <div class="overflow-x-auto">
        <table class="w-full min-w-[760px] text-left">
            <thead>
                <tr class="border-b border-outline-variant/30 bg-surface-container/50">
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Combo</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Made of</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Price</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Status</th>
                    <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($combos as $combo)
                    @php
                        $unavailable = ! $combo->bread->is_active || ! $combo->flavor->is_active || $combo->toppings->contains(fn ($topping) => ! $topping->is_active);
                    @endphp
                    <tr class="border-b border-outline-variant/20 last:border-0">
                        <td class="px-space-md py-space-sm {{ $combo->is_active ? '' : 'opacity-50' }}">
                            <div class="flex items-center gap-space-sm">
                                <span class="w-5 h-5 rounded-full border border-outline-variant/40 shrink-0" style="background-color: {{ $combo->flavor->hex_color ?: '#e8e2de' }}"></span>
                                <p class="font-label-lg text-label-lg text-on-surface">{{ $combo->name }}</p>
                            </div>
                        </td>
                        <td class="px-space-md py-space-sm font-body-sm text-body-sm text-on-surface-variant {{ $combo->is_active ? '' : 'opacity-50' }}">
                            {{ $combo->bread->name }}, {{ $combo->flavor->name }}@if ($combo->toppings->isNotEmpty()), {{ $combo->toppings->pluck('name')->join(', ') }}@endif
                        </td>
                        <td class="px-space-md py-space-sm font-body-md text-body-md text-on-surface-variant tabular-nums {{ $combo->is_active ? '' : 'opacity-50' }}">Rp {{ number_format($combo->price(), 0, ',', '.') }}</td>
                        <td class="px-space-md py-space-sm">
                            @if (! $combo->is_active)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant font-label-md text-label-md">Hidden</span>
                            @elseif ($unavailable)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-error-container/40 text-on-error-container font-label-md text-label-md">
                                    <span class="material-symbols-outlined text-[14px]">warning</span>
                                    An item is hidden
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-tertiary-fixed text-on-tertiary-fixed-variant font-label-md text-label-md">
                                    <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                    In the builder
                                </span>
                            @endif
                        </td>
                        <td class="px-space-md py-space-sm text-right">
                            <div class="inline-flex items-center gap-space-xs">
                                @foreach (['up' => ['arrow_upward', 'Move up'], 'down' => ['arrow_downward', 'Move down']] as $direction => [$icon, $label])
                                    <form method="POST" action="{{ route('staff.menu.combos.move', $combo) }}">
                                        @csrf
                                        <input type="hidden" name="direction" value="{{ $direction }}">
                                        <button type="submit" aria-label="{{ $label }}: {{ $combo->name }}" @disabled(($direction === 'up' && $loop->first) || ($direction === 'down' && $loop->last)) class="press-scale inline-flex items-center px-space-xs py-1 rounded text-on-surface-variant hover:bg-surface-container disabled:opacity-30 disabled:pointer-events-none transition-colors duration-200">
                                            <span class="material-symbols-outlined text-[16px]">{{ $icon }}</span>
                                        </button>
                                    </form>
                                @endforeach
                                <button type="button" @click="openCombo = openCombo === {{ $combo->id }} ? null : {{ $combo->id }}" :aria-expanded="openCombo === {{ $combo->id }}" class="press-scale inline-flex items-center gap-1 px-space-sm py-1 rounded bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-container-high transition-colors duration-200">
                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                    Edit
                                </button>
                                <form method="POST" action="{{ route('staff.menu.combos.toggle', $combo) }}">
                                    @csrf
                                    <button type="submit" class="press-scale inline-flex items-center gap-1 px-space-sm py-1 rounded {{ $combo->is_active ? 'text-error hover:bg-error-container/30' : 'bg-tertiary-fixed text-on-tertiary-fixed-variant hover:opacity-80' }} font-label-md text-label-md transition-colors duration-200">
                                        <span class="material-symbols-outlined text-[16px]">{{ $combo->is_active ? 'block' : 'check_circle' }}</span>
                                        {{ $combo->is_active ? 'Hide' : 'Show' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('staff.menu.combos.destroy', $combo) }}" data-confirm="{{ $combo->name }} will be removed from the builder. The menu items stay." data-confirm-title="Delete {{ $combo->name }}?" data-confirm-label="Delete" data-confirm-tone="danger">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" aria-label="Delete {{ $combo->name }}" class="press-scale inline-flex items-center gap-1 px-space-sm py-1 rounded text-error hover:bg-error-container/30 font-label-md text-label-md transition-colors duration-200">
                                        <span class="material-symbols-outlined text-[16px]">delete</span>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <tr x-show="openCombo === {{ $combo->id }}" x-cloak>
                        <td colspan="5" class="px-space-md pb-space-md bg-surface-container/30">
                            <div class="pt-space-sm">
                                @include('staff.menu.combo-form', ['formId' => 'combo-'.$combo->id, 'action' => route('staff.menu.combos.update', $combo), 'method' => 'PUT', 'combo' => $combo, 'marker' => 'combo-edit', 'comboId' => $combo->id])
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-space-md py-space-xl text-center">
                            <p class="font-title-md text-title-md text-on-surface">No signature combos</p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Use Add Combo above. Until then the builder shows no one-click creations.</p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
