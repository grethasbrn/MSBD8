<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Package;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BerandaController extends Controller
{
    private const NAMA_BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    public function index(Request $request)
    {
        return view('beranda', [
            'paket tersedia'    => Package::where('is_active', true)->orderBy('id')->get(),
            'addons'   => Addon::where('is_active', true)->orderBy('id')->get(),
            'kalender' => $this->kalender($request->query('bulan')),
        ]);
    }

    /** Menyusun kalender satu bulan dari fungsi database fn_kalender (kuota per tanggal). */
    private function kalender(?string $param): array
    {
        $tz = 'Asia/Jakarta';
        $bulanIni = Carbon::now($tz)->startOfMonth();
        $batas    = $bulanIni->copy()->addMonths(12);

        $bulan = $bulanIni->copy();
        if ($param && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $param)) {
            $bulan = Carbon::createFromFormat('!Y-m', $param, $tz);
        }
        if ($bulan->lt($bulanIni)) $bulan = $bulanIni->copy();   // tidak mundur ke bulan yang sudah lewat
        if ($bulan->gt($batas))    $bulan = $batas->copy();      // maksimal 12 bulan ke depan

        $jumlahHari = $bulan->daysInMonth;
        $data = collect(DB::select('select * from fn_kalender(?, ?)', [
            $bulan->toDateString(), $bulan->copy()->endOfMonth()->toDateString(),
        ]))->keyBy('event_date');

        $hariIni = Carbon::now($tz)->toDateString();
        $sel = [];
        for ($d = 1; $d <= $jumlahHari; $d++) {
            $tgl = $bulan->copy()->day($d)->toDateString();
            $row = $data[$tgl] ?? null;

            if ($tgl <= $hariIni)                              { $status = 'lewat'; }      // pesanan baru hanya untuk besok dan seterusnya
            elseif ($row && $row->is_full)                     { $status = 'penuh'; }
            elseif ($row && (int) $row->available_slots === 1) { $status = 'terbatas'; }
            else                                               { $status = 'tersedia'; }

            $sel[] = ['hari' => $d, 'status' => $status];
        }

        return [
            'judul' => self::NAMA_BULAN[(int) $bulan->format('n')] . ' ' . $bulan->format('Y'),
            'kosong_awal' => $bulan->dayOfWeek,            // 0 = Minggu
            'sel' => $sel,
            'prev' => $bulan->gt($bulanIni) ? $bulan->copy()->subMonth()->format('Y-m') : null,
            'next' => $bulan->lt($batas)    ? $bulan->copy()->addMonth()->format('Y-m')  : null,
        ];
    }
}
