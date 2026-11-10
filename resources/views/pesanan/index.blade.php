@extends('layouts.app')

@section('title', 'Pesanan Saya - Sanggar Tri Arga')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pesan.css') }}">
@endpush

@section('content')
    <section class="form-section">
        <div class="form-container" style="width: min(980px, 100%);">
            <h2>Pesanan Saya</h2>
            <p>Pantau status pesanan, pembayaran, dan sisa tagihan Anda.</p>

            @if (session('status'))
                <div class="pesan-alert sukses">{{ session('status') }}</div>
            @endif

            <div class="scroll-x">
                <table class="tabel-pesanan">
                    <thead>
                        <tr><th>ID Transaksi</th><th>Tanggal Acara</th><th>Paket</th><th>Total</th><th>Sisa Tagihan</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($pesanan as $p)
                            <tr>
                                <td><a href="{{ route('pesanan.show', ['order' => $p->code]) }}">{{ $p->code }}</a></td>
                                <td>{{ \App\Support\Label::tanggal($p->event_date) }}</td>
                                <td>{{ $p->package_name }}</td>
                                <td>{{ \App\Support\Label::rupiah($p->total_amount) }}</td>
                                <td>{{ $p->status === 'cancelled' ? '-' : \App\Support\Label::rupiah($p->sisa_tagihan) }}</td>
                                <td>
                                    <span class="badge-status status-{{ $p->status }}">{{ \App\Support\Label::statusPesanan($p->status) }}</span>
                                    @if ($p->lunas_at)<span class="badge-status status-approved">Lunas</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align: center; padding: 24px;">Belum ada pesanan. <a href="{{ route('pesan') }}">Buat pesanan pertama Anda</a>.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <a href="{{ route('pesan') }}" class="btn-lanjut">Buat Pesanan Baru</a>
            <a href="{{ route('beranda') }}" class="back-link">← Kembali ke Beranda</a>
        </div>
    </section>
@endsection
