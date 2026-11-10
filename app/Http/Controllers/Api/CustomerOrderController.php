<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerOrderController extends Controller
{
    public function __construct(private OrderService $orders) {}

    /** Rincian biaya sebelum membayar (boleh dipanggil tanpa login). */
    public function quote(Request $r): JsonResponse
    {
        $d = $r->validate([
            'package_id'  => 'required|integer|exists:packages,id',
            'addon_ids'   => 'array',
            'addon_ids.*' => 'integer',
            'event_date'  => 'nullable|date_format:Y-m-d',
        ]);
        return response()->json($this->orders->quote($d['package_id'], $d['addon_ids'] ?? [], $d['event_date'] ?? null));
    }

    /** "Konfirmasi & Kirim Pesanan" (multipart/form-data karena ada file bukti transfer). */
    public function store(Request $r): JsonResponse
    {
        $d = $r->validate([
            'package_id'     => 'required|integer|exists:packages,id',
            'addon_ids'      => 'array',
            'addon_ids.*'    => 'integer',
            'event_date'     => 'required|date_format:Y-m-d|after:today',
            'event_time'     => 'nullable|date_format:H:i',
            'event_location' => 'required|string|min:10|max:500',
            'notes'          => 'nullable|string|max:1000',
            'payment_method' => 'required|in:transfer,qris,ewallet',
            'dp_amount'      => 'nullable|numeric|min:1',
            'proof'          => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);

        $order = $this->orders->create($r->user(), $d, $r->file('proof'));

        return response()->json([
            'message'      => 'Pesanan tersimpan dengan status Pending Verification. Silakan lanjut kirim konfirmasi lewat WhatsApp.',
            'order'        => $order->load(['package', 'addons', 'payments']),
            'whatsapp_url' => $this->orders->whatsappUrl($order),
        ], 201);
    }

    public function index(Request $r): JsonResponse
    {
        return response()->json(
            DB::table('v_order_ringkas')->where('customer_id', $r->user()->id)->orderByDesc('id')->get()
        );
    }

    public function show(Request $r, Order $order): JsonResponse
    {
        $this->own($r, $order);
        $order->load(['package', 'addons', 'payments']);

        $hasPending = fn (string $t) => $order->payments->where('type', $t)->where('status', 'pending')->isNotEmpty();

        return response()->json([
            'order'          => $order,
            'sisa_tagihan'   => $this->orders->remaining($order),
            'payment_info'   => $this->orders->paymentInfo(),
            'whatsapp_url'   => $this->orders->whatsappUrl($order),
            'actions' => [   // petunjuk tombol apa yang boleh tampil di UI
                'can_upload_dp'        => $order->status === 'pending_verification' && !$hasPending('dp'),
                'can_upload_pelunasan' => in_array($order->status, ['approved', 'completed'], true)
                                          && $order->lunas_at === null && !$hasPending('pelunasan'),
            ],
        ]);
    }

    /** Unggah ulang bukti DP setelah ditolak. */
    public function uploadDpProof(Request $r, Order $order): JsonResponse
    {
        $this->own($r, $order);
        $d = $r->validate([
            'payment_method' => 'required|in:transfer,qris,ewallet',
            'amount'         => 'nullable|numeric|min:1',
            'proof'          => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);
        $p = $this->orders->uploadDpProof($order, $r->user(), $r->file('proof'), $d['payment_method'], isset($d['amount']) ? (float) $d['amount'] : null);
        return response()->json(['message' => 'Bukti DP terkirim, menunggu verifikasi Owner.', 'payment' => $p], 201);
    }

    /** Unggah bukti pelunasan sisa tagihan (70%). */
    public function uploadPelunasan(Request $r, Order $order): JsonResponse
    {
        $this->own($r, $order);
        $d = $r->validate([
            'payment_method' => 'required|in:transfer,qris,ewallet',
            'amount'         => 'nullable|numeric|min:1',
            'proof'          => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ]);
        $p = $this->orders->uploadPelunasan($order, $r->user(), $r->file('proof'), $d['payment_method'], isset($d['amount']) ? (float) $d['amount'] : null);
        return response()->json(['message' => 'Bukti pelunasan terkirim, menunggu verifikasi Owner.', 'payment' => $p], 201);
    }

    private function own(Request $r, Order $order): void
    {
        abort_unless($order->customer_id === $r->user()->id, 404);
    }
}
