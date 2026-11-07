<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $heading }} — Brread!</title>
    @include('partials.favicon')
    @include('partials.fonts')
    @vite(['resources/css/app.css'])
</head>
<body class="bg-surface-container-low text-on-surface antialiased font-plus-jakarta min-h-screen flex items-center justify-center p-margin">
    <main class="w-full max-w-sm text-center">
        <span class="material-symbols-outlined text-[40px] text-on-surface-variant/60">{{ $icon }}</span>
        <h1 class="mt-space-sm font-headline-sm text-headline-sm text-on-surface">{{ $heading }}</h1>
        <p class="mt-1 font-body-md text-body-md text-on-surface-variant">{{ $message }}</p>
        <a href="{{ $actionUrl }}" class="press-scale mt-space-md inline-flex items-center justify-center rounded-full bg-primary-container px-space-lg py-space-xs font-label-lg text-label-lg text-on-primary-container transition-opacity duration-200 hover:opacity-95">
            {{ $actionLabel }}
        </a>
        <p class="mt-space-md font-label-md text-label-md text-on-surface-variant/70">Error {{ $code }}</p>
    </main>
</body>
</html>
