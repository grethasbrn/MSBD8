<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penjaga halaman web berdasarkan peran (versi Blade dari EnsureRole yang dipakai API).
 * Pemakaian di route:  ->middleware(EnsureWebRole::class . ':owner')
 *  - belum login  -> diarahkan ke halaman login (pelanggan atau pekerja) lalu kembali ke halaman tujuan
 *  - peran salah  -> 403
 */
class EnsureWebRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            $tujuan = in_array('customer', $roles, true) ? 'login' : 'login.pekerja';
            return redirect()->guest(route($tujuan));
        }
        if (!$user->is_active || !in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }
        return $next($request);
    }
}
