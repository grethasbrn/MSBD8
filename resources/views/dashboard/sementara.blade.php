@extends('layouts.app')

@section('title', 'Dashboard ' . ucfirst($peran) . ' - Sanggar Tri Arga')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
@endpush

@section('content')
    <section class="auth-section">
        <div class="auth-card">
            <div class="auth-header">
                <img src="{{ asset('images/logo.png') }}" alt="Logo Sanggar Tri Arga" class="auth-logo">
                <h2>Dashboard {{ strtoupper($peran) }}</h2>
                <p>Halo, {{ auth()->user()->name }}!</p>
            </div>

            <p style="text-align: center; font-size: 14px; margin-bottom: 24px;">
                Anda berhasil masuk. Halaman dashboard ini sedang dikonversi ke Blade dan akan tersedia pada langkah berikutnya.
            </p>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-auth">Keluar</button>
            </form>

            <a href="{{ route('beranda') }}" class="back-home">← Kembali ke Beranda</a>
        </div>
    </section>
@endsection
