<?php

namespace App\Services;

use App\Models\Addon;
use App\Models\AppSetting;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Tx;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Seluruh alur pesanan & pembayaran.
 * Aturan inti (kuota, DP minimal, urutan status, DP hangus, Lunas) dijaga oleh database;
 * service ini mengatur urutan langkah, transaksi, dan notifikasi.
 */
class OrderService
{
    // ------------------------------------------------------------------ info umum

    public function paymentInfo(): array
    {
        return [
            'dp_percent'          => (float) AppSetting::read('dp_percent', '30'),
            'bank_name'           => AppSetting::read('bank_name'),
            'bank_account_number' => AppSetting::read('bank_account_number'),
            'bank_account_name'   => AppSetting::read('bank_account_name'),
            'qris_url'            => AppSetting::read('qris_url'),
        ];
    }

    private function calculate(int $packageId, array $addonIds): object
    {
        $ids = array_values(array_unique(array_map('intval', $addonIds)));

        return DB::selectOne(
            'select * from fn_hitung_biaya(?, ?::bigint[])',
            [$packageId, '{' . implode(',', $ids) . '}']
        );
    }

    private function slotInfo(string $date): object
    {
        return DB::selectOne('select * from fn_kalender(?, ?)', [$date, $date]);
    }

    private function isFull(object $slot): bool
    {
        return filter_var($slot->is_full, FILTER_VALIDATE_BOOLEAN);
    }

    private function rupiah(float|int|string $n): string
    {
        return 'Rp' . number_format((float) $n, 0, ',', '.');
    }

    // ------------------------------------------------------------------ pelanggan

    /** Rincian biaya sebelum pelanggan membayar (Total & Minimal DP). */
    public function quote(int $packageId, array $addonIds, ?string $date): array
    {
        $b = $this->calculate($packageId, $addonIds);

        $result = [
            'package_price'          => (float) $b->package_price,
            'addons_total'           => (float) $b->addons_total,
            'total_amount'           => (float) $b->total_amount,
            'min_dp_amount'          => (float) $b->min_dp_amount,
            'remaining_after_min_dp' => (float) $b->total_amount - (float) $b->min_dp_amount,
            'payment_info'           => $this->paymentInfo(),
            'quota'                  => null,
        ];

        if ($date) {
            $s = $this->slotInfo($date);

            $result['quota'] = [
                'date'            => $date,
                'available_slots' => (int) $s->available_slots,
                'max_slots'       => (int) $s->max_slots,
                'is_full'         => $this->isFull($s),
            ];
        }

        return $result;
    }

    /** Pelanggan membuat pesanan dan mengunggah bukti DP. */
    public function create(User $customer, array $data, UploadedFile $proof): Order
    {
        $slot = $this->slotInfo($data['event_date']);

        if ($this->isFull($slot)) {
            throw new DomainException(
                'Kuota tanggal ' .
                date('d-m-Y', strtotime($data['event_date'])) .
                ' sudah penuh. Silakan pilih tanggal lain.'
            );
        }

        $addonIds = array_values(array_unique(
            array_map('intval', $data['addon_ids'] ?? [])
        ));

        $path = $proof->store('proofs/dp', 'public');

        try {
            return Tx::run($customer, function () use ($customer, $data, $addonIds, $path) {
                $b = $this->calculate((int) $data['package_id'], $addonIds);

                $order = Order::create([
                    'customer_id'    => $customer->id,
                    'package_id'     => (int) $data['package_id'],
                    'event_date'     => $data['event_date'],
                    'event_time'     => $data['event_time'] ?? null,
                    'event_location' => $data['event_location'],
                    'notes'          => $data['notes'] ?? null,
                    'package_price'  => $b->package_price,
                    'addons_total'   => $b->addons_total,
                    'total_amount'   => $b->total_amount,
                    'min_dp_amount'  => $b->min_dp_amount,
                ]);

                if ($addonIds) {
                    $rows = Addon::whereIn('id', $addonIds)->get()->map(fn ($a) => [
                        'order_id'       => $order->id,
                        'addon_id'       => $a->id,
                        'price_at_order' => $a->price,
                    ])->all();

                    DB::table('order_addons')->insert($rows);
                }

                Payment::create([
                    'order_id'   => $order->id,
                    'type'       => 'dp',
                    'method'     => $data['payment_method'],
                    'amount'     => $data['dp_amount'] ?? $b->min_dp_amount,
                    'proof_path' => $path,
                ]);

                $order->refresh();

                Notifier::toRole(
                    'owner',
                    'Pesanan baru menunggu verifikasi',
                    "Pesanan {$order->code} dari {$customer->name} perlu dicek bukti DP-nya.",
                    'order_new',
                    $order->id
                );

                return $order;
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
    }

    // ------------------------------------------------------------------ Owner: membuat pesanan atas nama pelanggan

    /**
     * Owner membuat pesanan atas nama pelanggan.
     * DP tunai langsung diverifikasi.
     * DP non-tunai menunggu verifikasi Owner.
     */
    public function createByOwner(
        User $owner,
        array $data,
        ?UploadedFile $proof = null
    ): Order {
        $method = $data['payment_method'];
        $isCash = $method === 'tunai';

        if (!$isCash && !$proof) {
            throw new DomainException(
                'Bukti pembayaran wajib diunggah untuk pembayaran non-tunai.'
            );
        }

        $path = $proof?->store('proofs/dp', 'public');

        try {
            return Tx::run($owner, function () use (
                $owner,
                $data,
                $method,
                $isCash,
                $path
            ) {
                $slot = $this->slotInfo($data['event_date']);

                if ($this->isFull($slot)) {
                    throw new DomainException(
                        'Kuota tanggal ' .
                        date('d-m-Y', strtotime($data['event_date'])) .
                        ' sudah penuh. Silakan pilih tanggal lain.'
                    );
                }

                $addonIds = array_values(array_unique(
                    array_map('intval', $data['addon_ids'] ?? [])
                ));

                $b = $this->calculate(
                    (int) $data['package_id'],
                    $addonIds
                );

                $dpAmount = (float) $data['dp_amount'];

                if ($dpAmount < (float) $b->min_dp_amount) {
                    throw new DomainException(
                        'Nominal DP kurang dari minimal ' .
                        $this->rupiah($b->min_dp_amount)
                    );
                }

                $order = Order::create([
                    'customer_id'    => $owner->id,
                    'contact_name'   => $data['contact_name'],
                    'contact_phone'  => $data['contact_phone'],
                    'package_id'     => (int) $data['package_id'],
                    'event_date'     => $data['event_date'],
                    'event_time'     => $data['event_time'] ?? null,
                    'event_location' => $data['event_location'],
                    'notes'          => $data['notes'] ?? null,
                    'package_price'  => $b->package_price,
                    'addons_total'   => $b->addons_total,
                    'total_amount'   => $b->total_amount,
                    'min_dp_amount'  => $b->min_dp_amount,
                ]);

                if ($addonIds) {
                    $rows = Addon::whereIn('id', $addonIds)
                        ->get()
                        ->map(fn ($a) => [
                            'order_id'       => $order->id,
                            'addon_id'       => $a->id,
                            'price_at_order' => $a->price,
                        ])
                        ->all();

                    DB::table('order_addons')->insert($rows);
                }

                $paymentData = [
                    'order_id'   => $order->id,
                    'type'       => 'dp',
                    'method'     => $method,
                    'amount'     => $dpAmount,
                    'proof_path' => $path,
                ];

                if ($isCash) {
                    $paymentData['status'] = 'verified';
                    $paymentData['received_by'] = $owner->id;
                    $paymentData['verified_by'] = $owner->id;
                    $paymentData['verified_at'] = DB::raw('now()');
                }

                Payment::create($paymentData);

                if ($isCash) {
                    // Trigger database memeriksa DP dan mengunci kuota.
                    $order->update(['status' => 'approved']);
                } else {
                    Notifier::toRole(
                        'owner',
                        'Pesanan baru menunggu verifikasi',
                        "Pesanan {$order->code} atas nama {$data['contact_name']} menunggu verifikasi DP.",
                        'order_new',
                        $order->id
                    );
                }

                return $order->refresh();
            }, 'Owner membuat pesanan pelanggan');
        } catch (Throwable $e) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }

            throw $e;
        }
    }

    // ------------------------------------------------------------------ WhatsApp

    /** Link WhatsApp berisi rincian pesanan & ID Transaksi. */
    public function whatsappUrl(Order $order): string
    {
        $order->loadMissing(['package', 'addons', 'customer']);

        $dp = $order->payments()
            ->where('type', 'dp')
            ->orderByDesc('id')
            ->value('amount') ?? $order->min_dp_amount;

        $namaPelanggan = $order->contact_name ?: $order->customer->name;

        $text = implode("\n", [
            'Halo Sanggar, saya ingin mengonfirmasi pesanan:',
            "ID Transaksi: {$order->code}",
            "Nama: {$namaPelanggan}",
            "Paket: {$order->package->name}",
            'Add-on: ' . ($order->addons->pluck('name')->implode(', ') ?: '-'),
            'Tanggal acara: ' . $order->event_date->format('d-m-Y'),
            "Lokasi: {$order->event_location}",
            'Total biaya: ' . $this->rupiah($order->total_amount),
            'DP: ' . $this->rupiah($dp),
        ]);

        return 'https://wa.me/' .
            AppSetting::read('wa_number') .
            '?text=' . rawurlencode($text);
    }

    // ------------------------------------------------------------------ bukti pembayaran pelanggan

    public function uploadDpProof(
        Order $order,
        User $customer,
        UploadedFile $file,
        string $method,
        ?float $amount
    ): Payment {
        if ($order->status !== 'pending_verification') {
            throw new DomainException(
                'Bukti DP hanya bisa diunggah saat pesanan menunggu verifikasi.'
            );
        }

        return $this->storeProofPayment(
            $order,
            $customer,
            'dp',
            $file,
            $method,
            $amount ?? (float) $order->min_dp_amount
        );
    }

    public function uploadPelunasan(
        Order $order,
        User $customer,
        UploadedFile $file,
        string $method,
        ?float $amount
    ): Payment {
        return $this->storeProofPayment(
            $order,
            $customer,
            'pelunasan',
            $file,
            $method,
            $amount ?? $this->remaining($order)
        );
    }

    public function remaining(Order $order): float
    {
        $paid = (float) Payment::where('order_id', $order->id)
            ->where('status', 'verified')
            ->sum('amount');

        return max((float) $order->total_amount - $paid, 0);
    }

    private function storeProofPayment(
        Order $order,
        User $actor,
        string $type,
        UploadedFile $file,
        string $method,
        float $amount
    ): Payment {
        $path = $file->store("proofs/{$type}", 'public');

        try {
            return Tx::run($actor, function () use (
                $order,
                $type,
                $method,
                $amount,
                $path,
                $actor
            ) {
                $p = Payment::create([
                    'order_id'   => $order->id,
                    'type'       => $type,
                    'method'     => $method,
                    'amount'     => $amount,
                    'proof_path' => $path,
                ]);

                $label = $type === 'dp' ? 'DP' : 'pelunasan';

                Notifier::toRole(
                    'owner',
                    "Bukti {$label} baru",
                    "Pesanan {$order->code}: {$actor->name} mengunggah bukti {$label}.",
                    'payment_new',
                    $order->id
                );

                return $p->refresh();
            });
        } catch (Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }
    }

    // ------------------------------------------------------------------ Korlap: pelunasan tunai

    /** Korlap mencatat uang tunai pelunasan yang diterima di hari-H. */
    public function recordCashPelunasan(
        Order $order,
        User $korlap,
        ?float $amount
    ): Payment {
        return Tx::run($korlap, function () use ($order, $korlap, $amount) {
            $p = Payment::create([
                'order_id'   => $order->id,
                'type'       => 'pelunasan',
                'method'     => 'tunai',
                'amount'     => $amount ?? $this->remaining($order),
                'received_by' => $korlap->id,
            ]);

            Notifier::toRole(
                'owner',
                'Pelunasan tunai dicatat',
                "Korlap {$korlap->name} menerima pelunasan tunai untuk pesanan {$order->code}.",
                'payment_new',
                $order->id
            );

            return $p->refresh();
        });
    }

    // ------------------------------------------------------------------ Owner: verifikasi pembayaran

    public function approveDp(Order $order, User $owner): Order
    {
        return Tx::run($owner, function () use ($order, $owner) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();

            $payment = Payment::where('order_id', $order->id)
                ->where('type', 'dp')
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw new DomainException(
                    'Tidak ada bukti DP yang menunggu verifikasi.'
                );
            }

            Payment::whereKey($payment->id)->update([
                'status'      => 'verified',
                'verified_by' => $owner->id,
                'verified_at' => DB::raw('now()'),
            ]);

            $order->update(['status' => 'approved']);

            Notifier::to(
                $order->customer_id,
                'DP disetujui',
                "Pesanan {$order->code} telah disetujui. Slot tanggal acara Anda sudah terkunci.",
                'dp_approved',
                $order->id
            );

            return $order->refresh();
        }, 'DP disetujui');
    }

    public function rejectDp(
        Order $order,
        User $owner,
        string $reason
    ): Payment {
        return Tx::run($owner, function () use ($order, $owner, $reason) {
            $payment = Payment::where('order_id', $order->id)
                ->where('type', 'dp')
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                throw new DomainException(
                    'Tidak ada bukti DP yang menunggu verifikasi.'
                );
            }

            Payment::whereKey($payment->id)->update([
                'status'       => 'rejected',
                'reject_reason' => $reason,
                'verified_by'  => $owner->id,
                'verified_at'  => DB::raw('now()'),
            ]);

            Notifier::to(
                $order->customer_id,
                'Bukti DP ditolak',
                "Pesanan {$order->code}: {$reason}. Silakan unggah ulang bukti transfer.",
                'dp_rejected',
                $order->id
            );

            return $payment->refresh();
        });
    }

    public function verifyPelunasan(Payment $payment, User $owner): Payment
    {
        return Tx::run($owner, function () use ($payment, $owner) {
            $payment = $this->lockPendingPelunasan($payment);

            Payment::whereKey($payment->id)->update([
                'status'      => 'verified',
                'verified_by' => $owner->id,
                'verified_at' => DB::raw('now()'),
            ]);

            $order = Order::findOrFail($payment->order_id);

            Notifier::to(
                $order->customer_id,
                'Pelunasan diterima',
                "Pembayaran pesanan {$order->code} telah dinyatakan Lunas. Terima kasih!",
                'lunas',
                $order->id
            );

            return $payment->refresh();
        });
    }

    public function rejectPelunasan(
        Payment $payment,
        User $owner,
        string $reason
    ): Payment {
        return Tx::run($owner, function () use ($payment, $owner, $reason) {
            $payment = $this->lockPendingPelunasan($payment);

            Payment::whereKey($payment->id)->update([
                'status'       => 'rejected',
                'reject_reason' => $reason,
                'verified_by'  => $owner->id,
                'verified_at'  => DB::raw('now()'),
            ]);

            $order = Order::findOrFail($payment->order_id);

            Notifier::to(
                $order->customer_id,
                'Bukti pelunasan ditolak',
                "Pesanan {$order->code}: {$reason}. Silakan unggah ulang.",
                'payment_rejected',
                $order->id
            );

            return $payment->refresh();
        });
    }

    private function lockPendingPelunasan(Payment $payment): Payment
    {
        $p = Payment::whereKey($payment->id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($p->type !== 'pelunasan') {
            throw new DomainException(
                'Pembayaran DP diverifikasi lewat tombol Approve DP / Reject DP.'
            );
        }

        if ($p->status !== 'pending') {
            throw new DomainException('Pembayaran ini sudah diproses.');
        }

        return $p;
    }

    // ------------------------------------------------------------------ pembatalan pesanan

    public function cancel(
        Order $order,
        User $owner,
        string $reason
    ): Order {
        return Tx::run($owner, function () use ($order, $reason) {
            $order = Order::whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasApproved = $order->status === 'approved';

            $teamIds = DB::table('event_assignments')
                ->where('order_id', $order->id)
                ->whereIn('status', ['ditawarkan', 'diterima'])
                ->pluck('user_id')
                ->all();

            $order->update([
                'status'        => 'cancelled',
                'cancel_reason' => $reason,
            ]);

            $order->refresh();

            Notifier::to(
                $order->customer_id,
                'Pesanan dibatalkan',
                "Pesanan {$order->code} dibatalkan. " .
                ($wasApproved ? 'DP tidak dapat dikembalikan (non-refundable). ' : '') .
                "Alasan: {$reason}",
                'order_cancelled',
                $order->id
            );

            if ($teamIds) {
                Notifier::to(
                    $teamIds,
                    'Acara dibatalkan',
                    "Acara {$order->code} tanggal {$order->event_date->format('d-m-Y')} dibatalkan. Penugasan Anda dicabut.",
                    'order_cancelled',
                    $order->id
                );
            }

            return $order;
        }, $reason);
    }

    // ------------------------------------------------------------------ pencairan honor

    public function disburseHonor(Order $order, User $owner): object
    {
        return Tx::run($owner, function () use ($order, $owner) {
            $summary = DB::selectOne(
                'select * from fn_cairkan_honor(?, ?)',
                [$order->id, $owner->id]
            );

            $rows = DB::table('honorariums as h')
                ->join('event_assignments as ea', 'ea.id', '=', 'h.assignment_id')
                ->where('ea.order_id', $order->id)
                ->select('ea.user_id', 'h.amount')
                ->get();

            foreach ($rows as $r) {
                Notifier::to(
                    $r->user_id,
                    'Honor dicairkan',
                    "Honor {$this->rupiah($r->amount)} untuk acara {$order->code} sudah dicairkan.",
                    'honor_paid',
                    $order->id
                );
            }

            return $summary;
        });
    }

    // ------------------------------------------------------------------ Korlap: penyelesaian acara

    public function complete(
        Order $order,
        User $korlap,
        string $report
    ): Order {
        return Tx::run($korlap, function () use ($order, $korlap, $report) {
            $order = Order::whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->loans()->whereNull('returned_at')->exists()) {
                throw new DomainException(
                    'Masih ada barang yang belum dikembalikan. Selesaikan pengecekan barang bersama Helper dulu.'
                );
            }

            $order->update([
                'status'       => 'completed',
                'field_report' => $report,
            ]);

            Notifier::toRole(
                'owner',
                'Acara selesai',
                "Korlap {$korlap->name} melaporkan acara {$order->code} selesai.",
                'order_completed',
                $order->id
            );

            return $order->refresh();
        });
    }

    // ------------------------------------------------------------------ ringkasan kebutuhan tim

    public function staffingSummary(Order $order): array
    {
        $order->loadMissing(['package', 'addons']);

        $active = $order->assignments()
            ->whereIn('status', ['ditawarkan', 'diterima'])
            ->get();

        $row = fn (string $role, int $need) => [
            'dibutuhkan' => $need,
            'diterima'   => $active->where('role', $role)->where('status', 'diterima')->count(),
            'ditawarkan' => $active->where('role', $role)->where('status', 'ditawarkan')->count(),
        ];

        return [
            'penari'  => $row(
                'penari',
                (int) $order->package->dancers_needed +
                (int) $order->addons->sum('extra_dancers')
            ),
            'pemusik' => $row(
                'pemusik',
                (int) $order->package->musicians_needed +
                (int) $order->addons->sum('extra_musicians')
            ),
            'helper' => $row(
                'helper',
                (int) $order->package->helpers_needed
            ),
        ];
    }
}
