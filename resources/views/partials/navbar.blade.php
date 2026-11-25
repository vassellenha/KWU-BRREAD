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
                        alt="Brread! Ice Cream Sandwich Singapore Style"
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
                    data-sfx="none"
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
                    data-bgm-toggle
                    hidden
                    aria-pressed="false"
                    aria-label="Nyalakan musik latar"
                    title="Nyalakan musik latar"
                    class="site-nav-burger btn-chunky bgm-btn"
                    data-sfx="none"
                    style="--chunk: {{ $quiet ? '#8a1730' : '#c9728a' }}"
                >
                    <svg class="bgm-ico" width="24" height="24" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false">
                        <g class="bgm-note">
                            <rect x="11.2" y="3.2" width="2.6" height="15" rx="1.3" fill="#e0b070" stroke="#8a1730" stroke-width="1"/>
                            <path d="M13.8 3.6c3.2.1 5.6 1.3 6 4 .1 1-.3 2-1 2.6-.2-1.5-1.3-2.5-2.9-3-.7-.2-1.4-.3-2.1-.3z" fill="#8a1730"/>
                            <circle cx="19.2" cy="12.2" r="1.1" fill="#8a1730"/>
                            <rect x="2.4" y="13.6" width="10.2" height="7.6" rx="3" fill="#fbe3b5" stroke="#8a1730" stroke-width="1.2"/>
                            <rect x="4.6" y="15.8" width="5.8" height="3.2" rx="1.5" fill="#f2749f"/>
                            <ellipse cx="6.2" cy="16.7" rx="1.1" ry=".6" fill="#fff" opacity=".85"/>
                        </g>
                        <rect class="bgm-spark" x="19.4" y="2.2" width="2.8" height="1.4" rx=".7" transform="rotate(35 20.8 2.9)" fill="#f2749f"/>
                        <path class="bgm-strike" d="M3.5 3.5 20.5 20.5" stroke="#8a1730" stroke-width="2.2" stroke-linecap="round"/>
                    </svg>
                </button>
                <button
                    type="button"
                    data-sfx-toggle
                    aria-label="Sound effects"
                    aria-pressed="false"
                    data-tip="Sound on or off"
                    class="site-nav-burger btn-chunky"
                    data-sfx="none"
                    style="--chunk: {{ $quiet ? '#8a1730' : '#c9728a' }}"
                >
                    <svg class="sfx-ico sfx-ico-on" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z" fill="currentColor"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
                    <svg class="sfx-ico sfx-ico-off" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z" fill="currentColor"/><path d="m16 9 5 6"/><path d="m21 9-5 6"/></svg>
                </button>
                <button
                    type="button"
                    data-sfx="tick"
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
