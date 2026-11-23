<!doctype html>
<html lang="id" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'RuangServis — Servis Laptop')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>document.documentElement.dataset.theme = localStorage.getItem('ruangservis-theme') === 'dark' ? 'dark' : 'light';</script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="{{ route('home') }}"><img class="brand-logo" src="{{ asset('assets/ruangservis-logo.png') }}" alt=""><span>ruang<span class="brand-light">servis</span></span></a>
        <nav class="nav-links">
            @if (! auth()->check() || ! auth()->user()->isStaff())
                <a href="{{ route('reservations.create') }}">Reservasi</a>
                <a href="{{ route('tracking.lookup') }}">Lacak</a>
            @endif
            @auth
                @if (auth()->user()->isStaff())
                    <a href="{{ route('staff.queue') }}">Panel</a>
                    @if (auth()->user()->isAdmin())
                        <a href="{{ route('staff.finance') }}">Keuangan</a>
                        <a href="{{ route('staff.accounts') }}">Akun</a>
                    @endif
                @else
                    <a href="{{ route('account.history') }}">Riwayat</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="inline-form">@csrf<button class="nav-button" type="submit">Keluar</button></form>
            @else
                <a href="{{ route('login') }}">Masuk</a>
                <a class="button button-small" href="{{ route('register') }}">Daftar</a>
            @endauth
            <button class="theme-toggle" type="button" data-theme-toggle aria-label="Beralih ke mode gelap" aria-pressed="false">
                <span class="theme-toggle-icon" aria-hidden="true">☀</span>
            </button>
        </nav>
    </div>
</header>
<main>
    @if (session('success'))
        <div class="container flash flash-success">{{ session('success') }}</div>
    @endif
    @yield('content')
</main>
<footer class="site-footer">
    <div class="container footer-inner"><a class="brand brand-footer" href="{{ route('home') }}"><img class="brand-logo" src="{{ asset('assets/ruangservis-logo.png') }}" alt=""><span>ruang<span class="brand-light">servis</span></span></a><span>Perawatan laptop yang transparan, dari antrean hingga selesai.</span><span>© {{ now()->year }} RuangServis</span></div>
</footer>
<script src="{{ asset('js/app.js') }}" defer></script>
</body>
</html>
