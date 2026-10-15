@extends('layouts.app')
@section('title', 'Nota '.$service->service_number.' — RuangServis')
@section('content')
<section class="section page-section">
    <div class="container narrow-container">
        <div class="receipt-toolbar">
            <a class="back-link" href="{{ route('tracking.show', $service) }}">← Kembali ke status</a>
            <button class="button button-small" type="button" onclick="window.print()">Cetak nota</button>
        </div>

        <article class="panel receipt">
            <div class="receipt-brand">
                <span class="brand-icon">R</span>
                <div>
                    <h1>ruangservis</h1>
                    <p>LAPTOP CARE, MADE SIMPLE</p>
                </div>
            </div>

            <div class="receipt-title">
                <span>{{ $service->status === \App\Enums\ServiceStatus::Completed ? 'NOTA PEMBAYARAN' : 'BUKTI TANDA TERIMA' }}</span>
                <h2>{{ $service->service_number ?: $service->queue_number }}</h2>
                <p>{{ $service->created_at->format('d F Y') }}</p>
            </div>

            <div class="receipt-line">
                <span>Nama pelanggan</span>
                <strong>{{ $service->customer_name }}</strong>
            </div>
            <div class="receipt-line">
                <span>Perangkat</span>
                <strong>{{ $service->brand }} {{ $service->model }}</strong>
            </div>
            <div class="receipt-line">
                <span>Nomor seri</span>
                <strong>{{ $service->serial_number ?: '—' }}</strong>
            </div>
            <div class="receipt-line">
                <span>Keluhan</span>
                <strong>{{ $service->complaint }}</strong>
            </div>

            @if ($service->diagnosis)
                <div class="receipt-line">
                    <span>Diagnosis</span>
                    <strong>{{ $service->diagnosis }}</strong>
                </div>
                @if ($service->diagnostic_image_path)
                    <img
                        class="receipt-diagnosis-image"
                        src="{{ route('tracking.diagnosis-image', $service) }}"
                        alt="Foto kerusakan {{ $service->brand }} {{ $service->model }}"
                    >
                @endif
                <div class="receipt-line receipt-total">
                    <span>Total biaya</span>
                    <strong>Rp {{ number_format((float) $service->estimated_cost, 0, ',', '.') }}</strong>
                </div>
            @endif

            @if ($service->payment_method)
                <div class="receipt-line">
                    <span>Metode pembayaran</span>
                    <strong>{{ $service->payment_method }}</strong>
                </div>
                <div class="paid-stamp">LUNAS</div>
            @endif

            <div class="receipt-note">
                Simpan nota ini sebagai bukti penerimaan dan pengambilan unit. Terima kasih telah mempercayakan perangkat Anda kepada kami.
            </div>
        </article>
    </div>
</section>
@endsection
