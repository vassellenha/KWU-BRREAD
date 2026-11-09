@php
    $isAdminPortal = $portal === 'admin';
    $portalName = $isAdminPortal ? 'Admin Portal' : 'Staff Portal';
    $fieldClasses = 'w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $portalName }} Login — Brread!</title>
    @include('partials.favicon')
    @include('partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-container-low text-on-surface antialiased font-plus-jakarta min-h-screen flex items-center justify-center p-margin">

    <main class="w-full max-w-sm" x-data="{
        username: @js(old('username', $quickLoginEnabled ? $quickLoginUsername : '')),
        password: @js($quickLoginEnabled ? $quickLoginPassword : ''),
    }">
        <div class="flex flex-col items-center mb-space-lg">
            <img src="{{ asset('logo.png') }}" alt="Brread!" width="480" height="239" class="h-14 w-auto object-contain mb-space-sm">
            <h1 class="font-title-md text-title-md text-on-surface">{{ $portalName }}</h1>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
                {{ $isAdminPortal ? 'Full access to every module' : 'Sign in to open your module' }}
            </p>
        </div>

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 shadow-[0_4px_16px_rgba(113,59,44,0.06)] p-space-lg">
            @if ($errors->any())
                <div role="alert" class="mb-space-md px-space-md py-space-sm rounded bg-error-container text-on-error-container font-body-sm text-body-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route($attemptRoute) }}" autocomplete="off" class="space-y-space-sm">
                @csrf
                <div>
                    <label for="username" class="block font-label-md text-label-md text-on-surface-variant mb-1">Username</label>
                    <input
                        id="username"
                        type="text"
                        name="username"
                        x-model="username"
                        required
                        autofocus
                        autocomplete="off"
                        autocapitalize="none"
                        spellcheck="false"
                        maxlength="255"
                        class="{{ $fieldClasses }}"
                    >

                    @if ($quickLoginEnabled)
                        <button
                            type="submit"
                            form="quick-login-form"
                            x-show="@js($quickLoginUsername).startsWith(username.trim().toLowerCase())"
                            x-transition:enter="transition-[opacity,transform] duration-150 ease-[var(--ease-out)]"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            class="press-scale mt-space-xs w-full flex items-center gap-space-sm rounded border border-outline-variant/40 bg-surface-container/40 px-space-sm py-space-xs text-left hover:bg-surface-container transition-colors duration-150"
                        >
                            <span class="w-8 h-8 rounded-full bg-primary text-on-primary flex items-center justify-center font-label-lg text-label-lg shrink-0">A</span>
                            <span class="min-w-0 flex-1 leading-tight">
                                <span class="block font-label-lg text-label-lg text-on-surface">{{ $quickLoginUsername }}</span>
                                <span class="block font-label-md text-label-md text-on-surface-variant">Brread! Admin</span>
                            </span>
                            <span class="flex items-center gap-0.5 font-label-md text-label-md text-primary shrink-0">
                                Quick login
                                <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
                            </span>
                        </button>
                    @endif
                </div>
                <div>
                    <label for="password" class="block font-label-md text-label-md text-on-surface-variant mb-1">Password</label>
                    <input
                        id="password"
                        type="password"
                        name="password"
                        x-model="password"
                        required
                        autocomplete="new-password"
                        class="{{ $fieldClasses }}"
                    >
                </div>
                <label class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
                    <input type="checkbox" name="remember" class="rounded border-outline-variant text-primary focus:ring-primary">
                    Keep me signed in
                </label>

                <button
                    type="submit"
                    class="press-scale w-full flex items-center justify-center gap-space-xs rounded-full bg-primary-container px-space-lg py-space-sm font-label-lg text-label-lg text-on-primary-container shadow-[0_4px_12px_rgba(199,70,89,0.3)] transition-opacity duration-200 hover:opacity-95"
                >
                    <span class="material-symbols-outlined text-[20px]">login</span>
                    Sign in
                </button>
            </form>

            @if ($quickLoginEnabled)
                <form id="quick-login-form" method="POST" action="{{ route('admin.quick-login') }}" class="hidden">
                    @csrf
                </form>
            @endif
        </div>

        <p class="text-center font-body-sm text-body-sm text-on-surface-variant mt-space-md">
            <a href="{{ route('home') }}" class="hover:text-primary transition-colors">&larr; Back to Brread!</a>
        </p>
    </main>

</body>
</html>
