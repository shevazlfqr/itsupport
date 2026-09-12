<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>Helpdesk IT Support</title>
    <script>document.documentElement.dataset.theme = localStorage.getItem('helpdesk-theme') || 'light'</script>
    <link rel="stylesheet" href="{{ asset('assets/styles.css') }}">
</head>
<body class="login-page">
    <a class="skip-link" href="#main-content">Lewati ke konten utama</a>
    <main id="main-content" class="login-shell">
        <section class="login-visual">
            <div class="brand brand-light"><span class="brand-mark">H</span><span>Helpdesk</span></div>
            <div class="hero-copy">
                <span class="eyebrow">INTERNAL IT SUPPORT</span>
                <h1>Bantuan IT,<br><em>tanpa ribet.</em></h1>
                <p>Laporkan kendala, pantau progres, dan kembali bekerja dengan tenang.</p>
            </div>
            <div class="visual-stats">
                <div><strong>01</strong><span>Buat laporan</span></div>
                <div><strong>02</strong><span>Tim IT menangani</span></div>
                <div><strong>03</strong><span>Pantau sampai selesai</span></div>
            </div>
        </section>
        <section class="login-panel">
            <form method="post" action="{{ route('login.store') }}" class="login-form">
                @csrf
                <div class="mobile-brand brand"><span class="brand-mark">H</span><span>Helpdesk</span></div>
                <span class="eyebrow dark">PORTAL KARYAWAN</span>
                <h2>Masuk ke akunmu</h2>
                <p class="muted">Gunakan akun kantor untuk mengakses layanan IT.</p>
                @if (session('error'))
                    <div class="alert error" role="alert">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="alert error" role="alert">{{ $errors->first() }}</div>
                @endif
                <label>Email kantor<input type="email" name="email" value="{{ old('email') }}" placeholder="nama@kantor.local" required autofocus></label>
                <label>Password<input type="password" name="password" placeholder="Masukkan password" required></label>
                <button class="btn primary full" type="submit">Masuk ke Helpdesk</button>
            </form>
            <p class="login-footer">Butuh akses? Hubungi administrator IT kantor.</p>
        </section>
    </main>
    <script src="{{ asset('assets/app.js') }}"></script>
</body>
</html>
