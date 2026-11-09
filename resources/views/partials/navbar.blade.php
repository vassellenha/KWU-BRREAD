<header x-data="{ menuOpen: false }" class="fixed top-0 left-0 w-full z-50 bg-surface/90 backdrop-blur-xl shadow-[0_4px_16px_rgba(113,59,44,0.06)] font-plus-jakarta">
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
            <a href="{{ route('menu') }}" aria-label="Browse the menu" class="press-scale hidden sm:flex w-10 h-10 rounded-full bg-surface-container hover:bg-surface-container-high text-on-surface-variant items-center justify-center transition-colors duration-200">
                <span class="material-symbols-outlined text-[20px]">search</span>
            </a>
            <div class="hidden sm:flex items-center gap-space-xs bg-surface-container px-space-md py-space-xs rounded-full font-label-md text-label-md text-on-surface-variant">
                <span class="material-symbols-outlined text-[16px] text-tertiary">payments</span>
                <span>IDR</span>
            </div>

            {{-- Customer Auth Pill / Dropdown --}}
            @auth
                <div x-data="{ userMenuOpen: false }" class="relative">
                    <button
                        type="button"
                        @click="userMenuOpen = !userMenuOpen"
                        :aria-expanded="userMenuOpen"
                        class="press-scale flex items-center gap-2 bg-surface-container hover:bg-surface-container-high text-on-surface px-2.5 py-1.5 sm:px-3 sm:py-1.5 rounded-full font-label-md text-label-md transition-all duration-200 shadow-[0_2px_8px_rgba(113,59,44,0.04)]"
                    >
                        <img
                            src="{{ auth()->user()->avatarUrl() }}"
                            alt="{{ auth()->user()->name }}"
                            class="w-7 h-7 rounded-full object-cover border border-primary/20"
                        >
                        <span class="hidden md:inline font-semibold max-w-[110px] truncate">{{ auth()->user()->name }}</span>
                        <span class="material-symbols-outlined text-[18px] text-on-surface-variant transition-transform" :class="userMenuOpen ? 'rotate-180' : ''">expand_more</span>
                    </button>

                    <div
                        x-show="userMenuOpen"
                        x-cloak
                        @click.outside="userMenuOpen = false"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                        x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                        class="absolute right-0 mt-2 w-56 rounded-2xl bg-surface shadow-[0_12px_36px_rgba(113,59,44,0.18)] border border-outline-variant/30 py-2 z-50 text-left font-plus-jakarta"
                    >
                        <div class="px-4 py-2 border-b border-outline-variant/20 mb-1">
                            <p class="font-title-sm text-title-sm text-on-surface truncate">{{ auth()->user()->name }}</p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant truncate">{{ auth()->user()->email }}</p>
                            <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[11px] font-bold bg-primary-container text-on-primary-container">
                                {{ auth()->user()->role === 'customer' ? 'Customer' : ucfirst(auth()->user()->role) }}
                            </span>
                        </div>

                        <a
                            href="{{ route('profile.edit') }}"
                            @click="userMenuOpen = false"
                            class="flex items-center gap-2.5 px-4 py-2 text-label-md font-label-md text-on-surface hover:bg-surface-container transition-colors"
                        >
                            <span class="material-symbols-outlined text-[18px] text-primary">person</span>
                            <span>Profil Saya</span>
                        </a>

                        <a
                            href="{{ route('orders.index') }}"
                            @click="userMenuOpen = false"
                            class="flex items-center gap-2.5 px-4 py-2 text-label-md font-label-md text-on-surface hover:bg-surface-container transition-colors"
                        >
                            <span class="material-symbols-outlined text-[18px] text-secondary">receipt_long</span>
                            <span>Riwayat Transaksi</span>
                        </a>

                        @if(!auth()->user()->isCustomer())
                            <a
                                href="{{ route(auth()->user()->homeRouteName()) }}"
                                @click="userMenuOpen = false"
                                class="flex items-center gap-2.5 px-4 py-2 text-label-md font-label-md text-tertiary hover:bg-surface-container transition-colors"
                            >
                                <span class="material-symbols-outlined text-[18px]">dashboard</span>
                                <span>Staff Portal</span>
                            </a>
                        @endif

                        <div class="my-1 border-t border-outline-variant/20"></div>

                        <form method="POST" action="{{ route('customer.logout') }}" class="w-full">
                            @csrf
                            <button
                                type="submit"
                                class="w-full flex items-center gap-2.5 px-4 py-2 text-label-md font-label-md text-error hover:bg-error-container/20 transition-colors text-left"
                            >
                                <span class="material-symbols-outlined text-[18px]">logout</span>
                                <span>Keluar</span>
                            </button>
                        </form>
                    </div>
                </div>
            @else
                <a
                    href="{{ route('login') }}"
                    class="press-scale flex items-center gap-1.5 bg-surface-container hover:bg-surface-container-high text-on-surface px-space-md py-space-xs rounded-full font-label-lg text-label-lg transition-colors duration-200"
                >
                    <span class="material-symbols-outlined text-[18px] text-primary">account_circle</span>
                    <span>Masuk</span>
                </a>
            @endauth

            <button
                type="button"
                id="nav-cart-icon"
                @click="$store.cart.open = true"
                class="flex items-center gap-space-xs bg-primary-container text-on-primary-container px-space-md py-space-xs rounded-full font-label-lg text-label-lg shadow-[0_4px_12px_rgba(199,70,89,0.3)] hover:opacity-95 active:translate-y-[2px] transition-[opacity,transform,box-shadow] duration-200"
            >
                <span class="material-symbols-outlined text-[20px]">shopping_bag</span>
                <span class="hidden md:inline font-label-lg text-label-lg">My Sweet Cart</span>
                <span id="nav-cart-badge" class="bg-surface-container-lowest text-primary text-label-md font-label-md px-2 py-0.5 rounded-full tabular-nums" x-text="$store.cart.count">0</span>
            </button>
            <button
                type="button"
                @click="menuOpen = !menuOpen"
                :aria-expanded="menuOpen"
                aria-controls="mobile-menu"
                aria-label="Menu"
                class="press-scale xl:hidden w-10 h-10 rounded-full bg-surface-container hover:bg-surface-container-high text-on-surface-variant flex items-center justify-center transition-colors duration-200"
            >
                <span class="material-symbols-outlined text-[22px]" x-text="menuOpen ? 'close' : 'menu'">menu</span>
            </button>
        </div>
    </div>

    <nav
        id="mobile-menu"
        x-show="menuOpen"
        x-cloak
        x-transition:enter="transition-[opacity,transform] duration-150 ease-[var(--ease-out)]"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        @click.outside="menuOpen = false"
        @keydown.escape.window="menuOpen = false"
        aria-label="Main"
        class="xl:hidden border-t border-outline-variant/30 px-margin md:px-margin-tablet py-space-sm flex flex-col gap-1"
    >
        @foreach ([['home', 'Home'], ['menu', 'Explore Menu'], ['build', 'Brread! Studio'], ['contact', 'Contact']] as [$routeName, $label])
            <a
                href="{{ route($routeName) }}"
                class="px-space-md py-space-sm rounded font-label-lg text-label-lg {{ request()->routeIs($routeName) ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container' }}"
            >{{ $label }}</a>
        @endforeach

        <div class="my-1 border-t border-outline-variant/20"></div>

        @auth
            <a
                href="{{ route('profile.edit') }}"
                class="px-space-md py-space-sm rounded font-label-lg text-label-lg flex items-center gap-2 {{ request()->routeIs('profile.*') ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container' }}"
            >
                <span class="material-symbols-outlined text-[20px]">person</span>
                <span>Profil Saya ({{ auth()->user()->name }})</span>
            </a>
            <a
                href="{{ route('orders.index') }}"
                class="px-space-md py-space-sm rounded font-label-lg text-label-lg flex items-center gap-2 {{ request()->routeIs('orders.*') ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container' }}"
            >
                <span class="material-symbols-outlined text-[20px]">receipt_long</span>
                <span>Riwayat Transaksi</span>
            </a>
            <form method="POST" action="{{ route('customer.logout') }}" class="w-full">
                @csrf
                <button
                    type="submit"
                    class="w-full text-left px-space-md py-space-sm rounded font-label-lg text-label-lg text-error hover:bg-error-container/20 flex items-center gap-2"
                >
                    <span class="material-symbols-outlined text-[20px]">logout</span>
                    <span>Keluar</span>
                </button>
            </form>
        @else
            <a
                href="{{ route('login') }}"
                class="px-space-md py-space-sm rounded font-label-lg text-label-lg text-primary hover:bg-surface-container flex items-center gap-2"
            >
                <span class="material-symbols-outlined text-[20px]">login</span>
                <span>Masuk / Daftar Akun</span>
            </a>
        @endauth
    </nav>
</header>
