@extends('layouts.staff')

@section('title', 'Menu & Prices')

@section('content')

    @php
        $fieldClasses = 'rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary';
        $isRestoredAddForm = old('_form') === 'add';
        $categoryIcons = ['bread' => 'bakery_dining', 'ice_cream' => 'icecream', 'topping' => 'cookie'];
        $categoryHints = [
            'bread' => 'The base every creation starts with.',
            'ice_cream' => 'Flavors for the middle layer. Each one has a color that tints it in the builder.',
            'topping' => 'Optional extras, any number per creation.',
        ];
    @endphp

    <x-staff.page-header icon="bakery_dining" title="Menu & Prices">
        Every bread, ice cream flavor and topping a customer can pick. Changes go live in the builder at once.
    </x-staff.page-header>

    {{-- STAT SUMMARY --}}
    <div class="grid grid-cols-3 gap-space-md mb-space-lg">
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">bakery_dining</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Items</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums">{{ $stats['total'] }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">check_circle</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">On the menu</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums">{{ $stats['active'] }}</p>
        </div>
        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 p-space-md">
            <div class="flex items-center gap-space-xs mb-1">
                <span class="material-symbols-outlined text-[18px] text-on-surface-variant">block</span>
                <span class="font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Hidden</span>
            </div>
            <p class="font-headline-sm text-headline-sm text-on-surface tabular-nums">{{ $stats['hidden'] }}</p>
        </div>
    </div>

    {{-- ADD AN ITEM --}}
    <div x-data="{ showAddForm: @js($isRestoredAddForm), category: @js($isRestoredAddForm ? old('category', 'bread') : 'bread') }" class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden mb-space-lg">
        <div class="flex items-center justify-between gap-space-sm p-space-md">
            <div class="min-w-0">
                <h2 class="font-title-md text-title-md text-on-surface">Add to the menu</h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant">A new bread, ice cream flavor or topping shows up in the builder as soon as you save it.</p>
            </div>
            <button type="button" @click="showAddForm = !showAddForm" :aria-expanded="showAddForm" class="press-scale shrink-0 inline-flex items-center gap-1.5 px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
                <span class="material-symbols-outlined text-[18px]" x-text="showAddForm ? 'close' : 'add'">add</span>
                <span x-text="showAddForm ? 'Cancel' : 'Add Item'">Add Item</span>
            </button>
        </div>

        <div x-show="showAddForm" x-cloak class="p-space-md border-t border-outline-variant/30 bg-surface-container/30">
            <form method="POST" action="{{ route('staff.menu.store') }}" class="flex flex-wrap items-end gap-space-sm">
                @csrf
                <input type="hidden" name="_form" value="add">
                <div>
                    <label for="new-category" class="block font-label-md text-label-md text-on-surface-variant mb-1">Type</label>
                    <select id="new-category" name="category" x-model="category" class="{{ $fieldClasses }}">
                        @foreach (\App\Http\Controllers\Staff\MenuController::CATEGORIES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="new-name" class="block font-label-md text-label-md text-on-surface-variant mb-1">Name</label>
                    <input id="new-name" type="text" name="name" maxlength="60" value="{{ $isRestoredAddForm ? old('name') : '' }}" required placeholder="e.g. Brioche Bun" class="w-56 {{ $fieldClasses }}">
                </div>
                <div>
                    <label for="new-price" class="block font-label-md text-label-md text-on-surface-variant mb-1">Price, Rp</label>
                    <input id="new-price" type="text" inputmode="numeric" data-filter="integer" name="price" maxlength="6" value="{{ $isRestoredAddForm ? old('price') : '' }}" required placeholder="8000" class="w-28 tabular-nums {{ $fieldClasses }}">
                </div>
                <div x-show="category === 'ice_cream'" x-cloak>
                    <label for="new-color" class="block font-label-md text-label-md text-on-surface-variant mb-1">Flavor color</label>
                    <input id="new-color" type="color" name="hex_color" :disabled="category !== 'ice_cream'" value="{{ $isRestoredAddForm && old('hex_color') ? old('hex_color') : '#F8B8C4' }}" class="h-9 w-16 cursor-pointer rounded-full border border-outline-variant bg-surface-container-lowest p-1">
                </div>
                <div>
                    <label for="new-stock" class="block font-label-md text-label-md text-on-surface-variant mb-1">Starting stock (optional)</label>
                    <input id="new-stock" type="text" inputmode="numeric" data-filter="integer" name="initial_stock" maxlength="6" value="{{ $isRestoredAddForm ? old('initial_stock') : '' }}" placeholder="0" class="w-28 tabular-nums {{ $fieldClasses }}">
                </div>
                <div>
                    <label for="new-reorder" class="block font-label-md text-label-md text-on-surface-variant mb-1">Reorder point (optional)</label>
                    <input id="new-reorder" type="text" inputmode="numeric" data-filter="integer" name="reorder_point" maxlength="6" value="{{ $isRestoredAddForm ? old('reorder_point') : '' }}" placeholder="0" class="w-28 tabular-nums {{ $fieldClasses }}">
                </div>
                <button type="submit" class="press-scale px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
                    Save
                </button>
            </form>
            <p class="font-body-sm text-body-sm text-on-surface-variant/70 mt-space-xs">
                Stock starts at 0 and the builder shows an item as Sold Out until you add some, here or in Stock (MRP).
            </p>
        </div>
    </div>

    {{-- ONE TABLE PER KIND --}}
    @foreach ($groups as $category => $group)
        <div x-data="{ openId: {{ old('_row') ? (int) old('_row') : 'null' }} }" class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 overflow-hidden mb-space-lg">
            <div class="flex items-center gap-space-sm p-space-md border-b border-outline-variant/30">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-container/40 text-primary">
                    <span class="material-symbols-outlined text-[22px]">{{ $categoryIcons[$category] }}</span>
                </span>
                <div class="min-w-0">
                    <h2 class="font-title-md text-title-md text-on-surface">{{ $group['label'] }} <span class="font-label-md text-label-md text-on-surface-variant tabular-nums">({{ $group['items']->count() }})</span></h2>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $categoryHints[$category] }}</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left">
                    <thead>
                        <tr class="border-b border-outline-variant/30 bg-surface-container/50">
                            <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Name</th>
                            <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Price</th>
                            <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Stock</th>
                            <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide">Status</th>
                            <th class="px-space-md py-space-sm font-label-md text-label-md text-on-surface-variant uppercase tracking-wide text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($group['items'] as $component)
                            @php
                                $isRestoredRow = (int) old('_row') === $component->id;
                                $isRestoredPrice = (int) old('_price_row') === $component->id;
                                $dim = $component->is_active ? '' : 'opacity-50';
                            @endphp
                            <tr class="border-b border-outline-variant/20 last:border-0">
                                <td class="px-space-md py-space-sm {{ $dim }}">
                                    <div class="flex items-center gap-space-sm">
                                        @if ($component->hex_color)
                                            <span class="w-5 h-5 rounded-full border border-outline-variant/40 shrink-0" style="background-color: {{ $component->hex_color }}"></span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="font-label-lg text-label-lg text-on-surface">{{ $component->name }}</p>
                                            @if (in_array($component->name, $signature, true))
                                                <p class="font-label-md text-label-md text-on-surface-variant">Used by a signature creation</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="px-space-md py-space-sm">
                                    <form method="POST" action="{{ route('staff.menu.price', $component) }}" class="inline-flex items-center gap-space-xs">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="_price_row" value="{{ $component->id }}">
                                        <label for="price-{{ $component->id }}" class="font-label-md text-label-md text-on-surface-variant">Rp</label>
                                        <input id="price-{{ $component->id }}" type="text" inputmode="numeric" data-filter="integer" name="price" maxlength="6" value="{{ $isRestoredPrice ? old('price') : (int) $component->price }}" required aria-label="Price of {{ $component->name }}, in rupiah" class="w-28 tabular-nums {{ $fieldClasses }}">
                                        <button type="submit" class="press-scale inline-flex items-center gap-1 px-space-sm py-1 rounded bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-container-high transition-colors duration-200">
                                            <span class="material-symbols-outlined text-[16px]">check</span>
                                            Save
                                        </button>
                                    </form>
                                </td>
                                <td class="px-space-md py-space-sm font-body-md text-body-md text-on-surface-variant tabular-nums {{ $dim }}">{{ number_format($component->stock_quantity, 0, ',', '.') }} {{ $component->unit }}</td>
                                <td class="px-space-md py-space-sm">
                                    @if ($component->is_active)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-tertiary-fixed text-on-tertiary-fixed-variant font-label-md text-label-md">
                                            <span class="material-symbols-outlined text-[14px]">check_circle</span>
                                            On the menu
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant font-label-md text-label-md">
                                            Hidden
                                        </span>
                                    @endif
                                </td>
                                <td class="px-space-md py-space-sm text-right">
                                    <div class="inline-flex items-center gap-space-xs">
                                        <button type="button" @click="openId = openId === {{ $component->id }} ? null : {{ $component->id }}" :aria-expanded="openId === {{ $component->id }}" class="press-scale inline-flex items-center gap-1 px-space-sm py-1 rounded bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-container-high transition-colors duration-200">
                                            <span class="material-symbols-outlined text-[16px]">edit</span>
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('staff.menu.toggle', $component) }}">
                                            @csrf
                                            <button type="submit" class="press-scale inline-flex items-center gap-1 px-space-sm py-1 rounded {{ $component->is_active ? 'text-error hover:bg-error-container/30' : 'bg-tertiary-fixed text-on-tertiary-fixed-variant hover:opacity-80' }} font-label-md text-label-md transition-colors duration-200">
                                                <span class="material-symbols-outlined text-[16px]">{{ $component->is_active ? 'block' : 'check_circle' }}</span>
                                                {{ $component->is_active ? 'Hide' : 'Show' }}
                                            </button>
                                        </form>
                                        @if ($component->order_items_count === 0)
                                            <form method="POST" action="{{ route('staff.menu.destroy', $component) }}" data-confirm="{{ $component->name }} and its stock history will be deleted for good." data-confirm-title="Delete {{ $component->name }}?" data-confirm-label="Delete" data-confirm-tone="danger">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" aria-label="Delete {{ $component->name }}" class="press-scale inline-flex items-center gap-1 px-space-sm py-1 rounded text-error hover:bg-error-container/30 font-label-md text-label-md transition-colors duration-200">
                                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            <tr x-show="openId === {{ $component->id }}" x-cloak>
                                <td colspan="5" class="px-space-md pb-space-md bg-surface-container/30">
                                    <form method="POST" action="{{ route('staff.menu.update', $component) }}" class="flex flex-wrap items-end gap-space-sm pt-space-sm">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="_row" value="{{ $component->id }}">
                                        <div>
                                            <label for="name-{{ $component->id }}" class="block font-label-md text-label-md text-on-surface-variant mb-1">Name</label>
                                            <input id="name-{{ $component->id }}" type="text" name="name" maxlength="60" value="{{ $isRestoredRow ? old('name') : $component->name }}" required class="w-56 {{ $fieldClasses }}">
                                        </div>
                                        <div>
                                            <label for="edit-price-{{ $component->id }}" class="block font-label-md text-label-md text-on-surface-variant mb-1">Price, Rp</label>
                                            <input id="edit-price-{{ $component->id }}" type="text" inputmode="numeric" data-filter="integer" name="price" maxlength="6" value="{{ $isRestoredRow ? old('price') : (int) $component->price }}" required class="w-28 tabular-nums {{ $fieldClasses }}">
                                        </div>
                                        @if ($category === 'ice_cream')
                                            <div>
                                                <label for="color-{{ $component->id }}" class="block font-label-md text-label-md text-on-surface-variant mb-1">Flavor color</label>
                                                <input id="color-{{ $component->id }}" type="color" name="hex_color" value="{{ $isRestoredRow && old('hex_color') ? old('hex_color') : ($component->hex_color ?: '#F8B8C4') }}" class="h-9 w-16 cursor-pointer rounded-full border border-outline-variant bg-surface-container-lowest p-1">
                                            </div>
                                        @endif
                                        <button type="submit" class="press-scale px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
                                            Save
                                        </button>
                                    </form>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant/70 mt-space-xs">
                                        @if (in_array($component->name, $signature, true))
                                            The signature creations find this item by its name. If you rename or hide it, those creations show as unavailable.
                                        @endif
                                        @if ($component->order_items_count > 0)
                                            Ordered in {{ $component->order_items_count }} {{ $component->order_items_count === 1 ? 'line' : 'lines' }} so far, so it can be hidden but not deleted.
                                        @endif
                                        Price changes only affect new orders.
                                    </p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-space-md py-space-xl text-center">
                                    <p class="font-title-md text-title-md text-on-surface">Nothing here yet</p>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Use Add Item above to put the first {{ strtolower($group['label']) }} on the menu.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach

@endsection
