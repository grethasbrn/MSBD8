<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\MenerjemahkanGalat;
use App\Models\Addon;
use App\Models\Package;
use App\Services\OrderService;
use App\Support\Validasi;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Alur pemesanan sesuai dokumen:
 *   Langkah 1  /pesan              : paket utama, add-on, tanggal, lokasi, catatan (+ cek kuota)
 *   Langkah 2  /pesan/pembayaran   : rincian biaya, rekening/QRIS, unggah bukti DP
 *   Selesai    /pesanan/{kode}     : pesanan tersimpan (Pending Verification) + WhatsApp
 */
class PesanController extends Controller
{
    use MenerjemahkanGalat;

    private const TZ = 'Asia/Jakarta';

    public function __construct(private OrderService $orders) {}

    /* ------------------------------------------------------------ Langkah 1 */

    public function form(Request $request)
    {
        $tahun = Carbon::now(self::TZ)->year;

        return view('pesan.form', [
            'paket'  => Package::where('is_active', true)->orderBy('id')->get(),
            'addons' => Addon::where('is_active', true)->orderBy('id')->get(),
            'draft'  => $request->session()->get('pesan_draft', []),
            'tahun'  => [$tahun, $tahun + 1],
        ]);
    }

    public function rincian(Request $request)
    {
        $tahunIni = Carbon::now(self::TZ)->year;

        $d = $request->validate([
            'package_id'     => ['required', 'integer', Rule::exists('packages', 'id')->where('is_active', true)],
            'opsi_addon'     => 'required|in:normal,addon',
            'addon_ids'      => 'nullable|array',
            'addon_ids.*'    => 'integer',
            'tanggal'        => 'required|integer|min:1|max:31',
            'bulan'          => 'required|integer|min:1|max:12',
            'tahun'          => "required|integer|min:{$tahunIni}|max:" . ($tahunIni + 1),
            'event_time'     => 'nullable|date_format:H:i',
            'event_location' => 'required|string|min:10|max:500',
            'notes'          => 'nullable|string|max:1000',
        ], Validasi::pesan(), Validasi::nama());

        // Tanggal harus ada di kalender dan SETELAH hari ini
        if (!checkdate((int) $d['bulan'], (int) $d['tanggal'], (int) $d['tahun'])) {
            throw ValidationException::withMessages(['tanggal' => 'Tanggal yang dipilih tidak ada di kalender.']);
        }
        $tgl = Carbon::create((int) $d['tahun'], (int) $d['bulan'], (int) $d['tanggal'], 0, 0, 0, self::TZ);
        if ($tgl->lte(Carbon::now(self::TZ)->startOfDay())) {
            throw ValidationException::withMessages(['tanggal' => 'Tanggal acara harus setelah hari ini.']);
        }

        $addonIds = $d['opsi_addon'] === 'addon' ? array_values(array_unique(array_map('intval', $d['addon_ids'] ?? []))) : [];
        if ($d['opsi_addon'] === 'addon' && !$addonIds) {
            throw ValidationException::withMessages(['addon_ids' => 'Centang minimal satu add-on, atau pilih "Normal".']);
        }

        // Cek kuota harian + validasi paket/add-on lewat fungsi database
        $quote = $this->terjemahkan(fn () => $this->orders->quote((int) $d['package_id'], $addonIds, $tgl->toDateString()));
        if ($quote['quota']['is_full']) {
            throw ValidationException::withMessages(['tanggal' => 'Kuota tanggal ' . $tgl->format('d-m-Y') . ' sudah penuh. Silakan pilih tanggal lain.']);
        }

        $request->session()->put('pesan_draft', [
            'package_id'     => (int) $d['package_id'],
            'opsi_addon'     => $d['opsi_addon'],
            'addon_ids'      => $addonIds,
            'tanggal'        => (int) $d['tanggal'],
            'bulan'          => (int) $d['bulan'],
            'tahun'          => (int) $d['tahun'],
            'event_date'     => $tgl->toDateString(),
            'event_time'     => $d['event_time'] ?? null,
            'event_location' => trim($d['event_location']),
            'notes'          => isset($d['notes']) ? trim($d['notes']) : null,
        ]);

        return redirect()->route('pesan.pembayaran');
    }

    /* ------------------------------------------------------------ Langkah 2 */

    public function pembayaran(Request $request)
    {
        $draft = $request->session()->get('pesan_draft');
        if (!$draft) {
            return redirect()->route('pesan');
        }

        // Hitung ulang (harga/kuota bisa berubah sejak langkah 1)
        $quote = $this->terjemahkan(fn () => $this->orders->quote($draft['package_id'], $draft['addon_ids'], $draft['event_date']));
        if ($quote['quota']['is_full']) {
            return redirect()->route('pesan')->withErrors(['tanggal' => 'Kuota tanggal ' . Carbon::parse($draft['event_date'])->format('d-m-Y') . ' sudah penuh. Silakan pilih tanggal lain.']);
        }

        return view('pesan.pembayaran', [
            'draft'  => $draft,
            'quote'  => $quote,
            'paket'  => Package::find($draft['package_id']),
            'addons' => Addon::whereIn('id', $draft['addon_ids'])->orderBy('id')->get(),
        ]);
    }

    /** "Konfirmasi & Kirim Pesanan" */
    public function kirim(Request $request)
    {
        $draft = $request->session()->get('pesan_draft');
        if (!$draft) {
            return redirect()->route('pesan')->withErrors(['umum' => 'Sesi pemesanan berakhir. Silakan isi formulir kembali.']);
        }

        $quote = $this->terjemahkan(fn () => $this->orders->quote($draft['package_id'], $draft['addon_ids'], $draft['event_date']));

        $d = $request->validate([
            'payment_method' => 'required|in:transfer,qris,ewallet',
            'dp_amount'      => ['required', 'numeric', 'min:' . $quote['min_dp_amount']],
            'proof'          => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], Validasi::pesan() + ['dp_amount.min' => 'Nominal DP minimal ' . \App\Support\Label::rupiah($quote['min_dp_amount']) . '.'], Validasi::nama());

        $order = $this->terjemahkan(fn () => $this->orders->create($request->user(), [
            'package_id'     => $draft['package_id'],
            'addon_ids'      => $draft['addon_ids'],
            'event_date'     => $draft['event_date'],
            'event_time'     => $draft['event_time'],
            'event_location' => $draft['event_location'],
            'notes'          => $draft['notes'],
            'payment_method' => $d['payment_method'],
            'dp_amount'      => $d['dp_amount'],
        ], $request->file('proof')));

        $request->session()->forget('pesan_draft');       // cegah kirim ganda
        return redirect()->route('pesanan.show', ['order' => $order->code])->with('baru', true);
    }
}
