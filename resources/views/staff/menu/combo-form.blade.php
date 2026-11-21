@php
    $isRestored = $restoredCombo === ($comboId ?? 'add');
    $chosenToppings = $selectedToppings($comboId, $combo);
@endphp
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-space-sm">
    @csrf
    @if ($method)
        @method($method)
    @endif
    <input type="hidden" name="_form" value="{{ $marker }}">
    @if ($comboId)
        <input type="hidden" name="_combo" value="{{ $comboId }}">
    @endif
    <div>
        <label for="{{ $formId }}-name" class="block font-label-md text-label-md text-on-surface-variant mb-1">Name</label>
        <input id="{{ $formId }}-name" type="text" name="name" maxlength="40" required value="{{ $isRestored ? old('name') : ($combo?->name ?? '') }}" placeholder="e.g. Sunday Special" class="w-56 {{ $fieldClasses }}">
    </div>
    <div>
        <label for="{{ $formId }}-bread" class="block font-label-md text-label-md text-on-surface-variant mb-1">Bread</label>
        <select id="{{ $formId }}-bread" name="bread_id" required class="{{ $fieldClasses }}">
            @foreach ($comboChoices['bread'] as $item)
                <option value="{{ $item->id }}" @selected((int) ($isRestored ? old('bread_id') : ($combo?->bread_id ?? $comboChoices['bread']->first()?->id)) === $item->id)>{{ $item->name }}</option>
            @endforeach
            @if ($combo && ! $comboChoices['bread']->contains('id', $combo->bread_id))
                <option value="{{ $combo->bread_id }}" selected>{{ $combo->bread->name }} (hidden)</option>
            @endif
        </select>
    </div>
    <div>
        <label for="{{ $formId }}-flavor" class="block font-label-md text-label-md text-on-surface-variant mb-1">Flavor</label>
        <select id="{{ $formId }}-flavor" name="flavor_id" required class="{{ $fieldClasses }}">
            @foreach ($comboChoices['ice_cream'] as $item)
                <option value="{{ $item->id }}" @selected((int) ($isRestored ? old('flavor_id') : ($combo?->flavor_id ?? $comboChoices['ice_cream']->first()?->id)) === $item->id)>{{ $item->name }}</option>
            @endforeach
            @if ($combo && ! $comboChoices['ice_cream']->contains('id', $combo->flavor_id))
                <option value="{{ $combo->flavor_id }}" selected>{{ $combo->flavor->name }} (hidden)</option>
            @endif
        </select>
    </div>
    <fieldset>
        <legend class="block font-label-md text-label-md text-on-surface-variant mb-1">Toppings (optional)</legend>
        <div class="flex flex-wrap gap-x-space-md gap-y-1 py-1">
            @foreach ($comboChoices['topping'] as $item)
                <label class="inline-flex items-center gap-1.5 font-body-sm text-body-sm text-on-surface">
                    <input type="checkbox" name="toppings[]" value="{{ $item->id }}" @checked(in_array($item->id, $chosenToppings, true)) class="rounded border-outline-variant text-primary focus:ring-primary">
                    {{ $item->name }}
                </label>
            @endforeach
            @foreach ($combo?->toppings ?? [] as $item)
                @unless ($comboChoices['topping']->contains('id', $item->id))
                    <label class="inline-flex items-center gap-1.5 font-body-sm text-body-sm text-on-surface-variant">
                        <input type="checkbox" name="toppings[]" value="{{ $item->id }}" @checked(in_array($item->id, $chosenToppings, true)) class="rounded border-outline-variant text-primary focus:ring-primary">
                        {{ $item->name }} (hidden)
                    </label>
                @endunless
            @endforeach
        </div>
    </fieldset>
    <div>
        <label for="{{ $formId }}-photo" class="block font-label-md text-label-md text-on-surface-variant mb-1">Card photo (optional)</label>
        <input id="{{ $formId }}-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="block w-56 font-body-sm text-body-sm text-on-surface-variant file:mr-2 file:rounded-full file:border-0 file:bg-surface-container file:px-3 file:py-1.5 file:font-label-md file:text-on-surface">
        @if ($combo?->photo)
            <label class="mt-1 inline-flex items-center gap-1.5 font-body-sm text-body-sm text-on-surface-variant">
                <input type="checkbox" name="remove_photo" value="1" class="rounded border-outline-variant text-primary focus:ring-primary">
                Remove the current photo
            </label>
        @endif
    </div>
    <button type="submit" class="press-scale px-space-md py-space-xs rounded-full bg-primary-container text-on-primary-container font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
        Save
    </button>
</form>
