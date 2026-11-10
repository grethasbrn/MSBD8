@extends('layouts.app')
@section('title', 'Dashboard Owner - Tri Arga Dance Company')
@push('styles')
<style>
.dash{max-width:1150px;margin:35px auto;padding:0 18px;color:#30233a}.dash-head,.dash-row{display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap}.dash h1{color:#5b2544}.dash-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin:22px 0}.dash-card,.dash-panel{background:#fff;border:1px solid #eadfc8;border-radius:14px;padding:18px;box-shadow:0 4px 14px #32152b0b}.dash-card small{color:#766b73}.dash-card b{display:block;font-size:27px;margin-top:8px;color:#7d294e}.dash-cols{display:grid;grid-template-columns:1fr 1fr;gap:16px}.dash-row{padding:13px 0;border-top:1px solid #eee}.dash-row:first-of-type{border:0}.dash a{color:#7d294e;font-weight:700}.dash .muted{color:#786f76;font-size:13px}.dash .btn{display:inline-block;border:0;border-radius:8px;padding:9px 13px;background:#7d294e;color:white;text-decoration:none;cursor:pointer}.dash .btn.alt{background:#eee8ec;color:#54243c}.dash .flash{padding:12px;background:#e8f6ec;border-radius:8px;margin:12px 0}.dash form.inline{display:inline-block}.dash input{padding:8px;border:1px solid #ddd;border-radius:7px;max-width:170px}@media(max-width:800px){.dash-grid{grid-template-columns:repeat(2,1fr)}.dash-cols{grid-template-columns:1fr}}
</style>
@endpush
@section('content')
<div class="dash">
 <div class="dash-head"><div><h1>Dashboard Owner</h1><p class="muted">Selamat datang, {{ auth()->user()->name }}. Kelola pesanan dan kegiatan Tri Arga.</p></div><form method="POST" action="{{ route('logout') }}">@csrf<button class="btn alt">Keluar</button></form></div>
 @if(session('status'))<div class="flash">{{ session('status') }}</div>@endif
 @if($errors->any())<div class="flash">{{ $errors->first() }}</div>@endif
 <div class="dash-panel" style="margin: 20px 0;">
    <div class="dash-head">
        <h3>Notifikasi</h3>
        <span class="muted">
            {{ $notifikasi->whereNull('read_at')->count() }} belum dibaca
        </span>
    </div>

    @forelse($notifikasi as $notif)
        <div class="dash-row">
            <div>
                <b>{{ $notif->title }}</b>

                @if(!$notif->read_at)
                    <span style="color:#b7791f;font-size:12px;">
                        (Baru)
                    </span>
                @endif

                <div>{{ $notif->body }}</div>

                <div class="muted">
                    {{ $notif->created_at?->format('d/m/Y H:i') }}
                </div>
            </div>
        </div>
    @empty
        <p class="muted">Belum ada notifikasi.</p>
    @endforelse
</div>
 <div class="dash-grid">
  <div class="dash-card"><small>Total Pesanan</small><b>{{ number_format($ringkasan['total_pesanan']) }}</b></div>
  <div class="dash-card"><small>Menunggu Verifikasi DP</small><b>{{ number_format($ringkasan['menunggu_dp']) }}</b></div>
  <div class="dash-card"><small>Pesanan Aktif</small><b>{{ number_format($ringkasan['pesanan_aktif']) }}</b></div>
  <div class="dash-card"><small>Acara Mendatang</small><b>{{ number_format($ringkasan['acara_mendatang']) }}</b></div>
 </div>
 <div class="dash-cols">
  <section class="dash-panel"><h3>Pembayaran Menunggu Verifikasi</h3><p class="muted">Periksa bukti DP atau pelunasan sebelum diproses.</p>
   @forelse($pembayaranMenunggu as $p)<div class="dash-row"><div><b>{{ $p->code }} · {{ $p->customer_name }}</b><div class="muted">{{ $p->type === 'dp' ? 'DP' : 'Pelunasan' }} · {{ $p->method }} · {{ \Illuminate\Support\Carbon::parse($p->event_date)->format('d/m/Y') }}</div><b>Rp {{ number_format((float)$p->amount,0,',','.') }}</b><div><a target="_blank" rel="noopener" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($p->proof_path) }}">Lihat bukti pembayaran</a></div><a href="{{ route('owner.pesanan.show',$p->order_id) }}">Buka detail pesanan</a></div></div>@empty<div class="muted">Belum ada pembayaran yang menunggu verifikasi.</div>@endforelse
  </section>
  <section class="dash-panel"><h3>Acara Mendatang</h3><p class="muted">Pesanan yang DP-nya sudah disetujui.</p>
   @forelse($acaraMendatang as $a)<div class="dash-row"><div><b>{{ $a->package_name }} · {{ $a->customer_name }}</b><div class="muted">{{ $a->code }} · {{ \Illuminate\Support\Carbon::parse($a->event_date)->format('d M Y') }} {{ $a->event_time ? substr((string)$a->event_time,0,5) : '' }}</div><div class="muted">{{ $a->event_location }}</div><a href="{{ route('owner.pesanan.show',$a->id) }}">Kelola acara</a></div></div>@empty<div class="muted">Belum ada acara mendatang.</div>@endforelse
  </section>
 </div>
 <p style="margin-top:20px; display:flex; gap:10px; flex-wrap:wrap;">
    <a class="btn" href="{{ route('owner.pesanan.index') }}">
        Lihat Semua Pesanan
    </a>

    <a class="btn" href="{{ route('owner.laporan.index') }}">
        Laporan Keuangan
    </a>
</p>
</div>
@endsection
