<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventAssignment;
use App\Models\ItemLoan;
use App\Models\Order;
use App\Services\Notifier;
use App\Support\Tx;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Logistik Helper (dibantu Korlap): barang keluar & pengembalian. Status katalog diubah otomatis oleh trigger. */
class LogisticsController extends Controller
{
    /** Acara yang saya tangani (sebagai Helper atau Korlap). */
    public function orders(Request $r): JsonResponse
    {
        return response()->json(
            Order::whereHas('assignments', fn ($q) => $q->where('user_id', $r->user()->id)
                    ->whereIn('role', ['helper', 'korlap'])->where('status', 'diterima'))
                ->whereIn('status', ['approved', 'completed'])
                ->with('package:id,name')->orderBy('event_date')
                ->get(['id', 'code', 'status', 'event_date', 'event_time', 'event_location', 'notes', 'package_id'])
        );
    }

    public function loans(Request $r, Order $order): JsonResponse
    {
        $this->onTeam($r, $order);
        return response()->json($order->loans()->with('item:id,code,name,category,status')->orderBy('id')->get());
    }

    /** Catat barang keluar. Body: {"item_ids": [1,2,3]} (semua berhasil, atau semua dibatalkan). */
    public function borrow(Request $r, Order $order): JsonResponse
    {
        $this->onTeam($r, $order);
        $d = $r->validate(['item_ids' => 'required|array|min:1', 'item_ids.*' => 'integer|distinct|exists:inventory_items,id']);

        Tx::run($r->user(), function () use ($d, $order, $r) {
            foreach ($d['item_ids'] as $itemId) {
                ItemLoan::create(['item_id' => $itemId, 'order_id' => $order->id, 'borrowed_by' => $r->user()->id]);
            }
        });

        return response()->json(['message' => 'Barang tercatat keluar.',
            'loans' => $order->loans()->with('item:id,code,name,status')->whereNull('returned_at')->get()], 201);
    }

    /**
     * Catat pengembalian + kondisi.
     * Body: {"returns": [{"loan_id": 5, "condition": "baik|rusak|hilang", "notes": "..."}]}
     */
    public function returnItems(Request $r, Order $order): JsonResponse
    {
        $this->onTeam($r, $order);
        $d = $r->validate([
            'returns'              => 'required|array|min:1',
            'returns.*.loan_id'    => 'required|integer|distinct',
            'returns.*.condition'  => 'required|in:baik,rusak,hilang',
            'returns.*.notes'      => 'nullable|string|max:500',
        ]);

        Tx::run($r->user(), function () use ($d, $order, $r) {
            $problems = [];
            foreach ($d['returns'] as $row) {
                $loan = ItemLoan::where('order_id', $order->id)->whereNull('returned_at')->lockForUpdate()->find($row['loan_id']);
                if (!$loan) {
                    throw new DomainException("Data peminjaman #{$row['loan_id']} tidak ditemukan atau sudah dikembalikan.");
                }
                ItemLoan::whereKey($loan->id)->update([
                    'returned_at' => DB::raw('now()'), 'returned_by' => $r->user()->id,
                    'return_condition' => $row['condition'], 'notes' => $row['notes'] ?? null,
                ]);
                if ($row['condition'] !== 'baik') {
                    $problems[] = $loan->item->name . " ({$row['condition']})";
                }
            }
            if ($problems) {   // lapor ke Owner dan Korlap
                $korlapIds = EventAssignment::where('order_id', $order->id)->where('role', 'korlap')->where('status', 'diterima')->pluck('user_id')->all();
                $text = "Acara {$order->code}: " . implode(', ', $problems) . ' dilaporkan bermasalah.';
                Notifier::toRole('owner', 'Barang rusak/hilang', $text, 'item_problem', $order->id);
                Notifier::to($korlapIds, 'Barang rusak/hilang', $text, 'item_problem', $order->id);
            }
        });

        return response()->json(['message' => 'Pengembalian tercatat.',
            'loans' => $order->loans()->with('item:id,code,name,status')->orderBy('id')->get()]);
    }

    private function onTeam(Request $r, Order $order): void
    {
        $ok = EventAssignment::where('order_id', $order->id)->where('user_id', $r->user()->id)
            ->whereIn('role', ['helper', 'korlap'])->where('status', 'diterima')->exists();
        abort_unless($ok, 403, 'Anda tidak bertugas pada acara ini.');
    }
}
