<?php

namespace App\Support;

use Throwable;

/**
 * Mengubah error PostgreSQL menjadi pesan Bahasa Indonesia yang aman ditampilkan ke pengguna.
 *  - P0001 : RAISE EXCEPTION buatan kita di trigger/fungsi (pesannya sudah rapi)
 *  - 23505 : pelanggaran UNIQUE
 *  - 23514 : pelanggaran CHECK
 */
final class DbErrors
{
    private const UNIQUE_MESSAGES = [
        'users_email_uq'           => 'Email sudah terdaftar.',
        'users_phone_uq'           => 'Nomor WhatsApp sudah terdaftar.',
        'payments_one_pending_uq'  => 'Sudah ada bukti pembayaran yang menunggu verifikasi.',
        'payments_one_verified_uq' => 'Pembayaran jenis ini sudah terverifikasi.',
        'ea_one_korlap_uq'         => 'Pesanan ini sudah memiliki Korlap.',
        'ea_no_double_booking_uq'  => 'SDM tersebut sudah punya penugasan lain pada tanggal yang sama.',
        'item_loans_open_uq'       => 'Barang tersebut sedang dipinjam.',
        'inventory_items_code_key' => 'Kode barang sudah dipakai.',
    ];

    public static function message(Throwable $e): ?string
    {
        $info = null;
        for ($t = $e; $t !== null; $t = $t->getPrevious()) {
            if (property_exists($t, 'errorInfo') && is_array($t->errorInfo)) {
                $info = $t->errorInfo;
                break;
            }
        }
        if ($info === null) {
            return null;
        }

        $state = (string) ($info[0] ?? '');
        $raw   = (string) ($info[2] ?? '');

        if ($state === 'P0001') {
            $first = trim(preg_split('/\R/', $raw)[0] ?? '');
            return trim(preg_replace('/^ERROR:\s*/', '', $first));
        }
        if ($state === '23505') {
            if (preg_match('/constraint "([^"]+)"/', $raw, $m) && isset(self::UNIQUE_MESSAGES[$m[1]])) {
                return self::UNIQUE_MESSAGES[$m[1]];
            }
            return 'Data yang sama sudah ada.';
        }
        if ($state === '23514') {
            return 'Data tidak memenuhi aturan database.';
        }
        if ($state === '23503') {
            return 'Data masih terhubung dengan data lain atau data rujukan tidak ditemukan.';
        }
        return null;
    }
}
