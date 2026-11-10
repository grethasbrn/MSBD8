@extends('layouts.app')

@section('title', 'Sanggar Tri Arga - Seni dan Pertunjukan')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/modal.css') }}">
@endpush

@section('content')
    <header class="navbar">
        <div class="nav-container">
            <a href="#beranda" class="logo">
                <img src="{{ asset('images/logo.png') }}" alt="Logo Sanggar Tri Arga" class="logo-img">
                <span>SANGGAR <strong>TRIARGA</strong></span>
            </a>
            <nav class="nav-menu" id="navMenu">
                <a href="#beranda" class="nav-link active">Beranda</a>
                <a href="#paket" class="nav-link">Paket</a>
                <a href="#barang" class="nav-link">Kostum dan Alat</a>
                <a href="#kalender" class="nav-link">Kalender</a>
                <a href="#galeri" class="nav-link">Galeri</a>
            </nav>
            <div class="nav-actions">
                @auth
                    <span style="color: #fff; font-size: 13px; margin-right: 8px;">{{ auth()->user()->name }}</span>
                    @if (auth()->user()->role === 'customer')
                        <a href="{{ route('pesanan.index') }}" class="btn-small btn-yellow">Pesanan Saya</a>
                    @else
                        <a href="{{ route('dashboard.' . auth()->user()->role) }}" class="btn-small btn-yellow">Dashboard</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" style="display: inline;">
                        @csrf
                        <button type="submit" class="btn-small btn-yellow">Keluar</button>
                    </form>
                @else
                    <a href="{{ route('login') }}" class="btn-small btn-yellow">Masuk</a>
                    <a href="{{ route('register') }}" class="btn-small btn-yellow">Daftar</a>
                @endauth
            </div>
        </div>
    </header>

    <main class="main-container">

        <!-- ================= BERANDA / HERO ================= -->
        <section class="page-section hero" id="beranda">
            <div class="hero-content">
                <p class="eyebrow">✦ SENI • BUDAYA • PERTUNJUKAN ✦</p>
                <h1>Hadirkan Seni di Setiap Momen</h1>
                <p class="hero-description">
                    Temukan paket pertunjukan tari dan musik, kostum, serta perlengkapan untuk melengkapi acara spesial Anda dengan nuansa budaya nusantara.
                </p>
                <div class="hero-buttons">
                    <a href="#paket" class="btn-primary">Lihat Paket</a>
                    @auth
                        <a href="{{ route('pesan') }}" class="btn-primary">Buat Pesanan</a>
                    @else
                        <!-- Tamu yang belum login diminta masuk lewat pop-up -->
                        <button type="button" class="btn-primary" id="btnBuatPesanan">Buat Pesanan</button>
                    @endauth
                </div>
            </div>
        </section>


        <!-- ================= 01 & 02. PAKET & OPSI TAMBAHAN ================= -->
        <section class="page-section packages section" id="paket">
            <div class="inner-content">
                <div class="section-heading">
                    <div>
                        <p class="section-label">01 — PERTUNJUKAN</p>
                        <h2>Pilih Pertunjukan untuk Acara Anda</h2>
                    </div>
                </div>

                <div class="package-grid">
                    @php
                        $warna = ['card-navy', 'card-red', 'card-yellow'];
                        $ikon  = ['✦', '◇', '♫'];
                    @endphp
                    @forelse ($paket as $p)
                        <article class="package-card {{ $warna[$loop->index % 3] }}">
                            <div class="package-top">
                                <span class="package-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="status available">Tersedia</span>
                            </div>
                            <div class="package-icon">{{ $ikon[$loop->index % 3] }}</div>
                            <h3>{{ $p->name }}</h3>
                            <p>{{ $p->description }}</p>
                            <div class="package-bottom">
                                <small>Mulai dari</small>
                                <strong>Rp{{ number_format($p->price, 0, ',', '.') }}</strong>
                            </div>
                        </article>
                    @empty
                        <p>Belum ada paket yang tersedia.</p>
                    @endforelse
                </div>

                <div class="addons-navy-banner">
                    <div class="addons-text">
                        <p class="section-label-gold">02 — OPSI TAMBAHAN</p>
                        <h2>Sesuaikan kebutuhan acara Anda</h2>
                        <p>Tambahkan kebutuhan ekstra pada pesanan Anda sesuai dengan konsep acara.</p>
                    </div>

                    <div class="addons-list-box">
                        @foreach ($addons as $a)
                            <div class="addon-item">
                                <div class="addon-item-info">
                                    <h4>{{ $a->name }}</h4>
                                    <p>{{ $a->description }}</p>
                                </div>
                                <span class="addon-item-price">+Rp{{ number_format($a->price, 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

            </div>
        </section>


        <!-- ================= 03. KATALOG BARANG ================= -->
        <section class="page-section section" id="barang">
            <div class="inner-content">
                <div class="section-heading">
                    <div>
                        <p class="section-label">03 — KATALOG BARANG</p>
                        <h2>Kostum dan Alat Musik</h2>
                    </div>
                </div>

                <div class="inventory-grid">
                    <a href="{{ route('katalog.kostum') }}" class="inventory-card">
                        <div class="inventory-image costume" style="background-image: url('{{ asset('images/coverbajuadat.png') }}'); background-size: cover; background-position: center; position: relative;">
                            <div style="position: absolute; inset: 0; background: rgba(74, 18, 21, 0.45); display: flex; align-items: center; justify-content: center;">
                                <span style="font-family: 'Times New Roman', Times, serif; font-size: 26px; color: var(--gold); font-weight: bold; letter-spacing: 2px; text-shadow: 0 2px 4px rgba(0,0,0,0.6);">KOSTUM</span>
                            </div>
                        </div>
                        <div class="inventory-info">
                            <div>
                                <h3>Kostum Tari</h3>
                                <p>Koleksi Kostum untuk Pertunjukan</p>
                            </div>
                            <span class="inventory-btn-link">LIHAT KATALOG</span>
                        </div>
                    </a>

                    <a href="{{ route('katalog.alat') }}" class="inventory-card">
                        <div class="inventory-image instrument" style="background-image: url('{{ asset('images/coverkatalogalatmusik.png') }}'); background-size: cover; background-position: center; position: relative;">
                            <div style="position: absolute; inset: 0; background: rgba(13, 22, 38, 0.45); display: flex; align-items: center; justify-content: center;">
                                <span style="font-family: 'Times New Roman', Times, serif; font-size: 26px; color: var(--gold); font-weight: bold; letter-spacing: 2px; text-shadow: 0 2px 4px rgba(0,0,0,0.6);">ALAT MUSIK</span>
                            </div>
                        </div>
                        <div class="inventory-info">
                            <div>
                                <h3>Alat Musik</h3>
                                <p>Gamelan, Kendang, Flute, Keyboard</p>
                            </div>
                            <span class="inventory-btn-link">LIHAT KATALOG</span>
                        </div>
                    </a>
                </div>
            </div>
        </section>


        <!-- ================= 04. KETERSEDIAAN ================= -->
        <section class="page-section calendar-preview section" id="kalender">
            <div class="inner-content">
                <div class="calendar-box">
                    <div class="calendar-text">
                        <p class="section-label">04 — KETERSEDIAAN</p>
                        <h2>Cek Tanggal Acara Anda</h2>
                        <p>
                            Maksimal 3 acara dapat diterima dalam satu hari. Status tanggal akan diperbarui secara realtime oleh owner berdasarkan pesanan masuk.
                        </p>
                    </div>

                    <div class="mini-calendar">
                        <div class="calendar-header">
                            <strong id="calendar-month-year">{{ $kalender['judul'] }}</strong>
                            <span>
                                @if ($kalender['prev'])
                                    <a href="{{ route('beranda', ['bulan' => $kalender['prev']]) }}#kalender" style="text-decoration: none; color: inherit;" title="Bulan sebelumnya">‹</a>
                                @else
                                    <span style="opacity: .3;">‹</span>
                                @endif
                                &nbsp;
                                @if ($kalender['next'])
                                    <a href="{{ route('beranda', ['bulan' => $kalender['next']]) }}#kalender" style="text-decoration: none; color: inherit;" title="Bulan berikutnya">›</a>
                                @else
                                    <span style="opacity: .3;">›</span>
                                @endif
                            </span>
                        </div>
                        <div class="calendar-days">
                            <span>Min</span><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span>
                        </div>
                        <div class="calendar-dates" id="realtime-calendar-dates">
                            @for ($i = 0; $i < $kalender['kosong_awal']; $i++)<span></span>@endfor
                            @foreach ($kalender['sel'] as $s)
                                @if ($s['status'] === 'lewat')
                                    <span style="opacity: .35;">{{ $s['hari'] }}</span>
                                @elseif ($s['status'] === 'penuh')
                                    <span title="Penuh" style="box-shadow: inset 0 -4px 0 var(--red-bright);">{{ $s['hari'] }}</span>
                                @elseif ($s['status'] === 'terbatas')
                                    <span title="Terbatas (sisa 1 slot)" style="box-shadow: inset 0 -4px 0 var(--orange-bright);">{{ $s['hari'] }}</span>
                                @else
                                    <span title="Tersedia" style="box-shadow: inset 0 -4px 0 var(--green);">{{ $s['hari'] }}</span>
                                @endif
                            @endforeach
                        </div>
                        <div class="calendar-legend">
                            <span><i class="available-dot"></i> Tersedia</span>
                            <span><i class="busy-dot"></i> Terbatas</span>
                            <span><i class="full-dot"></i> Penuh</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>


        <!-- ================= 05. GALERI ================= -->
        <section class="page-section section" id="galeri">
            <div class="inner-content">
                <div class="section-heading">
                    <div>
                        <p class="section-label">05 — GALERI</p>
                        <h2>Momen Pertunjukan Kami</h2>
                    </div>
                    <a href="https://instagram.com/triarga_dc" target="_blank" class="instagram-link">
                        Lihat lebih lengkap di Instagram <strong>@@triarga_dc</strong> 
                    </a>
                </div>

                <div class="gallery-grid">
                    <div class="gallery-card">
                        <img src="{{ asset('images/tari tradisisonal.png') }}" alt="Tari Tradisional">
                        <span>Tari Tradisional</span>
                    </div>
                    <div class="gallery-card">
                        <img src="{{ asset('images/pertunjukan tari.png') }}" alt="Pertunjukan Tari">
                        <span>Pertunjukan Tari</span>
                    </div>
                    <div class="gallery-card">
                        <img src="{{ asset('images/musik & karawitan.png') }}" alt="Musik dan Karawitan">
                        <span>Musik dan Karawitan</span>
                    </div>
                </div>
            </div>
        </section>

    </main>

    @guest
    <div style="text-align: center; padding: 18px 0; font-size: 12px; color: var(--muted);">
        Anggota tim sanggar? <a href="{{ route('login.pekerja') }}" style="color: var(--maroon); font-weight: 700;">Masuk sebagai pekerja</a>
    </div>
    @endguest

    <!-- ================= POPUP MODAL PERINGATAN (MASUK / DAFTAR) ================= -->
    @guest
    <div id="loginModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-icon">⚠️</div>
            <h3>Akun Diperlukan</h3>
            <p>Untuk membuat pesanan, silakan masuk jika Anda sudah memiliki akun, atau daftar terlebih dahulu jika belum memiliki akun.</p>
            <div class="modal-actions-custom">
                <a href="{{ route('login') }}" class="btn-modal-nav">Masuk</a>
                <a href="{{ route('register') }}" class="btn-modal-nav">Daftar</a>
            </div>
            <button type="button" id="closeModal" class="modal-close-text">Tutup</button>
        </div>
    </div>
    @endguest
@endsection

@push('scripts')
    <script>
        const navLinks = document.querySelectorAll('.nav-link');
        const sections = document.querySelectorAll('.page-section');

        window.addEventListener('scroll', () => {
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                if (pageYOffset >= (sectionTop - 150)) {
                    current = section.getAttribute('id');
                }
            });

            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href').includes(current)) {
                    link.classList.add('active');
                }
            });
        });

        // Pop-up "Akun Diperlukan" (hanya ada untuk tamu yang belum login)
        const btnBuatPesanan = document.getElementById('btnBuatPesanan');
        const loginModal = document.getElementById('loginModal');
        const closeModal = document.getElementById('closeModal');

        if (btnBuatPesanan && loginModal) {
            btnBuatPesanan.addEventListener('click', () => {
                loginModal.style.display = 'flex';
            });
            closeModal.addEventListener('click', () => {
                loginModal.style.display = 'none';
            });
            window.addEventListener('click', (e) => {
                if (e.target === loginModal) {
                    loginModal.style.display = 'none';
                }
            });
        }
    </script>
@endpush
