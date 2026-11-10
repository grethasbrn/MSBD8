<?php

namespace App\Support;

class Validasi
{
    public static function pesan(): array
    {
        return [
            'required' => ':attribute wajib diisi.',
            'integer' => ':attribute harus berupa angka.',
            'numeric' => ':attribute harus berupa angka.',
            'string' => ':attribute harus berupa teks.',
            'array' => ':attribute tidak valid.',

            'min' => [
                'string' => ':attribute minimal :min karakter.',
                'numeric' => ':attribute minimal :min.',
                'file' => 'Ukuran file terlalu kecil.',
            ],

            'max' => [
                'string' => ':attribute maksimal :max karakter.',
                'numeric' => ':attribute maksimal :max.',
                'file' => 'Ukuran file maksimal 5 MB.',
            ],

            'exists' => ':attribute yang dipilih tidak tersedia.',
            'date_format' => 'Format :attribute tidak valid.',
            'in' => 'Pilihan :attribute tidak valid.',
            'mimes' => 'Format file tidak didukung.',
            'uploaded' => 'File gagal diunggah.',
        ];
    }

    public static function nama(): array
    {
        return [
            'package_id' => 'Paket pertunjukan',
            'opsi_addon' => 'Opsi tambahan',
            'addon_ids' => 'Add-on',
            'addon_ids.*' => 'Add-on',
            'tanggal' => 'Tanggal acara',
            'bulan' => 'Bulan acara',
            'tahun' => 'Tahun acara',
            'event_time' => 'Jam acara',
            'event_location' => 'Lokasi acara',
            'notes' => 'Catatan khusus',
            'payment_method' => 'Metode pembayaran',
            'dp_amount' => 'Nominal DP',
            'proof' => 'Bukti pembayaran',
        ];
    }
}
