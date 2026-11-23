@extends('tata-letak.utama')

@section('title', 'Buat Reservasi — RuangServis')

@section('content')
<section class="section page-section reservation-page">
    <div class="container booking-grid">
        <div class="section-intro">
            <a class="back-link" href="{{ route('home') }}">← Kembali ke beranda</a>
            <span class="eyebrow">MULAI DARI SINI</span>
            <h1 class="page-title">Jadwalkan servis,<br><span>sesuai cara Anda.</span></h1>
            <p>Isi detail laptop dan complaintnya. Kami akan siapkan nomor antrean—akun tidak diperlukan.</p>
            <div class="steps-mini">
                <div><span>1</span><p><strong>Isi formulir</strong><br>Siapkan nomor WhatsApp aktif.</p></div>
                <div><span>2</span><p><strong>Dapat nomor antrean</strong><br>Simpan kode reservasi Anda.</p></div>
                <div><span>3</span><p><strong>Setujui estimasi</strong><br>Perbaikan dimulai setelah izin Anda.</p></div>
            </div>
        </div>
        <form class="panel booking-form" method="POST" action="{{ route('reservations.store') }}">
            @csrf
            <div class="form-heading">
                <div><span class="eyebrow">RESERVASI ONLINE</span><h2>Data laptop Anda</h2></div>
                <span class="form-step">01 / 02</span>
            </div>
            @include('bagian.kesalahan')
            <div class="field-row">
                <label>Nama lengkap<input name="customer_name" value="{{ old('customer_name', auth()->user()?->name) }}" autocomplete="name" required maxlength="120" placeholder="Nama Anda"></label>
                <label>Nomor WhatsApp<input name="phone" value="{{ old('phone', auth()->user()?->phone) }}" inputmode="tel" autocomplete="tel" required minlength="8" maxlength="25" placeholder="08xxxxxxxxxx"></label>
            </div>
            <div class="field-row">
                <label>Merk laptop<input name="brand" value="{{ old('brand') }}" required maxlength="80" placeholder="Contoh: ASUS"></label>
                <label>Model laptop<input name="model" value="{{ old('model') }}" required maxlength="120" placeholder="Contoh: VivoBook 14"></label>
            </div>
            <label>Nomor seri <span class="label-optional">OPSIONAL JIKA BELUM TAHU</span><input name="serial_number" value="{{ old('serial_number') }}" maxlength="120" placeholder="Bisa diverifikasi saat unit diterima"></label>
            <label>Keluhan laptop<textarea name="complaint" rows="3" required maxlength="3000" placeholder="Ceritakan kendala yang dialami...">{{ old('complaint') }}</textarea></label>
            <button class="button button-full" type="submit">Dapatkan nomor antrean <span>→</span></button>
            <p class="form-footnote">Dengan melanjutkan, Anda menyetujui data digunakan untuk proses servis ini.</p>
        </form>
    </div>
</section>
@endsection
