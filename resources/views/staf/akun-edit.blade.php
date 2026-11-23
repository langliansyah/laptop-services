@extends('tata-letak.utama')
@section('title', 'Ubah Akun Staf — RuangServis')
@section('content')
<section class="section page-section">
    <div class="container">
        <div class="staff-heading">
            <div>
                <span class="eyebrow">MANAJEMEN AKUN</span>
                <h1 class="page-title">Ubah akun staf.</h1>
                <p class="page-lead">Perbarui data dan peran akun. Kosongkan kata sandi jika tidak ingin menggantinya.</p>
            </div>
            <span class="role-tag">ADMIN</span>
        </div>
        <form class="panel form-panel" method="POST" action="{{ route('staff.accounts.update', $account) }}">
            @csrf
            @method('PUT')
            @include('bagian.kesalahan')
            <label>Nama lengkap<input name="name" value="{{ old('name', $account->name) }}" required maxlength="120" autocomplete="name"></label>
            <label>Email<input name="email" type="email" value="{{ old('email', $account->email) }}" required maxlength="255" autocomplete="email"></label>
            <label>Nomor WhatsApp<input name="phone" value="{{ old('phone', $account->phone) }}" required minlength="8" maxlength="25" inputmode="tel" autocomplete="tel"></label>
            <label>Peran
                <select name="role" required>
                    <option value="technician" @selected(old('role', $account->role) === 'technician')>Teknisi</option>
                    <option value="admin" @selected(old('role', $account->role) === 'admin')>Admin</option>
                </select>
            </label>
            <label>Kata sandi baru<input name="password" type="password" autocomplete="new-password"></label>
            <label>Ulangi kata sandi baru<input name="password_confirmation" type="password" autocomplete="new-password"></label>
            <button class="button button-full" type="submit">Simpan perubahan <span>→</span></button>
            <p class="form-footnote"><a href="{{ route('staff.accounts') }}">Kembali ke daftar akun</a></p>
        </form>
    </div>
</section>
@endsection
