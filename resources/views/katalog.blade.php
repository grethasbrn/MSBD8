@extends('layouts.app')

@section('title', $judulTab . ' - Sanggar Tri Arga')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/katalog.css') }}">
@endpush

@section('content')
    <section class="catalog-section">
        <div class="catalog-container">
            <div class="catalog-header">
                <p>KATALOG INVENTARIS</p>
                <h1>{{ $judul }}</h1>
                <p class="desc">{{ $deskripsi }}</p>
            </div>

            @forelse ($kelompok as $k)
                @if ($k->judul)
                    <h2 class="category-title">{{ $k->judul }}</h2>
                @endif
                <div class="instrument-grid">
                    @foreach ($k->barang as $item)
                        <div class="instrument-card">
                            <div class="instrument-img-box">
                                @if ($item->gambar)
                                    <img src="{{ asset($item->gambar) }}" alt="{{ $item->nama }}">
                                @endif
                            </div>
                            <div class="instrument-info">
                                <h3>{{ $item->nama }}</h3>
                                <p>{{ $item->deskripsi }}</p>
                                @if ($item->label === null)
                                    <span class="instrument-badge" style="background: rgba(192, 57, 43, .12); color: #c0392b;">Sedang tidak tersedia</span>
                                @else
                                    <span class="instrument-badge">{{ $item->label }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @empty
                <p style="text-align: center;">Belum ada data.</p>
            @endforelse

            <div class="back-nav">
                <a href="{{ route('beranda') }}" class="btn-back">← Kembali ke Beranda</a>
            </div>
        </div>
    </section>
@endsection
