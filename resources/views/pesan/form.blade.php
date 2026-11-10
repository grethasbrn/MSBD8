
@extends('layouts.app')

@section('title', 'Formulir Pemesanan - Sanggar Tri Arga')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pesan.css') }}">
@endpush

@section('content')
    @php
        // Mengambil isian sebelumnya atau data draft
        $nilai = fn (string $kunci, $bawaan = null) => old($kunci, $draft[$kunci] ?? $bawaan);

        $opsi = $nilai('opsi_addon', 'normal');

        $addonDipilih = collect(old('addon_ids', $draft['addon_ids'] ?? []))
            ->map(fn ($x) => (int) $x)
            ->all();

        $sekarang = \Carbon\Carbon::now('Asia/Jakarta');

        $namaBulan = [
            'Januari',
            'Februari',
            'Maret',
            'April',
            'Mei',
            'Juni',
            'Juli',
            'Agustus',
            'September',
            'Oktober',
            'November',
            'Desember'
        ];
    @endphp

    <section class="form-section">
        <div class="form-container">

            <h2>Formulir Pemesanan Acara</h2>

            <p>
                Langkah 1 dari 2: lengkapi detail acara Anda.
                Rincian biaya dan DP dihitung pada langkah berikutnya.
            </p>

            @include('pesan._galat')

            <form method="POST" action="{{ route('pesan.rincian') }}">
                @csrf

                <!-- Nama Pemesan -->
                <div class="input-group">
                    <label for="namaPemesan">Nama Lengkap Pemesan</label>

                    <input
                        type="text"
                        id="namaPemesan"
                        value="{{ auth()->user()->name }}"
                        readonly
                    >
                </div>

                <!-- Paket Utama -->
                <div class="input-group">
                    <label>Pilih Paket Pertunjukan</label>

                    <div class="radio-box">
                        @forelse ($paket as $p)
                            <label class="option-item">
                                <div class="option-left">

                                    <input
                                        type="radio"
                                        name="package_id"
                                        value="{{ $p->id }}"
                                        @checked((int) $nilai('package_id') === (int) $p->id)
                                        required
                                    >

                                    <span>
                                        {{ $p->name }}
                                        <small>{{ $p->description }}</small>
                                    </span>

                                </div>

                                <strong>
                                    Rp {{ number_format($p->price, 0, ',', '.') }}
                                </strong>
                            </label>
                        @empty
                            <p>Belum ada paket yang tersedia.</p>
                        @endforelse
                    </div>
                </div>

                <!-- Tanggal Acara -->
                <div class="input-group">
                    <label>Tanggal Pelaksanaan Acara</label>

                    <div class="date-picker-grid">

                        <select name="tanggal" id="pilihTanggal" required>
                            <option value="">Tanggal</option>

                            @for ($i = 1; $i <= 31; $i++)
                                <option
                                    value="{{ $i }}"
                                    @selected((int) $nilai('tanggal') === $i)
                                >
                                    {{ $i }}
                                </option>
                            @endfor
                        </select>

                        <select name="bulan" id="pilihBulan" required>
                            <option value="">Bulan</option>

                            @foreach (range(1, 12) as $b)
                                <option
                                    value="{{ $b }}"
                                    @selected((int) $nilai('bulan') === $b)
                                >
                                    {{ $namaBulan[$b - 1] }}
                                </option>
                            @endforeach
                        </select>

                        <select name="tahun" id="pilihTahun" required>
                            @foreach ($tahun as $t)
                                <option
                                    value="{{ $t }}"
                                    @selected((int) $nilai('tahun', $tahun[0]) === (int) $t)
                                >
                                    {{ $t }}
                                </option>
                            @endforeach
                        </select>

                    </div>

                    <small>
                        Maksimal 3 acara per hari. Pesanan dapat dibuat untuk besok dan seterusnya.

                        <a
                            href="{{ route('beranda', ['bulan' => $sekarang->format('Y-m')]) }}#kalender"
                            target="_blank"
                            style="color: var(--maroon); font-weight: 700;"
                        >
                            Lihat kalender
                        </a>
                    </small>
                </div>

                <!-- Jam Acara -->
                <div class="input-group">
                    <label for="jamAcara">Jam Acara (boleh dikosongkan)</label>

                    <input
                        type="time"
                        id="jamAcara"
                        name="event_time"
                        value="{{ $nilai('event_time') }}"
                    >
                </div>

                <!-- Lokasi Acara -->
                <div class="input-group">
                    <label for="lokasiAcara">Lokasi Acara (alamat lengkap)</label>

                    <textarea
                        id="lokasiAcara"
                        name="event_location"
                        rows="3"
                        required
                        placeholder="Contoh: Gedung Serbaguna, Jl. Sudirman No. 10, Pekanbaru"
                    >{{ $nilai('event_location') }}</textarea>
                </div>

                <!-- Catatan -->
                <div class="input-group">
                    <label for="catatan">Catatan Khusus (boleh dikosongkan)</label>

                    <textarea
                        id="catatan"
                        name="notes"
                        rows="3"
                        placeholder="Contoh: tema acara, permintaan khusus"
                    >{{ $nilai('notes') }}</textarea>
                </div>

                <!-- Opsi Tambahan -->
                <div class="input-group">
                    <label>Opsi Tambahan (Pilih Salah Satu)</label>

                    <div class="radio-box">

                        <!-- Normal -->
                        <label class="option-item">
                            <div class="option-left">

                                <input
                                    type="radio"
                                    name="opsi_addon"
                                    value="normal"
                                    @checked($opsi !== 'addon')
                                    onclick="toggleAddons(false)"
                                >

                                <span>
                                    <strong>Normal</strong>
                                    (Tidak ada tambahan add-on)
                                </span>

                            </div>
                        </label>

                        <!-- Add-on -->
                        <label class="option-item" style="margin-top: 12px;">
                            <div class="option-left">

                                <input
                                    type="radio"
                                    name="opsi_addon"
                                    value="addon"
                                    @checked($opsi === 'addon')
                                    onclick="toggleAddons(true)"
                                >

                                <span>
                                    <strong>Pilih Add-on Acara</strong>
                                    (Bebas pilih rincian add-on)
                                </span>

                            </div>
                        </label>

                        <!-- Daftar Add-on -->
                        <div
                            class="sub-conditional-box"
                            id="subAddonsContainer"
                            style="display: {{ $opsi === 'addon' ? 'block' : 'none' }};"
                        >

                            <h4>Pilih Rincian Add-on:</h4>

                            @foreach ($addons as $a)
                                <label class="option-item">

                                    <div class="option-left">

                                        <input
                                            type="checkbox"
                                            name="addon_ids[]"
                                            value="{{ $a->id }}"
                                            @checked(in_array((int) $a->id, $addonDipilih, true))
                                        >

                                        <span>
                                            {{ $a->name }}
                                            <small>{{ $a->description }}</small>
                                        </span>

                                    </div>

                                    <strong>
                                        +Rp {{ number_format($a->price, 0, ',', '.') }}
                                    </strong>

                                </label>
                            @endforeach

                        </div>

                    </div>
                </div>

                <!-- Tombol Submit -->
                <button type="submit" class="btn-submit">
                    Lanjut ke Rincian & Pembayaran
                </button>

            </form>

            <a href="{{ route('beranda') }}" class="back-link">
                ← Kembali ke Beranda
            </a>

        </div>
    </section>
@endsection

@push('scripts')
    <script>
        // Tampilkan atau sembunyikan daftar add-on
        function toggleAddons(tampil) {
            const container = document.getElementById('subAddonsContainer');

            container.style.display = tampil ? 'block' : 'none';

            if (!tampil) {
                document.querySelectorAll('input[name="addon_ids[]"]')
                    .forEach(el => el.checked = false);
            }
        }

        // Data tanggal hari ini
        const HARI_INI = {
            t: {{ $sekarang->year }},
            b: {{ $sekarang->month }},
            h: {{ $sekarang->day }}
        };

        const selTanggal = document.getElementById('pilihTanggal');
        const selBulan = document.getElementById('pilihBulan');
        const selTahun = document.getElementById('pilihTahun');

        // Menyesuaikan jumlah tanggal berdasarkan bulan dan tahun
        function isiTanggal() {
            const bulan = parseInt(selBulan.value);
            const tahun = parseInt(selTahun.value);

            const jumlah = (bulan && tahun)
                ? new Date(tahun, bulan, 0).getDate()
                : 31;

            const dipilih = selTanggal.value;

            selTanggal.innerHTML = '<option value="">Tanggal</option>';

            for (let d = 1; d <= jumlah; d++) {

                // Menyembunyikan tanggal yang sudah lewat
                if (
                    tahun === HARI_INI.t &&
                    bulan === HARI_INI.b &&
                    d <= HARI_INI.h
                ) {
                    continue;
                }

                const opt = document.createElement('option');

                opt.value = d;
                opt.textContent = d;

                selTanggal.appendChild(opt);
            }

            selTanggal.value = dipilih;
        }

        selBulan.addEventListener('change', isiTanggal);
        selTahun.addEventListener('change', isiTanggal);

        // Jalankan saat halaman pertama kali dibuka
        isiTanggal();
    </script>
@endpush
