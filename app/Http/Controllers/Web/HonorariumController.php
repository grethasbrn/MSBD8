<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\EventAssignment;
use App\Models\Honorarium;
use App\Models\HonorRate;
use App\Models\Order;
use App\Support\Tx;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HonorariumController extends Controller
{
    public function index(Order $order)
    {
        $assignments = EventAssignment::with(['user', 'honorarium.paidBy'])
            ->where('order_id', $order->id)
            ->where('status', 'diterima')
            ->orderBy('role')
            ->get();

        return view('dashboard.honorarium', compact(
            'order',
            'assignments'
        ));
    }

    public function cairkan(Order $order): RedirectResponse
    {
        $tanggalAcara = \Carbon\Carbon::parse($order->event_date)->startOfDay();
        if (now('Asia/Jakarta')->startOfDay()->lt($tanggalAcara)) {
            throw ValidationException::withMessages([
                'honorarium' => 'Honorarium hanya dapat dicairkan pada tanggal acara atau setelahnya.'
            ]);
        }
        Tx::run(auth()->user(), function () use ($order) {

            Order::whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $assignments = EventAssignment::where('order_id', $order->id)
                ->where('status', 'diterima')
                ->whereNotNull('attended_at')
                ->whereDoesntHave('honorarium')
                ->lockForUpdate()
                ->get();

            if ($assignments->isEmpty()) {
                throw ValidationException::withMessages([
                    'honorarium' => 'Belum ada anggota hadir yang honornya dapat dicairkan.'
                ]);
            }

            foreach ($assignments as $assignment) {
                $rate = HonorRate::where('role', $assignment->role)->first();

                if (!$rate) {
                    throw ValidationException::withMessages([
                        'honorarium' => "Tarif honor untuk {$assignment->role} belum tersedia."
                    ]);
                }

                Honorarium::create([
                    'assignment_id' => $assignment->id,
                    'amount' => $rate->amount,
                    'paid_by' => auth()->id(),
                    'paid_at' => now(),
                ]);
            }
        }, 'Pencairan honorarium');

        return back()->with('success', 'Honorarium berhasil dicairkan.');
    }
}
