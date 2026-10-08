<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#1046AE">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('icons/icon-192.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.svg') }}">
    <title>@yield('title', 'Helpdesk IT Support')</title>
    <script>document.documentElement.dataset.theme=localStorage.getItem('helpdesk-theme')||'light'</script>
    @php($assetVersion = '20261008.14')
    <link rel="stylesheet" href="{{ asset('assets/styles.css') }}?v={{ $assetVersion }}">
    <link rel="stylesheet" href="{{ asset('assets/pwa.css') }}?v={{ $assetVersion }}">
    @stack('head')
</head>
<body data-updates-endpoint="{{ route('updates') }}">
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>
    <div class="app-shell">
        @include('dashboard.partials.sidebar')
        <main id="main-content" class="content">
            @include('dashboard.partials.topbar')
            <div class="page">
                @include('dashboard.partials.flash')
                @yield('content')
            </div>
        </main>
    </div>
    <aside class="pwa-status" data-pwa-status hidden aria-live="polite">
        <div class="pwa-status-copy">
            <strong data-pwa-title>IT Support</strong>
            <span data-pwa-message></span>
        </div>
        <div class="pwa-status-actions">
            <button type="button" data-pwa-action hidden></button>
            <button type="button" data-pwa-dismiss aria-label="Tutup pemberitahuan">&times;</button>
        </div>
    </aside>
    <script src="{{ asset('assets/app.js') }}?v={{ $assetVersion }}"></script>
    <script src="{{ asset('assets/pwa.js') }}?v={{ $assetVersion }}"></script>
    @stack('scripts')
</body>
</html>
