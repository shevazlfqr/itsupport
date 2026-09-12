<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>@yield('title', 'Helpdesk IT Support')</title>
    <script>document.documentElement.dataset.theme=localStorage.getItem('helpdesk-theme')||'light'</script>
    <link rel="stylesheet" href="{{ asset('assets/styles.css') }}">
    @stack('head')
</head>
<body>
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
    <script src="{{ asset('assets/app.js') }}"></script>
    @stack('scripts')
</body>
</html>
