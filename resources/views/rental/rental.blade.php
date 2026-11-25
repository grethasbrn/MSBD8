@extends('layouts.app')

@section('title', 'Rental Mandiri - Sanggar Tri Arga')

@section('content')

\<style>     .rental-wrapper {         max-width: 1100px;         margin: 40px auto;         padding: 24px;     }      .rental-heading {         text-align: center;         margin-bottom: 30px;     }      .rental-heading h1 {         color: #682b4b;         font-size: 30px;         font-weight: 700;     }      .rental-heading p {         color: #777;     }      .rental-section {         background: #fff;         border: 1px solid #eadde3;         border-radius: 14px;         padding: 24px;         margin-bottom: 24px;     }      .rental-section h2 {         color: #682b4b;         font-size: 20px;         margin-bottom: 18px;     }      .rental-grid {         display: grid;         grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));         gap: 18px;     }      .rental-card {         border: 1px solid #eadde3;         border-radius: 12px;         padding: 16px;     }      .rental-card h3 {         color: #682b4b;         margin: 8px 0;     }      .rental-card p {         color: #666;         font-size: 14px;     }      .rental-card label {         display: block;         margin-top: 12px;     }      .rental-card input[type="checkbox"] {         margin-right: 6px;     }      .rental-input {         width: 100%;         padding: 11px;         border: 1px solid #d8c5ce;         border-radius: 8px;         margin-top: 7px;     }      .rental-button {         background: #682b4b;         color: white;         border: none;         padding: 13px 24px;         border-radius: 8px;         cursor: pointer;         font-weight: 600;     }      .rental-button\:hover {         background: #762c50;     }      .rental-error {         color: #c0392b;         font-size: 13px;         margin-top: 5px;     } \</style>

\<div class="rental-wrapper">     \<div class="rental-heading">         \<h1>Rental Kostum & Alat Musik\</h1>         \<p>Sewa perlengkapan Sanggar Tri Arga tanpa harus memesan paket pertunjukan.\</p>     \</div>

```
@if ($errors->any())
    <div class="rental-section">
        <strong>Periksa kembali data berikut:</strong>
        @foreach ($errors->all() as $error)
            <div class="rental-error">{{ $error }}</div>
        @endforeach
    </div>
@endif

<form action="{{ route('rental.store') }}" method="POST" enctype="multipart/form-data">
    @csrf

    <div class="rental-section">
        <h2>1. Pilih Barang</h2>

        @if ($items->isEmpty())
            <p>Belum ada barang yang memiliki stok dan harga rental. Silakan hubungi pihak sanggar.</p>
        @else
            <div class="rental-grid">
                @foreach ($items as $index => $item)
                    <div class="rental-card">
                        <h3>{{ $item->name }}</h3>

                        <p>
                            Kategori:
                            {{ ucwords(str_replace('_', ' ', $item->category)) }}
                        </p>

                        <p>{{ $item->description }}</p>

                        <p>
                            Harga:
                            <strong>
                                Rp{{ number_format($item->rental_price, 0, ',', '.') }}/hari
                            </strong>
                        </p>

                        <p>Stok: {{ $item->stock_quantity }}</p>

                        <label>
                            <input
                                type="checkbox"
                                name="rentals[{{ $index }}][inventory_item_id]"
                                value="{{ $item->id }}"
                                {{ old("rentals.$index.inventory_item_id") ? 'checked' : '' }}
                            >
                            Pilih barang ini
                        </label>

                        <label>
                            Jumlah barang
                            <input
                                class="rental-input"
                                type="number"
                                name="rentals[{{ $index }}][quantity]"
                                min="1"
                                max="{{ $item->stock_quantity }}"
                                value="{{ old("rentals.$index.quantity", 1) }}"
                            >
                        </label>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="rental-section">
        <h2>2. Tanggal Rental</h2>

        <label>
            Tanggal mulai
            <input
                class="rental-input"
                type="date"
                name="rental_start_date"
                min="{{ date('Y-m-d') }}"
                value="{{ old('rental_start_date') }}"
                required
            >
        </label>

        <label>
            Tanggal selesai
            <input
                class="rental-input"
                type="date"
                name="rental_end_date"
                min="{{ date('Y-m-d') }}"
                value="{{ old('rental_end_date') }}"
                required
            >
        </label>
    </div>

    <div class="rental-section">
        <h2>3. Pembayaran</h2>

        <label>
            Metode pembayaran
            <select class="rental-input" name="payment_method" required>
                <option value="">Pilih metode pembayaran</option>
                <option value="transfer" {{ old('payment_method') == 'transfer' ? 'selected' : '' }}>Transfer Bank</option>
                <option value="qris" {{ old('payment_method') == 'qris' ? 'selected' : '' }}>QRIS</option>
                <option value="ewallet" {{ old('payment_method') == 'ewallet' ? 'selected' : '' }}>E-Wallet</option>
                <option value="tunai" {{ old('payment_method') == 'tunai' ? 'selected' : '' }}>Tunai</option>
            </select>
        </label>

        <label>
            Nominal DP
            <input
                class="rental-input"
                type="number"
                name="dp_amount"
                min="1"
                value="{{ old('dp_amount') }}"
                placeholder="Kosongkan untuk menggunakan DP minimum"
            >
        </label>

        <label>
            Bukti pembayaran
            <input
                class="rental-input"
                type="file"
                name="proof"
                accept=".jpg,.jpeg,.png,.pdf"
                required
            >
        </label>
        <small>Format JPG, JPEG, PNG, atau PDF. Maksimal 5 MB.</small>
    </div>

    <div class="rental-section">
        <h2>4. Catatan Tambahan</h2>
        <textarea
            class="rental-input"
            name="notes"
            rows="4"
            placeholder="Tambahkan catatan jika diperlukan"
        >{{ old('notes') }}</textarea>
    </div>

    <button
        type="submit"
        class="rental-button"
        {{ $items->isEmpty() ? 'disabled' : '' }}
    >
        Kirim Pesanan Rental
    </button>
</form>
```

\</div>
@endsection
