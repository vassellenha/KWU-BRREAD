{{-- The sticky "save all" bar and the unsaved-changes dialog. Lives inside the batchForm root (see resources/js/staff-batch.js). --}}
<p class="sr-only" role="status" aria-live="polite" x-text="announce"></p>

<div class="batch-bar" role="region" aria-label="Save all changes">
    <div class="batch-bar__inner">
        <div class="min-w-0">
            <p class="batch-bar__status" :class="{ 'is-pending': count > 0 }">
                <span class="batch-bar__dot" aria-hidden="true"></span>
                <span x-text="count === 0 ? 'No unsaved changes' : (count === 1 ? '1 row changed' : count + ' rows changed')"></span>
                <span x-show="problems > 0" x-cloak class="text-error" x-text="'· ' + problems + (problems === 1 ? ' problem' : ' problems')"></span>
            </p>
            <p class="batch-bar__keys">Ctrl+S saves all · Esc puts a field back · arrow keys move between cells</p>
        </div>
        <div class="flex shrink-0 items-center gap-space-xs">
            <button type="button" x-show="conflicts > 0" x-cloak @click="reloadPage()" class="press-scale batch-btn batch-btn--ghost">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">refresh</span>
                Reload
            </button>
            <button type="button" @click="discardAll()" :disabled="count === 0 || saving" class="press-scale batch-btn batch-btn--ghost">Discard all</button>
            <button type="button" @click="save()" :disabled="count === 0 || saving" :aria-busy="saving" class="press-scale batch-btn batch-btn--primary">
                <span class="material-symbols-outlined text-[18px]" :class="{ 'animate-spin': saving }" aria-hidden="true" x-text="saving ? 'progress_activity' : 'done_all'">done_all</span>
                <span x-text="saving ? 'Saving' : (count > 0 ? 'Save all changes (' + count + ')' : 'Save all changes')">Save all changes</span>
            </button>
        </div>
    </div>
</div>

<div
    x-show="guardOpen"
    x-cloak
    @keydown="guardKeydown($event)"
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="batch-guard-title"
    aria-describedby="batch-guard-text"
    class="fixed inset-0 z-[100] flex items-center justify-center p-4"
>
    <div x-show="guardOpen" x-transition.opacity.duration.150ms @click="guardStay()" class="absolute inset-0 bg-on-surface/50"></div>
    <div
        x-show="guardOpen"
        x-transition:enter="transition duration-200 ease-[var(--ease-out)]"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        class="relative w-full max-w-md rounded-3xl bg-surface-container-lowest p-6 shadow-[0_16px_48px_rgba(113,59,44,0.25)] font-plus-jakarta"
    >
        <div class="flex items-start gap-4">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-container text-on-primary-container">
                <span class="material-symbols-outlined text-[24px]" aria-hidden="true">edit_note</span>
            </span>
            <div class="min-w-0">
                <h2 id="batch-guard-title" class="font-title-md text-title-md text-on-surface">Leave with unsaved changes?</h2>
                <p id="batch-guard-text" class="mt-1 font-body-md text-body-md text-on-surface-variant" x-text="count === 1 ? '1 row has changes that are not saved yet.' : count + ' rows have changes that are not saved yet.'"></p>
            </div>
        </div>
        <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button type="button" x-ref="guardStay" @click="guardStay()" class="batch-btn batch-btn--ghost justify-center">Stay here</button>
            <button type="button" @click="guardDiscard()" class="batch-btn batch-btn--danger justify-center">Discard and leave</button>
            <button type="button" @click="guardSave()" :disabled="saving" class="batch-btn batch-btn--primary justify-center">Save all and leave</button>
        </div>
    </div>
</div>
