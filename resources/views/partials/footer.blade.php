<footer id="site-footer" class="w-full bg-surface-container-low pt-space-xl pb-space-lg shadow-[0_-4px_20px_rgba(113,59,44,0.04)] font-plus-jakarta">
    <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-space-xl pb-space-xl">

            {{-- Brand --}}
            <div class="space-y-space-md">
                <div class="flex items-center gap-space-sm">
                    <span class="w-9 h-9 rounded-full bg-secondary-fixed flex items-center justify-center text-primary">
                        <span class="material-symbols-outlined text-[20px]">icecream</span>
                    </span>
                    <span class="font-headline-sm text-headline-sm text-primary">Brread!</span>
                </div>
                <p class="font-body-md text-body-md text-on-surface-variant">
                    Reinventing Singapore Orchard Road street cart nostalgia. Pillowy rainbow milk bread and
                    crispy golden wafers paired with artisanal tropical ice cream cuts, made fresh for
                    delivery across Jakarta.
                </p>
                <div class="flex items-center gap-space-sm text-on-surface-variant">
                    <span class="hover-wiggle w-8 h-8 rounded-full bg-surface-container flex items-center justify-center hover:bg-primary-container hover:text-on-primary-container transition-colors duration-200 cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]">storefront</span>
                    </span>
                    <span class="hover-wiggle w-8 h-8 rounded-full bg-surface-container flex items-center justify-center hover:bg-primary-container hover:text-on-primary-container transition-colors duration-200 cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]">forum</span>
                    </span>
                    <span class="hover-wiggle w-8 h-8 rounded-full bg-surface-container flex items-center justify-center hover:bg-primary-container hover:text-on-primary-container transition-colors duration-200 cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]">share</span>
                    </span>
                </div>
            </div>

            {{-- Heritage & Sourcing --}}
            <div class="space-y-space-md">
                <h4 class="font-title-md text-title-md text-on-surface">Heritage &amp; Sourcing</h4>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    Every slab is sliced from nostalgic recipe blocks: fresh coconut pandan, sweet yam,
                    creamy durian, and rich chocolate ganache wrapped inside soft swirl loaves.
                </p>
                <div class="p-space-md rounded bg-surface-container">
                    <p class="font-label-md text-label-md text-primary mb-1 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[16px]">info</span>Allergen &amp; Dietary Advisory
                    </p>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">
                        Contains dairy, wheat (gluten), and soy. Crafted in a facility processing tree nuts
                        and peanuts. 100% Halal certified ingredients.
                    </p>
                </div>
            </div>

            {{-- Quick Bites --}}
            <div class="space-y-space-md">
                <h4 class="font-title-md text-title-md text-on-surface">Quick Bites</h4>
                <ul class="space-y-2 font-body-sm text-body-sm text-on-surface-variant">
                    <li><a href="{{ route('menu') }}" class="hover:text-primary transition-colors">Signature Rainbow Bread Sets</a></li>
                    <li><a href="{{ route('build') }}" class="hover:text-primary transition-colors">Custom Bread's Slab Studio</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-primary transition-colors">Party Cart &amp; Corporate Boxes</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-primary transition-colors">Cloud Kitchen Locations</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-primary transition-colors">Cold-Chain Delivery FAQ</a></li>
                </ul>
            </div>

            {{-- Join the Cart Club --}}
            <div class="space-y-space-md">
                <h4 class="font-title-md text-title-md text-on-surface">Join the Cart Club</h4>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    Get secret seasonal drops, flash alerts, and IDR 15.000 off your maiden order.
                </p>
                <form class="flex items-center gap-2" onsubmit="return false;">
                    <input
                        type="email"
                        placeholder="Your email"
                        class="flex-1 min-w-0 px-space-md py-space-xs rounded-full border border-outline-variant bg-surface-container-lowest font-body-sm text-body-sm text-on-surface placeholder:text-on-surface-variant/60 focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                    <button type="submit" class="press-scale shrink-0 bg-primary-container text-on-primary-container px-space-md py-space-xs rounded-full font-label-lg text-label-lg hover:opacity-95 transition-opacity duration-200">
                        Join
                    </button>
                </form>
                <p class="font-body-sm text-body-sm text-on-surface-variant/70">We respect your inbox. No melted spam.</p>
            </div>
        </div>

        <div class="pt-space-lg border-t border-outline-variant/40 flex flex-col sm:flex-row items-center justify-between gap-space-sm">
            <p class="font-body-sm text-body-sm text-on-surface-variant text-center sm:text-left">
                Inspired by Singapore's Uncle Ice Cream Cart Legacy &bull; Made with pride in Indonesia
            </p>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
                &copy; {{ date('Y') }} Brread! Dessert Co. All rights reserved.
            </p>
        </div>
    </div>
</footer>
