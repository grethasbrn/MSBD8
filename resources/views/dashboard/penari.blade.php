
@extends('layouts.app')

@section('title', 'Dashboard Penari')

@push('styles')
<style>
    .penari-wrap {
        max-width: 1100px;
        margin: 35px auto;
        padding: 0 20px;
        color: #352633;
    }

    .penari-header {
        background: #682b4b;
        color: white;
        padding: 28px;
        border-radius: 14px;
        margin-bottom: 25px;
    }

    .penari-header h1 {
        margin: 0 0 8px;
    }

    .penari-card {
        background: white;
        border: 1px solid #eadfe6;
        border-radius: 12px;
        padding: 22px;
        margin-bottom: 16px;
    }

    .penari-card h3 {
        color: #682b4b;
        margin-top: 0;
    }

    .penari-info {
        margin: 8px 0;
        color: #625761;
    }

    .penari-actions {
        display: flex;
        gap: 10px;
        margin-top: 18px;
    }

    .penari-actions button {
        border: 0;
        padding: 10px 20px;
        border-radius: 7px;
        cursor: pointer;
        color: white;
    }

    .btn-terima {
        background: #27845b;
    }

    .btn-tolak {
        background: #b94040;
    }

    .penari-status {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        background: #f2e8ef;
        color: #682b4b;
        font-size: 13px;
    }

    .penari-empty {
        color: #777;
        padding: 15px 0;
    }
</style>
@endpush

@section('content')
<div class="penari-wrap">

    <div class="penari-header">
        <h1>Halo, {{ auth()->user()->name }}!</h1>
        <p>Selamat datang di Dashboard Penari Tri Arga Dance Company.</p>
        <p>Periksa tawaran tugas dan jadwal acara Anda di sini.</p>
    </div>

    @if(session('status'))
        <div class="penari-card">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="penari-card">
            {{ $errors->first() }}
        </div>
    @endif

    <h2>Tawaran Tugas</h2>

    @forelse($tawaran as $item)
        <div class="penari-card">
            <h3>{{ $item->order->code }}</h3>

            <p class="penari-info">
                <strong>Tanggal:</strong>
                {{ $item->order->event_date->format('d-m-Y') }}
            </p>

            <p class="penari-info">
                <strong>Waktu:</strong>
                {{ $item->order->event_time }}
            </p>

            <p class="penari-info">
                <strong>Lokasi:</strong>
                {{ $item->order->event_location }}
            </p>

            <p class="penari-info">
                <strong>Pelanggan:</strong>
                {{ $item->order->customer->name ?? '-' }}
            </p>

            <p class="penari-info">
                <strong>Paket:</strong>
                {{ $item->order->package->name ?? '-' }}
            </p>

            <p class="penari-info">
                <strong>Peran:</strong> Penari
            </p>

            <span class="penari-status">Menunggu respons</span>

            <div class="penari-actions">
                <form method="POST"
                    action="{{ route('penari.tugas.respond', $item->id) }}">
                    @csrf
                    <input type="hidden" name="action" value="accept">
                    <button type="submit" class="btn-terima">
                        Terima
                    </button>
                </form>

                <form method="POST"
                    action="{{ route('penari.tugas.respond', $item->id) }}">
                    @csrf
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn-tolak">
                        Tolak
                    </button>
                </form>
            </div>
        </div>
    @empty
        <div class="penari-card">
            <p class="penari-empty">
                Belum ada tawaran tugas untuk Anda.
            </p>
        </div>
    @endforelse

    <h2 style="margin-top: 35px;">Riwayat Tugas</h2>

    @forelse($tugasSaya as $item)
        <div class="penari-card">
            <h3>{{ $item->order->code }}</h3>

            <p class="penari-info">
                <strong>Tanggal:</strong>
                {{ $item->order->event_date->format('d-m-Y') }}
            </p>

            <p class="penari-info">
                <strong>Lokasi:</strong>
                {{ $item->order->event_location }}
            </p>

            <p class="penari-info">
                <strong>Status:</strong>
                <span class="penari-status">
                    {{ ucfirst($item->status) }}
                </span>
            </p>
        </div>
    @empty
        <div class="penari-card">
            <p class="penari-empty">Belum ada riwayat tugas.</p>
        </div>
    @endforelse

</div>
@endsection
