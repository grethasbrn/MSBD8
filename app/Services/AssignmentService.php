<?php

namespace App\Services;

use App\Models\EventAssignment;
use App\Models\Order;
use App\Models\User;
use App\Support\Tx;
use DomainException;
use Illuminate\Support\Facades\DB;

/** Penugasan Korlap (oleh Owner) dan penugasan tim lapangan (oleh Korlap), termasuk presensi. */
class AssignmentService
{
    public function assignKorlap(Order $order, User $owner, int $korlapId): EventAssignment
    {
        return Tx::run($owner, function () use ($order, $owner, $korlapId) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status !== 'approved') {
                throw new DomainException('Korlap hanya bisa ditugaskan pada pesanan berstatus Approved.');
            }

            $old = EventAssignment::where('order_id', $order->id)->where('role', 'korlap')
                ->whereIn('status', ['ditawarkan', 'diterima'])->first();
            if ($old) {
                if ((int) $old->user_id === $korlapId) {
                    throw new DomainException('Korlap tersebut sudah ditugaskan pada pesanan ini.');
                }
                $old->update(['status' => 'dibatalkan']);   // ganti Korlap
                Notifier::to($old->user_id, 'Penugasan Korlap dicabut', "Anda tidak lagi menjadi Korlap untuk acara {$order->code}.", 'assignment_cancelled', $order->id);
            }

            $a = EventAssignment::create([
                'order_id' => $order->id, 'user_id' => $korlapId, 'role' => 'korlap',
                'status' => 'diterima', 'assigned_by' => $owner->id,   // Korlap menerima mandat langsung
            ]);
            Notifier::to($korlapId, 'Mandat acara baru',
                "Anda ditunjuk menjadi Korlap acara {$order->code} pada {$order->event_date->format('d-m-Y')}.", 'korlap_assigned', $order->id);
            return $a->refresh();
        });
    }

    /** Korlap menawarkan tugas ke penari / pemusik / helper. */
    public function offer(Order $order, User $korlap, int $userId, string $role, ?string $instrument): EventAssignment
    {
        return Tx::run($korlap, function () use ($order, $korlap, $userId, $role, $instrument) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $a = EventAssignment::create([
                'order_id' => $order->id, 'user_id' => $userId, 'role' => $role,
                'instrument' => $instrument, 'status' => 'ditawarkan', 'assigned_by' => $korlap->id,
            ]);
            Notifier::to($userId, 'Tawaran penugasan baru',
                "Anda ditawari tugas sebagai {$role} pada {$order->event_date->format('d-m-Y')} di {$order->event_location}. Silakan Terima atau Tolak.", 'assignment_offer', $order->id);
            return $a->refresh();
        });
    }

    /** Penari/pemusik/helper menekan Terima atau Tolak. */
    public function respond(EventAssignment $a, User $user, string $action): EventAssignment
    {
        if ((int) $a->user_id !== (int) $user->id) {
            throw new DomainException('Penugasan ini bukan untuk Anda.');
        }
        return Tx::run($user, function () use ($a, $user, $action) {
            $a = EventAssignment::whereKey($a->id)->lockForUpdate()->firstOrFail();
            if ($a->status !== 'ditawarkan') {
                throw new DomainException('Tawaran ini sudah direspons atau dibatalkan.');
            }
            $a->update(['status' => $action === 'accept' ? 'diterima' : 'ditolak']);

            $order = Order::findOrFail($a->order_id);
            $korlapId = EventAssignment::where('order_id', $order->id)->where('role', 'korlap')->where('status', 'diterima')->value('user_id');
            if ($korlapId) {
                $verb = $action === 'accept' ? 'menerima' : 'MENOLAK';
                $hint = $action === 'accept' ? '' : ' Silakan cari pengganti.';
                Notifier::to($korlapId, "Tugas {$verb}",
                    "{$user->name} {$verb} tugas {$a->role} untuk acara {$order->code}.{$hint}", 'assignment_response', $order->id);
            }
            return $a->refresh();
        });
    }

    /** Korlap mencabut tawaran / mengganti anggota tim. */
    public function cancel(EventAssignment $a, User $korlap): EventAssignment
    {
        if ($a->role === 'korlap') {
            throw new DomainException('Korlap hanya bisa diganti oleh Owner.');
        }
        return Tx::run($korlap, function () use ($a) {
            $a = EventAssignment::whereKey($a->id)->lockForUpdate()->firstOrFail();
            if ($a->attended_at) {
                throw new DomainException('Penugasan yang sudah dipresensi tidak bisa dibatalkan.');
            }
            $a->update(['status' => 'dibatalkan']);
            Notifier::to($a->user_id, 'Penugasan dibatalkan', 'Penugasan Anda pada sebuah acara dibatalkan oleh Korlap.', 'assignment_cancelled', $a->order_id);
            return $a->refresh();
        });
    }

    /** Presensi digital oleh Korlap (syarat pencairan honor). Mengembalikan jumlah yang dicatat. */
    public function markAttendance(Order $order, User $korlap, array $assignmentIds): int
    {
        $ids = array_values(array_unique(array_map('intval', $assignmentIds)));

        return Tx::run($korlap, function () use ($order, $korlap, $ids) {
            $rows = EventAssignment::where('order_id', $order->id)->whereIn('id', $ids)
                ->where('status', 'diterima')->whereNull('attended_at')->lockForUpdate()->get();

            if ($rows->count() !== count($ids)) {
                throw new DomainException('Sebagian penugasan tidak valid, belum diterima, atau presensinya sudah dicatat.');
            }
            foreach ($rows as $row) {
                EventAssignment::whereKey($row->id)->update(['attended_at' => DB::raw('now()'), 'attendance_by' => $korlap->id]);
            }
            return $rows->count();
        });
    }

    /** SDM aktif dengan peran tertentu yang BELUM punya penugasan aktif pada tanggal itu. */
    public function availableStaff(string $role, string $date, ?string $specialization = null)
    {
        return User::where('role', $role)->where('is_active', true)
            ->when($specialization, fn ($q, $s) => $q->where('specialization', 'ilike', "%{$s}%"))
            ->whereNotExists(function ($q) use ($date) {
                $q->select(DB::raw(1))->from('event_assignments as ea')
                    ->whereColumn('ea.user_id', 'users.id')
                    ->where('ea.event_date', $date)
                    ->whereIn('ea.status', ['ditawarkan', 'diterima']);
            })
            ->orderBy('name')->get(['id', 'name', 'phone', 'specialization']);
    }
}
