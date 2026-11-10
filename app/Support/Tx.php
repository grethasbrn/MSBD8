<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Menjalankan blok kode dalam satu transaksi database.
 * Sekaligus memberi tahu database "siapa pelakunya" (app.user_id) dan catatannya (app.note),
 * sehingga trigger bisa mengisi tabel order_status_logs dengan benar.
 */
final class Tx
{
    public static function run(?User $actor, callable $fn, ?string $note = null): mixed
    {
        return DB::transaction(function () use ($actor, $fn, $note) {
            if ($actor) {
                DB::select("select set_config('app.user_id', ?, true)", [(string) $actor->id]);
            }
            if ($note !== null) {
                DB::select("select set_config('app.note', ?, true)", [$note]);
            }
            return $fn();
        });
    }
}
