<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\GalleryItem;
use App\Models\InventoryItem;
use App\Models\Package;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Katalog publik: dapat diakses tanpa login. */
class PublicController extends Controller
{
    public function packages(Request $r): JsonResponse
    {
        $r->validate(['category' => 'nullable|in:tari,musik']);
        return response()->json(
            Package::where('is_active', true)
                ->when($r->query('category'), fn ($q, $c) => $q->where('category', $c))
                ->orderBy('category')->orderBy('price')->get()
        );
    }

    public function package(int $id): JsonResponse
    {
        return response()->json(Package::where('is_active', true)->findOrFail($id));
    }

    public function addons(): JsonResponse
    {
        return response()->json(Addon::where('is_active', true)->orderBy('price')->get());
    }

    /** Katalog sewa kostum & alat musik beserta status ketersediaan (real-time dari database). */
    public function inventory(Request $r): JsonResponse
    {
        $r->validate([
            'category' => 'nullable|in:kostum,aksesoris,alat_musik',
            'status'   => 'nullable|in:tersedia,dipinjam,rusak,hilang',
        ]);
        return response()->json(
            InventoryItem::query()
                ->when($r->query('category'), fn ($q, $c) => $q->where('category', $c))
                ->when($r->query('status'), fn ($q, $s) => $q->where('status', $s))
                ->orderBy('category')->orderBy('name')->get()
        );
    }

    public function gallery(): JsonResponse
    {
        return response()->json(GalleryItem::where('is_published', true)->orderByDesc('event_date')->orderByDesc('id')->get());
    }

    /** Kalender kuota publik: sisa slot per tanggal (maks. 92 hari per permintaan). */
    public function calendar(Request $r): JsonResponse
    {
        $d = $r->validate([
            'from' => 'required|date_format:Y-m-d',
            'to'   => 'required|date_format:Y-m-d|after_or_equal:from',
        ]);
        if (strtotime($d['to']) - strtotime($d['from']) > 92 * 86400) {
            return response()->json(['message' => 'Rentang tanggal maksimal 92 hari.'], 422);
        }
        $rows = DB::select('select * from fn_kalender(?, ?)', [$d['from'], $d['to']]);
        return response()->json(array_map(fn ($x) => [
            'date'            => $x->event_date,
            'booked_slots'    => (int) $x->booked_slots,
            'max_slots'       => (int) $x->max_slots,
            'available_slots' => (int) $x->available_slots,
            'is_full'         => filter_var($x->is_full, FILTER_VALIDATE_BOOLEAN),
        ], $rows));
    }

    public function paymentInfo(OrderService $orders): JsonResponse
    {
        return response()->json($orders->paymentInfo());
    }
}
