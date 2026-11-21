@props(['label' => 'value'])

{{-- A number field with small - and + buttons. The buttons stay out of the Tab order: the arrow keys and Shift+Up/Down do the same job. --}}
<span data-stepper-wrap class="batch-stepper">
    <button type="button" tabindex="-1" data-step-button="-1" aria-label="Decrease {{ $label }}" class="batch-stepper__btn">
        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">remove</span>
    </button>
    {{ $slot }}
    <button type="button" tabindex="-1" data-step-button="1" aria-label="Increase {{ $label }}" class="batch-stepper__btn">
        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">add</span>
    </button>
</span>
