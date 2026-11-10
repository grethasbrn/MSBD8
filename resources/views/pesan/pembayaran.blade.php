@extends('layouts.app')

@section('title', 'Pembayaran DP - Sanggar Tri Arga')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pesan.css') }}">
@endpush

@section('content')
    @php $info = $quote['payment_info']; @endphp

    <section class="form-section">
        <div class="form-container">
            <h2>Rincian Biaya & Pembayaran DP</h2>
            <p>Langkah 2 dari 2: periksa pesanan Anda, transfer DP, lalu unggah bukti pembayaran.</p>

            @include('pesan._galat')

            <!-- Ringkasan acara -->
            <div class="ringkasan-box">
                <h4>Ringkasan Acara</h4>
                <div class="biaya-row"><span>Pemesan</span><span>{{ auth()->user()->name }}</span></div>
                <div class="biaya-row"><span>Paket</span><span>{{ $paket->name }}</span></div>
                <div class="biaya-row">
                    <span>Add-on</span>
                    <span>{{ $addons->isEmpty() ? '-' : $addons->pluck('name')->implode(', ') }}</span>
                </div>
                <div class="biaya-row"><span>Tanggal acara</span><span>{{ \App\Support\Label::tanggal($draft['event_date']) }}</span></div>
                @if ($draft['event_time'])
                    <div class="biaya-row"><span>Jam acara</span><span>{{ $draft['event_time'] }}</span></div>
                @endif
                <div class="biaya-row"><span>Lokasi</span><span>{{ $draft['event_location'] }}</span></div>
                @if ($draft['notes'])
                    <div class="biaya-row"><span>Catatan</span><span>{{ $draft['notes'] }}</span></div>
                @endif
            </div>

            <!-- Rincian biaya (dihitung oleh fungsi fn_hitung_biaya di PostgreSQL) -->
            <div class="ringkasan-box">
                <h4>Rincian Biaya</h4>
                <div class="biaya-row"><span>{{ $paket->name }}</span><span>{{ \App\Support\Label::rupiah($quote['package_price']) }}</span></div>
                @foreach ($addons as $a)
                    <div class="biaya-row"><span>{{ $a->name }}</span><span>+{{ \App\Support\Label::rupiah($a->price) }}</span></div>
                @endforeach
                <div class="biaya-row total"><span>Total Biaya</span><span>{{ \App\Support\Label::rupiah($quote['total_amount']) }}</span></div>
                <div class="biaya-row"><span>DP minimal ({{ rtrim(rtrim(number_format($info['dp_percent'], 2, '.', ''), '0'), '.') }}%)</span><strong>{{ \App\Support\Label::rupiah($quote['min_dp_amount']) }}</strong></div>
                <div class="biaya-row"><span>Sisa pelunasan setelah DP</span><span>{{ \App\Support\Label::rupiah($quote['remaining_after_min_dp']) }}</span></div>
            </div>

            <form method="POST" action="{{ route('pesan.kirim') }}" enctype="multipart/form-data">
                @csrf

                <div class="input-group">
                    <label>Tujuan Pembayaran DP</label>
                    <div class="radio-box">
                        <div class="pay-info">
                            Transfer ke <strong>{{ $info['bank_name'] }} {{ $info['bank_account_number'] }}</strong><br>
                            a.n. {{ $info['bank_account_name'] }}
                            @if ($info['qris_url'])
                                <br><img src="{{ $info['qris_url'] }}" alt="QRIS Sanggar" style="max-width: 200px; margin-top: 8px;">
                            @endif
                        </div>

                        <label for="metodeBayar">Metode Pembayaran</label>
                        <select id="metodeBayar" name="payment_method" style="margin-bottom: 14px;">
                            @foreach (['transfer' => 'Transfer Bank', 'qris' => 'QRIS', 'ewallet' => 'E-Wallet'] as $nilai => $teks)
                                <option value="{{ $nilai }}" @selected(old('payment_method', 'transfer') === $nilai)>{{ $teks }}</option>
                            @endforeach
                        </select>

                        <label for="nominalDp">Nominal DP yang dibayarkan (Rp)</label>
                        <input type="number" id="nominalDp" name="dp_amount" min="{{ $quote['min_dp_amount'] }}" step="1000"
                               value="{{ old('dp_amount', (int) $quote['min_dp_amount']) }}" required style="margin-bottom: 14px;">

                        <label for="buktiBayar">Unggah Bukti Pembayaran (JPG, PNG, WEBP, atau PDF, maks. 5 MB)</label>
                        <input type="file" id="buktiBayar" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Konfirmasi & Kirim Pesanan</button>
            </form>

            <a href="{{ route('pesan') }}" class="back-link">← Ubah Data Acara</a>
        </div>
    </section>
@endsection
