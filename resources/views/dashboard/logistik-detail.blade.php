 @extends('layouts.app')

@section('title', 'Detail Logistik')

@section('content')
@php
    $tanggalAcara = \Carbon\Carbon::parse($order->event_date)->startOfDay();
    $mulaiPeminjaman = $tanggalAcara->copy()->subDay();

    $bolehPinjam = now()->gte($mulaiPeminjaman);
    $bolehKembali = now()->startOfDay()->gte($tanggalAcara);
@endphp

<div class="wrap">
    <a href="{{ route(auth()->user()->role . '.logistik.index') }}">
        ← Kembali ke daftar acara
    </a>

    <h1>Logistik Acara {{ $order->code }}</h1>

    <div class="panel">
        <h3>Informasi Acara</h3>
        <p>Tanggal: {{ $order->event_date?->format('d-m-Y') }}</p>
        <p>Lokasi: {{ $order->event_location }}</p>
    </div>

    <div class="panel">
        <h3>Pinjam Perlengkapan</h3>

        @if($bolehPinjam)
            <form method="POST"
                action="{{ route(auth()->user()->role . '.logistik.borrow', $order->id) }}">
                @csrf

                @forelse($items->where('status', 'tersedia') as $item)
                    <label class="item">
                        <input type="checkbox" name="item_ids[]"
                            value="{{ $item->id }}">
                        {{ $item->name }} ({{ $item->code }})
                    </label>
                @empty
                    <p>Tidak ada perlengkapan yang tersedia.</p>
                @endforelse

                @if($items->where('status', 'tersedia')->isNotEmpty())
                    <button type="submit">Catat Barang Keluar</button>
                @endif
            </form>
        @else
            <p class="info">
                Peminjaman perlengkapan baru dapat dilakukan mulai
                {{ $mulaiPeminjaman->format('d-m-Y') }} (H-1 acara).
            </p>
        @endif
    </div>

    <div class="panel">
        <h3>Daftar Peminjaman</h3>

        @forelse($loans as $loan)
            <div class="loan">
                <strong>{{ $loan->item->name ?? 'Barang tidak ditemukan' }}</strong>

                @if(!$loan->returned_at)
                    @if($bolehKembali)
                        <form method="POST"
                            action="{{ route(auth()->user()->role . '.logistik.return', $order->id) }}">
                            @csrf
                            <input type="hidden" name="returns[0][loan_id]"
                                value="{{ $loan->id }}">

                            <label>Kondisi barang</label>
                            <select name="returns[0][condition]" required>
                                <option value="baik">Baik</option>
                                <option value="rusak">Rusak</option>
                                <option value="hilang">Hilang</option>
                            </select>

                            <label>Keterangan</label>
                            <input type="text" name="returns[0][notes]"
                                placeholder="Keterangan tambahan">

                            <button type="submit">Catat Pengembalian</button>
                        </form>
                    @else
                        <p class="info">
                            Pengembalian baru dapat dilakukan pada tanggal
                            {{ $tanggalAcara->format('d-m-Y') }} atau setelahnya.
                        </p>
                    @endif
                @else
                    <p>
                        Sudah dikembalikan -
                        {{ ucfirst($loan->return_condition) }}
                    </p>
                @endif
            </div>
        @empty
            <p>Belum ada barang yang dipinjam untuk acara ini.</p>
        @endforelse
    </div>
</div>

<style>
    .wrap {
        max-width: 1000px;
        margin: 35px auto;
        padding: 0 20px;
        color: #352633;
    }

    .panel {
        background: white;
        padding: 22px;
        border: 1px solid #eadde5;
        border-radius: 12px;
        margin-top: 20px;
    }

    .item, .loan {
        display: block;
        padding: 12px 0;
        border-bottom: 1px solid #eee;
    }

    .info {
        padding: 12px;
        background: #f8f0f4;
        border-radius: 8px;
        color: #682b4b;
    }

    button {
        margin-top: 12px;
        padding: 10px 16px;
        background: #682b4b;
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }

    input, select {
        display: block;
        margin: 8px 0;
        padding: 9px;
        border: 1px solid #ddd;
        border-radius: 6px;
    }
</style>
@endsection