
@extends('layouts.app')

@section('title', 'Pembagian Tim - ' . $order->code)

@push('styles')
<style>
    .wrap {
        max-width: 1050px;
        margin: 30px auto;
        padding: 0 18px;
        color: #352633;
    }

    .wrap h1 {
        color: #682b4b;
    }

    .panel {
        background: #fff;
        border: 1px solid #eadfc8;
        border-radius: 12px;
        padding: 18px;
        margin: 14px 0;
    }

    .muted {
        color: #777;
        font-size: 13px;
    }

    .row {
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }

    input,
    select {
        padding: 9px;
        border: 1px solid #ccc;
        border-radius: 7px;
        max-width: 100%;
    }

    .btn {
        border: 0;
        border-radius: 7px;
        padding: 9px 13px;
        background: #762c50;
        color: white;
        cursor: pointer;
    }

    .flash {
        padding: 12px;
        background: #e9f5eb;
        border-radius: 8px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    td,
    th {
        text-align: left;
        padding: 9px;
        border-bottom: 1px solid #eee;
    }
</style>
@endpush

@section('content')
<main class="wrap">

    <p>
        <a href="{{ route('dashboard.korlap') }}">
            ← Dashboard Korlap
        </a>
    </p>

    <h1>Atur Tim Acara {{ $order->code }}</h1>

    {{-- PESAN NOTIFIKASI --}}
    @if(session('status'))
        <p class="flash">{{ session('status') }}</p>
    @endif

    @if($errors->any())
        <p class="flash">{{ $errors->first() }}</p>
    @endif

    {{-- INFORMASI ACARA --}}
    <section class="panel">
        <h3>Informasi Acara</h3>

        <p>
            {{ $order->package->name }}
            ·
            {{ \Carbon\Carbon::parse($order->event_date)->format('d F Y') }}
            ·
            {{ $order->event_location }}
        </p>

        <p class="muted">
            Pelanggan: {{ $order->customer->name }}
            ·
            {{ $order->notes ?: 'Tidak ada catatan tambahan' }}
        </p>
    </section>

    {{-- KEBUTUHAN TIM --}}
    <section class="panel">
        <h3>Kebutuhan Tim</h3>

        <table>
            <thead>
                <tr>
                    <th>Peran</th>
                    <th>Dibutuhkan</th>
                    <th>Sudah menerima</th>
                    <th>Menunggu jawaban</th>
                </tr>
            </thead>

            <tbody>
                @foreach($kebutuhan as $role => $angka)
                    <tr>
                        <td>{{ ucfirst($role) }}</td>
                        <td>{{ $angka['dibutuhkan'] }}</td>
                        <td>{{ $angka['diterima'] }}</td>
                        <td>{{ $angka['ditawarkan'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>

    {{-- TAWARKAN TUGAS --}}
    <section class="panel">
        <h3>Tawarkan Tugas kepada Anggota</h3>

        <form
            method="POST"
            action="{{ route('korlap.tim.tugaskan', $order) }}"
        >
            @csrf

            <label for="role">Peran</label>
            <br>

            <select name="role" id="role" required>
                <option value="">Pilih peran</option>
                <option value="penari">Penari</option>
                <option value="pemusik">Pemusik</option>
                <option value="helper">Helper</option>
            </select>

            <br><br>

            <label for="staff">Anggota yang tersedia</label>
            <br>

            <select name="user_id" id="staff" required>
                <option value="">Pilih peran terlebih dahulu</option>
            </select>

            <br><br>

            <label for="instrument">
                Instrumen (khusus pemusik, opsional)
            </label>
            <br>

            <input
                type="text"
                name="instrument"
                id="instrument"
                placeholder="Contoh: gendang"
            >

            <br><br>

            <button class="btn" type="submit">
                Kirim Tawaran Tugas
            </button>
        </form>
    </section>

    {{-- DAFTAR TIM --}}
    <section class="panel">
        <h3>Daftar Tim</h3>

        @forelse(
            $order->assignments->whereIn('role', ['penari', 'pemusik', 'helper'])
            as $a
        )
            <div class="row">
                <b>{{ $a->user->name ?? 'Anggota' }}</b>
                · {{ ucfirst($a->role) }}
                · {{ $a->status }}

                @if($a->instrument)
                    · {{ $a->instrument }}
                @endif

                @if($a->status === 'ditawarkan')
                    <form
                        method="POST"
                        action="{{ route('korlap.tim.batal', $a) }}"
                        style="display: inline"
                    >
                        @csrf
                        @method('DELETE')

                        <button class="btn" type="submit">
                            Batalkan
                        </button>
                    </form>
                @endif
            </div>
        @empty
            <p class="muted">
                Belum ada anggota tim yang ditawarkan.
            </p>
        @endforelse
    </section>

    {{-- PRESENSI DIGITAL --}}
    <section class="panel">
        <h3>Presensi Digital</h3>

        <p class="muted">
            Centang anggota yang hadir pada acara ini.
        </p>

        @php
            $anggotaBelumPresensi = $order->assignments
                ->whereIn('role', ['penari', 'pemusik', 'helper'])
                ->where('status', 'diterima')
                ->whereNull('attended_at');
        @endphp

        <form
            method="POST"
            action="{{ route('korlap.presensi.simpan', $order) }}"
        >
            @csrf

            @forelse($anggotaBelumPresensi as $a)
                <div class="row">
                    <label>
                        <input
                            type="checkbox"
                            name="assignment_ids[]"
                            value="{{ $a->id }}"
                        >

                        <strong>
                            {{ $a->user->name ?? 'Anggota' }}
                        </strong>

                        - {{ ucfirst($a->role) }}

                        @if($a->instrument)
                            ({{ $a->instrument }})
                        @endif
                    </label>
                </div>
            @empty
                <p class="muted">
                    Tidak ada anggota yang menunggu presensi.
                </p>
            @endforelse

            @if($anggotaBelumPresensi->isNotEmpty())
                <br>

                <button type="submit" class="btn">
                    Simpan Presensi
                </button>
            @endif
        </form>

        <hr>

        <h4>Anggota Sudah Dipresensi</h4>

        @forelse(
            $order->assignments
                ->whereIn('role', ['penari', 'pemusik', 'helper'])
                ->whereNotNull('attended_at') as $a
        )
            <div class="row">
                <strong>
                    {{ $a->user->name ?? 'Anggota' }}
                </strong>

                - {{ ucfirst($a->role) }}

                <span class="muted">
                    Hadir pada:
                    {{ $a->attended_at->format('d-m-Y H:i') }}
                </span>
            </div>
        @empty
            <p class="muted">
                Belum ada presensi yang dicatat.
            </p>
        @endforelse
    </section>

</main>

<script>
    const staffOptions = @json(
        $staf->map(fn($items) =>
            $items->map(fn($u) => [
                'id' => $u->id,
                'name' => $u->name
            ])->values()
        )
    );

    const roleSelect = document.getElementById('role');
    const staffSelect = document.getElementById('staff');

    roleSelect.addEventListener('change', () => {
        const list = staffOptions[roleSelect.value] || [];

        staffSelect.innerHTML =
            '<option value="">Pilih anggota</option>';

        list.forEach(u => {
            const option = document.createElement('option');

            option.value = u.id;
            option.textContent = u.name;

            staffSelect.appendChild(option);
        });

        if (list.length === 0) {
            staffSelect.innerHTML =
                '<option value="">Tidak ada anggota tersedia pada tanggal acara</option>';
        }
    });
</script>
@endsection
