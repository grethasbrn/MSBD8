<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventAssignment;
use App\Models\Order;
use App\Services\AssignmentService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Dashboard Koordinator Lapangan: acara yang dimandatkan, pembentukan tim, presensi, laporan. */
class KorlapController extends Controller
{
    public function __construct(private AssignmentService $assignments, private OrderService $orders) {}

    /** Acara yang dimandatkan kepada Korlap ini. */
    public function events(Request $r): JsonResponse
    {
        return response()->json(
            Order::whereHas('assignments', fn ($q) => $q->where('user_id', $r->user()->id)
                    ->where('role', 'korlap')->where('status', 'diterima'))
                ->with('package:id,name,category')
                ->orderBy('event_date')->get(['id', 'code', 'status', 'event_date', 'event_time', 'event_location', 'package_id'])
        );
    }

    /** Detail lengkap acara: lokasi, tanggal, jam, paket/add-on, catatan pelanggan, tim, kebutuhan. */
    public function show(Request $r, Order $order): JsonResponse
    {
        $this->ownEvent($r, $order);
        $order->load([
            'customer:id,name,phone', 'package', 'addons',
            'assignments' => fn ($q) => $q->whereIn('status', ['ditawarkan', 'diterima', 'ditolak'])->orderBy('role'),
            'assignments.user:id,name,phone,specialization',
            'loans.item',
        ]);
        return response()->json([
            'order'         => $order,
            'sisa_tagihan'  => $this->orders->remaining($order),
            'kebutuhan_tim' => $this->orders->staffingSummary($order),
        ]);
    }

    /** SDM yang kosong di tanggal acara (filter: role, specialization untuk pemusik). */
    public function availableStaff(Request $r, Order $order): JsonResponse
    {
        $this->ownEvent($r, $order);
        $d = $r->validate(['role' => 'required|in:penari,pemusik,helper', 'specialization' => 'nullable|string|max:100']);
        return response()->json($this->assignments->availableStaff($d['role'], $order->event_date->toDateString(), $d['specialization'] ?? null));
    }

    /** Tawarkan tugas ke penari / pemusik / helper. */
    public function assign(Request $r, Order $order): JsonResponse
    {
        $this->ownEvent($r, $order);
        $d = $r->validate([
            'user_id'    => 'required|integer|exists:users,id',
            'role'       => 'required|in:penari,pemusik,helper',
            'instrument' => 'nullable|string|max:100',
        ]);
        $a = $this->assignments->offer($order, $r->user(), (int) $d['user_id'], $d['role'], $d['instrument'] ?? null);
        return response()->json(['message' => 'Tawaran penugasan dikirim.', 'assignment' => $a->load('user:id,name,specialization')], 201);
    }

    /** Cabut tawaran / ganti anggota tim (misalnya setelah ada yang menolak). */
    public function cancelAssignment(Request $r, EventAssignment $assignment): JsonResponse
    {
        $this->ownEvent($r, Order::findOrFail($assignment->order_id));
        return response()->json(['message' => 'Penugasan dibatalkan.', 'assignment' => $this->assignments->cancel($assignment, $r->user())]);
    }

    /** Presensi digital: kirim daftar id penugasan yang hadir (termasuk Korlap sendiri). */
    public function attendance(Request $r, Order $order): JsonResponse
    {
        $this->ownEvent($r, $order);
        $d = $r->validate(['assignment_ids' => 'required|array|min:1', 'assignment_ids.*' => 'integer']);
        $n = $this->assignments->markAttendance($order, $r->user(), $d['assignment_ids']);
        return response()->json(['message' => "Presensi {$n} orang tercatat."]);
    }

    /** Pelunasan tunai yang diterima Korlap di hari-H (menunggu verifikasi Owner). */
    public function cashPelunasan(Request $r, Order $order): JsonResponse
    {
        $this->ownEvent($r, $order);
        $d = $r->validate(['amount' => 'nullable|numeric|min:1']);
        $p = $this->orders->recordCashPelunasan($order, $r->user(), isset($d['amount']) ? (float) $d['amount'] : null);
        return response()->json(['message' => 'Pelunasan tunai dicatat, menunggu verifikasi Owner.', 'payment' => $p], 201);
    }

    /** Ubah status acara menjadi Completed + kirim laporan pelaksanaan ke Owner. */
    public function complete(Request $r, Order $order): JsonResponse
    {
        $this->ownEvent($r, $order);
        $d = $r->validate(['report' => 'required|string|min:10|max:5000']);
        return response()->json(['message' => 'Acara ditandai selesai dan laporan terkirim ke Owner.',
            'order' => $this->orders->complete($order, $r->user(), $d['report'])]);
    }

    private function ownEvent(Request $r, Order $order): void
    {
        $ok = EventAssignment::where('order_id', $order->id)->where('user_id', $r->user()->id)
            ->where('role', 'korlap')->where('status', 'diterima')->exists();
        abort_unless($ok, 403, 'Anda bukan Korlap untuk acara ini.');
    }
}
