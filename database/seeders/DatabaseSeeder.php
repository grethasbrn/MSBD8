<?php

namespace Database\Seeders;

use App\Models\Addon;
use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Data contoh yang disamakan dengan UI Sanggar Tri Arga. Semua password akun: "password". */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ---------- Akun ----------
        $users = [
            ['Owner Sanggar',    'owner@sanggar.test',    '081200000001', 'owner',   null],
            ['Korlap Budi',      'korlap1@sanggar.test',  '081200000011', 'korlap',  null],
            ['Korlap Sari',      'korlap2@sanggar.test',  '081200000012', 'korlap',  null],
            ['Korlap Andi',      'korlap3@sanggar.test',  '081200000013', 'korlap',  null],
            ['Helper Joko',      'helper1@sanggar.test',  '081200000021', 'helper',  null],
            ['Helper Rina',      'helper2@sanggar.test',  '081200000022', 'helper',  null],
            ['Penari Ayu',       'penari1@sanggar.test',  '081200000031', 'penari',  null],
            ['Penari Dewi',      'penari2@sanggar.test',  '081200000032', 'penari',  null],
            ['Penari Intan',     'penari3@sanggar.test',  '081200000033', 'penari',  null],
            ['Penari Maya',      'penari4@sanggar.test',  '081200000034', 'penari',  null],
            ['Penari Putri',     'penari5@sanggar.test',  '081200000035', 'penari',  null],
            ['Penari Sinta',     'penari6@sanggar.test',  '081200000036', 'penari',  null],
            ['Pemusik Bima',     'pemusik1@sanggar.test', '081200000041', 'pemusik', 'Kendang'],
            ['Pemusik Candra',   'pemusik2@sanggar.test', '081200000042', 'pemusik', 'Talempong'],
            ['Pemusik Dani',     'pemusik3@sanggar.test', '081200000043', 'pemusik', 'Flute'],
            ['Pemusik Eko',      'pemusik4@sanggar.test', '081200000044', 'pemusik', 'Sarunai'],
            ['Pelanggan Contoh', 'pelanggan@sanggar.test','081200000099', 'customer', null],
        ];
        foreach ($users as [$name, $email, $phone, $role, $spec]) {
            User::create([
                'name' => $name, 'email' => $email, 'phone' => '62' . substr($phone, 1),
                'password' => 'password', 'role' => $role, 'specialization' => $spec,
            ]);
        }

        // ---------- Paket (harga sama dengan index.html / pesan.html) ----------
        // dancers/musicians/helpers_needed = kebutuhan tim (angka CONTOH, sesuaikan dengan sanggar)
        Package::insert([
            ['name' => 'Tari Sambut', 'category' => 'tari',
             'description' => 'Pertunjukan tari untuk menyambut tamu dan memeriahkan acara.',
             'price' => 1500000, 'dancers_needed' => 6, 'musicians_needed' => 4, 'helpers_needed' => 2],
            ['name' => 'Tari Tradisional', 'category' => 'tari',
             'description' => 'Pertunjukan tari tradisional dengan nuansa budaya yang khas.',
             'price' => 2000000, 'dancers_needed' => 8, 'musicians_needed' => 4, 'helpers_needed' => 2],
            ['name' => 'Musik dan Karawitan', 'category' => 'musik',
             'description' => 'Pertunjukan musik dan karawitan untuk melengkapi acara Anda.',
             'price' => 1800000, 'dancers_needed' => 0, 'musicians_needed' => 6, 'helpers_needed' => 2],
        ]);

        // ---------- Add-on ----------
        Addon::insert([
            ['name' => '+2 Penari',      'description' => 'Tambahan penari pertunjukan',    'price' => 100000, 'extra_dancers' => 2, 'extra_musicians' => 0],
            ['name' => '+1 Tari Ekstra', 'description' => 'Tambahan satu pertunjukan tari', 'price' => 200000, 'extra_dancers' => 0, 'extra_musicians' => 0],
            ['name' => 'Live Music',     'description' => 'Tambahan pemusik untuk acara',   'price' => 500000, 'extra_dancers' => 0, 'extra_musicians' => 2],
        ]);

        // ---------- Alat musik: 1 baris = 1 unit fisik (stok 3 = 3 baris) ----------
        $alat = [
            ['Bass', 1, 'Alat musik petik bass modern', 'images/bass.png'],
            ['Gitar Listrik', 1, 'Gitar elektrik untuk pertunjukan', 'images/gitarlistrik.png'],
            ['Gitar Akustik', 1, 'Gitar akustik standar', 'images/gitarakustik.png'],
            ['Drum', 1, 'Set drum akustik lengkap', 'images/drum.png'],
            ['Talempong Melody', 1, 'Gong kecil melodis khas Minang', 'images/talempongcanang.png'],
            ['Talempong Canang', 1, 'Perangkat musik tradisional', 'images/talempongcanang.png'],
            ['Talempong Ritm', 1, 'Talempong pengatur tempo/ritme', 'images/talempongritmis.png'],
            ['Talempong Pakcik', 1, 'Talempong ukuran khusus', 'images/talempongpakcik.png'],
            ['Jimbei', 1, 'Gendang perkusi tradisional', 'images/jimbe.png'],
            ['Sarunai', 3, 'Alat musik tiup tradisional', 'images/sarunai.png'],
            ['Suling', 5, 'Suling bambu tradisional', 'images/suling.png'],
            ['Flute', 2, 'Suling tiup modern', 'images/flute.png'],
            ['Bansi', 3, 'Suling khas Minangkabau', 'images/bansi.png'],
            ['Synthesizer Digital', 1, 'Keyboard synthesizer elektronik', 'images/syntesizerdigital.png'],
            ['Tambur', 3, 'Gendang besar tradisional', 'images/tambur.png'],
        ];
        $no = 1;
        foreach ($alat as [$nama, $stok, $deskripsi, $gambar]) {
            for ($i = 0; $i < $stok; $i++) {
                InventoryItem::create([
                    'code' => sprintf('ALT-%03d', $no++), 'name' => $nama, 'category' => 'alat_musik',
                    'unit_label' => str_starts_with($nama, 'Talempong') ? 'Set' : 'Unit',
                    'description' => $deskripsi, 'image_url' => $gambar,
                ]);
            }
        }

        // ---------- Kostum & pakaian adat (sesuai kostum.html) ----------
        // [kelompok di katalog, nama, deskripsi, jumlah unit, satuan tampil, gambar]
        $kostum = [
            ['Pakaian Tari Minang (Laki-laki)', 'Baju Baldu Merah Strip Gold', 'Pakaian penari laki-laki khas Minang', 4, 'Unit', 'images/Baju Baldu Merah Strip Gold.png'],
            ['Pakaian Tari Minang (Laki-laki)', 'Celana Baldu Merah Strip Gold', 'Celana pasangan baju baldu merah', 4, 'Unit', 'images/Celana Baldu Merah Strip Gold.png'],
            ['Pakaian Tari Minang (Laki-laki)', 'Baju Baldu Marun', 'Pakaian penari laki-laki warna marun', 3, 'Unit', 'images/Baju Baldu Marun.png'],
            ['Pakaian Tari Minang (Laki-laki)', 'Celana Baldu Marun', 'Celana pasangan baldu marun', 3, 'Unit', 'images/Celana Baldu Marun.png'],
            ['Pakaian Tari Minang (Laki-laki)', 'Baju Satin Merah', 'Pakaian bahan satin merah', 4, 'Unit', 'images/Baju Satin Merah.png'],
            ['Pakaian Tari Minang (Laki-laki)', 'Celana Satin Merah', 'Celana satin merah', 4, 'Unit', 'images/Celana Satin Merah.png'],
            ['Pakaian Tari Minang (Laki-laki)', 'Baju Satin Biru', 'Pakaian bahan satin biru', 4, 'Unit', 'images/Baju Satin Biru.png'],
            ['Pakaian Tari Minang (Laki-laki)', 'Celana Satin Biru', 'Celana satin biru', 4, 'Unit', 'images/Celana Satin Biru.png'],
            ['Pakaian Tari Minang (Laki-laki)', 'Baju Baldu Oranye', 'Pakaian baldu oranye', 4, 'Unit', 'images/Baju Baldu Oranye.png'],
            ['Pakaian Tari Minang (Laki-laki)', 'Celana Baldu Oranye', 'Celana baldu oranye', 4, 'Unit', 'images/Celana Baldu Oranye.png'],
            ['Destar (Topi Tari Minang)', 'Destar Merah', 'Topi penutup kepala khas Minang', 5, 'Unit', 'images/Destar Merah.png'],
            ['Destar (Topi Tari Minang)', 'Destar Hitam', 'Topi penutup kepala warna hitam', 5, 'Unit', 'images/Destar Hitam.png'],
            ['Destar (Topi Tari Minang)', 'Destar Kuning', 'Topi penutup kepala warna kuning', 5, 'Unit', 'images/Destar Kuning.png'],
            ['Pakaian Tari Minang (Perempuan)', 'Baju Baldu Merah Strip Gold', 'Baju baldu perempuan khas Minang', 4, 'Unit', 'images/Baju Baldu Merah Strip Gold copy Prp.png'],
            ['Pakaian Tari Minang (Perempuan)', 'Rok Baldu Merah Strip Gold', 'Rok pasangan baldu merah', 4, 'Unit', 'images/Rok Baldu Merah Strip Gold.png'],
            ['Pakaian Tari Minang (Perempuan)', 'Baju Baldu Marun', 'Baju baldu perempuan warna marun', 4, 'Unit', 'images/Baju Baldu Marun Prp.png'],
            ['Pakaian Tari Minang (Perempuan)', 'Baju Satin Merah', 'Baju satin merah perempuan', 6, 'Unit', 'images/Baju Satin Merah Prp.png'],
            ['Pakaian Tari Minang (Perempuan)', 'Rok Satin Merah', 'Rok satin merah perempuan', 6, 'Unit', 'images/Rok Satin Merah.png'],
            ['Pakaian Tari Minang (Perempuan)', 'Songket Hitam', 'Kain songket hitam tradisional', 5, 'Unit', 'images/Songket Hitam Minang.png'],
            ['Pakaian & Aksesoris Tari Mandailing (Perempuan)', 'Baju Satin Merah Strip Gold', 'Baju perempuan Mandailing', 5, 'Unit', 'images/Baju Satin Merah Gold Mandailing.png'],
            ['Pakaian & Aksesoris Tari Mandailing (Perempuan)', 'Songket Hitam Mandailing', 'Kain songket khas Mandailing', 5, 'Unit', 'images/Songket Hitam Mandailing.png'],
            ['Pakaian & Aksesoris Tari Mandailing (Perempuan)', 'Kain Slempang Mandailing', 'Slempang khas tradisi Mandailing', 6, 'Unit', 'images/Kain Slempang Mandailing.png'],
            ['Pakaian & Aksesoris Tari Mandailing (Perempuan)', 'Keranjang Merah', 'Properti keranjang tradisional', 5, 'Unit', 'images/Keranjang Merah.png'],
            ['Pakaian & Aksesoris Tari Mandailing (Perempuan)', 'Kalung', 'Aksesoris kalung tradisional', 5, 'Unit', 'images/Kalung Mandailing.png'],
            ['Pakaian & Aksesoris Tari Mandailing (Perempuan)', 'Tokah Hitam', 'Aksesoris tokah hitam', 5, 'Unit', 'images/Tokah Hitam.png'],
            ['Pakaian & Aksesoris Tari Mandailing (Perempuan)', 'Bulang Mandailing', 'Hiasan kepala adat Mandailing', 1, 'Set Lengkap', 'images/Bulang Mandailing.png'],
            ['Pakaian & Aksesoris Tari Mandailing (Laki-laki)', 'Blazer Hitam', 'Blazer pakaian adat laki-laki', 5, 'Unit', 'images/Blazer Hitam Mandailing.png'],
            ['Pakaian & Aksesoris Tari Mandailing (Laki-laki)', 'Kain Songket Mandailing', 'Kain songket laki-laki', 5, 'Unit', 'images/Kain Songket Mandailing Lk.png'],
            ['Pakaian & Aksesoris Tari Mandailing (Laki-laki)', 'Topi Mandailing', 'Penutup kepala khas laki-laki', 5, 'Unit', 'images/Topi Mandailing.png'],
            ['Pakaian & Aksesoris Tari Mandailing (Laki-laki)', 'Bros Mandailing', 'Aksesoris bros pelengkap', 1, 'Set Lengkap', 'images/Bros Mandailing.png'],
            ['Pakaian Tari Aceh & Aksesoris (Perempuan)', 'Baju Bordir Hijau', 'Baju bordir tradisional Aceh', 4, 'Unit', 'images/Baju Bordir Hijau Aceh.png'],
            ['Pakaian Tari Aceh & Aksesoris (Perempuan)', 'Celana Bordir Hijau', 'Celana pasangan bordir hijau', 4, 'Unit', 'images/Celana Bordir Hijau Aceh.png'],
            ['Pakaian Tari Aceh & Aksesoris (Perempuan)', 'Topi Aceh', 'Hiasan kepala khas Aceh', 4, 'Unit', 'images/Topi Aceh.png'],
            ['Pakaian Tari Aceh & Aksesoris (Perempuan)', 'Slempang Aceh', 'Kain slempang dekoratif Aceh', 4, 'Unit', 'images/Slempang Aceh.png'],
            ['Pakaian Tari Aceh (Laki-laki)', 'Baju Satin Hijau', 'Baju satin hijau laki-laki', 3, 'Unit', 'images/Baju Satin Hijau Lk Aceh.png'],
            ['Pakaian Tari Aceh (Laki-laki)', 'Baju Satin Pink', 'Baju satin pink laki-laki', 3, 'Unit', 'images/Baju Satin Pink Lk Aceh.png'],
            ['Pakaian Tari Aceh (Laki-laki)', 'Celana Satin Hitam', 'Celana satin hitam penari Aceh', 6, 'Unit', 'images/Celana Satin Hitam Aceh.png'],
        ];
        $no = 1;
        foreach ($kostum as [$kelompok, $nama, $deskripsi, $stok, $satuan, $gambar]) {
            for ($i = 0; $i < $stok; $i++) {
                InventoryItem::create([
                    'code' => sprintf('KST-%03d', $no++), 'name' => $nama, 'category' => 'kostum',
                    'subcategory' => $kelompok, 'unit_label' => $satuan,
                    'description' => $deskripsi, 'image_url' => $gambar,
                ]);
            }
        }
    }
}
