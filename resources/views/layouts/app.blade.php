<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7a3b6">
    <title>@hasSection('title')@yield('title') | @endif{{ 'Brread!' }} Singapore Ice Cream Sandwich</title>
    <meta name="description" content="@yield('description', 'Brread! makes Singapore-style ice cream sandwiches to order: soft bread, a thick slab of ice cream and a topping. Build your own, we deliver cold across Jakarta.')">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Brread!">
    <meta property="og:title" content="@hasSection('title')@yield('title') | @endif{{ 'Brread!' }} Singapore Ice Cream Sandwich">
    <meta property="og:description" content="@yield('description', 'Pick the bread, pick the ice cream, add a topping. Made to order and delivered cold across Jakarta.')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ asset('img/social-preview.jpg') }}">
    <meta property="og:image:width" content="1280">
    <meta property="og:image:height" content="640">
    <meta name="twitter:card" content="summary_large_image">
    {{-- Entry intro plays once per browser session; a short variant plays when Home was reached through the logo or the
         Home link (flag set by app.js a moment before). Both are decided before first paint so nothing flashes. --}}
    <script>try{var d=document.documentElement,s=sessionStorage,q=+s.getItem('brreadHomeNav')||0;s.removeItem('brreadHomeNav');if(matchMedia('(prefers-reduced-motion: reduce)').matches){d.classList.add('no-intro')}else if(!s.getItem('brreadIntro')){s.setItem('brreadIntro','1')}else if(q&&Date.now()-q<6000&&location.pathname==='/'){d.classList.add('quick-intro')}else{d.classList.add('no-intro')}}catch(e){document.documentElement.classList.add('no-intro')}</script>
    <link rel="preload" as="image" href="/img/intro/bread-white.webp">
    <link rel="preload" as="image" href="/img/intro/icecream-strawberry.webp">
    @include('partials.lockdown-guard')
    @include('partials.favicon')
    @include('partials.fonts')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface text-on-surface antialiased font-plus-jakarta">

    <div id="brread-intro" aria-hidden="true">
        <div class="intro-panel doodle">
            <div class="intro-stack">
                <img class="intro-piece intro-top" src="/img/intro/bread-white.webp" alt="" width="420" height="161">
                <div class="intro-piece intro-ice">
                    <img src="/img/intro/icecream-strawberry.webp" alt="" width="420" height="150">
                    <img class="intro-topping" src="/img/intro/topping-sprinkles.webp" alt="" width="420" height="170">
                </div>
                <img class="intro-piece intro-bottom" src="/img/intro/bread-white.webp" alt="" width="420" height="161">
                <span class="intro-sprinkle" style="--x: -70px; --y: -93px; --r: -108deg; --c: #f4a7b9"></span>
                <span class="intro-sprinkle" style="--x: 179px; --y: -69px; --r: 85deg; --c: #9cc9cf"></span>
                <span class="intro-sprinkle" style="--x: -111px; --y: -147px; --r: -126deg; --c: #f3c969"></span>
                <span class="intro-sprinkle" style="--x: -180px; --y: -68px; --r: 121deg; --c: #b7a6e0"></span>
                <span class="intro-sprinkle" style="--x: -42px; --y: 34px; --r: -130deg; --c: #ffffff"></span>
                <span class="intro-sprinkle" style="--x: -77px; --y: -37px; --r: 114deg; --c: #f58fa6"></span>
                <span class="intro-sprinkle" style="--x: -6px; --y: -100px; --r: -72deg; --c: #f4a7b9"></span>
                <span class="intro-sprinkle" style="--x: -136px; --y: -103px; --r: -51deg; --c: #9cc9cf"></span>
                <span class="intro-sprinkle" style="--x: -177px; --y: 42px; --r: -27deg; --c: #f3c969"></span>
                <span class="intro-sprinkle" style="--x: -51px; --y: -121px; --r: -76deg; --c: #b7a6e0"></span>
                <span class="intro-sprinkle" style="--x: -32px; --y: -96px; --r: 30deg; --c: #ffffff"></span>
                <span class="intro-sprinkle" style="--x: -146px; --y: 46px; --r: 150deg; --c: #f58fa6"></span>
                <span class="intro-sprinkle" style="--x: -18px; --y: 1px; --r: 38deg; --c: #f4a7b9"></span>
                <span class="intro-sprinkle" style="--x: 69px; --y: -107px; --r: -69deg; --c: #9cc9cf"></span>
            </div>
            <img class="intro-logo" src="{{ asset('logo.png') }}" alt="">
            <p class="intro-count font-hand">scooping yours<span></span></p>
        </div>
        <div class="intro-melt">
            @include('partials.drip', ['flavor' => 'strawberry', 'seed' => 1, 'class' => 'intro-melt-top', 'drops' => false])
            @include('partials.drip', ['flavor' => 'strawberry', 'seed' => 2, 'class' => 'intro-melt-bottom', 'drops' => false])
        </div>
    </div>

    <a href="#main" class="skip-link">Skip to content</a>

    @include('partials.navbar')

    <main id="main" class="pt-20">
        @yield('content')
    </main>

    @include('partials.footer')

    @include('partials.cart-drawer')

</body>
</html>
