<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#BC0001">
    <meta name="description" content="Ray-Ban event moments">
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Ray-Ban Moments">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>@yield('title', 'Ray-Ban Moments') · Ray-Ban</title>
    @stack('head')
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
</head>
<body class="rayban-page @yield('body-class')">
    <a class="skip-link" href="#main">Skip to content</a>
    <header class="site-header">
    @if(auth()->check() && request()->routeIs('upload', 'gallery'))
        <div class="staff-toolbar">
            <form action="{{ route('logout') }}" method="post">
                @csrf
                <button class="signout-button" type="submit" aria-label="Sign out" title="Sign out">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                </button>
            </form>
        </div>
    @endif
        <a class="brand" href="{{ route('upload') }}" aria-label="Ray-Ban, upload home">
        <img src="{{ asset('media/partner-logo.png') }}?v={{ filemtime(public_path('media/partner-logo.png')) }}" alt="Ray-Ban" width="2048" height="1180">
        </a>
        @hasSection('navigation')
            <nav class="site-nav" aria-label="Main navigation">@yield('navigation')
            </nav>
        @endif
    </header>
    <main id="main" class="page-shell">@yield('content')</main>
    @stack('scripts')
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch((error) => {
                    console.error('Service worker registration failed:', error);
                });
            });
        }
    </script>
</body>
</html>
