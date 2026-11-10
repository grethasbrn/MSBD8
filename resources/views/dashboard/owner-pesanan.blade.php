@extends('layouts.app')

@section('title', 'Daftar Pesanan - Owner')

@push('styles')
<style>
    .wrap {
        max-width: 1100px;
        margin: 35px auto;
        padding: 0 18px;
    }

    .wrap h1 {
        color: #672b4a;
        margin-bottom: 8px;
    }

    .muted {
        color: #777;
        font-size: 13px;
        margin-bottom: 22px;
    }

    .box {
        background: white;
        border: 1px solid #eadfc8;
        border-radius: 12px;
        padding: 18px;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th,
    td {
        text-align: left;
        padding: 12px;
        border-bottom: 1px solid #eee;
        white-space: nowrap;
    }

    th {
        color: #672b4a;
        background: #faf7f9;
        font-size: 14px;
    }

    td {
        color: #444;
        font-size: 14px;
    }

    a {
        color: #762c50;
        font-weight: bold;
        text-decoration: none;
    }

    a:hover {
        text-decoration: underline;
    }

    .status-message {
        background: #edf8ef;
        color: #276738;
        padding: 12px 15px;
        border-radius: 8px;
        margin-bottom: 18px;
    }

    .empty {
        text-align: center;
        color: #777;
        padding: 25px;
    }
</style>
@endpush

@section('content')
<main class="wrap">

    <p>
        <a href="{{ route('dashboard.owner') }}">
            ← Dashboard Owner
        </a>
    </p>

    <h1>Daftar Pesanan Masuk</h1>

    <p class="muted">
        Seluruh pesanan pelanggan yang tercatat di sistem.
    </p>
    <div style="margin-bottom: 20px;">
        <a href="{{ route('owner.pesanan.create') }}"
        style="display: inline-block; background: #672b4a; color: white;
                padding: 10px 18px; border-radius: 8px;">
            + Buat Pesanan Pelanggan
        </a>
    </div>

    @if(session('status'))
        <div class="status-message">
            {{ session('status') }}
        </div>
    @endif

    <div class="box">
        <table>
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Pelanggan</th>
                    <th>Paket</th>
                    <th>Tanggal Acara</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>

            <tbody>
                @forelse($pesanan as $o)
                    <tr>
                        <td>{{ $o->code }}</td>

                        <td>
                            {{ $o->contact_name ?: ($o->customer->name ?? '-') }}
                        </td>

                        <td>
                            {{ $o->package->name ?? '-' }}
                        </td>

                        <td>
                            {{ $o->event_date->format('d/m/Y') }}
                        </td>

                        <td>
                            Rp {{ number_format((float) $o->total_amount, 0, ',', '.') }}
                        </td>

                        <td>
                            {{ str_replace('_', ' ', ucfirst($o->status)) }}
                        </td>

                        <td>
                            <a href="{{ route('owner.pesanan.show', $o) }}">
                                Detail
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty">
                            Belum ada pesanan.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top: 18px;">
            {{ $pesanan->links() }}
        </div>
    </div>

</main>
@endsection
