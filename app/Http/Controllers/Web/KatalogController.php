<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InventoryItem;

class KatalogController extends Controller
{
    public function alatMusik()
    {
        return $this->tampil(['alat_musik'], 'Katalog Alat Musik', 'Daftar Alat Musik Sanggar Tri Arga',
            'Koleksi alat musik tradisional nusantara dan modern yang siap disewakan untuk melengkapi pementasan atau acara spesial Anda.');
    }

    public function kostum()
    {
        return $this->tampil(['kostum', 'aksesoris'], 'Katalog Kostum & Pakaian Adat', 'Katalog Kostum & Pakaian Adat',
            'Koleksi lengkap pakaian tari dan aksesoris tradisional Minang, Mandailing, dan Aceh untuk pementasan.');
    }

    private function tampil(array $kategori, string $judulTab, string $judul, string $deskripsi)
    {
        $unit = InventoryItem::whereIn('category', $kategori)->orderBy('id')->get();

        // Di database 1 baris = 1 unit fisik. Kelompokkan: sub-kelompok (urutan kemunculan) -> nama barang,
        // lalu hitung stok. Barang bernama sama di sub-kelompok berbeda dihitung terpisah.
        $kelompok = $unit->groupBy(fn ($i) => $i->subcategory ?? '')
            ->map(fn ($isi, $namaKelompok) => (object) [
                'judul'  => $namaKelompok !== '' ? $namaKelompok : null,
                'barang' => $isi->groupBy('name')->map(function ($u, $nama) {
                    $contoh   = $u->first();
                    $total    = $u->count();
                    $tersedia = $u->where('status', 'tersedia')->count();
                    $satuan   = $contoh->unit_label;

                    if ($tersedia === 0)               $label = null;                       // semua sedang dipinjam/rusak
                    elseif ($satuan === 'Set Lengkap') $label = 'Set Lengkap';
                    elseif ($tersedia < $total)        $label = "Tersedia: {$tersedia} dari {$total} {$satuan}";
                    else                               $label = "Tersedia: {$total} {$satuan}";

                    return (object) [
                        'nama' => $nama, 'deskripsi' => $contoh->description,
                        'gambar' => $contoh->image_url, 'label' => $label,
                    ];
                })->values(),
            ])->values();

        return view('katalog', compact('kelompok', 'judulTab', 'judul', 'deskripsi'));
    }
}
