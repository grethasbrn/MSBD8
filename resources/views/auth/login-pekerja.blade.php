@extends('layouts.app')

@section('title', 'Login Pekerja & Kru - Sanggar Tri Arga')
@section('body_class', 'body-pekerja')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/alert.css') }}">
@endpush

@section('content')
    <header class="navbar-kru">
        <div class="nav-container">
            <a href="{{ route('beranda') }}" class="logo">
                <img src="{{ asset('images/logo.png') }}" alt="Logo Sanggar Tri Arga" class="logo-img">
                <span>SANGGAR <strong>TRI ARGA</strong></span>
            </a>
        </div>
    </header>

    <div class="login-wrapper">

        <section class="banner-top">
            <h1>✦ SELAMAT DATANG, TIM TRI ARGA ✦</h1>
        </section>

        <section class="login-box-bottom">
            <div class="form-header-title">
                <h3>Silakan Pilih Peran Anda untuk Mengakses Dashboard!</h3>
            </div>

            @if ($errors->any())
                <div class="auth-alert">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.pekerja.proses') }}">
                @csrf
                <div class="role-grid">

                    <label class="role-option" style="grid-column: span 2;">
                        <input type="radio" name="role" value="owner" @checked(old('role', 'owner') === 'owner')>
                        <div class="role-card">
                            <strong>OWNER</strong>
                            <small>Pemilik & Penanggung Jawab Keuangan</small>
                        </div>
                    </label>

                    <label class="role-option">
                        <input type="radio" name="role" value="korlap" @checked(old('role', 'owner') === 'korlap')>
                        <div class="role-card">
                            <strong>KORLAP</strong>
                            <small>Koordinator Lapangan & Tim</small>
                        </div>
                    </label>

                    <label class="role-option">
                        <input type="radio" name="role" value="helper" @checked(old('role', 'owner') === 'helper')>
                        <div class="role-card">
                            <strong>HELPER</strong>
                            <small>Logistik, Kostum & Alat Musik</small>
                        </div>
                    </label>

                    <label class="role-option">
                        <input type="radio" name="role" value="penari" @checked(old('role', 'owner') === 'penari')>
                        <div class="role-card">
                            <strong>PENARI</strong>
                            <small>Tim Pertunjukan Tari</small>
                        </div>
                    </label>

                    <label class="role-option">
                        <input type="radio" name="role" value="pemusik" @checked(old('role', 'owner') === 'pemusik')>
                        <div class="role-card">
                            <strong>PEMUSIK</strong>
                            <small>Tim Pertunjukan Musik & Instrumen</small>
                        </div>
                    </label>
                </div>

                <div class="inputs-group">
                    <div class="form-group">
                        <label for="worker_id">EMAIL / NOMOR WHATSAPP</label>
                        <input type="text" id="worker_id" name="login" value="{{ old('login') }}" class="form-control" placeholder="Contoh: owner@sanggar.test" required>
                    </div>

                    <div class="form-group">
                        <label for="password">PASSWORD</label>
                        <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit-login">
                    MASUK DASHBOARD PEKERJA <span>→</span>
                </button>
            </form>
        </section>

    </div>
@endsection
