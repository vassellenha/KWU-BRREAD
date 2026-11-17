{{-- In-page confirmation used instead of the browser's confirm() box. Driven by Alpine.store('dialog'); forms opt in with data-confirm. --}}
<div
    x-data
    x-show="$store.dialog.open"
    x-cloak
    x-effect="if ($store.dialog.open) $nextTick(() => $refs.cancel && $refs.cancel.focus())"
    @keydown.escape.window="$store.dialog.answer(false)"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="confirm-dialog-title"
    aria-describedby="confirm-dialog-message"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4"
>
    <div
        x-show="$store.dialog.open"
        x-transition.opacity.duration.150ms
        @click="$store.dialog.answer(false)"
        class="absolute inset-0 bg-on-surface/50"
    ></div>
    <div
        x-show="$store.dialog.open"
        x-transition:enter="transition duration-200 ease-[var(--ease-out)]"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        class="relative w-full max-w-sm rounded-3xl bg-surface-container-lowest p-6 shadow-[0_16px_48px_rgba(113,59,44,0.25)] font-plus-jakarta"
    >
        <div class="flex items-start gap-4">
            <span
                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                :class="$store.dialog.tone === 'danger' ? 'bg-error-container text-on-error-container' : 'bg-primary-container text-on-primary-container'"
            >
                <span x-show="$store.dialog.tone === 'danger'" class="material-symbols-outlined text-[24px]">warning</span>
                <span x-show="$store.dialog.tone !== 'danger'" class="material-symbols-outlined text-[24px]">check_circle</span>
            </span>
            <div class="min-w-0">
                <h2 id="confirm-dialog-title" class="font-title-md text-title-md text-on-surface" x-text="$store.dialog.title"></h2>
                <p id="confirm-dialog-message" class="mt-1 font-body-md text-body-md text-on-surface-variant" x-text="$store.dialog.message"></p>
            </div>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button
                type="button"
                x-ref="cancel"
                @click="$store.dialog.answer(false)"
                class="rounded-full bg-surface-container px-5 py-2.5 font-label-lg text-label-lg text-on-surface transition-colors hover:bg-surface-container-highest active:translate-y-px"
                x-text="$store.dialog.cancelLabel"
            ></button>
            <button
                type="button"
                @click="$store.dialog.answer(true)"
                class="rounded-full px-5 py-2.5 font-label-lg text-label-lg shadow-sm transition-opacity hover:opacity-95 active:translate-y-px"
                :class="$store.dialog.tone === 'danger' ? 'bg-error text-on-error' : 'bg-primary-container text-on-primary-container'"
                x-text="$store.dialog.confirmLabel"
            ></button>
        </div>
    </div>
</div>
