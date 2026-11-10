<?php

namespace App\Support;

use Carbon\Carbon;

class Label
{
    // Format Rupiah
    public static function rupiah($nominal): string
    {
        return 'Rp ' . number_format((float) $nominal, 0, ',', '.');
    }

    // Format tanggal Indonesia
    public static function tanggal($tanggal): string
    {
        return Carbon::parse($tanggal)
            ->locale('id')
            ->translatedFormat('d F Y');
    }

    // Label status pesanan
    public static function statusPesanan($status): string
    {
        return match (strtolower((string) $status)) {
            'pending', 'pending_verification' => 'Menunggu Verifikasi',
            'paid' => 'Sudah Dibayar',
            'confirm' => 'Dikonfirmasi',
            'done' => 'Selesai',
            'rejected' => 'Ditolak',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    // Jenis pembayaran
    public static function jenisBayar($jenis): string
    {
        return match (strtolower((string) $jenis)) {
            'dp' => 'Uang Muka (DP)',
            'pelunasan' => 'Pelunasan',
            'full' => 'Pembayaran Penuh',
            default => ucfirst(str_replace('_', ' ', (string) $jenis)),
        };
    }

    // Metode pembayaran
    public static function metode($metode): string
    {
        return match (strtolower((string) $metode)) {
            'transfer' => 'Transfer Bank',
            'qris' => 'QRIS',
            'ewallet' => 'E-Wallet',
            default => ucfirst(str_replace('_', ' ', (string) $metode)),
        };
    }

    // Status pembayaran
    public static function statusBayar($status): string
    {
        return match (strtolower((string) $status)) {
            'pending' => 'Menunggu Verifikasi',
            'verified', 'paid', 'success' => 'Terverifikasi',
            'rejected' => 'Ditolak',
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }
}
