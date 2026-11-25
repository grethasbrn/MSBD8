<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Tx;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class RentalOrderService
{
    /**
     * Membuat pesanan rental mandiri.
     * Harga dihitung per hari x jumlah barang.
     */
    public function create(
        User $customer,
        array $data,
        UploadedFile $proof
    ): Order {
        $startDate = $data['rental_start_date'];
        $endDate = $data['rental_end_date'];

        if ($endDate < $startDate) {
            throw new DomainException(
                'Tanggal selesai rental tidak boleh sebelum tanggal mulai.'
            );
        }

        $days = (int) (
            (strtotime($endDate) - strtotime($startDate)) / 86400
        ) + 1;

        $rentals = $data['rentals'] ?? [];

        if (empty($rentals)) {
            throw new DomainException(
                'Pilih minimal satu barang untuk disewa.'
            );
        }

        $path = $proof->store('proofs/dp', 'public');

        try {
            return Tx::run($customer, function () use (
                $customer,
                $data,
                $rentals,
                $startDate,
                $endDate,
                $days,
                $path
            ) {
                $rentalRows = [];
                $rentalsTotal = 0;

                // Kunci data barang agar stok tidak dipesan bersamaan
                $itemIds = array_values(array_unique(array_map(
                    fn ($r) => (int) $r['inventory_item_id'],
                    $rentals
                )));

                $items = DB::table('inventory_items')
                    ->whereIn('id', $itemIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($items->count() !== count($itemIds)) {
                    throw new DomainException(
                        'Ada barang rental yang tidak ditemukan.'
                    );
                }

                foreach ($rentals as $rental) {
                    $itemId = (int) $rental['inventory_item_id'];
                    $quantity = (int) $rental['quantity'];

                    if ($quantity < 1) {
                        throw new DomainException(
                            'Jumlah barang rental minimal 1.'
                        );
                    }

                    $item = $items->get($itemId);

                    if (in_array($item->status, ['rusak', 'hilang'], true)) {
                        throw new DomainException(
                            "Barang {$item->name} tidak tersedia untuk disewa."
                        );
                    }

                    // Hitung jumlah barang yang sudah dipesan
                    // pada periode yang saling bertumpang tindih.
                    $booked = DB::table('order_rentals as r')
                        ->join('orders as o', 'o.id', '=', 'r.order_id')
                        ->where('r.inventory_item_id', $itemId)
                        ->whereIn('o.status', [
                            'pending_verification',
                            'approved'
                        ])
                        ->where('r.rental_start_date', '<=', $endDate)
                        ->where('r.rental_end_date', '>=', $startDate)
                        ->sum('r.quantity');

                    $available = (int) $item->stock_quantity - (int) $booked;

                    if ($quantity > $available) {
                        throw new DomainException(
                            "Stok {$item->name} tidak mencukupi. " .
                            "Stok tersedia: " . max($available, 0)
                        );
                    }

                    $price = (float) $item->rental_price;
                    $subtotal = $price * $quantity * $days;

                    $rentalsTotal += $subtotal;

                    $rentalRows[] = [
                        'inventory_item_id' => $itemId,
                        'rental_start_date' => $startDate,
                        'rental_end_date' => $endDate,
                        'quantity' => $quantity,
                        'rental_price' => $price,
                        'subtotal' => $subtotal,
                        'created_at' => now(),
                    ];
                }

                if ($rentalsTotal <= 0) {
                    throw new DomainException(
                        'Total rental harus lebih dari Rp0.'
                    );
                }

                // Mengambil persentase DP dari pengaturan sistem.
                $dpPercent = (float) \App\Models\AppSetting::read(
                    'dp_percent',
                    '30'
                );

                $minDp = ceil($rentalsTotal * $dpPercent / 100);

                $dpAmount = (float) (
                    $data['dp_amount'] ?? $minDp
                );

                if ($dpAmount < $minDp || $dpAmount > $rentalsTotal) {
                    throw new DomainException(
                        'Nominal DP tidak sesuai. Minimal DP: Rp' .
                        number_format($minDp, 0, ',', '.')
                    );
                }

                // Pesanan rental tidak memiliki paket atau tanggal acara.
                $order = Order::create([
                    'customer_id' => $customer->id,
                    'package_id' => null,
                    'event_date' => null,
                    'event_time' => null,
                    'event_location' => null,
                    'notes' => $data['notes'] ?? null,
                    'package_price' => 0,
                    'addons_total' => 0,
                    'rentals_total' => $rentalsTotal,
                    'total_amount' => $rentalsTotal,
                    'min_dp_amount' => $minDp,
                ]);

                foreach ($rentalRows as &$row) {
                    $row['order_id'] = $order->id;
                }
                unset($row);

                DB::table('order_rentals')->insert($rentalRows);

                Payment::create([
                    'order_id' => $order->id,
                    'type' => 'dp',
                    'method' => $data['payment_method'],
                    'amount' => $dpAmount,
                    'proof_path' => $path,
                ]);

                return $order->refresh();
            }, 'Pelanggan membuat pesanan rental');

        } catch (Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
    }
}
