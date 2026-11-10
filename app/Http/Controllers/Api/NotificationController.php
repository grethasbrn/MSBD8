<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        return response()->json(
            AppNotification::where('user_id', $r->user()->id)
                ->when($r->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
                ->orderByDesc('id')->limit(50)->get()
        );
    }

    public function read(Request $r, AppNotification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $r->user()->id, 404);
        AppNotification::whereKey($notification->id)->whereNull('read_at')->update(['read_at' => DB::raw('now()')]);
        return response()->json(['message' => 'Ditandai sudah dibaca.']);
    }

    public function readAll(Request $r): JsonResponse
    {
        AppNotification::where('user_id', $r->user()->id)->whereNull('read_at')->update(['read_at' => DB::raw('now()')]);
        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }
}
