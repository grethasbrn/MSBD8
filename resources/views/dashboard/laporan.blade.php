@extends('layouts.app')

@section('title', 'Laporan Keuangan')

@section('content')
<div class="wrap">
    <h1>Laporan Keuangan</h1>
    <p>Ringkasan aktivitas pesanan dan keuangan Tri Arga Dance Company.</p>

    <div class="report-grid">
        <div class="report-card">
            <h3>Total Pesanan</h3>
            <h2>{{ $totalPesanan }}</h2>
        </div>

        <div class="report-card">
            <h3>Total Pemasukan</h3>
            <h2>Rp {{ number_format($totalPemasukan, 0, ',', '.') }}</h2>
        </div>

        <div class="report-card">
            <h3>Total Honorarium</h3>
            <h2>Rp {{ number_format($totalHonorarium, 0, ',', '.') }}</h2>
        </div>

        <div class="report-card">
            <h3>Saldo Bersih</h3>
            <h2>Rp {{ number_format($saldoBersih, 0, ',', '.') }}</h2>
        </div>
    </div>

    <a href="{{ route('owner.pesanan.index') }}" class="btn">
        Kembali ke Pesanan
    </a>
</div>

<style>
    .wrap {
        max-width: 1100px;
        margin: 30px auto;
        padding: 20px;
    }

    .report-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin: 25px 0;
    }

    .report-card {
        background: white;
        border: 1px solid #e5e5e5;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    }

    .report-card h3 {
        margin: 0 0 12px;
        color: #666;
        font-size: 16px;
    }

    .report-card h2 {
        margin: 0;
        color: #682b4b;
        font-size: 25px;
    }

    @media (max-width: 600px) {
        .report-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection