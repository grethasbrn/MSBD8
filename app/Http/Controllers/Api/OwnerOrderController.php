<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\AssignmentService;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Semua aksi Owner terkait pesanan, pembayaran, Korlap, dan honor. */
class OwnerOrderController extends Controller
{
    public function __construct(private OrderService $orders, private AssignmentService $assignments) {}

    /** Dashboard: angka ringkas, grafik arus kas, antrean verifikasi, acara mendatang. */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'ringkasan'           => DB::selectOne('select * from v_owner_ringkasan'),
            'arus_kas'            => DB::select('select * from v_arus_kas_bulanan order by bulan'),
            'menunggu_verifikasi' => Payment::with($this->paymentRelations())
                                        ->where('status', 'pending')->orderBy('id')->limit(20)->get(),
            'acara_mendatang'     => DB::table('v_order_ringkas')->where('status', 'approved')
                                        ->where('event_date', '>=', DB::raw('fn_today_wib()'))
                                        ->orderBy('event_date')->limit(10)->get(),
        ]);
    }

    /** Data pesanan yang dibawa setiap pembayaran (dipakai tabel Verifikasi DP). */
    private function paymentRelations(): array
    {
        return [
            'order:id,code,customer_id,package_id,event_date,total_amount',
            'order.customer:id,name',
            'order.package:id,name',
        ];
    }

    public function index(Request $r): JsonResponse
    {
        $r->validate([
            'status' => 'nullable|in:pending_verification,approved,cancelled,completed',
            'q'      => 'nullable|string|max:100',
            'date'   => 'nullable|date_format:Y-m-d',
        ]);

        $q = DB::table('v_order_ringkas')
            ->when($r->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($r->query('date'), fn ($q, $d) => $q->where('event_date', $d))
            ->when($r->query('q'), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('code', 'ilike', "%{$s}%")->orWhere('customer_name', 'ilike', "%{$s}%")))
            ->orderByDesc('id');

        return response()->json($q->paginate(20));
    }

    public function show(Order $order): JsonResponse
    {
        $order->load([
            'customer:id,name,phone,email', 'package', 'addons', 'payments',
            'assignments.user:id,name,phone,role,specialization', 'assignments.honorarium',
            'loans.item', 'statusLogs',
        ]);
        return response()->json([
            'order'        => $order,
            'sisa_tagihan' => $this->orders->remaining($order),
            'kebutuhan_tim' => $this->orders->staffingSummary($order),
        ]);
    }

    public function approveDp(Request $r, Order $order): JsonResponse
    {
        return response()->json(['message' => 'DP disetujui. Slot kuota tanggal acara terkunci.',
            'order' => $this->orders->approveDp($order, $r->user())]);
    }

    public function rejectDp(Request $r, Order $order): JsonResponse
    {
        $d = $r->validate(['reason' => 'required|string|max:500']);
        return response()->json(['message' => 'Bukti DP ditolak.', 'payment' => $this->orders->rejectDp($order, $r->user(), $d['reason'])]);
    }

    public function cancel(Request $r, Order $order): JsonResponse
    {
        $d = $r->validate(['reason' => 'required|string|max:500']);
        return response()->json(['message' => 'Pesanan dibatalkan.', 'order' => $this->orders->cancel($order, $r->user(), $d['reason'])]);
    }

    public function payments(Request $r): JsonResponse
    {
        $r->validate(['status' => 'nullable|in:pending,verified,rejected', 'type' => 'nullable|in:dp,pelunasan']);
        return response()->json(
            Payment::with($this->paymentRelations())
                ->when($r->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->when($r->query('type'), fn ($q, $t) => $q->where('type', $t))
                ->orderByDesc('id')->paginate(20)
        );
    }

    public function verifyPayment(Request $r, Payment $payment): JsonResponse
    {
        return response()->json(['message' => 'Pelunasan diverifikasi.', 'payment' => $this->orders->verifyPelunasan($payment, $r->user())]);
    }

    public function rejectPayment(Request $r, Payment $payment): JsonResponse
    {
        $d = $r->validate(['reason' => 'required|string|max:500']);
        return response()->json(['message' => 'Bukti pelunasan ditolak.', 'payment' => $this->orders->rejectPelunasan($payment, $r->user(), $d['reason'])]);
    }

    /** Daftar Korlap yang kosong pada tanggal acara. */
    public function availableKorlap(Order $order): JsonResponse
    {
        return response()->json($this->assignments->availableStaff('korlap', $order->event_date->toDateString()));
    }

    public function assignKorlap(Request $r, Order $order): JsonResponse
    {
        $d = $r->validate(['korlap_id' => 'required|integer|exists:users,id']);
        return response()->json(['message' => 'Korlap ditugaskan.',
            'assignment' => $this->assignments->assignKorlap($order, $r->user(), (int) $d['korlap_id'])->load('user:id,name,phone')], 201);
    }

    public function disburseHonor(Request $r, Order $order): JsonResponse
    {
        $s = $this->orders->disburseHonor($order, $r->user());
        return response()->json([
            'message' => 'Honor berhasil dicairkan.',
            'jumlah_penerima' => (int) $s->jumlah_penerima,
            'total_honor'     => (float) $s->total_honor,
        ]);
    }
}
