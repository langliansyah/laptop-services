@extends('tata-letak.utama')
@section('title', 'Keuangan — RuangServis')
@section('content')
<section class="section page-section">
    <div class="container">
        <div class="staff-heading">
            <div>
                <span class="eyebrow">PANEL KEUANGAN</span>
                <h1 class="page-title">Halo, {{ auth()->user()->name }}.</h1>
                <p class="page-lead">Pantau pendapatan dan riwayat pembayaran servis.</p>
            </div>
            <span class="role-tag">ADMIN</span>
        </div>
        <div class="report-grid">
            <div class="panel report-card"><span>Servis selesai hari ini</span><strong>{{ $reports['today_completed'] }}</strong></div>
            <div class="panel report-card"><span>Pendapatan hari ini</span><strong>Rp {{ number_format((float) $reports['today_revenue'], 0, ',', '.') }}</strong></div>
            <div class="panel report-card"><span>Servis selesai bulan ini</span><strong>{{ $reports['month_completed'] }}</strong></div>
            <div class="panel report-card"><span>Pendapatan bulan ini</span><strong>Rp {{ number_format((float) $reports['month_revenue'], 0, ',', '.') }}</strong></div>
        </div>
        <div class="finance-summary-grid">
            <div class="panel report-card report-export-card">
                <span>Ekspor ringkasan harian</span>
                <form method="GET" action="{{ route('staff.reports.daily') }}">
                    <label for="report-date">Pilih tanggal</label>
                    <input id="report-date" type="date" name="date" value="{{ now()->toDateString() }}" required>
                    <button class="button button-small" type="submit">Unduh CSV</button>
                </form>
            </div>
            <div class="panel report-card report-export-card">
                <span>Ekspor ringkasan bulanan</span>
                <form method="GET" action="{{ route('staff.reports.monthly') }}">
                    <label for="report-month">Pilih bulan</label>
                    <input id="report-month" type="month" name="month" value="{{ now()->format('Y-m') }}" required>
                    <button class="button button-small" type="submit">Unduh CSV</button>
                </form>
            </div>
            <div class="panel finance-total-card">
                <span>Total pendapatan</span>
                <strong>Rp {{ number_format((float) $reports['total_revenue'], 0, ',', '.') }}</strong>
            </div>
        </div>

        <div class="panel table-panel staff-table-panel">
            <div class="table-heading">
                <div><span class="eyebrow">RIWAYAT PEMBAYARAN</span><h2>Transaksi keuangan</h2></div>
            </div>
            <div class="table-scroll">
                <table>
                    <thead><tr><th>Tanggal</th><th>Pelanggan</th><th>Kode servis</th><th>Metode</th><th>Nominal</th></tr></thead>
                    <tbody>
                    @forelse ($payments as $payment)
                        <tr>
                            <td>{{ $payment->paid_at->format('d M Y, H:i') }}</td>
                            <td><strong>{{ $payment->reservation->customer_name ?? '-' }}</strong></td>
                            <td>{{ $payment->reservation->service_number ?? $payment->reservation->queue_number ?? '-' }}</td>
                            <td><span class="status-badge">{{ $payment->method }}</span></td>
                            <td><strong>Rp {{ number_format((float) $payment->amount, 0, ',', '.') }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="empty-state">Belum ada pembayaran yang tercatat.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination-wrap">{{ $payments->links() }}</div>
        </div>
    </div>
</section>
@endsection
