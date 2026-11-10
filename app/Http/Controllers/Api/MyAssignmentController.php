<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventAssignment;
use App\Services\AssignmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Untuk Penari, Pemusik, Helper (dan Korlap): tawaran tugas, riwayat job, status honor. */
class MyAssignmentController extends Controller
{
    public function __construct(private AssignmentService $assignments) {}

    /** Daftar penugasan saya + susunan tim lain pada tiap acara. */
    public function index(Request $r): JsonResponse
    {
        $r->validate(['status' => 'nullable|in:ditawarkan,diterima,ditolak,dibatalkan']);

        $items = EventAssignment::where('user_id', $r->user()->id)
            ->when($r->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->with(['order:id,code,status,event_date,event_time,event_location,notes,package_id', 'order.package:id,name,category', 'honorarium'])
            ->orderByDesc('event_date')->get();

        $teams = EventAssignment::whereIn('order_id', $items->pluck('order_id')->unique())
            ->whereIn('status', ['ditawarkan', 'diterima'])
            ->with('user:id,name,specialization')->get()->groupBy('order_id');

        $items->each(fn ($a) => $a->setAttribute('team', ($teams[$a->order_id] ?? collect())->map(fn ($t) => [
            'name' => $t->user->name, 'role' => $t->role, 'instrument' => $t->instrument, 'status' => $t->status,
        ])->values()));

        return response()->json($items);
    }

    /** Terima / Tolak tawaran. Body: {"action": "accept" | "reject"} */
    public function respond(Request $r, EventAssignment $assignment): JsonResponse
    {
        $d = $r->validate(['action' => 'required|in:accept,reject']);
        $a = $this->assignments->respond($assignment, $r->user(), $d['action']);
        return response()->json(['message' => $d['action'] === 'accept' ? 'Tugas diterima.' : 'Tugas ditolak.', 'assignment' => $a]);
    }

    /** Riwayat job yang sudah dihadiri + status pencairan honor. */
    public function honorariums(Request $r): JsonResponse
    {
        $rows = EventAssignment::where('user_id', $r->user()->id)->whereNotNull('attended_at')
            ->with(['order:id,code,status,event_date,event_location', 'honorarium'])
            ->orderByDesc('event_date')->get()
            ->map(fn ($a) => [
                'assignment_id' => $a->id,
                'role'          => $a->role,
                'order'         => $a->order,
                'status_honor'  => $a->honorarium ? 'cair' : 'belum_cair',
                'jumlah'        => $a->honorarium?->amount,
                'dicairkan_pada' => $a->honorarium?->paid_at,
            ]);

        return response()->json([
            'riwayat'      => $rows,
            'total_cair'   => (float) $rows->where('status_honor', 'cair')->sum('jumlah'),
            'jumlah_belum' => $rows->where('status_honor', 'belum_cair')->count(),
        ]);
    }
}
