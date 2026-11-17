<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07050a">
    <meta name="robots" content="noindex">
    <title>Sistem terkunci | Brread!</title>
    @include('partials.favicon')
    @include('partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="staff bg-black antialiased font-plus-jakarta min-h-screen">
    @include('partials.lockdown', ['open' => true, 'seconds' => $seconds])
</body>
</html>
