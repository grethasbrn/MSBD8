<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /** Pelanggan baru: nama lengkap, nomor WhatsApp, email, password. */
    public function register(Request $r): JsonResponse
    {
        $r->merge(['phone' => Phone::normalize((string) $r->input('phone'))]);
        $data = $r->validate([
            'name'     => 'required|string|max:150',
            'phone'    => 'required|string|min:9|max:20|unique:users,phone',
            'email'    => 'required|email|max:190|unique:users,email',
            'password' => 'required|string|min:8|max:100',
        ]);

        $user = User::create($data + ['role' => 'customer']);
        $user->refresh();

        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user'  => $user,
        ], 201);
    }

    /** Login dengan email ATAU nomor WhatsApp + password. */
    public function login(Request $r): JsonResponse
    {
        $r->validate(['login' => 'required|string', 'password' => 'required|string']);
        $login = trim((string) $r->input('login'));

        $user = User::whereRaw('lower(email) = ?', [strtolower($login)])
            ->orWhere('phone', Phone::normalize($login))
            ->first();

        if (!$user || !$user->is_active || !Hash::check((string) $r->input('password'), $user->password)) {
            return response()->json(['message' => 'Email/nomor WhatsApp atau password salah.'], 401);
        }

        return response()->json([
            'token' => $user->createToken('web')->plainTextToken,
            'user'  => $user,
        ]);
    }

    public function logout(Request $r): JsonResponse
    {
        $r->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Berhasil keluar.']);
    }

    public function me(Request $r): JsonResponse
    {
        $u = $r->user();
        return response()->json([
            'user' => $u,
            'unread_notifications' => AppNotification::where('user_id', $u->id)->whereNull('read_at')->count(),
        ]);
    }
}
