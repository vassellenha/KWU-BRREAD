{{-- Shown once per browser session on pages with "save all". Lives inside the batchForm root. --}}
<div x-show="hintOpen" x-cloak id="batch-hint" class="batch-hint" role="note">
    <span class="material-symbols-outlined text-[20px] text-primary shrink-0" aria-hidden="true">keyboard</span>
    <p class="min-w-0 flex-1 font-body-sm text-body-sm text-on-surface">
        Change as many rows as you like, then save them together. <strong>Arrow keys</strong> move between cells, <strong>Ctrl+S</strong> saves all, <strong>Esc</strong> puts a field back.
    </p>
    <button type="button" @click="dismissHint()" class="press-scale shrink-0 rounded-full px-space-sm py-1 font-label-md text-label-md text-primary hover:bg-primary-container/40">Got it</button>
</div>
