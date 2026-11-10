<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\MenerjemahkanGalat;
use App\Models\Order;
use App\Services\OrderService;
use App\Support\Validasi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Riwayat dan detail pesanan milik pelanggan, termasuk unggah ulang DP dan pelunasan. */
class PesananController extends Controller
{
    use MenerjemahkanGalat;

    public function __construct(private OrderService $orders) {}

    public function index(Request $request)
    {
        return view('pesanan.index', [
            'pesanan' => DB::table('v_order_ringkas')->where('customer_id', $request->user()->id)->orderByDesc('id')->get(),
        ]);
    }

    public function show(Request $request, Order $order)
    {
        $this->milik($request, $order);
        $order->load(['package', 'addons', 'payments' => fn ($q) => $q->orderBy('id')]);

        $menunggu = fn (string $jenis) => $order->payments->where('type', $jenis)->where('status', 'pending')->isNotEmpty();

        return view('pesanan.show', [
            'order'     => $order,
            'dibayar'   => (float) $order->payments->where('status', 'verified')->sum('amount'),
            'sisa'      => $this->orders->remaining($order),
            'waUrl'     => $this->orders->whatsappUrl($order),
            'info'      => $this->orders->paymentInfo(),
            'bisaDp'    => $order->status === 'pending_verification' && !$menunggu('dp'),
            'bisaLunas' => in_array($order->status, ['approved', 'completed'], true) && $order->lunas_at === null && !$menunggu('pelunasan'),
        ]);
    }

    /** Unggah ulang bukti DP (setelah ditolak Owner). */
    public function unggahDp(Request $request, Order $order)
    {
        $this->milik($request, $order);
        $d = $this->validasiBukti($request);

        $this->terjemahkan(fn () => $this->orders->uploadDpProof($order, $request->user(), $request->file('proof'), $d['payment_method'], isset($d['amount']) ? (float) $d['amount'] : null));
        return redirect()->route('pesanan.show', ['order' => $order->code])->with('status', 'Bukti DP terkirim dan menunggu verifikasi Owner.');
    }

    /** Unggah bukti pelunasan sisa tagihan. */
    public function unggahPelunasan(Request $request, Order $order)
    {
        $this->milik($request, $order);
        $d = $this->validasiBukti($request);

        $this->terjemahkan(fn () => $this->orders->uploadPelunasan($order, $request->user(), $request->file('proof'), $d['payment_method'], isset($d['amount']) ? (float) $d['amount'] : null));
        return redirect()->route('pesanan.show', ['order' => $order->code])->with('status', 'Bukti pelunasan terkirim dan menunggu verifikasi Owner.');
    }

    private function validasiBukti(Request $request): array
    {
        return $request->validate([
            'payment_method' => 'required|in:transfer,qris,ewallet',
            'amount'         => 'nullable|numeric|min:1',
            'proof'          => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], Validasi::pesan(), Validasi::nama());
    }

    private function milik(Request $request, Order $order): void
    {
        abort_unless($order->customer_id === $request->user()->id, 404);
    }
}
