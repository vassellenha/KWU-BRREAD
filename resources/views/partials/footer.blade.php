<footer id="site-footer" class="relative w-full bg-surface-container text-on-surface pt-40 pb-space-lg font-plus-jakarta">
    @include('partials.drip', ['flavor' => $footerDrip ?? 'vanilla', 'seed' => 0, 'drops' => false])
    <div class="max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-space-xl pb-space-xl">

            <div class="space-y-space-sm">
                <span class="font-headline-sm text-headline-sm text-primary">Brread!</span>
                <p class="font-body-md text-body-md text-on-surface-variant">
                    Singapore-style ice cream sandwiches, made to order and delivered cold across Jakarta.
                </p>
            </div>

            <div class="space-y-space-sm">
                <h2 class="font-title-md text-title-md text-on-surface">What goes in</h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    A slab of vanilla, strawberry or chocolate ice cream folded inside one slice of white or
                    whole wheat bread, topped with chocolate sauce or sprinkles.
                </p>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    <span class="font-bold text-on-surface">Allergens:</span> dairy, wheat (gluten) and soy.
                    Made in a facility that also handles tree nuts and peanuts. All ingredients are halal certified.
                </p>
            </div>

            <div class="space-y-space-sm">
                <h2 class="font-title-md text-title-md text-on-surface">Explore</h2>
                <ul class="space-y-2 font-body-sm text-body-sm text-on-surface-variant">
                    <li><a href="{{ route('menu') }}" class="hover:text-primary transition-colors">Menu</a></li>
                    <li><a href="{{ route('build') }}" class="hover:text-primary transition-colors">Brread! Studio</a></li>
                    <li><a href="{{ route('about') }}" class="hover:text-primary transition-colors">About us</a></li>
                    <li><a href="{{ route('contact') }}" class="hover:text-primary transition-colors">Contact</a></li>
                    <li><a href="{{ route('privacy') }}" class="hover:text-primary transition-colors">Privacy</a></li>
                </ul>
            </div>

            <div class="space-y-space-sm">
                <h2 class="font-title-md text-title-md text-on-surface">Visit</h2>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    Telkom University Jakarta, Kampus C<br>Jl. Minangkabau Barat No. 50<br>Setiabudi, Jakarta Selatan
                </p>
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    Daily, 11:00 AM – 9:30 PM
                </p>
            </div>
        </div>

        <div class="pt-space-lg border-t border-outline-variant/40 flex flex-col sm:flex-row items-center justify-between gap-space-sm">
            <p class="font-body-sm text-body-sm text-on-surface-variant text-center sm:text-left">
                Inspired by the ice cream carts of Orchard Road, Singapore.
            </p>
            <p class="font-body-sm text-body-sm text-on-surface-variant">
                &copy; {{ date('Y') }} Brread! Dessert Co.
            </p>
        </div>
    </div>
</footer>
