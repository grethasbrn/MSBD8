<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const PERAN_PEKERJA = ['owner', 'korlap', 'helper', 'penari', 'pemusik'];
    private const MAKS_PERCOBAAN = 5;      // gagal berturut-turut sebelum dikunci sementara
    private const KUNCI_DETIK = 60;

    private const PESAN = [
        'required'  => ':attribute wajib diisi.',
        'email'     => 'Format email tidak valid.',
        'min'       => ':attribute minimal :min karakter.',
        'max'       => ':attribute terlalu panjang.',
        'unique'    => ':attribute sudah terdaftar.',
        'confirmed' => 'Konfirmasi kata sandi tidak sama.',
        'in'        => ':attribute tidak valid.',
    ];
    private const NAMA = [
        'name' => 'Nama lengkap', 'email' => 'Email', 'phone' => 'Nomor WhatsApp',
        'password' => 'Kata sandi', 'login' => 'Email atau nomor WhatsApp', 'role' => 'Peran',
    ];

    /* ------------------------------------------------------------ halaman */

    public function formLogin()
    {
        return Auth::check() ? redirect()->route('beranda') : view('auth.login');
    }

    public function formDaftar()
    {
        return Auth::check() ? redirect()->route('beranda') : view('auth.register');
    }

    public function formLoginPekerja()
    {
        return Auth::check() ? redirect()->route('beranda') : view('auth.login-pekerja');
    }

    /* ------------------------------------------------------------ proses */

    /** Login PELANGGAN (email atau nomor WhatsApp + kata sandi). */
    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login'    => 'required|string|max:190',
            'password' => 'required|string|max:100',
        ], self::PESAN, self::NAMA);

        $user = $this->coba($request, $data['login'], $data['password']);

        if ($user->role !== 'customer') {
            $this->tolak('Akun ini milik tim sanggar. Silakan masuk lewat halaman Login Pekerja.');
        }

        $this->masuk($request, $user);
        return redirect()->intended(route('beranda'));
    }

    /** Login PEKERJA: peran yang dipilih harus sama dengan peran asli akun di database. */
    public function loginPekerja(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role'     => 'required|in:' . implode(',', self::PERAN_PEKERJA),
            'login'    => 'required|string|max:190',
            'password' => 'required|string|max:100',
        ], self::PESAN, self::NAMA);

        $user = $this->coba($request, $data['login'], $data['password']);

        if ($user->role === 'customer') {
            $this->tolak('Ini akun pelanggan. Silakan masuk lewat halaman Login pelanggan.');
        }
        if ($user->role !== $data['role']) {
            $this->tolak('Akun ini terdaftar sebagai ' . strtoupper($user->role) . ', bukan ' . strtoupper($data['role']) . '. Pilih peran yang sesuai.');
        }

        $this->masuk($request, $user);
        return redirect()->intended(route('dashboard.' . $user->role));
    }

    /** Pendaftaran pelanggan baru. */
    public function daftar(Request $request): RedirectResponse
    {
        $request->merge([
            'phone' => Phone::normalize((string) $request->input('phone')),
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $data = $request->validate([
            'name'     => 'required|string|max:150',
            'email'    => 'required|email|max:190|unique:users,email',
            'phone'    => 'required|string|min:9|max:20|unique:users,phone',
            'password' => 'required|string|min:8|max:100|confirmed',
        ], self::PESAN, self::NAMA);

        User::create([
            'name' => trim($data['name']), 'email' => $data['email'], 'phone' => $data['phone'],
            'password' => $data['password'], 'role' => 'customer',
        ]);

        return redirect()->route('login')->with('status', 'Registrasi berhasil! Akun Anda telah tersimpan. Silakan masuk.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('beranda');
    }

    /* ------------------------------------------------------------ bantu */

    /** Cek kredensial + batasi percobaan beruntun (per akun + alamat IP). Mengembalikan user bila benar. */
    private function coba(Request $request, string $login, string $password): User
    {
        $kunci = Str::lower(trim($login)) . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($kunci, self::MAKS_PERCOBAAN)) {
            $this->tolak('Terlalu banyak percobaan gagal. Coba lagi dalam ' . RateLimiter::availableIn($kunci) . ' detik.');
        }

        $user = User::whereRaw('lower(email) = ?', [Str::lower(trim($login))])
            ->orWhere('phone', Phone::normalize($login))
            ->first();

        if (!$user || !$user->is_active || !Hash::check($password, $user->password)) {
            RateLimiter::hit($kunci, self::KUNCI_DETIK);
            $this->tolak('Email/nomor WhatsApp atau kata sandi salah.');
        }

        RateLimiter::clear($kunci);
        return $user;
    }

    private function masuk(Request $request, User $user): void
    {
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();      // cegah pembajakan sesi
    }

    private function tolak(string $pesan): never
    {
        throw ValidationException::withMessages(['akun' => $pesan]);
    }
}
