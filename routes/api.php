<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CustomerOrderController;
use App\Http\Controllers\Api\KorlapController;
use App\Http\Controllers\Api\LogisticsController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\MyAssignmentController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OwnerOrderController;
use App\Http\Controllers\Api\PublicController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\StaffController;
use Illuminate\Support\Facades\Route;

/* ------------------------------------------------------------------ Publik (tanpa login) */
Route::get('packages', [PublicController::class, 'packages']);
Route::get('packages/{id}', [PublicController::class, 'package'])->whereNumber('id');
Route::get('addons', [PublicController::class, 'addons']);
Route::get('inventory', [PublicController::class, 'inventory']);
Route::get('gallery', [PublicController::class, 'gallery']);
Route::get('calendar', [PublicController::class, 'calendar']);
Route::get('payment-info', [PublicController::class, 'paymentInfo']);
Route::post('orders/quote', [CustomerOrderController::class, 'quote'])->middleware('throttle:60,1');

/* ------------------------------------------------------------------ Auth */
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:sanctum')->group(function () {

    /* -------------------------------------------------------------- Notifikasi (semua peran) */
    Route::get('notifications', [NotificationController::class, 'index']);
    Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);

    /* -------------------------------------------------------------- Pelanggan */
    Route::middleware('role:customer')->group(function () {
        Route::get('orders', [CustomerOrderController::class, 'index']);
        Route::post('orders', [CustomerOrderController::class, 'store']);
        Route::get('orders/{order}', [CustomerOrderController::class, 'show']);
        Route::post('orders/{order}/dp-proof', [CustomerOrderController::class, 'uploadDpProof']);
        Route::post('orders/{order}/pelunasan', [CustomerOrderController::class, 'uploadPelunasan']);
    });

    /* -------------------------------------------------------------- Owner */
    Route::middleware('role:owner')->prefix('owner')->group(function () {
        Route::get('dashboard', [OwnerOrderController::class, 'dashboard']);

        Route::get('orders', [OwnerOrderController::class, 'index']);
        Route::get('orders/{order}', [OwnerOrderController::class, 'show']);
        Route::post('orders/{order}/approve-dp', [OwnerOrderController::class, 'approveDp']);
        Route::post('orders/{order}/reject-dp', [OwnerOrderController::class, 'rejectDp']);
        Route::post('orders/{order}/cancel', [OwnerOrderController::class, 'cancel']);
        Route::get('orders/{order}/available-korlap', [OwnerOrderController::class, 'availableKorlap']);
        Route::post('orders/{order}/assign-korlap', [OwnerOrderController::class, 'assignKorlap']);
        Route::post('orders/{order}/disburse-honor', [OwnerOrderController::class, 'disburseHonor']);

        Route::get('payments', [OwnerOrderController::class, 'payments']);
        Route::post('payments/{payment}/verify', [OwnerOrderController::class, 'verifyPayment']);
        Route::post('payments/{payment}/reject', [OwnerOrderController::class, 'rejectPayment']);

        Route::get('staff', [StaffController::class, 'index']);
        Route::post('staff', [StaffController::class, 'store']);
        Route::put('staff/{user}', [StaffController::class, 'update']);

        Route::get('settings', [SettingsController::class, 'index']);
        Route::put('settings', [SettingsController::class, 'update']);
        Route::get('honor-rates', [SettingsController::class, 'honorRates']);
        Route::put('honor-rates/{role}', [SettingsController::class, 'updateHonorRate']);

        // CRUD data master: packages | addons | inventory | gallery
        Route::prefix('master/{resource}')->whereIn('resource', ['packages', 'addons', 'inventory', 'gallery'])->group(function () {
            Route::get('/', [MasterDataController::class, 'index']);
            Route::post('/', [MasterDataController::class, 'store']);
            Route::put('{id}', [MasterDataController::class, 'update'])->whereNumber('id');
            Route::delete('{id}', [MasterDataController::class, 'destroy'])->whereNumber('id');
        });
    });

    /* -------------------------------------------------------------- Korlap */
    Route::middleware('role:korlap')->prefix('korlap')->group(function () {
        Route::get('events', [KorlapController::class, 'events']);
        Route::get('events/{order}', [KorlapController::class, 'show']);
        Route::get('events/{order}/available-staff', [KorlapController::class, 'availableStaff']);
        Route::post('events/{order}/assign', [KorlapController::class, 'assign']);
        Route::post('events/{order}/attendance', [KorlapController::class, 'attendance']);
        Route::post('events/{order}/cash-pelunasan', [KorlapController::class, 'cashPelunasan']);
        Route::post('events/{order}/complete', [KorlapController::class, 'complete']);
        Route::delete('assignments/{assignment}', [KorlapController::class, 'cancelAssignment']);
    });

    /* -------------------------------------------------------------- Tim lapangan */
    Route::middleware('role:korlap,penari,pemusik,helper')->prefix('my')->group(function () {
        Route::get('assignments', [MyAssignmentController::class, 'index']);
        Route::post('assignments/{assignment}/respond', [MyAssignmentController::class, 'respond']);
        Route::get('honorariums', [MyAssignmentController::class, 'honorariums']);
    });

    Route::middleware('role:helper,korlap')->prefix('logistics')->group(function () {
        Route::get('orders', [LogisticsController::class, 'orders']);
        Route::get('orders/{order}/loans', [LogisticsController::class, 'loans']);
        Route::post('orders/{order}/borrow', [LogisticsController::class, 'borrow']);
        Route::post('orders/{order}/return', [LogisticsController::class, 'returnItems']);
    });
});
