@extends('layouts.app')

@section('title', 'Pesanan ' . $order->code . ' - Sanggar Tri Arga')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/pesan.css') }}">
@endpush

@section('content')
    <section class="form-section">
        <div class="form-container">
            <p class="judul-kecil" style="margin-bottom: 4px;">ID TRANSAKSI</p>
            <h2>{{ $order->code }}</h2>
            <p>
                <span class="badge-status status-{{ $order->status }}">{{ \App\Support\Label::statusPesanan($order->status) }}</span>
                @if ($order->lunas_at)
                    <span class="badge-status status-approved">Lunas</span>
                @endif
            </p>

            @if (session('baru'))
                <div class="pesan-alert sukses" id="bannerBaru">
                    <strong>Pesanan berhasil dikirim!</strong> Status: Menunggu Verifikasi DP. Simpan ID Transaksi di atas.
                    Anda akan diarahkan ke WhatsApp untuk konfirmasi dalam <span id="hitungMundur">5</span> detik.
                    <a href="#" id="batalAlihkan" style="color: inherit; text-decoration: underline;">Tetap di halaman ini</a>
                </div>
            @endif
            @if (session('status'))
                <div class="pesan-alert sukses">{{ session('status') }}</div>
            @endif
            @include('pesan._galat')

            <a href="{{ $waUrl }}" target="_blank" rel="noopener" class="btn-wa" id="btnWA">Konfirmasi via WhatsApp</a>

            <div class="ringkasan-box">
                <h4>Detail Acara</h4>
                <div class="biaya-row"><span>Paket</span><span>{{ $order->package->name }}</span></div>
                <div class="biaya-row"><span>Add-on</span><span>{{ $order->addons->isEmpty() ? '-' : $order->addons->pluck('name')->implode(', ') }}</span></div>
                <div class="biaya-row"><span>Tanggal acara</span><span>{{ \App\Support\Label::tanggal($order->event_date) }}</span></div>
                @if ($order->event_time)
                    <div class="biaya-row"><span>Jam acara</span><span>{{ substr($order->event_time, 0, 5) }}</span></div>
                @endif
                <div class="biaya-row"><span>Lokasi</span><span>{{ $order->event_location }}</span></div>
                @if ($order->notes)
                    <div class="biaya-row"><span>Catatan</span><span>{{ $order->notes }}</span></div>
                @endif
            </div>

            <div class="ringkasan-box">
                <h4>Rincian Biaya</h4>
                <div class="biaya-row"><span>Total biaya</span><span>{{ \App\Support\Label::rupiah($order->total_amount) }}</span></div>
                <div class="biaya-row"><span>DP minimal</span><span>{{ \App\Support\Label::rupiah($order->min_dp_amount) }}</span></div>
                <div class="biaya-row"><span>Sudah dibayar (terverifikasi)</span><span>{{ \App\Support\Label::rupiah($dibayar) }}</span></div>
                <div class="biaya-row total"><span>Sisa tagihan</span><span>{{ \App\Support\Label::rupiah($sisa) }}</span></div>
                @if ($order->status === 'cancelled' && $order->compensation_amount > 0)
                    <p style="margin: 10px 0 0; font-size: 12px;">DP sebesar {{ \App\Support\Label::rupiah($order->compensation_amount) }} tidak dapat dikembalikan (non-refundable).</p>
                @endif
            </div>

            <div class="ringkasan-box scroll-x">
                <h4>Riwayat Pembayaran</h4>
                <table class="tabel-pesanan">
                    <thead><tr><th>Jenis</th><th>Metode</th><th>Nominal</th><th>Status</th><th>Bukti</th></tr></thead>
                    <tbody>
                        @forelse ($order->payments as $p)
                            <tr>
                                <td>{{ \App\Support\Label::jenisBayar($p->type) }}</td>
                                <td>{{ \App\Support\Label::metode($p->method) }}</td>
                                <td>{{ \App\Support\Label::rupiah($p->amount) }}</td>
                                <td>
                                    <span class="badge-status pay-{{ $p->status }}">{{ \App\Support\Label::statusBayar($p->status) }}</span>
                                    @if ($p->status === 'rejected' && $p->reject_reason)
                                        <br><small>Alasan: {{ $p->reject_reason }}</small>
                                    @endif
                                </td>
                                <td>@if ($p->proof_url)<a href="{{ $p->proof_url }}" target="_blank" rel="noopener">Lihat</a>@else - @endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="5">Belum ada pembayaran.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Unggah ulang DP (jika ditolak Owner) --}}
            @if ($bisaDp)
                <form method="POST" action="{{ route('pesanan.dp', ['order' => $order->code]) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="input-group">
                        <label>Unggah Ulang Bukti DP</label>
                        <div class="radio-box">
                            <div class="pay-info">Transfer ke <strong>{{ $info['bank_name'] }} {{ $info['bank_account_number'] }}</strong> a.n. {{ $info['bank_account_name'] }}</div>
                            <label for="metodeDp">Metode Pembayaran</label>
                            <select id="metodeDp" name="payment_method" style="margin-bottom: 14px;">
                                @foreach (['transfer' => 'Transfer Bank', 'qris' => 'QRIS', 'ewallet' => 'E-Wallet'] as $nilai => $teks)
                                    <option value="{{ $nilai }}">{{ $teks }}</option>
                                @endforeach
                            </select>
                            <label for="nominalDp">Nominal (Rp)</label>
                            <input type="number" id="nominalDp" name="amount" min="{{ $order->min_dp_amount }}" step="1000" value="{{ (int) $order->min_dp_amount }}" style="margin-bottom: 14px;">
                            <label for="buktiDp">Bukti Pembayaran</label>
                            <input type="file" id="buktiDp" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit">Kirim Bukti DP</button>
                </form>
            @endif

            {{-- Pelunasan sisa tagihan --}}
            @if ($bisaLunas)
                <form method="POST" action="{{ route('pesanan.pelunasan', ['order' => $order->code]) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="input-group">
                        <label>Pelunasan Sisa Tagihan</label>
                        <div class="radio-box">
                            <div class="pay-info">
                                Sisa tagihan <strong>{{ \App\Support\Label::rupiah($sisa) }}</strong>. Transfer ke
                                <strong>{{ $info['bank_name'] }} {{ $info['bank_account_number'] }}</strong> a.n. {{ $info['bank_account_name'] }},
                                atau bayar tunai kepada Koordinator Lapangan di hari acara.
                            </div>
                            <label for="metodeLunas">Metode Pembayaran</label>
                            <select id="metodeLunas" name="payment_method" style="margin-bottom: 14px;">
                                @foreach (['transfer' => 'Transfer Bank', 'qris' => 'QRIS', 'ewallet' => 'E-Wallet'] as $nilai => $teks)
                                    <option value="{{ $nilai }}">{{ $teks }}</option>
                                @endforeach
                            </select>
                            <label for="nominalLunas">Nominal (Rp)</label>
                            <input type="number" id="nominalLunas" name="amount" min="1" step="1000" value="{{ (int) $sisa }}" style="margin-bottom: 14px;">
                            <label for="buktiLunas">Bukti Pembayaran</label>
                            <input type="file" id="buktiLunas" name="proof" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                        </div>
                    </div>
                    <button type="submit" class="btn-submit">Kirim Bukti Pelunasan</button>
                </form>
            @endif

            <a href="{{ route('pesanan.index') }}" class="back-link">← Semua Pesanan Saya</a>
            &nbsp;&nbsp;<a href="{{ route('beranda') }}" class="back-link">Beranda</a>
        </div>
    </section>
@endsection

@if (session('baru'))
    @push('scripts')
        <script>
            // Sesuai alur: setelah pesanan tersimpan, arahkan pelanggan ke WhatsApp sanggar
            const tujuanWA = @json($waUrl);
            let sisaDetik = 5;
            const label = document.getElementById('hitungMundur');
            const timer = setInterval(() => {
                sisaDetik--;
                label.textContent = sisaDetik;
                if (sisaDetik <= 0) {
                    clearInterval(timer);
                    window.location.href = tujuanWA;
                }
            }, 1000);
            document.getElementById('batalAlihkan').addEventListener('click', e => {
                e.preventDefault();
                clearInterval(timer);
                document.getElementById('bannerBaru').innerHTML = '<strong>Pesanan berhasil dikirim!</strong> Status: Menunggu Verifikasi DP. Tekan tombol hijau di bawah untuk konfirmasi lewat WhatsApp.';
            });
        </script>
    @endpush
@endif
