<?php

use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\BerandaController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\KatalogController;
use App\Http\Controllers\Web\PesanController;
use App\Http\Controllers\Web\PesananController;
use App\Http\Controllers\Web\LogistikController;
use App\Http\Controllers\Web\HonorariumController;
use App\Http\Controllers\Web\LaporanController;
use App\Http\Controllers\Web\OwnerPesananController;
use App\Http\Middleware\EnsureWebRole;
use Illuminate\Support\Facades\Route;


/* ---------- Halaman publik ---------- */
Route::get('/', [BerandaController::class, 'index'])->name('beranda');
Route::get('/alat-musik', [KatalogController::class, 'alatMusik'])->name('katalog.alat');
Route::get('/kostum', [KatalogController::class, 'kostum'])->name('katalog.kostum');

/* ---------- Masuk, daftar, keluar ---------- */
Route::get('/login', [AuthController::class, 'formLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.proses');
Route::get('/register', [AuthController::class, 'formDaftar'])->name('register');
Route::post('/register', [AuthController::class, 'daftar'])->name('register.proses');
Route::get('/login-pekerja', [AuthController::class, 'formLoginPekerja'])->name('login.pekerja');
Route::post('/login-pekerja', [AuthController::class, 'loginPekerja'])->name('login.pekerja.proses');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

/* ---------- Pemesanan & pesanan (khusus pelanggan) ---------- */
Route::middleware(EnsureWebRole::class . ':customer')->group(function () {
    Route::get('/pesan', [PesanController::class, 'form'])->name('pesan');
    Route::post('/pesan', [PesanController::class, 'rincian'])->name('pesan.rincian');
    Route::get('/pesan/pembayaran', [PesanController::class, 'pembayaran'])->name('pesan.pembayaran');
    Route::post('/pesan/kirim', [PesanController::class, 'kirim'])->name('pesan.kirim');

    Route::get('/pesanan', [PesananController::class, 'index'])->name('pesanan.index');
    Route::get('/pesanan/{order:code}', [PesananController::class, 'show'])->name('pesanan.show');
    Route::post('/pesanan/{order:code}/dp', [PesananController::class, 'unggahDp'])->name('pesanan.dp');
    Route::post('/pesanan/{order:code}/pelunasan', [PesananController::class, 'unggahPelunasan'])->name('pesanan.pelunasan');
});

/* ---------- Dashboard pekerja ---------- */
foreach (['owner', 'korlap', 'helper', 'penari', 'pemusik'] as $peran) {
    Route::get("/dashboard/{$peran}", [DashboardController::class, 'sementara'])
        ->defaults('peran', $peran)
        ->middleware(EnsureWebRole::class . ":{$peran}")
        ->name("dashboard.{$peran}");
}

Route::middleware(EnsureWebRole::class . ':owner')->prefix('owner')->name('owner.')->group(function () {
    Route::get('/pesanan', [DashboardController::class, 'ownerPesanan'])->name('pesanan.index');
    Route::get('/pesanan/buat', [OwnerPesananController::class, 'create']) ->name('pesanan.create');
    Route::post('/pesanan', [OwnerPesananController::class, 'store']) ->name('pesanan.store');
    Route::get('/pesanan/{order}', [DashboardController::class, 'ownerDetail'])->name('pesanan.show');
    Route::post('/pesanan/{order}/dp/setujui', [DashboardController::class, 'setujuiDp'])->name('dp.setujui');
    Route::post('/pesanan/{order}/dp/tolak', [DashboardController::class, 'tolakDp'])->name('dp.tolak');
    Route::post('/pembayaran/{payment}/pelunasan/verifikasi', [DashboardController::class, 'verifikasiPelunasan'])->name('pelunasan.verifikasi');
    Route::post('/pembayaran/{payment}/pelunasan/tolak', [DashboardController::class, 'tolakPelunasan'])->name('pelunasan.tolak');
    Route::post('/pesanan/{order}/korlap', [DashboardController::class, 'tugaskanKorlap'])->name('korlap.tugaskan');
    Route::get('/pesanan/{order}/honorarium', [HonorariumController::class, 'index']) ->name('honorarium.index');
    Route::post('/pesanan/{order}/honorarium/cairkan',[HonorariumController::class, 'cairkan'])->name('honorarium.cairkan');
    Route::get('/laporan', [LaporanController::class, 'index'])->name('laporan.index');
});


Route::middleware(EnsureWebRole::class . ':korlap')->prefix('korlap')->name('korlap.')->group(function () {
    Route::get('/acara/{order}', [DashboardController::class, 'detailKorlap'])->name('acara.show');
    Route::post('/acara/{order}/tim', [DashboardController::class, 'tugaskanTim'])->name('tim.tugaskan');
    Route::delete('/tugas/{assignment}', [DashboardController::class, 'batalTugas'])->name('tim.batal');
    Route::post('/acara/{order}/presensi',[DashboardController::class, 'simpanPresensi'])->name('presensi.simpan');
});

foreach (['penari', 'pemusik', 'helper'] as $peran) {
    Route::middleware(EnsureWebRole::class . ":{$peran}")
        ->prefix($peran)
        ->name("{$peran}.")
        ->group(function () {
            Route::post('/tugas/{assignment}/respons',
                [DashboardController::class, 'responsTugas']
            )->name('tugas.respond');
        });
}
// ---------- Logistik Helper dan Korlap ----------
foreach (['helper', 'korlap'] as $peran) {
    Route::middleware(EnsureWebRole::class . ":{$peran}")
        ->prefix($peran)
        ->name("{$peran}.")
        ->group(function () {
            Route::get('/logistik', [LogistikController::class, 'index'])
                ->name('logistik.index');

            Route::get('/logistik/{order}', [LogistikController::class, 'show'])
                ->name('logistik.show');
            Route::post('/logistik/{order}/borrow',
                [LogistikController::class, 'borrow']
            )->name('logistik.borrow');

            Route::post('/logistik/{order}/return',
                [LogistikController::class, 'returnItems']
            )->name('logistik.return');
        });
}