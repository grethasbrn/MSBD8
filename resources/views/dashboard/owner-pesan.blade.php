@extends('layouts.app')

@section('title', 'Buat Pesanan Pelanggan')

@section('content')
<div class="wrap">
    <div class="page-header">
        <div>
            <h1>Buat Pesanan Pelanggan</h1>
            <p>Catat pesanan pelanggan yang memesan secara langsung kepada Owner.</p>
        </div>

        <a href="{{ route('owner.pesanan.index') }}">
            ← Kembali ke Pesanan
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Pesanan belum berhasil disimpan.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        action="{{ route('owner.pesanan.store') }}"
        method="POST"
        enctype="multipart/form-data"
    >
        @csrf

        <div class="panel">
            <h3>Data Pelanggan</h3>

            <label>Nama Pelanggan</label>
            <input
                type="text"
                name="contact_name"
                value="{{ old('contact_name') }}"
                placeholder="Masukkan nama pelanggan"
                required
            >

            <label>Nomor Telepon / WhatsApp</label>
            <input
                type="text"
                name="contact_phone"
                value="{{ old('contact_phone') }}"
                placeholder="Contoh: 081234567890"
                required
            >
        </div>

        <div class="panel">
            <h3>Detail Acara</h3>

            <label>Pilih Paket</label>
            <select name="package_id" required>
                <option value="">-- Pilih Paket --</option>

                @foreach ($packages as $package)
                    <option
                        value="{{ $package->id }}"
                        @selected(old('package_id') == $package->id)
                    >
                        {{ $package->name }}
                    </option>
                @endforeach
            </select>

            <label>Add-on Tambahan</label>

            @forelse ($addons as $addon)
                <div>
                    <label>
                        <input
                            type="checkbox"
                            name="addon_ids[]"
                            value="{{ $addon->id }}"
                            @checked(in_array(
                                $addon->id,
                                old('addon_ids', [])
                            ))
                        >
                        {{ $addon->name }}
                    </label>
                </div>
            @empty
                <p>Belum ada add-on tersedia.</p>
            @endforelse

            <label>Tanggal Acara</label>
            <input
                type="date"
                name="event_date"
                value="{{ old('event_date') }}"
                min="{{ now()->toDateString() }}"
                required
            >

            <label>Waktu Acara</label>
            <input
                type="time"
                name="event_time"
                value="{{ old('event_time') }}"
            >

            <label>Lokasi Acara</label>
            <textarea
                name="event_location"
                placeholder="Masukkan lokasi acara"
                required
            >{{ old('event_location') }}</textarea>

            <label>Catatan Tambahan</label>
            <textarea
                name="notes"
                placeholder="Catatan jika ada"
            >{{ old('notes') }}</textarea>
        </div>

        <div class="panel">
            <h3>Pembayaran DP</h3>

            <label>Nominal DP</label>
            <input
                type="number"
                name="dp_amount"
                value="{{ old('dp_amount') }}"
                min="1"
                step="0.01"
                placeholder="Masukkan nominal DP"
                required
            >

            <label>Metode Pembayaran</label>
            <select name="payment_method" id="payment_method" required>
                <option value="">-- Pilih Metode --</option>
                <option value="tunai" @selected(old('payment_method') == 'tunai')>
                    Tunai
                </option>
                <option value="transfer" @selected(old('payment_method') == 'transfer')>
                    Transfer
                </option>
                <option value="qris" @selected(old('payment_method') == 'qris')>
                    QRIS
                </option>
                <option value="ewallet" @selected(old('payment_method') == 'ewallet')>
                    E-Wallet
                </option>
            </select>

            <div id="proof_section">
                <label>Bukti Pembayaran</label>
                <input
                    type="file"
                    name="proof"
                    accept=".jpg,.jpeg,.png,.webp,.pdf"
                >
                <small>
                    Wajib untuk pembayaran non-tunai. Maksimal 5 MB.
                </small>
            </div>
        </div>

        <button type="submit">
            Simpan Pesanan
        </button>
    </form>
</div>

<script>
    const paymentMethod = document.getElementById('payment_method');
    const proofSection = document.getElementById('proof_section');
    const proofInput = proofSection.querySelector('input');

    function toggleProof() {
        const isCash = paymentMethod.value === 'tunai';

        proofSection.style.display = isCash ? 'none' : 'block';
        proofInput.required = !isCash;
    }

    paymentMethod.addEventListener('change', toggleProof);
    toggleProof();
</script>
@endsection
