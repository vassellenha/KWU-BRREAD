<header x-data="{}" class="fixed top-0 left-0 w-full z-50 bg-surface/90 backdrop-blur-xl shadow-[0_4px_16px_rgba(113,59,44,0.06)] font-plus-jakarta">
    <div class="h-20 max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop flex items-center justify-between gap-space-md">
        <div class="flex items-center gap-space-lg">
            <a class="flex items-center gap-space-sm group" href="{{ route('home') }}">
                <img
                    src="{{ asset('logo.png') }}"
                    alt="Brread! — Ice Cream Sandwich Singapore Style"
                    class="h-12 w-auto object-contain group-hover:scale-105 transition-transform"
                    onerror="this.style.display='none'"
                >
            </a>

            <nav class="hidden xl:flex items-center gap-space-xs bg-surface-container p-space-xs rounded-full shadow-[0_2px_8px_rgba(113,59,44,0.04)]">
                <a
                    href="{{ route('home') }}"
                    class="px-space-md py-space-xs transition-[background-color,color,box-shadow] duration-200 rounded-full font-label-lg text-label-lg {{ request()->routeIs('home') ? 'bg-primary-container text-on-primary-container shadow-[0_2px_8px_rgba(199,70,89,0.25)]' : 'text-on-surface-variant hover:text-on-surface' }}"
                >
                    Home
                </a>
                <a
                    href="{{ route('menu') }}"
                    class="px-space-md py-space-xs transition-[background-color,color,box-shadow] duration-200 rounded-full font-label-lg text-label-lg {{ request()->routeIs('menu') ? 'bg-primary-container text-on-primary-container shadow-[0_2px_8px_rgba(199,70,89,0.25)]' : 'text-on-surface-variant hover:text-on-surface' }}"
                >
                    Explore Menu
                </a>
                <a
                    href="{{ route('build') }}"
                    class="px-space-md py-space-xs rounded-full font-label-lg text-label-lg transition-[background-color,color,box-shadow] duration-200 flex items-center gap-1.5 {{ request()->routeIs('build') ? 'bg-primary-container text-on-primary-container shadow-[0_2px_8px_rgba(199,70,89,0.25)]' : 'text-on-surface-variant hover:text-on-surface' }}"
                >
                    <span class="material-symbols-outlined text-[16px] text-primary">auto_awesome</span>
                    <span>Brread! Studio</span>
                </a>
                <a
                    href="{{ route('contact') }}"
                    class="px-space-md py-space-xs rounded-full font-label-lg text-label-lg transition-[background-color,color,box-shadow] duration-200 {{ request()->routeIs('contact') ? 'bg-primary-container text-on-primary-container shadow-[0_2px_8px_rgba(199,70,89,0.25)]' : 'text-on-surface-variant hover:text-on-surface' }}"
                >
                    Contact
                </a>
            </nav>
        </div>

        <div class="flex items-center gap-space-sm sm:gap-space-md">
            <button class="press-scale w-10 h-10 rounded-full bg-surface-container hover:bg-surface-container-high text-on-surface-variant flex items-center justify-center transition-colors duration-200" type="button">
                <span class="material-symbols-outlined text-[20px]">search</span>
            </button>
            <div class="hidden sm:flex items-center gap-space-xs bg-surface-container px-space-md py-space-xs rounded-full font-label-md text-label-md text-on-surface-variant">
                <span class="material-symbols-outlined text-[16px] text-tertiary">payments</span>
                <span>IDR</span>
            </div>
            <button
                type="button"
                id="nav-cart-icon"
                @click="$store.cart.open = true"
                class="flex items-center gap-space-xs bg-primary-container text-on-primary-container px-space-md py-space-xs rounded-full font-label-lg text-label-lg shadow-[0_4px_12px_rgba(199,70,89,0.3)] hover:opacity-95 active:translate-y-[2px] transition-[opacity,transform,box-shadow] duration-200"
            >
                <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
                <span class="hidden md:inline font-label-lg text-label-lg">My Sweet Cart</span>
                <span id="nav-cart-badge" class="bg-surface-container-lowest text-primary text-label-md font-label-md px-2 py-0.5 rounded-full" x-text="$store.cart.count"></span>
            </button>
        </div>
    </div>
</header>
