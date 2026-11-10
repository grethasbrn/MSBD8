<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\MenerjemahkanGalat;
use App\Models\AppNotification;
use App\Models\EventAssignment;
use App\Models\Order;
use App\Models\Payment;
use App\Services\AssignmentService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use MenerjemahkanGalat;

    public function __construct(
        private OrderService $orders,
        private AssignmentService $assignments
    ) {}

    public function sementara(string $peran)
    {
        if ($peran === 'owner') {
            $hariIni = now('Asia/Jakarta')->toDateString();
            $notifikasi = AppNotification::where('user_id', auth()->id())
                ->orderByDesc('id')
                ->limit(10)
                ->get();
            $ringkasan = [
                'total_pesanan' => Order::count(),
                'menunggu_dp' => Payment::where('type', 'dp')->where('status', 'pending')->count(),
                'pesanan_aktif' => Order::whereIn('status', ['pending_verification', 'approved'])->count(),
                'acara_mendatang' => Order::where('status', 'approved')->whereDate('event_date', '>=', $hariIni)->count(),
            ];
            $pembayaranMenunggu = DB::table('payments as p')
                ->join('orders as o', 'o.id', '=', 'p.order_id')
                ->join('users as u', 'u.id', '=', 'o.customer_id')
                ->where('p.status', 'pending')->orderBy('p.id')->limit(10)
                ->select('p.id as payment_id', 'p.type', 'p.amount', 'p.method', 'p.proof_path', 'o.id as order_id', 'o.code', 'o.event_date', 'u.name as customer_name')->get();
            $acaraMendatang = DB::table('orders as o')
                ->join('users as u', 'u.id', '=', 'o.customer_id')
                ->join('packages as pk', 'pk.id', '=', 'o.package_id')
                ->where('o.status', 'approved')->whereDate('o.event_date', '>=', $hariIni)
                ->orderBy('o.event_date')->limit(8)
                ->select('o.id', 'o.code', 'o.event_date', 'o.event_time', 'o.event_location', 'u.name as customer_name', 'pk.name as package_name')->get();
            return view('dashboard.owner', compact(
                'ringkasan',
                'pembayaranMenunggu',
                'acaraMendatang',
                'notifikasi'
            ));
        }

        if ($peran === 'korlap') {
            $acara = Order::whereHas('assignments', fn ($q) => $q->where('user_id', auth()->id())->where('role', 'korlap')->where('status', 'diterima'))
                ->with(['customer:id,name', 'package:id,name'])
                ->orderBy('event_date')->get();
            return view('dashboard.korlap', compact('acara'));
        }

        if (in_array($peran, ['penari', 'pemusik', 'helper'])) {
            $tawaran = EventAssignment::with(['order.customer', 'order.package'])
                ->where('user_id', auth()->id())
                ->where('role', $peran)
                ->where('status', 'ditawarkan')
                ->orderByDesc('id')
                ->get();

            $tugasSaya = EventAssignment::with(['order.customer', 'order.package'])
                ->where('user_id', auth()->id())
                ->where('role', $peran)
                ->whereIn('status', ['diterima', 'ditolak'])
                ->orderByDesc('id')
                ->get();

            $namaPeran = match ($peran) {
                'penari' => 'Penari',
                'pemusik' => 'Pemusik',
                'helper' => 'Helper',
            };

            return view('dashboard.anggota', compact(
                'tawaran',
                'tugasSaya',
                'namaPeran'
            ));
}
    }

    public function ownerPesanan()
    {
        $pesanan = Order::with(['customer:id,name', 'package:id,name', 'payments'])
            ->orderByDesc('id')->paginate(15);
        return view('dashboard.owner-pesanan', compact('pesanan'));
    }

    public function ownerDetail(Order $order)
    {
        $order->load(['customer', 'package', 'addons', 'payments' => fn ($q) => $q->orderByDesc('id'), 'assignments.user']);
        return view('dashboard.owner-detail', [
            'order' => $order,
            'dibayar' => (float) $order->payments->where('status', 'verified')->sum('amount'),
            'sisa' => $this->orders->remaining($order),
            'korlapTersedia' => $this->assignments->availableStaff('korlap', $order->event_date->toDateString()),
        ]);
    }

    public function setujuiDp(Request $request, Order $order)
    {
        $this->terjemahkan(fn () => $this->orders->approveDp($order, $request->user()));
        return back()->with('status', 'DP berhasil diverifikasi. Pesanan sekarang disetujui.');
    }

    public function tolakDp(Request $request, Order $order)
    {
        $d = $request->validate(['reason' => 'required|string|max:500']);
        $this->terjemahkan(fn () => $this->orders->rejectDp($order, $request->user(), $d['reason']));
        return back()->with('status', 'Bukti DP ditolak.');
    }

    public function verifikasiPelunasan(Request $request, Payment $payment)
    {
        $this->terjemahkan(fn () => $this->orders->verifyPelunasan($payment, $request->user()));
        return back()->with('status', 'Pelunasan berhasil diverifikasi.');
    }

    public function tolakPelunasan(Request $request, Payment $payment)
    {
        $d = $request->validate(['reason' => 'required|string|max:500']);
        $this->terjemahkan(fn () => $this->orders->rejectPelunasan($payment, $request->user(), $d['reason']));
        return back()->with('status', 'Bukti pelunasan ditolak.');
    }

    public function tugaskanKorlap(Request $request, Order $order)
    {
        $d = $request->validate(['korlap_id' => 'required|integer|exists:users,id']);
        $this->terjemahkan(fn () => $this->assignments->assignKorlap($order, $request->user(), (int) $d['korlap_id']));
        return back()->with('status', 'Korlap berhasil ditugaskan.');
    }

    public function detailKorlap(Order $order)
    {
        abort_unless($order->assignments()->where('user_id', auth()->id())->where('role', 'korlap')->where('status', 'diterima')->exists(), 404);
        $order->load(['customer', 'package', 'addons', 'assignments.user']);
        $kebutuhan = $this->orders->staffingSummary($order);
        $staf = collect(['penari', 'pemusik', 'helper'])->mapWithKeys(fn ($role) => [$role => $this->assignments->availableStaff($role, $order->event_date->toDateString())]);
        return view('dashboard.korlap-detail', compact('order', 'kebutuhan', 'staf'));
    }

    public function tugaskanTim(Request $request, Order $order)
    {
        abort_unless($order->assignments()->where('user_id', $request->user()->id)->where('role', 'korlap')->where('status', 'diterima')->exists(), 404);
        $d = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'role' => 'required|in:penari,pemusik,helper',
            'instrument' => 'nullable|string|max:100',
        ]);
        $this->terjemahkan(fn () => $this->assignments->offer($order, $request->user(), (int) $d['user_id'], $d['role'], $d['instrument'] ?? null));
        return back()->with('status', 'Tawaran tugas berhasil dikirim kepada anggota tim.');
    }

    public function batalTugas(Request $request, EventAssignment $assignment)
    {
        $order = Order::findOrFail($assignment->order_id);
        abort_unless($order->assignments()->where('user_id', $request->user()->id)->where('role', 'korlap')->where('status', 'diterima')->exists(), 404);
        $this->terjemahkan(fn () => $this->assignments->cancel($assignment, $request->user()));
        return back()->with('status', 'Tawaran tugas dibatalkan.');
    }

    public function responsTugas(Request $request, EventAssignment $assignment)
    {
        $data = $request->validate([
            'action' => 'required|in:accept,reject',
        ]);

        $this->terjemahkan(fn () =>
            $this->assignments->respond(
                $assignment,
                $request->user(),
                $data['action']
            )
        );

        $pesan = $data['action'] === 'accept'
            ? 'Tawaran tugas berhasil diterima.'
            : 'Tawaran tugas berhasil ditolak.';

        return back()->with('status', $pesan);
    }
    public function simpanPresensi(Request $request, Order $order)
    {
        // Pastikan yang mengakses adalah Korlap acara ini
        abort_unless(
            $order->assignments()
                ->where('user_id', $request->user()->id)
                ->where('role', 'korlap')
                ->where('status', 'diterima')
                ->exists(),
            404
        );

        $data = $request->validate([
            'assignment_ids' => 'required|array|min:1',
            'assignment_ids.*' => 'required|integer|distinct',
        ]);

        $jumlah = $this->terjemahkan(fn () =>
            $this->assignments->markAttendance(
                $order,
                $request->user(),
                $data['assignment_ids']
            )   
        );

        return back()->with(
            'status',
            "{$jumlah} anggota berhasil dicatat kehadirannya."
        );
    }
}
