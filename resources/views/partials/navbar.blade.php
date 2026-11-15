@php
    $navLinks = [['home', 'Home'], ['menu', 'Menu'], ['build', 'Studio'], ['about', 'About'], ['contact', 'Contact']];
    // The Studio is a work surface: its header stays flat and quiet, with nothing hanging over the builder.
    $quiet = request()->routeIs('build');
@endphp
{{-- The bar is a slab of strawberry: the drip under it is part of the header, so it melts on every page. --}}
<header x-data="{ menuOpen: false }" class="site-nav {{ $quiet ? 'site-nav--quiet' : '' }} fixed top-0 left-0 w-full z-50 font-plus-jakarta">
    <div class="site-nav-bar">
        <div class="h-20 max-w-[1200px] mx-auto px-margin md:px-margin-tablet lg:px-margin-desktop flex items-center justify-between gap-space-md">
            <div class="flex items-center gap-space-lg">
                <a class="site-nav-logo" href="{{ route('home') }}" aria-label="Brread! home">
                    <img
                        src="{{ asset('logo.png') }}"
                        alt="Brread! — Ice Cream Sandwich Singapore Style"
                        width="480" height="239"
                        onerror="this.style.display='none'"
                    >
                </a>

                <nav aria-label="Main" class="hidden lg:flex items-center gap-1">
                    @foreach ($navLinks as [$routeName, $label])
                        <a
                            href="{{ route($routeName) }}"
                            @if (request()->routeIs($routeName)) aria-current="page" @endif
                            class="site-nav-link {{ request()->routeIs($routeName) ? 'is-active' : '' }}"
                        >{{ $label }}</a>
                    @endforeach
                </nav>
            </div>

            <div class="flex items-center gap-space-sm">
                <button
                    type="button"
                    id="nav-cart-icon"
                    @click="$store.cart.open = true"
                    data-tip="Open your cart"
                    class="site-nav-cart btn-chunky"
                    style="--chunk: {{ $quiet ? '#8a1730' : '#c9728a' }}"
                >
                    <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
                    <span class="hidden md:inline">Cart</span>
                    <span id="nav-cart-badge" class="site-nav-badge tabular-nums" x-text="$store.cart.count">0</span>
                </button>
                <button
                    type="button"
                    @click="menuOpen = !menuOpen"
                    :aria-expanded="menuOpen"
                    aria-controls="mobile-menu"
                    aria-label="Menu"
                    class="site-nav-burger btn-chunky lg:hidden"
                    style="--chunk: {{ $quiet ? '#8a1730' : '#c9728a' }}"
                >
                    <span class="material-symbols-outlined text-[22px]" x-text="menuOpen ? 'close' : 'menu'">menu</span>
                </button>
            </div>
        </div>
    </div>

    @unless ($quiet)
        @include('partials.drip', ['flavor' => 'strawberry', 'seed' => 2, 'class' => 'drip-nav drip-live', 'drops' => false])
    @endunless

    <nav
        id="mobile-menu"
        x-show="menuOpen"
        x-cloak
        x-transition:enter="transition-[opacity,transform] duration-200 ease-[var(--ease-out)]"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        @click.outside="menuOpen = false"
        @keydown.escape.window="menuOpen = false"
        aria-label="Mobile"
        class="site-nav-sheet lg:hidden"
    >
        @foreach ($navLinks as $i => [$routeName, $label])
            <a
                href="{{ route($routeName) }}"
                @if (request()->routeIs($routeName)) aria-current="page" @endif
                class="site-nav-sheet-link {{ request()->routeIs($routeName) ? 'is-active' : '' }}"
            >
                <span class="font-hand" aria-hidden="true">{{ $i + 1 }}</span>{{ $label }}
            </a>
        @endforeach
    </nav>
</header>
