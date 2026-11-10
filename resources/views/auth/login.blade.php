@extends('layouts.app')

@section('title', 'Masuk - Sanggar Tri Arga')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/alert.css') }}">
@endpush

@section('content')
    <section class="auth-section">
        <div class="auth-card">
            <div class="auth-header">
                <img src="{{ asset('images/logo.png') }}" alt="Logo Sanggar Tri Arga" class="auth-logo">
                <h2>Masuk Akun</h2>
                <p>Silakan masuk untuk melanjutkan pemesanan</p>
            </div>

            @if (session('status'))
                <div class="auth-alert sukses">{{ session('status') }}</div>
            @endif
            @error('akun')
                <div class="auth-alert">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('login.proses') }}">
                @csrf
                <div class="form-group">
                    <label for="loginUser">Email atau Nomor WhatsApp</label>
                    <input type="text" id="loginUser" name="login" value="{{ old('login') }}" required placeholder="Masukkan email / nomor WhatsApp" autofocus>
                    @error('login')<div class="error-msg" style="display: block;">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="loginPass">Kata Sandi (Password)</label>
                    <input type="password" id="loginPass" name="password" required placeholder="Masukkan kata sandi">
                    @error('password')<div class="error-msg" style="display: block;">{{ $message }}</div>@enderror
                </div>

                <div class="form-extra">
                    <label>
                        <input type="checkbox" id="rememberMe" name="remember" value="1"> Ingat Saya (Remember Me)
                    </label>
                    <a href="#" onclick="alert('Fitur Lupa Password akan segera dihubungkan ke backend database.')">Lupa Password?</a>
                </div>

                <button type="submit" class="btn-auth">Masuk</button>
            </form>

            <div class="auth-footer">
                Belum punya akun? <a href="{{ route('register') }}">Sign Up (Daftar)</a>
            </div>

            <a href="{{ route('beranda') }}" class="back-home">← Kembali ke Beranda</a>
        </div>
    </section>
@endsection
