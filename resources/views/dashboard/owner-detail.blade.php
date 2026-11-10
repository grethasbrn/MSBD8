
@extends('layouts.app')

@section('title', 'Detail Pesanan ' . $order->code)

@push('styles')
<style>
    .wrap {
        max-width: 1000px;
        margin: 30px auto;
        padding: 0 18px;
        color: #352633;
    }

    .wrap h1 {
        color: #682b4b;
    }

    .panel {
        background: white;
        border: 1px solid #eadfc8;
        border-radius: 12px;
        padding: 18px;
        margin: 15px 0;
    }

    .row {
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }

    .muted {
        color: #777;
        font-size: 13px;
    }

    .btn {
        border: 0;
        border-radius: 7px;
        padding: 9px 13px;
        background: #762c50;
        color: white;
        cursor: pointer;
    }

    .btn.red {
        background: #a33b3b;
    }

    .btn.gray {
        background: #eee;
        color: #333;
    }

    input, select, textarea {
        padding: 9px;
        border: 1px solid #ccc;
        border-radius: 7px;
        max-width: 100%;
    }

    .flash {
        padding: 12px;
        background: #e9f5eb;
        border-radius: 8px;
    }

    .actions {
        display: flex;
        gap: 9px;
        flex-wrap: wrap;
        margin-top: 10px;
    }
</style>
@endpush

@section('content')
<main class="wrap">

    <p>
        <a href="{{ route('owner.pesanan.index') }}">
            ← Daftar Pesanan
        </a>
    </p>

    <h1>Pesanan {{ $order->code }}</h1>

    @if(session('status'))
        <p class="flash">{{ session('status') }}</p>
    @endif

    @if($errors->any())
        <p class="flash">{{ $errors->first() }}</p>
    @endif

    {{-- DETAIL ACARA --}}
    <section class="panel">
        <h3>Detail Acara</h3>

        <div class="row">
            Pelanggan: <b>{{ $order->customer->name }}</b>
        </div>

        <div class="row">
            Paket: {{ $order->package->name }}
        </div>

        <div class="row">
            Tanggal:
            {{ \Carbon\Carbon::parse($order->event_date)->format('d F Y') }}
            ·
            {{ $order->event_time ? substr((string) $order->event_time, 0, 5) : 'Jam belum ditentukan' }}
        </div>

        <div class="row">
            Lokasi: {{ $order->event_location }}
        </div>

        <div class="row">
            Catatan: {{ $order->notes ?: '-' }}
        </div>

        <div class="row">
            Status:
            <b>{{ str_replace('_', ' ', ucfirst($order->status)) }}</b>
        </div>

        <div class="row">
            Total:
            Rp {{ number_format((float) $order->total_amount, 0, ',', '.') }}

            · Sudah dibayar:
            Rp {{ number_format($dibayar, 0, ',', '.') }}

            · Sisa:
            Rp {{ number_format($sisa, 0, ',', '.') }}
        </div>
    </section>

    {{-- RIWAYAT PEMBAYARAN --}}
    <section class="panel">
        <h3>Riwayat Pembayaran</h3>

        @forelse($order->payments as $p)
            <div class="row">

                <b>{{ strtoupper($p->type) }}</b>
                · {{ $p->method }}
                · Rp {{ number_format((float) $p->amount, 0, ',', '.') }}

                <div class="muted">
                    Status: {{ $p->status }}
                </div>

                @if($p->proof_path)
                    <a target="_blank"
                       rel="noopener"
                       href="{{ $p->proof_url }}">
                        Lihat bukti pembayaran
                    </a>
                @endif

                @if($p->status === 'pending' && $p->type === 'dp')
                    <div class="actions">

                        <form method="POST"
                              action="{{ route('owner.dp.setujui', $order) }}">
                            @csrf

                            <button class="btn"
                                    onclick="return confirm('Setujui bukti DP ini?')">
                                Setujui DP
                            </button>
                        </form>

                        <form method="POST"
                              action="{{ route('owner.dp.tolak', $order) }}">
                            @csrf

                            <input name="reason"
                                   required
                                   placeholder="Alasan penolakan">

                            <button class="btn red">
                                Tolak DP
                            </button>
                        </form>

                    </div>

                @elseif($p->status === 'pending' && $p->type === 'pelunasan')
                    <div class="actions">

                        <form method="POST"
                              action="{{ route('owner.pelunasan.verifikasi', $p) }}">
                            @csrf

                            <button class="btn">
                                Verifikasi Pelunasan
                            </button>
                        </form>

                        <form method="POST"
                              action="{{ route('owner.pelunasan.tolak', $p) }}">
                            @csrf

                            <input name="reason"
                                   required
                                   placeholder="Alasan penolakan">

                            <button class="btn red">
                                Tolak Pelunasan
                            </button>
                        </form>

                    </div>
                @endif

            </div>

        @empty
            <p class="muted">Belum ada pembayaran.</p>
        @endforelse
    </section>

    {{-- PENUGASAN KORLAP --}}
    <section class="panel">
        <h3>Penugasan Korlap</h3>

        @php
            $korlapAktif = $order->assignments->first(
                fn($a) => $a->role === 'korlap'
                    && in_array($a->status, ['diterima', 'ditawarkan'])
            );
        @endphp

        @if($korlapAktif)
            <p>
                Korlap:
                <b>{{ $korlapAktif->user->name ?? '-' }}</b>
                ({{ $korlapAktif->status }})
            </p>
        @else
            <p class="muted">
                Belum ada Korlap yang ditugaskan.
            </p>
        @endif

        @if($order->status === 'approved')
            <form method="POST"
                  action="{{ route('owner.korlap.tugaskan', $order) }}">
                @csrf

                <label for="korlap_id">
                    Pilih Korlap yang tersedia
                </label>

                <br>

                <select name="korlap_id" id="korlap_id" required>
                    <option value="">-- Pilih Korlap --</option>

                    @foreach($korlapTersedia as $k)
                        <option value="{{ $k->id }}">
                            {{ $k->name }}
                        </option>
                    @endforeach
                </select>

                <button class="btn">
                    Tugaskan Korlap
                </button>
            </form>
        @else
            <p class="muted">
                Korlap dapat ditugaskan setelah DP disetujui.
            </p>
        @endif
        {{-- TOMBOL KELOLA HONORARIUM --}}
        <div style="margin-top: 20px;">
            <a href="{{ route('owner.honorarium.index', $order->id) }}"
               class="btn">
                Kelola Honorarium
            </a>
        </div>

    </section>

</main>
@endsection
