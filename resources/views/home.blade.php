@extends('layouts.app')

@section('title', 'RuangServis — Servis Laptop Tanpa Ribet')

@section('content')
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow"><span class="eyebrow-dot"></span> SERVIS LAPTOP, LEBIH TENANG</span>
            <h1>Perbaikan jelas.<br><span>Tanpa kejutan.</span></h1>
            <p>Booking tanpa akun, cek estimasi sebelum perbaikan, dan pantau setiap langkah servis laptop Anda.</p>
            <div class="hero-actions"><a href="#reservasi" class="button">Mulai reservasi <span>↗</span></a><a href="{{ route('tracking.lookup') }}" class="text-link">Lacak servis <span>→</span></a></div>
            <div class="hero-proof"><div class="proof-avatars"><span>R</span><span>A</span><span>D</span></div><span><strong>Proses transparan</strong><br>Persetujuan Anda sebelum perbaikan</span></div>
        </div>
        <div class="hero-visual">
            <div class="visual-orbit orbit-one"></div><div class="visual-orbit orbit-two"></div>
            <div class="laptop-illustration"><div class="laptop-screen"><div class="screen-glow"></div><span class="screen-mark">R</span><div class="screen-line"></div><div class="screen-line short"></div><span class="screen-status">●&nbsp; ALL SYSTEMS CHECKED</span></div><div class="laptop-base"></div><div class="laptop-foot"></div></div>
            <div class="floating-card card-check"><span class="float-icon">✓</span><span><strong>Diagnosis selesai</strong><small>Menunggu persetujuan Anda</small></span></div>
            <div class="floating-card card-time"><span class="time-icon">◷</span><span><small>ESTIMASI SERVIS</small><strong>2–3 hari kerja</strong></span></div>
            <div class="visual-caption">TEKNOLOGI DIRAWAT DENGAN BAIK.</div>
        </div>
    </div>
</section>
<section class="trust-strip"><div class="container trust-inner"><span>01 &nbsp; BOOKING TANPA LOGIN</span><span>02 &nbsp; ESTIMASI SEBELUM PERBAIKAN</span><span>03 &nbsp; PROGRES BISA DIPANTAU</span></div></section>
<section class="section section-booking" id="reservasi">
    <div class="container booking-grid">
        <div class="section-intro"><span class="eyebrow">MULAI DARI SINI</span><h2>Jadwalkan servis,<br><span>sesuai cara Anda.</span></h2><p>Isi detail laptop dan keluhannya. Kami akan siapkan nomor antrean—akun tidak diperlukan.</p>
            <div class="steps-mini"><div><span>1</span><p><strong>Isi formulir</strong><br>Siapkan nomor WhatsApp aktif.</p></div><div><span>2</span><p><strong>Dapat nomor antrean</strong><br>Simpan kode reservasi Anda.</p></div><div><span>3</span><p><strong>Setujui estimasi</strong><br>Perbaikan dimulai setelah izin Anda.</p></div></div>
        </div>
        <form class="panel booking-form" method="POST" action="{{ route('reservations.store') }}">
            @csrf
            <div class="form-heading"><div><span class="eyebrow">RESERVASI ONLINE</span><h3>Data laptop Anda</h3></div><span class="form-step">01 / 02</span></div>
            @include('partials.errors')
            <div class="field-row"><label>Nama lengkap<input name="customer_name" value="{{ old('customer_name', auth()->user()?->name) }}" autocomplete="name" required maxlength="120" placeholder="Nama Anda"></label><label>Nomor WhatsApp<input name="phone" value="{{ old('phone', auth()->user()?->phone) }}" inputmode="tel" autocomplete="tel" required minlength="8" maxlength="25" placeholder="08xxxxxxxxxx"></label></div>
            <div class="field-row"><label>Merk laptop<input name="brand" value="{{ old('brand') }}" required maxlength="80" placeholder="Contoh: ASUS"></label><label>Model laptop<input name="model" value="{{ old('model') }}" required maxlength="120" placeholder="Contoh: VivoBook 14"></label></div>
            <label>Nomor seri <span class="label-optional">OPSIONAL JIKA BELUM TAHU</span><input name="serial_number" value="{{ old('serial_number') }}" maxlength="120" placeholder="Bisa diverifikasi saat unit diterima"></label>
            <label>Keluhan laptop<textarea name="complaint" rows="3" required maxlength="3000" placeholder="Ceritakan kendala yang dialami...">{{ old('complaint') }}</textarea></label>
            <button class="button button-full" type="submit">Dapatkan nomor antrean <span>→</span></button>
            <p class="form-footnote">Dengan melanjutkan, Anda menyetujui data digunakan untuk proses servis ini.</p>
        </form>
    </div>
</section>
<section class="section section-track"><div class="container track-banner"><div><span class="eyebrow">SUDAH PUNYA NOMOR?</span><h2>Perkembangan servis<br><span>ada dalam genggaman.</span></h2><p>Gunakan kode antrean atau service ID dan nomor WhatsApp Anda.</p></div><a class="button button-light" href="{{ route('tracking.lookup') }}">Lacak servis saya <span>→</span></a><div class="track-decoration">STATUS<br><strong>LIVE</strong></div></div></section>
<section class="section section-how"><div class="container"><div class="section-center"><span class="eyebrow">LANGKAH YANG JELAS</span><h2>Anda selalu tahu<br><span>apa yang terjadi.</span></h2></div><div class="how-grid"><article><span class="how-number">01</span><span class="how-icon">⌕</span><h3>Diagnosis transparan</h3><p>Teknisi mencatat masalah dan estimasi biaya untuk Anda tinjau.</p></article><article><span class="how-number">02</span><span class="how-icon">✓</span><h3>Anda yang menyetujui</h3><p>Tidak ada perbaikan sebelum Anda menyetujui estimasi.</p></article><article><span class="how-number">03</span><span class="how-icon">↗</span><h3>Progres bisa dilacak</h3><p>Pantau servis hingga siap diambil dengan kode dan WhatsApp.</p></article></div></div></section>
@endsection
