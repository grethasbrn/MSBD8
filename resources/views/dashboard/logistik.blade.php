
@extends('layouts.app')

@section('title', 'Logistik Acara')

@section('content')
<div class="wrap">
    <h1>Logistik Acara</h1>
    <p>Daftar acara yang menjadi tanggung jawabmu.</p>

    @if(session('success'))
        <div class="alert-success">
            {{ session('success') }}
        </div>
    @endif

    @forelse($orders as $order)
        <div class="order-card">
            <h3>{{ $order->code }}</h3>

            <p>
                Paket:
                {{ $order->package->name ?? '-' }}
            </p>

            <p>
                Tanggal:
                {{ $order->event_date?->format('d-m-Y') ?? '-' }}
            </p>

            <p>
                Lokasi:
                {{ $order->event_location ?? '-' }}
            </p>

            <a href="{{ route(auth()->user()->role . '.logistik.show', $order->id) }}">
                Kelola Perlengkapan
            </a>
        </div>
    @empty
        <p>Belum ada acara yang perlu ditangani.</p>
    @endforelse
</div>

<style>
    .wrap {
        max-width: 1000px;
        margin: 35px auto;
        padding: 0 20px;
        color: #352633;
    }

    .order-card {
        background: white;
        border: 1px solid #eadde5;
        border-radius: 12px;
        padding: 20px;
        margin-top: 18px;
    }

    .order-card a {
        display: inline-block;
        margin-top: 10px;
        padding: 10px 16px;
        background: #682b4b;
        color: white;
        text-decoration: none;
        border-radius: 8px;
    }

    .alert-success {
        padding: 12px;
        background: #e5f5e9;
        border-radius: 8px;
    }
</style>
@endsection
