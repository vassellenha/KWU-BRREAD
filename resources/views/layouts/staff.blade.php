@php
    use App\Models\User;

    $currentUser = auth()->user();

    $navLinks = collect([
        ['module' => User::MODULE_ORDERS, 'route' => 'staff.orders.index', 'active' => 'staff.orders.*', 'icon' => 'receipt_long', 'label' => 'Orders'],
        ['module' => User::MODULE_MRP, 'route' => 'staff.mrp.index', 'active' => 'staff.mrp.index', 'icon' => 'inventory_2', 'label' => 'Stock (MRP)'],
        ['module' => User::MODULE_MRP, 'route' => 'staff.mrp.movements', 'active' => 'staff.mrp.movements', 'icon' => 'history', 'label' => 'Stock Movements'],
        ['module' => User::MODULE_MENU, 'route' => 'staff.menu.index', 'active' => 'staff.menu.*', 'icon' => 'bakery_dining', 'label' => 'Menu & Prices'],
        ['module' => User::MODULE_HR, 'route' => 'staff.hr.index', 'active' => 'staff.hr.*', 'icon' => 'groups', 'label' => 'HR'],
        ['module' => User::MODULE_ACCOUNTING, 'route' => 'staff.accounting.index', 'active' => 'staff.accounting.*', 'icon' => 'account_balance', 'label' => 'Accounting'],
        ['module' => User::MODULE_SALES, 'route' => 'staff.sales.index', 'active' => 'staff.sales.*', 'icon' => 'monitoring', 'label' => 'Sales'],
        ['module' => User::MODULE_SITE, 'route' => 'staff.site.index', 'active' => 'staff.site.*', 'icon' => 'storefront', 'label' => 'Website'],
        ['module' => User::MODULE_SECURITY, 'route' => 'staff.security.index', 'active' => 'staff.security.*', 'icon' => 'shield', 'label' => 'Security'],
    ])->filter(fn (array $link) => $currentUser->canAccessModule($link['module']));

    $portalName = $currentUser->isAdmin() ? 'Superadmin' : 'Staff Portal';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7a3b6">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Staff Portal') — Brread!</title>
    @include('partials.lockdown-guard')
    @include('partials.favicon')
    @include('partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="staff text-on-surface antialiased font-plus-jakarta">

    <div class="min-h-screen flex">
        {{-- SIDEBAR --}}
        <aside class="staff-side hidden md:flex md:w-64 md:flex-col md:sticky md:top-0 md:h-screen shrink-0">
            <div class="staff-side-brand">
                <img src="{{ asset('logo.png') }}" alt="Brread!" width="480" height="239">
                <p class="staff-side-role font-hand">{{ strtolower($portalName) }}</p>
                @include('partials.drip', ['flavor' => 'strawberry', 'seed' => 1, 'drops' => false])
            </div>
            <nav class="flex-1 px-3 pt-12 pb-3 space-y-1 overflow-y-auto" aria-label="Modules">
                @foreach ($navLinks as $link)
                    <a
                        href="{{ route($link['route']) }}"
                        @if (request()->routeIs($link['active'])) aria-current="page" @endif
                        class="staff-link {{ request()->routeIs($link['active']) ? 'is-active' : '' }}"
                    >
                        <span class="material-symbols-outlined text-[20px]">{{ $link['icon'] }}</span>
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>
            <div class="p-3 border-t border-[#fbeee4]/15">
                <div class="flex items-center gap-space-sm px-2 py-space-xs">
                    <span class="w-9 h-9 rounded-full bg-[#f7a3b6] flex items-center justify-center text-[#40000e] font-headline-sm text-[16px] font-extrabold shrink-0">
                        {{ strtoupper(substr($currentUser->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0">
                        <p class="font-label-lg text-label-lg text-[#fff8f4] truncate">{{ $currentUser->name }}</p>
                        <p class="font-body-sm text-body-sm text-[#fbeee4]/60 truncate">{{ '@'.$currentUser->username }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('staff.logout') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="staff-link w-full">
                        <span class="material-symbols-outlined text-[20px]">logout</span>
                        Log out
                    </button>
                </form>
            </div>
        </aside>

        {{-- MAIN --}}
        <div class="flex-1 min-w-0 flex flex-col">
            <header class="md:hidden sticky top-0 z-40 bg-[#f7a3b6] text-[#40000e]">
                <div class="h-14 flex items-center justify-between px-margin">
                    <div class="flex items-center gap-space-sm min-w-0">
                        <img src="{{ asset('logo.png') }}" alt="Brread!" width="480" height="239" class="h-7 w-auto object-contain">
                        <span class="font-hand text-[24px] leading-none text-[#5b0f22] truncate">{{ strtolower($portalName) }}</span>
                    </div>
                    <form method="POST" action="{{ route('staff.logout') }}">
                        @csrf
                        <button type="submit" aria-label="Log out" class="w-10 h-10 -mr-2 flex items-center justify-center text-[#5b0f22]">
                            <span class="material-symbols-outlined text-[22px]">logout</span>
                        </button>
                    </form>
                </div>
                <nav class="flex gap-space-xs overflow-x-auto px-margin pb-space-sm" aria-label="Modules">
                    @foreach ($navLinks as $link)
                        <a
                            href="{{ route($link['route']) }}"
                            @if (request()->routeIs($link['active'])) aria-current="page" x-data x-init="$el.scrollIntoView({ inline: 'center', block: 'nearest' })" @endif
                            class="shrink-0 flex items-center gap-1.5 px-space-sm py-1.5 rounded-full font-label-md text-label-md {{ request()->routeIs($link['active']) ? 'bg-[#fff8f4] text-primary shadow-[0_3px_0_#c9728a]' : 'bg-[#fff8f4]/45 text-[#5b0f22]' }}"
                        >
                            <span class="material-symbols-outlined text-[16px]">{{ $link['icon'] }}</span>
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
            </header>

            <section class="staff-topbar" aria-label="Overview">
                <p class="staff-topbar-hi font-hand">hi, {{ strtok($currentUser->name, ' ') }}</p>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="staff-chip"><span class="material-symbols-outlined text-[16px] text-primary">today</span>{{ now()->timezone('Asia/Jakarta')->translatedFormat('D, j M Y') }}</span>
                    <span class="staff-chip"><span class="material-symbols-outlined text-[16px] text-primary">person</span>{{ $portalName }}</span>
                </div>
            </section>

            <main class="flex-1 p-margin md:px-margin-desktop md:pt-space-lg md:pb-margin-desktop">
                @yield('content')
            </main>
        </div>
    </div>

    @if (session('status') || $errors->any())
        @php $isError = $errors->any(); @endphp
        <div
            x-data="{ show: true }"
            x-init="setTimeout(() => show = false, {{ $isError ? 7000 : 3400 }})"
            x-show="show"
            x-transition:leave="transition-opacity duration-150 ease-[var(--ease-out)]"
            x-transition:leave-end="opacity-0"
            role="{{ $isError ? 'alert' : 'status' }}"
            class="fixed bottom-4 left-4 right-4 sm:left-auto sm:bottom-6 sm:right-6 sm:max-w-md z-50"
        >
            <div class="flex items-start gap-space-sm px-space-md py-space-sm rounded-lg {{ $isError ? 'bg-error-container text-on-error-container' : 'bg-inverse-surface text-inverse-on-surface' }} shadow-[0_12px_28px_rgba(32,27,21,0.25)] font-label-lg text-label-lg">
                <span class="material-symbols-outlined text-[18px] shrink-0 mt-0.5 {{ $isError ? '' : 'text-tertiary-fixed' }}">{{ $isError ? 'error' : 'check_circle' }}</span>
                <span class="min-w-0 flex-1">{{ $isError ? $errors->first() : session('status') }}</span>
                <button type="button" @click="show = false" aria-label="Dismiss" class="shrink-0 -my-1 -mr-2 w-7 h-7 flex items-center justify-center opacity-70 hover:opacity-100">
                    <span class="material-symbols-outlined text-[16px]">close</span>
                </button>
            </div>
        </div>
    @endif

    @include('partials.confirm-dialog')
</body>
</html>
