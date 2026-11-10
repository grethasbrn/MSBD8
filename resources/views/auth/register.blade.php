@extends('layouts.app')

@section('title', 'Daftar Akun - Sanggar Tri Arga')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset('css/alert.css') }}">
@endpush

@section('content')
    <section class="auth-section">
        <div class="auth-card">
            <div class="auth-header">
                <img src="{{ asset('images/logo.png') }}" alt="Logo Sanggar Tri Arga" class="auth-logo">
                <h2>Sign Up (Daftar)</h2>
                <p>Buat akun baru untuk mulai memesan pertunjukan</p>
            </div>

            <form method="POST" action="{{ route('register.proses') }}">
                @csrf
                <div class="form-group">
                    <label for="regNama">Nama Lengkap</label>
                    <input type="text" id="regNama" name="name" value="{{ old('name') }}" required placeholder="Masukkan nama lengkap">
                    @error('name')<div class="error-msg" style="display: block;">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="regEmail">Email</label>
                    <input type="email" id="regEmail" name="email" value="{{ old('email') }}" required placeholder="contoh@email.com">
                    @error('email')<div class="error-msg" style="display: block;">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="regPhone">Nomor WhatsApp</label>
                    <input type="tel" id="regPhone" name="phone" value="{{ old('phone') }}" required placeholder="Contoh: 081234567890">
                    @error('phone')<div class="error-msg" style="display: block;">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="regPass">Kata Sandi (Minimal 8 Karakter)</label>
                    <input type="password" id="regPass" name="password" required placeholder="Minimal 8 karakter">
                    @error('password')<div class="error-msg" style="display: block;">{{ $message }}</div>@enderror
                </div>

                <div class="form-group">
                    <label for="regPassConfirm">Konfirmasi Kata Sandi</label>
                    <input type="password" id="regPassConfirm" name="password_confirmation" required placeholder="Ulangi kata sandi">
                </div>

                <button type="submit" class="btn-auth">Daftar Sekarang</button>
            </form>

            <div class="auth-footer">
                Sudah memiliki akun? <a href="{{ route('login') }}">Masuk di sini</a>
            </div>

            <a href="{{ route('beranda') }}" class="back-home">← Kembali ke Beranda</a>
        </div>
    </section>
@endsection
