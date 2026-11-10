<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Honorarium;

class LaporanController extends Controller
{
    public function index()
    {
        $totalPesanan = Order::count();

        $totalPemasukan = Payment::where('status', 'verified')
            ->sum('amount');

        $totalHonorarium = Honorarium::sum('amount');

        $saldoBersih = $totalPemasukan - $totalHonorarium;

        return view('dashboard.laporan', compact(
            'totalPesanan',
            'totalPemasukan',
            'totalHonorarium',
            'saldoBersih'
        ));
    }
}