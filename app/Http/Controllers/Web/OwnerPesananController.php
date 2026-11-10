<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use App\Models\Order;
use App\Models\Package;
use App\Services\OrderService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class OwnerPesananController extends Controller
{
    public function __construct(
        private OrderService $orders
    ) {
    }

    /**
     * Menampilkan form pembuatan pesanan oleh Owner.
     */
    public function create(): View
    {
        $packages = Package::where('is_active', true)
            ->orderBy('name')
            ->get();

        $addons = Addon::where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('dashboard.owner-pesan', compact(
            'packages',
            'addons'
        ));
    }

    /**
     * Menyimpan pesanan pelanggan yang dicatat oleh Owner.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'contact_name' => [
                'required',
                'string',
                'max:150',
            ],

            'contact_phone' => [
                'required',
                'string',
                'max:30',
            ],

            'package_id' => [
                'required',
                'integer',
                Rule::exists('packages', 'id')
                    ->where('is_active', true),
            ],

            'addon_ids' => [
                'nullable',
                'array',
            ],

            'addon_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('addons', 'id')
                    ->where('is_active', true),
            ],

            'event_date' => [
                'required',
                'date',
                'after_or_equal:today',
            ],

            'event_time' => [
                'nullable',
                'date_format:H:i',
            ],

            'event_location' => [
                'required',
                'string',
                'max:1000',
            ],

            'notes' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'dp_amount' => [
                'required',
                'numeric',
                'min:1',
            ],

            'payment_method' => [
                'required',
                Rule::in(['tunai', 'transfer', 'qris', 'ewallet']),
            ],

            'proof' => [
                'nullable',
                'required_unless:payment_method,tunai',
                'file',
                'mimes:jpg,jpeg,png,webp,pdf',
                'max:5120',
            ],
        ], [
            'contact_name.required' => 'Nama pelanggan wajib diisi.',
            'contact_phone.required' => 'Nomor telepon pelanggan wajib diisi.',
            'package_id.required' => 'Silakan pilih paket acara.',
            'addon_ids.*.exists' => 'Ada add-on yang tidak tersedia.',
            'event_date.required' => 'Tanggal acara wajib diisi.',
            'event_date.after_or_equal' => 'Tanggal acara tidak boleh sebelum hari ini.',
            'event_location.required' => 'Lokasi acara wajib diisi.',
            'dp_amount.required' => 'Nominal DP wajib diisi.',
            'payment_method.required' => 'Silakan pilih metode pembayaran.',
            'proof.required_unless' => 'Bukti pembayaran wajib diunggah untuk pembayaran non-tunai.',
            'proof.max' => 'Ukuran bukti pembayaran maksimal 5 MB.',
            'proof.mimes' => 'Bukti pembayaran harus berupa JPG, PNG, WEBP, atau PDF.',
        ]);

        try {
            $order = $this->orders->createByOwner(
                $request->user(),
                $validated,
                $request->file('proof')
            );

            return redirect()
                ->route('owner.pesanan.show', $order)
                ->with(
                    'success',
                    'Pesanan atas nama ' . $order->contact_name . ' berhasil dibuat.'
                );

        } catch (DomainException $e) {
            throw ValidationException::withMessages([
                'pesanan' => $e->getMessage(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return back()
                ->withInput()
                ->withErrors([
                    'pesanan' => 'Pesanan gagal disimpan. Periksa kembali data dan pembayaran.',
                ]);
        }
    }
}
