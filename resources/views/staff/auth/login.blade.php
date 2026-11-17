@php
    $isAdminPortal = $portal === 'admin';
    $locked = $locked ?? false;
    $portalName = $isAdminPortal ? 'Superadmin' : 'Staff Portal';
    // On a locked page the header greets whoever is knocking, by the name they typed.
    $lockName = $locked ? \App\Support\Lockdown::greeting($typedUsername ?? null, $trace ?? null) : null;
    $fieldClasses = 'w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#2d2b33">
    <meta name="robots" content="noindex">
    <title>{{ $portalName }} Login — Brread!</title>
    @include('partials.lockdown-guard')
    @include('partials.favicon')
    @include('partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="staff staff-login doodle {{ $locked ? 'is-locked' : '' }} bg-surface-container-low text-on-surface antialiased font-plus-jakarta min-h-screen flex items-center justify-center p-margin">
    @include('partials.drip', ['flavor' => 'strawberry', 'seed' => 2, 'drops' => false])

    <main class="w-full max-w-sm" x-data="staffLogin(@js(old('username', $quickLoginEnabled ? $quickLoginUsername : '')), @js(route('captcha.image')))">
        <div class="flex flex-col items-center mb-space-lg">
            <img src="{{ asset('logo.png') }}" alt="Brread!" width="480" height="239" class="h-20 w-auto object-contain mb-space-sm -rotate-3">
            <p class="login-tagline font-hand text-[28px] leading-none -rotate-2" data-login-tagline>{{ $locked ? 'welcome,' : 'behind the counter' }}</p>
            <h1 class="login-title font-headline-lg text-[34px] leading-tight font-extrabold text-on-surface" data-login-title>{{ $lockName ?? $portalName }}</h1>
            <p class="login-whoami" data-login-whoami @unless ($locked) hidden @endunless></p>
            @unless ($isAdminPortal)
                <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Sign in to open your module</p>
            @endunless
        </div>

        <div id="login-card" class="staff-login-card relative bg-surface-container-lowest rounded-lg border border-outline-variant/40 p-space-lg">
            <div id="lockdown-banner" class="lockdown-banner" role="alert" @unless ($locked) hidden @endunless>
                <span>TOO MANY ATTEMPTS.</span><span>WE KNOW.</span><span>SIGN-IN HALTED.</span>
            </div>

            @if ($errors->any())
                <div role="alert" class="mb-space-md px-space-md py-space-sm rounded bg-error-container text-on-error-container font-body-sm text-body-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <div x-show="error" x-cloak x-text="error" role="alert" class="mb-space-md px-space-md py-space-sm rounded bg-error-container text-on-error-container font-body-sm text-body-sm"></div>

            <form method="POST" action="{{ route($attemptRoute) }}" class="space-y-space-sm" x-on:submit.prevent="submit($event)">
                <fieldset class="contents" @disabled($locked)>
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
                        autocomplete="username"
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
                        autocomplete="current-password"
                        class="{{ $fieldClasses }}"
                    >
                </div>
                @if ($captchaEnabled)
                    <div>
                        <div class="flex items-center gap-space-xs">
                            <div class="flex h-14 min-w-0 flex-1 items-center justify-center overflow-hidden rounded-2xl border border-outline-variant bg-white">
                                <img
                                    src="{{ route('captcha.image') }}?v={{ now()->getTimestampMs() }}"
                                    :src="captchaUrl"
                                    alt="Letters to type below"
                                    width="260"
                                    height="64"
                                    class="block h-full w-full object-contain"
                                >
                            </div>
                            <button
                                type="button"
                                x-on:click="refreshCaptcha(); $refs.captcha.focus()"
                                aria-label="Show a new image"
                                class="press-scale flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-outline-variant bg-surface-container-lowest text-on-surface-variant transition-colors duration-150 hover:bg-surface-container"
                            >
                                <span class="material-symbols-outlined text-[22px]">refresh</span>
                            </button>
                        </div>
                        <label for="captcha" class="block font-label-md text-label-md text-on-surface-variant mt-space-xs mb-1">Type the letters above</label>
                        <input
                            id="captcha"
                            x-ref="captcha"
                            type="text"
                            name="captcha"
                            required
                            autocomplete="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            maxlength="8"
                            class="{{ $fieldClasses }} uppercase tracking-widest"
                        >
                    </div>
                @endif
                <label class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
                    <input type="checkbox" name="remember" class="rounded border-outline-variant text-primary focus:ring-primary">
                    Keep me signed in
                </label>

                <button
                    id="login-submit"
                    type="submit"
                    x-bind:disabled="busy"
                    class="press-scale w-full flex items-center justify-center gap-space-xs rounded-full bg-primary-container disabled:opacity-70 px-space-lg py-space-sm font-label-lg text-label-lg text-on-primary-container shadow-[0_4px_12px_rgba(199,70,89,0.3)] transition-opacity duration-200 hover:opacity-95"
                >
                    <span class="material-symbols-outlined text-[20px]" data-login-icon>login</span>
                    <span data-login-label>{{ $locked ? 'SYSTEM LOCKED' : 'Sign in' }}</span>
                    <span data-login-timer class="tabular-nums"></span>
                </button>
                </fieldset>
            </form>

            @if ($quickLoginEnabled)
                <form id="quick-login-form" method="POST" action="{{ route('admin.quick-login') }}" class="hidden">
                    @csrf
                </form>
            @endif
        </div>

        <p class="text-center mt-space-md">
            <a id="login-back" href="{{ route('home') }}" @if ($locked) aria-disabled="true" tabindex="-1" @endif class="group inline-flex items-center gap-2 rounded-full border border-[#fbeee4]/20 bg-[#fbeee4]/5 px-5 py-2.5 font-label-lg text-label-lg text-[#fbeee4]/85 transition-all duration-200 hover:border-[#f7a3b6] hover:bg-[#f7a3b6] hover:text-[#40000e] active:translate-y-px">
                <span class="material-symbols-outlined text-[20px] transition-transform duration-200 group-hover:-translate-x-1">arrow_back</span>
                Back to Brread!
            </a>
        </p>
    </main>

    @include('partials.lockdown', ['name' => $lockName, 'open' => $locked, 'seconds' => $lockSeconds ?? 0, 'preview' => $preview ?? false, 'trace' => $trace ?? null])
    @if ($preview ?? false)
        <p id="lockdown-preview-hint" class="fixed bottom-4 left-4 z-[300] rounded-full bg-black/70 px-4 py-2 font-label-md text-label-md text-white/80">Preview: click anywhere</p>
    @endif
</body>
</html>
