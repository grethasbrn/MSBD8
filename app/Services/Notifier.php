<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\User;

/** Membuat notifikasi dalam aplikasi (muncul di lonceng dashboard pengguna). */
final class Notifier
{
    /** @param int|int[] $userIds */
    public static function to(int|array $userIds, string $title, string $body, string $type = 'info', ?int $orderId = null): void
    {
        foreach ((array) $userIds as $id) {
            AppNotification::create([
                'user_id'  => $id,
                'title'    => $title,
                'body'     => $body,
                'type'     => $type,
                'order_id' => $orderId,
            ]);
        }
    }

    public static function toRole(string $role, string $title, string $body, string $type = 'info', ?int $orderId = null): void
    {
        $ids = User::where('role', $role)->where('is_active', true)->pluck('id')->all();
        self::to($ids, $title, $body, $type, $orderId);
    }
}
