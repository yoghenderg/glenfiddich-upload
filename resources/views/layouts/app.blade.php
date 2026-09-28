<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#033037">
    <meta name="description" content="Aston Martin Formula One Team x Glenfiddich event moments">
    <title>@yield('title', 'Event moments') · Aston Martin F1 x Glenfiddich</title>
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="aston-page @yield('body-class')">
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="site-header">
        <a class="brand" href="{{ route('upload') }}" aria-label="Aston Martin Formula One Team and Glenfiddich, upload home">
            <img src="{{ asset('media/partner-logo-trim.png') }}" alt="Aston Martin Formula One Team and Glenfiddich Global Partner" width="1596" height="663">
        </a>
        @hasSection('navigation')
            <nav class="site-nav" aria-label="Main navigation">@yield('navigation')</nav>
        @endif
    </header>
    <main id="main" class="page-shell">@yield('content')</main>
    @stack('scripts')
</body>
</html>
