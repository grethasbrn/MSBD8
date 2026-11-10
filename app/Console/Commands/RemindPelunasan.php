<?php

namespace App\Console\Commands;

use App\Services\Notifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemindPelunasan extends Command
{
    protected $signature = 'sanggar:remind-pelunasan';
    protected $description = 'Kirim pengingat pelunasan ke pelanggan yang acaranya sudah tiba/lewat tetapi belum lunas';

    public function handle(): int
    {
        $rows = DB::table('v_order_ringkas')
            ->whereIn('status', ['approved', 'completed'])
            ->whereNull('lunas_at')
            ->where('event_date', '<=', DB::raw('fn_today_wib()'))
            ->get();

        $sent = 0;
        foreach ($rows as $o) {
            $already = DB::table('app_notifications')
                ->where('user_id', $o->customer_id)->where('order_id', $o->id)->where('type', 'reminder_pelunasan')
                ->whereRaw("(created_at AT TIME ZONE 'Asia/Jakarta')::date = fn_today_wib()")->exists();
            if ($already) {
                continue;
            }
            $sisa = 'Rp' . number_format((float) $o->sisa_tagihan, 0, ',', '.');
            Notifier::to($o->customer_id, 'Pengingat pelunasan',
                "Sisa tagihan pesanan {$o->code} sebesar {$sisa} belum dilunasi. Silakan unggah bukti pelunasan.",
                'reminder_pelunasan', $o->id);
            $sent++;
        }

        $this->info("Pengingat terkirim: {$sent}");
        return self::SUCCESS;
    }
}
