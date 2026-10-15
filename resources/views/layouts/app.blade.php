<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'RuangServis — Laptop Care')</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<header class="site-header">
    <div class="container nav-wrap">
        <a class="brand" href="{{ route('home') }}"><span class="brand-icon">R</span><span>ruang<span class="brand-light">servis</span><small> LAPTOP CARE, MADE SIMPLE</small></span></a>
        <nav class="nav-links">
            <a href="{{ route('home') }}#reservasi">Buat reservasi</a>
            <a href="{{ route('tracking.lookup') }}">Lacak servis</a>
            @auth
                @if (auth()->user()->isStaff())
                    <a href="{{ route('staff.index') }}">Panel staf</a>
                @else
                    <a href="{{ route('account.history') }}">Riwayat saya</a>
                @endif
                <form method="POST" action="{{ route('logout') }}" class="inline-form">@csrf<button class="nav-button" type="submit">Keluar</button></form>
            @else
                <a href="{{ route('login') }}">Masuk</a>
                <a class="button button-small" href="{{ route('register') }}">Daftar member</a>
            @endauth
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
    <div class="container footer-inner"><a class="brand brand-footer" href="{{ route('home') }}"><span class="brand-icon">R</span><span>ruang<span class="brand-light">servis</span></span></a><span>Perawatan laptop yang transparan, dari antrean hingga selesai.</span><span>© {{ now()->year }} RuangServis</span></div>
</footer>
</body>
</html>
