@extends('layouts.app')

@section('title', 'Honorarium')

@section('content')
<div class="wrap" style="max-width: 1100px; margin: 30px auto; padding: 0 20px;">

    <a href="{{ route('owner.pesanan.show', $order->id) }}">
        ← Kembali ke detail pesanan
    </a>

    <h1>Honorarium Anggota</h1>
    <p>Pesanan: {{ $order->code }}</p>

    {{-- Pesan berhasil --}}
    @if(session('success'))
        <div style="padding: 12px; background: #d1fae5; margin: 15px 0;">
            {{ session('success') }}
        </div>
    @endif

    {{-- Pesan error --}}
    @if($errors->any())
        <div style="padding: 12px; background: #fee2e2; margin: 15px 0;">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @php
        $siapDicairkan = $assignments->contains(
            fn($a) => $a->attended_at && !$a->honorarium
        );
    @endphp

    {{-- Tabel honorarium --}}
    <div style="overflow-x: auto; margin-top: 20px;">
        <table width="100%" cellpadding="12" cellspacing="0"
            style="border-collapse: collapse; background: white;">

            <thead>
                <tr style="background: #eee;">
                    <th>Nama Anggota</th>
                    <th>Peran</th>
                    <th>Kehadiran</th>
                    <th>Honorarium</th>
                    <th>Status</th>
                    <th>Tanggal Pencairan</th>
                    <th>Dicairkan Oleh</th>
                </tr>
            </thead>

            <tbody>
                @forelse($assignments as $assignment)
                    <tr style="border-bottom: 1px solid #ddd;">

                        <td>{{ $assignment->user->name }}</td>

                        <td>{{ ucfirst($assignment->role) }}</td>

                        <td>
                            {{ $assignment->attended_at ? 'Hadir' : 'Belum hadir' }}
                        </td>

                        <td>
                            @if($assignment->honorarium)
                                Rp{{ number_format($assignment->honorarium->amount, 0, ',', '.') }}
                            @else
                                -
                            @endif
                        </td>

                        <td>
                            @if($assignment->honorarium)
                                Sudah dicairkan
                            @elseif($assignment->attended_at)
                                Siap dicairkan
                            @else
                                Menunggu presensi
                            @endif
                        </td>

                        <td>
                            {{ $assignment->honorarium?->paid_at?->format('d-m-Y H:i') ?? '-' }}
                        </td>

                        <td>
                            {{ $assignment->honorarium?->paidBy?->name ?? '-' }}
                        </td>

                    </tr>
                @empty
                    <tr>
                        <td colspan="7" align="center">
                            Belum ada anggota yang diterima dalam penugasan.
                        </td>
                    </tr>
                @endforelse
            </tbody>

        </table>
    </div>

    {{-- Tombol pencairan --}}
    @if($siapDicairkan)
        <form method="POST"
            action="{{ route('owner.honorarium.cairkan', $order->id) }}"
            style="margin-top: 20px;">

            @csrf

            <button type="submit"
                onclick="return confirm('Yakin ingin mencairkan honorarium anggota yang sudah hadir?')">
                Cairkan Honorarium
            </button>
        </form>
    @else
        <p style="margin-top: 20px;">
            Belum ada honorarium yang siap dicairkan.
        </p>
    @endif

</div>
@endsection
