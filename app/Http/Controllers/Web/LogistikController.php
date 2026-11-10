<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\InventoryItem;
use App\Models\ItemLoan;
use App\Models\EventAssignment;
use App\Services\Notifier;
use App\Support\Tx;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;
use DomainException;

class LogistikController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $orders = Order::whereHas('assignments', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->whereIn('role', ['helper', 'korlap'])
                ->where('status', 'diterima');
        })
        ->whereIn('status', ['approved', 'completed'])
        ->with('package:id,name')
        ->orderBy('event_date')
        ->get();

        $items = InventoryItem::orderBy('name')->get();

        return view('dashboard.logistik', compact('orders', 'items'));
    }

    public function show(Order $order)
    {
        $this->checkTeam($order);

        $loans = $order->loans()
            ->with('item')
            ->orderBy('id')
            ->get();

        $items = InventoryItem::orderBy('name')->get();

        return view(
            'dashboard.logistik-detail',
            compact('order', 'loans', 'items')
        );
    }

    // PINJAM BARANG
    public function borrow(Request $request, Order $order)
    {
        $this->checkTeam($order);

        // Peminjaman hanya boleh mulai H-1 acara
        $tanggalAcara = Carbon::parse($order->event_date)->startOfDay();
        $waktuMulaiPinjam = $tanggalAcara->copy()->subDay();

        if (now()->lt($waktuMulaiPinjam)) {
            throw ValidationException::withMessages([
                'item_ids' => 'Peminjaman perlengkapan baru dapat dilakukan mulai H-1 acara.'
            ]);
        }

        $data = $request->validate([
            'item_ids' => 'required|array|min:1',
            'item_ids.*' => 'integer|distinct|exists:inventory_items,id',
        ]);

        try {
            Tx::run($request->user(), function () use ($data, $order, $request) {
                foreach ($data['item_ids'] as $itemId) {
                    ItemLoan::create([
                        'item_id' => $itemId,
                        'order_id' => $order->id,
                        'borrowed_by' => $request->user()->id,
                    ]);
                }
            });
        } catch (DomainException $e) {
            throw ValidationException::withMessages([
                'item_ids' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route(auth()->user()->role . '.logistik.show', $order->id)
            ->with('success', 'Peminjaman barang berhasil dicatat.');
    }

    // KEMBALIKAN BARANG
    public function returnItems(Request $request, Order $order)
    {
        $this->checkTeam($order);

        // Pengembalian hanya boleh pada tanggal acara atau setelahnya
        $tanggalAcara = Carbon::parse($order->event_date)->startOfDay();

        if (now()->startOfDay()->lt($tanggalAcara)) {
            throw ValidationException::withMessages([
                'returns' => 'Pengembalian perlengkapan baru dapat dilakukan pada tanggal acara atau setelah acara selesai.'
            ]);
        }

        $data = $request->validate([
            'returns' => 'required|array|min:1',
            'returns.*.loan_id' => 'required|integer|distinct',
            'returns.*.condition' => 'required|in:baik,rusak,hilang',
            'returns.*.notes' => 'nullable|string|max:500',
        ]);

        try {
            Tx::run($request->user(), function () use ($data, $order, $request) {
                $problems = [];

                foreach ($data['returns'] as $row) {
                    $loan = ItemLoan::where('order_id', $order->id)
                        ->whereNull('returned_at')
                        ->lockForUpdate()
                        ->find($row['loan_id']);

                    if (!$loan) {
                        throw new DomainException(
                            "Data peminjaman #{$row['loan_id']} tidak ditemukan atau sudah dikembalikan."
                        );
                    }

                    ItemLoan::whereKey($loan->id)->update([
                        'returned_at' => DB::raw('now()'),
                        'returned_by' => $request->user()->id,
                        'return_condition' => $row['condition'],
                        'notes' => $row['notes'] ?? null,
                    ]);

                    if ($row['condition'] !== 'baik') {
                        $problems[] = $loan->item->name . " ({$row['condition']})";
                    }
                }

                // Kirim notifikasi jika ada barang rusak atau hilang
                if ($problems) {
                    $korlapIds = EventAssignment::where('order_id', $order->id)
                        ->where('role', 'korlap')
                        ->where('status', 'diterima')
                        ->pluck('user_id')
                        ->all();

                    $text = "Acara {$order->code}: "
                        . implode(', ', $problems)
                        . ' dilaporkan bermasalah.';

                    Notifier::toRole(
                        'owner',
                        'Barang rusak/hilang',
                        $text,
                        'item_problem',
                        $order->id
                    );

                    Notifier::to(
                        $korlapIds,
                        'Barang rusak/hilang',
                        $text,
                        'item_problem',
                        $order->id
                    );
                }
            });
        } catch (DomainException $e) {
            throw ValidationException::withMessages([
                'returns' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route(auth()->user()->role . '.logistik.show', $order->id)
            ->with('success', 'Pengembalian barang berhasil dicatat.');
    }

    // CEK APAKAH PENGGUNA TERMASUK TIM ACARA
    private function checkTeam(Order $order): void
    {
        $ok = $order->assignments()
            ->where('user_id', auth()->id())
            ->whereIn('role', ['helper', 'korlap'])
            ->where('status', 'diterima')
            ->exists();

        abort_unless($ok, 403, 'Anda tidak bertugas pada acara ini.');
    }
}
