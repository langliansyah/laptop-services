@extends('layouts.app')
@section('title', 'Lacak Servis — RuangServis')
@section('content')
<section class="section page-section"><div class="container narrow-container"><span class="eyebrow">PELACAKAN SERVIS</span><h1 class="page-title">Cari status <span>servis Anda.</span></h1><p class="page-lead">Masukkan kode antrean atau service ID beserta nomor WhatsApp yang digunakan saat reservasi.</p>
    <form class="panel form-panel" method="POST" action="{{ route('tracking.lookup.submit') }}">@csrf @include('partials.errors')<label>Kode antrean / service ID<input name="reference" value="{{ old('reference') }}" required maxlength="40" placeholder="ANT-261014-ABCDE atau SRV-2610-ABCDEFG"></label><label>Nomor WhatsApp<input name="phone" value="{{ old('phone') }}" inputmode="tel" required minlength="8" maxlength="25" placeholder="08xxxxxxxxxx"></label><button class="button button-full" type="submit">Lihat status servis <span>→</span></button></form>
    <p class="privacy-note">Pelacakan hanya menampilkan servis yang cocok dengan nomor WhatsApp Anda.</p>
</div></section>
@endsection
