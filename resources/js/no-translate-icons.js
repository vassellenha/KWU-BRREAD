/*
 * Chrome's "Translate this page" (on by default for many phones) also translates the names inside the icon spans,
 * because an icon is just text such as "shopping_bag" that the icon font turns into a picture. Once translated the
 * name is no longer a known icon, so the raw words ("TAS_BELANJA", "PANAH_") are drawn over the page. Marking every
 * icon translate="no" keeps them out of the translation; the real words around them are still translated.
 */
const SELECTOR = '.material-symbols-outlined';

function protect(root) {
    if (root.nodeType !== 1) return;
    if (root.matches(SELECTOR)) root.setAttribute('translate', 'no');
    root.querySelectorAll(SELECTOR).forEach((icon) => icon.setAttribute('translate', 'no'));
}

protect(document.documentElement);

// Icons that Alpine adds later (cart lines, dialogs, lists) are marked as soon as they appear.
new MutationObserver((records) => {
    for (const record of records) record.addedNodes.forEach(protect);
}).observe(document.documentElement, { childList: true, subtree: true });
