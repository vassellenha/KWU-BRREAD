@php
    use App\Models\User;

    $currentUser = auth()->user();

    $navLinks = collect([
        ['module' => User::MODULE_MRP, 'route' => 'staff.mrp.index', 'active' => 'staff.mrp.index', 'icon' => 'inventory_2', 'label' => 'Stock (MRP)'],
        ['module' => User::MODULE_MRP, 'route' => 'staff.mrp.movements', 'active' => 'staff.mrp.movements', 'icon' => 'history', 'label' => 'Stock Movements'],
        ['module' => User::MODULE_HR, 'route' => 'staff.hr.index', 'active' => 'staff.hr.*', 'icon' => 'groups', 'label' => 'HR'],
        ['module' => User::MODULE_ACCOUNTING, 'route' => 'staff.accounting.index', 'active' => 'staff.accounting.*', 'icon' => 'account_balance', 'label' => 'Accounting'],
        ['module' => User::MODULE_SALES, 'route' => 'staff.sales.index', 'active' => 'staff.sales.*', 'icon' => 'monitoring', 'label' => 'Sales'],
    ])->filter(fn (array $link) => $currentUser->canAccessModule($link['module']));

    $portalName = $currentUser->isAdmin() ? 'Admin Portal' : 'Staff Portal';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title', 'Staff Portal') — Brread!</title>
    @include('partials.favicon')
    @include('partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-container-low text-on-surface antialiased font-plus-jakarta">

    <div class="min-h-screen flex">
        {{-- SIDEBAR --}}
        <aside class="hidden md:flex md:w-60 md:flex-col md:sticky md:top-0 md:h-screen bg-surface-container-lowest border-r border-outline-variant/30 shrink-0">
            <div class="h-16 flex items-center gap-space-sm px-space-md border-b border-outline-variant/30">
                <img src="{{ asset('logo.png') }}" alt="Brread!" width="480" height="239" class="h-8 w-auto object-contain">
                <span class="font-label-lg text-label-lg text-on-surface-variant">{{ $portalName }}</span>
            </div>
            <nav class="flex-1 p-space-sm space-y-1 overflow-y-auto" aria-label="Modules">
                @foreach ($navLinks as $link)
                    <a
                        href="{{ route($link['route']) }}"
                        @if (request()->routeIs($link['active'])) aria-current="page" @endif
                        class="flex items-center gap-space-sm px-space-md py-space-xs rounded font-label-lg text-label-lg {{ request()->routeIs($link['active']) ? 'bg-primary-container text-on-primary-container' : 'text-on-surface-variant hover:bg-surface-container' }}"
                    >
                        <span class="material-symbols-outlined text-[20px]">{{ $link['icon'] }}</span>
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>
            <div class="p-space-sm border-t border-outline-variant/30">
                <div class="flex items-center gap-space-sm px-space-md py-space-xs">
                    <span class="w-8 h-8 rounded-full bg-secondary-fixed flex items-center justify-center text-primary font-label-md text-label-md shrink-0">
                        {{ strtoupper(substr($currentUser->name, 0, 1)) }}
                    </span>
                    <div class="min-w-0">
                        <p class="font-label-lg text-label-lg text-on-surface truncate">{{ $currentUser->name }}</p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant truncate">{{ '@'.$currentUser->username }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('staff.logout') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-space-sm px-space-md py-space-xs rounded font-label-lg text-label-lg text-on-surface-variant hover:bg-surface-container">
                        <span class="material-symbols-outlined text-[20px]">logout</span>
                        Log out
                    </button>
                </form>
            </div>
        </aside>

        {{-- MAIN --}}
        <div class="flex-1 min-w-0 flex flex-col">
            <header class="md:hidden sticky top-0 z-40 bg-surface-container-lowest border-b border-outline-variant/30">
                <div class="h-14 flex items-center justify-between px-margin">
                    <div class="flex items-center gap-space-sm min-w-0">
                        <img src="{{ asset('logo.png') }}" alt="Brread!" width="480" height="239" class="h-7 w-auto object-contain">
                        <span class="font-label-lg text-label-lg text-on-surface-variant truncate">{{ $portalName }}</span>
                    </div>
                    <form method="POST" action="{{ route('staff.logout') }}">
                        @csrf
                        <button type="submit" aria-label="Log out" class="w-10 h-10 -mr-2 flex items-center justify-center text-on-surface-variant">
                            <span class="material-symbols-outlined text-[22px]">logout</span>
                        </button>
                    </form>
                </div>
                <nav class="flex gap-space-xs overflow-x-auto px-margin pb-space-xs" aria-label="Modules">
                    @foreach ($navLinks as $link)
                        <a
                            href="{{ route($link['route']) }}"
                            @if (request()->routeIs($link['active'])) aria-current="page" x-data x-init="$el.scrollIntoView({ inline: 'center', block: 'nearest' })" @endif
                            class="shrink-0 flex items-center gap-1.5 px-space-sm py-1.5 rounded-full font-label-md text-label-md {{ request()->routeIs($link['active']) ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container text-on-surface-variant' }}"
                        >
                            <span class="material-symbols-outlined text-[16px]">{{ $link['icon'] }}</span>
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>
            </header>

            <main class="flex-1 p-margin md:p-margin-desktop">
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

</body>
</html>
