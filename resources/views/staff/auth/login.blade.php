<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Login — Brread!</title>
    @include('partials.favicon')
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=swap" rel="stylesheet">
    {{ Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-container-low text-on-surface antialiased font-plus-jakarta min-h-screen flex items-center justify-center p-margin">

    <div class="w-full max-w-sm">
        <div class="flex flex-col items-center mb-space-lg">
            <img src="{{ asset('logo.png') }}" alt="Brread!" class="h-14 w-auto object-contain mb-space-sm">
            <h1 class="font-title-md text-title-md text-on-surface">Staff Portal</h1>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Internal access only</p>
        </div>

        @php $devAutofill = app()->environment('local'); @endphp

        @if ($devAutofill)
            <div class="mb-space-sm flex items-center gap-1.5 px-space-md py-space-xs rounded-full bg-tertiary-fixed text-on-tertiary-fixed-variant font-label-md text-label-md w-fit mx-auto">
                <span class="material-symbols-outlined text-[14px]">bolt</span>
                Dev autofill active — Admin
            </div>
        @endif

        <div class="bg-surface-container-lowest rounded-lg border border-outline-variant/30 shadow-[0_4px_16px_rgba(113,59,44,0.06)] p-space-lg">
            @if ($errors->any())
                <div class="mb-space-md px-space-md py-space-sm rounded-DEFAULT bg-error-container text-on-error-container font-body-sm text-body-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('staff.login.attempt') }}" class="space-y-space-sm">
                @csrf
                <div>
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Email</label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email', $devAutofill ? 'admin@brread.id' : '') }}"
                        required
                        autofocus
                        class="w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                </div>
                <div>
                    <label class="block font-label-md text-label-md text-on-surface-variant mb-1">Password</label>
                    <input
                        type="password"
                        name="password"
                        value="{{ $devAutofill ? 'brread-admin-2026' : '' }}"
                        required
                        class="w-full rounded-full border border-outline-variant bg-surface-container-lowest px-space-md py-space-xs font-body-md text-body-md text-on-surface focus:outline-none focus:ring-2 focus:ring-primary"
                    >
                </div>
                <label class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
                    <input type="checkbox" name="remember" {{ $devAutofill ? 'checked' : '' }} class="rounded border-outline-variant text-primary focus:ring-primary">
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
        </div>

        <p class="text-center font-body-sm text-body-sm text-on-surface-variant mt-space-md">
            <a href="{{ route('home') }}" class="hover:text-primary transition-colors">&larr; Back to Brread!</a>
        </p>
    </div>

</body>
</html>
