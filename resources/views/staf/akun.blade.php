@extends('tata-letak.utama')
@section('title', 'Akun Staf — RuangServis')
@section('content')
<section class="section page-section">
    <div class="container">
        <div class="staff-heading">
            <div>
                <span class="eyebrow">MANAJEMEN AKUN</span>
                <h1 class="page-title">Akun admin & teknisi.</h1>
                <p class="page-lead">Buat akun staf dan atur peran untuk mengakses panel operasional.</p>
            </div>
            <span class="role-tag">ADMIN</span>
        </div>
        <form class="panel form-panel" method="POST" action="{{ route('staff.accounts.store') }}">
            @csrf
            @include('bagian.kesalahan')
            <label>Nama lengkap<input name="name" value="{{ old('name') }}" required maxlength="120" autocomplete="name"></label>
            <label>Email<input name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"></label>
            <label>Nomor WhatsApp<input name="phone" value="{{ old('phone') }}" required minlength="8" maxlength="25" inputmode="tel" autocomplete="tel"></label>
            <label>Peran
                <select name="role" required>
                    <option value="technician" @selected(old('role') === 'technician')>Teknisi</option>
                    <option value="admin" @selected(old('role') === 'admin')>Admin</option>
                </select>
            </label>
            <label>Kata sandi<input name="password" type="password" required autocomplete="new-password"></label>
            <label>Ulangi kata sandi<input name="password_confirmation" type="password" required autocomplete="new-password"></label>
            <button class="button button-full" type="submit">Buat akun staf <span>→</span></button>
        </form>

        <div class="panel table-panel">
            <div class="table-heading"><div><span class="eyebrow">DAFTAR AKUN</span><h2>Akun admin dan teknisi</h2></div></div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Nama</th><th>Email</th><th>Nomor WhatsApp</th><th>Peran</th><th>Aksi</th></tr></thead>
                    <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td><strong>{{ $account->name }}</strong></td>
                            <td>{{ $account->email }}</td>
                            <td>{{ $account->phone }}</td>
                            <td><span class="status-badge">{{ $account->role === 'admin' ? 'Admin' : 'Teknisi' }}</span></td>
                            <td class="action-cell">
                                <a class="button button-small button-outline" href="{{ route('staff.accounts.edit', $account) }}">Ubah</a>
                                @if (auth()->id() !== $account->id && ! ($account->role === 'admin' && $adminCount <= 1))
                                    <form method="POST" action="{{ route('staff.accounts.destroy', $account) }}" onsubmit="return confirm('Hapus akun staf ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="button button-small button-danger" type="submit">Hapus</button>
                                    </form>
                                @elseif ($account->role === 'admin' && $adminCount <= 1)
                                    <span class="action-hint">Admin terakhir tidak dapat dihapus.</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Belum ada akun staf.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $accounts->links() }}</div>
        </div>
    </div>
</section>
@endsection
