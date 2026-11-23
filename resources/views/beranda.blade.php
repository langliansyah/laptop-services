@extends('tata-letak.utama')

@section('title', 'RuangServis — Servis Laptop Tanpa Ribet')

@section('content')
<section class="hero">
    <div class="container hero-grid">
        <div class="hero-copy">
            <span class="eyebrow"><span class="eyebrow-dot"></span> Servis laptop, dengan proses yang jelas</span>
            <h1>Perbaikan laptop,<br><span>tanpa menebak-nebak.</span></h1>
            <p>Ajukan servis tanpa akun. Kami akan menyampaikan diagnosis dan estimasi sebelum perbaikan dimulai.</p>
            <div class="hero-actions"><a href="{{ route('reservations.create') }}" class="button">Mulai reservasi <span>↗</span></a></div>
            <div class="hero-proof"><span class="hero-proof-mark" aria-hidden="true">✓</span><span><strong>Perbaikan dimulai dengan persetujuan Anda</strong><br>Biaya dan estimasi disampaikan lebih dulu.</span></div>
        </div>
        <div class="hero-visual">
            <div class="service-preview panel">
                <div class="service-preview-heading">
                    <div><span class="eyebrow">Gambaran proses</span><h2>Servis, langkah demi langkah</h2></div>
                    <img class="preview-mark" src="{{ asset('assets/ruangservis-logo.png') }}" alt="">
                </div>
                <ol class="service-preview-steps">
                    <li><span aria-hidden="true">1</span><div><strong>Unit diterima</strong><small>Keluhan dan kondisi laptop dicatat</small></div></li>
                    <li><span aria-hidden="true">2</span><div><strong>Diagnosis & estimasi</strong><small>Teknisi menjelaskan temuan dan biaya</small></div></li>
                    <li><span aria-hidden="true">3</span><div><strong>Persetujuan Anda</strong><small>Perbaikan dimulai setelah disetujui</small></div></li>
                    <li><span aria-hidden="true">4</span><div><strong>Perbaikan & pengambilan</strong><small>Pantau progres sampai unit siap</small></div></li>
                </ol>
                <p class="service-preview-note">Anda dapat melihat status servis menggunakan nomor antrean dan WhatsApp.</p>
            </div>
        </div>
    </div>
</section>
<section class="trust-strip"><div class="container trust-inner"><span>Reservasi tanpa akun</span><span>Estimasi sebelum perbaikan</span><span>Progres servis dapat dipantau</span></div></section>
<section class="section section-testimonials">
    <div class="container">
        <div class="section-center">
            <span class="eyebrow">CERITA PELANGGAN</span>
            <h2>Dipercaya, dirawat,<br><span>dan direkomendasikan.</span></h2>
            @if ($ratingSummary->total > 0)
                <div class="rating-summary" aria-label="Rata-rata {{ number_format((float) $ratingSummary->average_rating, 1, ',', '.') }} dari 5 berdasarkan {{ $ratingSummary->total }} rating pelanggan">
                    <span class="rating-summary-stars" aria-hidden="true">★★★★★</span>
                    <strong>{{ number_format((float) $ratingSummary->average_rating, 1, ',', '.') }}</strong>
                    <span>{{ $ratingSummary->total }} rating pelanggan</span>
                </div>
            @else
                <p class="testimonial-empty">Jadilah pelanggan pertama yang membagikan pengalaman servis Anda.</p>
            @endif
        </div>

        @if ($testimonials->isNotEmpty())
            <div class="testimonial-grid">
                @foreach ($testimonials as $testimonial)
                    <article class="panel testimonial-card">
                        <div class="testimonial-stars" aria-label="Rating {{ $testimonial->rating }} dari 5">
                            {{ str_repeat('★', $testimonial->rating) }}<span>{{ str_repeat('☆', 5 - $testimonial->rating) }}</span>
                        </div>
                        <p class="testimonial-comment">{{ $testimonial->comment }}</p>
                        <div class="testimonial-author">
                            <span class="testimonial-avatar" aria-hidden="true">{{ strtoupper(substr($testimonial->service->customer_name, 0, 1)) }}</span>
                            <span><strong>{{ explode(' ', trim($testimonial->service->customer_name))[0] }}</strong><small>Pelanggan RuangServis</small></span>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </div>
</section>
<section class="section section-track"><div class="container track-banner"><div><span class="eyebrow">SUDAH PUNYA NOMOR?</span><h2>Perkembangan servis<br><span>ada dalam genggaman.</span></h2><p>Gunakan kode antrean atau service ID dan nomor WhatsApp Anda.</p></div><a class="button button-light" href="{{ route('tracking.lookup') }}">Lacak servis saya <span>→</span></a><div class="track-decoration">STATUS<br><strong>LIVE</strong></div></div></section>
<section class="section section-how"><div class="container"><div class="section-center"><span class="eyebrow">LANGKAH YANG JELAS</span><h2>Anda selalu tahu<br><span>apa yang terjadi.</span></h2></div><div class="how-grid"><article><span class="how-number">01</span><span class="how-icon">⌕</span><h3>Diagnosis transparan</h3><p>Teknisi mencatat masalah dan estimasi biaya untuk Anda tinjau.</p></article><article><span class="how-number">02</span><span class="how-icon">✓</span><h3>Anda yang menyetujui</h3><p>Tidak ada perbaikan sebelum Anda menyetujui estimasi.</p></article><article><span class="how-number">03</span><span class="how-icon">↗</span><h3>Progres bisa dilacak</h3><p>Pantau servis hingga siap diambil dengan kode dan WhatsApp.</p></article></div></div></section>
@endsection
