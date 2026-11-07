<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Brread!') — Singapore Ice Cream Sandwich</title>
    @include('partials.favicon')
    @include('partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface text-on-surface antialiased font-plus-jakarta">

    @include('partials.navbar')

    <div class="pt-20">
        @yield('content')
    </div>

    @include('partials.footer')

    @include('partials.cart-drawer')

</body>
</html>
