<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Staff Portal') — Brread!</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    {{ Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-container-low text-on-surface antialiased font-plus-jakarta">

    <div class="min-h-screen flex">
        {{-- SIDEBAR --}}
        <aside class="hidden md:flex md:w-60 md:flex-col bg-surface-container-lowest border-r border-outline-variant/30 shrink-0">
            <div class="h-16 flex items-center gap-space-sm px-space-md border-b border-outline-variant/30">
                <img src="{{ asset('logo.png') }}" alt="Brread!" class="h-8 w-auto object-contain">
                <span class="font-label-lg text-label-lg text-on-surface-variant">Staff Portal</span>
            </div>
            <nav class="flex-1 p-space-sm space-y-1">
                <a
                    href="{{ route('staff.mrp.index') }}"
                    class="flex items-center gap-space-sm px-space-md py-space-xs rounded-DEFAULT font-label-lg text-label-lg transition-colors duration-200 {{ request()->routeIs('staff.mrp.index') ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container' }}"
                >
                    <span class="material-symbols-outlined text-[20px]">inventory_2</span>
                    Stock (MRP)
                </a>
                <a
                    href="{{ route('staff.mrp.movements') }}"
                    class="flex items-center gap-space-sm px-space-md py-space-xs rounded-DEFAULT font-label-lg text-label-lg transition-colors duration-200 {{ request()->routeIs('staff.mrp.movements') ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container' }}"
                >
                    <span class="material-symbols-outlined text-[20px]">history</span>
                    Stock Movements
                </a>
                @if (auth()->user()?->hasRole(\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_HR))
                    <a
                        href="{{ route('staff.hr.index') }}"
                        class="flex items-center gap-space-sm px-space-md py-space-xs rounded-DEFAULT font-label-lg text-label-lg transition-colors duration-200 {{ request()->routeIs('staff.hr.*') ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container' }}"
                    >
                        <span class="material-symbols-outlined text-[20px]">groups</span>
                        HR
                    </a>
                @endif
                @if (auth()->user()?->hasRole(\App\Models\User::ROLE_ADMIN, \App\Models\User::ROLE_ACCOUNTANT))
                    <a
                        href="{{ route('staff.accounting.index') }}"
                        class="flex items-center gap-space-sm px-space-md py-space-xs rounded-DEFAULT font-label-lg text-label-lg transition-colors duration-200 {{ request()->routeIs('staff.accounting.*') ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container' }}"
                    >
                        <span class="material-symbols-outlined text-[20px]">account_balance</span>
                        Accounting
                    </a>
                @endif
            </nav>
            <div class="p-space-sm border-t border-outline-variant/30">
                <div class="flex items-center gap-space-sm px-space-md py-space-xs">
                    <span class="w-8 h-8 rounded-full bg-secondary-fixed flex items-center justify-center text-primary font-label-md text-label-md shrink-0">
                        {{ strtoupper(substr(auth()->user()->name ?? '?', 0, 1)) }}
                    </span>
                    <div class="min-w-0">
                        <p class="font-label-lg text-label-lg text-on-surface truncate">{{ auth()->user()->name }}</p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">{{ \App\Models\User::ROLES[auth()->user()->role] ?? auth()->user()->role }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('staff.logout') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-space-sm px-space-md py-space-xs rounded-DEFAULT font-label-lg text-label-lg text-on-surface-variant hover:bg-surface-container transition-colors duration-200">
                        <span class="material-symbols-outlined text-[20px]">logout</span>
                        Log out
                    </button>
                </form>
            </div>
        </aside>

        {{-- MAIN --}}
        <div class="flex-1 min-w-0 flex flex-col">
            <header class="md:hidden h-16 flex items-center justify-between px-margin bg-surface-container-lowest border-b border-outline-variant/30">
                <div class="flex items-center gap-space-sm">
                    <img src="{{ asset('logo.png') }}" alt="Brread!" class="h-7 w-auto object-contain">
                    <span class="font-label-lg text-label-lg text-on-surface-variant">Staff Portal</span>
                </div>
                <form method="POST" action="{{ route('staff.logout') }}">
                    @csrf
                    <button type="submit" class="text-on-surface-variant">
                        <span class="material-symbols-outlined text-[22px]">logout</span>
                    </button>
                </form>
            </header>

            <main class="flex-1 p-margin md:p-margin-desktop">
                @yield('content')
            </main>
        </div>
    </div>

    @if (session('status'))
        <div
            x-data="{ show: false }"
            x-init="setTimeout(() => show = true, 20); setTimeout(() => show = false, 3400)"
            x-show="show"
            x-transition:enter="transition-[opacity,transform] duration-[400ms] ease"
            x-transition:enter-start="opacity-0 translate-y-full"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-transition:leave="transition-[opacity,transform] duration-[400ms] ease"
            x-transition:leave-start="opacity-100 translate-y-0"
            x-transition:leave-end="opacity-0 translate-y-full"
            class="fixed bottom-6 right-6 z-50"
            x-cloak
        >
            <div class="flex items-center gap-space-sm px-space-md py-space-sm rounded-full bg-inverse-surface text-inverse-on-surface shadow-[0_12px_28px_rgba(32,27,21,0.25)] font-label-lg text-label-lg">
                <span class="material-symbols-outlined text-[18px] text-tertiary-fixed">check_circle</span>
                {{ session('status') }}
            </div>
        </div>
    @endif

</body>
</html>
