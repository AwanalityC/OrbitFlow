@extends('layouts.siswa')

@section('title', 'Dashboard - OrbitFlow')
@section('page-heading', 'Dashboard')
@section('page-subtitle', 'Sistem Inventarisasi dan Peminjaman Alat Sekolah')

@section('content')
            <div class="toolbar">
                <form method="GET" action="{{ route('siswa.dashboard') }}" class="filter-form" aria-label="Cari dan filter alat" data-live-filter data-live-target="#katalog" data-live-debounce="180">
                    <label class="search-wrap">
                        <span class="search-icon" aria-hidden="true">⌕</span>
                        <input class="search-input" type="search" name="search" placeholder="Cari alat..." value="{{ request('search') }}" aria-label="Cari alat">
                    </label>

                    <select class="filter-select" name="category" aria-label="Filter kategori">
                        <option value="">Kategori: Semua</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>

                    <select class="filter-select status-select" name="status" aria-label="Filter status">
                        <option value="">Status: Semua</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="button js-live-filter-fallback">Terapkan</button>
                </form>

                <a href="{{ route('borrowing.create') }}" class="button primary">＋ Ajukan Peminjaman</a>
            </div>

            <section class="overview-grid" aria-label="Ringkasan inventaris">
                <article class="overview-card overview-card-blue">
                    <span class="overview-icon" aria-hidden="true">▧</span>
                    <div class="overview-copy">
                        <div class="overview-label">Jenis Alat</div>
                        <div class="overview-value">{{ $totalEquipment }}</div>
                        <div class="overview-note">Inventaris terdaftar</div>
                    </div>
                </article>
                <article class="overview-card overview-card-green">
                    <span class="overview-icon" aria-hidden="true">✓</span>
                    <div class="overview-copy">
                        <div class="overview-label">Unit Tersedia</div>
                        <div class="overview-value">{{ $availableUnitsCount }}</div>
                        <div class="overview-note">Termasuk kondisi rusak ringan</div>
                    </div>
                </article>
                <article class="overview-card overview-card-violet">
                    <span class="overview-icon" aria-hidden="true">↗</span>
                    <div class="overview-copy">
                        <div class="overview-label">Sedang Dipinjam</div>
                        <div class="overview-value">{{ $borrowedUnitsCount }}</div>
                        <div class="overview-note">Unit dalam peminjaman</div>
                    </div>
                </article>
                <article class="overview-card overview-card-amber">
                    <span class="overview-icon" aria-hidden="true">⌘</span>
                    <div class="overview-copy">
                        <div class="overview-label">Kategori</div>
                        <div class="overview-value">{{ $categoryCount }}</div>
                        <div class="overview-note">Kelompok inventaris</div>
                    </div>
                </article>
            </section>

            <section id="katalog" data-live-results aria-labelledby="catalog-heading">
                <div class="catalog-heading-row">
                    <div>
                        <h2 class="catalog-title" id="catalog-heading">Katalog Alat Tersedia</h2>
                        <p class="catalog-subtitle">Temukan alat yang dibutuhkan dan lihat ketersediaan unitnya.</p>
                    </div>
                    <a href="{{ route('equipment.index') }}" class="catalog-all-link">Lihat semua alat <span aria-hidden="true">→</span></a>
                </div>

                @if ($equipment->isEmpty())
                    <div class="empty-state">Tidak ada alat yang sesuai dengan pencarian atau filter.</div>
                @else
                    <div class="equipment-grid">
                        @foreach ($equipment as $item)
                            @php
                                $availableUnits = $item->borrowableUnits;
                                $available = $availableUnits->count();
                                $availableNormal = $availableUnits->where('condition', 'Baik')->count();
                                $availableMinor = $availableUnits->where('condition', 'Rusak Ringan')->count();
                                $filterStatus = request('status');

                                if ($filterStatus && $item->units->contains('status', $filterStatus)) {
                                    $displayStatus = $filterStatus;
                                } elseif ($available > 0) {
                                    $displayStatus = 'Tersedia';
                                } else {
                                    $statusPriority = ['Dipinjam', 'Rusak', 'Hilang', 'Tidak Tersedia'];
                                    $displayStatus = collect($statusPriority)->first(
                                        fn ($status) => $item->units->contains('status', $status)
                                    ) ?? 'Tidak Tersedia';
                                }

                                $statusClass = match ($displayStatus) {
                                    'Tersedia' => 'available',
                                    'Dipinjam' => 'borrowed',
                                    'Rusak' => 'damaged',
                                    'Hilang' => 'lost',
                                    default => 'unavailable',
                                };
                            @endphp

                            <article class="equipment-card">
                                <div class="equipment-image {{ $item->catalogPhotoUrl() ? 'has-equipment-photo' : '' }}" @if (!$item->catalogPhotoUrl()) role="img" aria-label="Foto {{ $item->name }} belum tersedia" @endif>
                                    @if ($item->catalogPhotoUrl())
                                        <img src="{{ $item->catalogPhotoUrl() }}" alt="Foto kondisi {{ $item->name }}" loading="lazy">
                                        @if ($item->catalogPhotoCondition())<span class="photo-condition-label">{{ $item->catalogPhotoCondition() }}</span>@else<span class="photo-condition-label">Foto katalog</span>@endif
                                    @else
                                        <span class="image-label">FOTO ALAT</span>
                                    @endif
                                </div>
                                <h3 class="equipment-name">{{ $item->name }}</h3>
                                <div class="equipment-category">{{ $item->category }}</div>
                                <div class="equipment-category" style="margin-top:4px">Tersedia: {{ $available }} unit (Baik {{ $availableNormal }}, Rusak Ringan {{ $availableMinor }})</div>
                                <div class="card-footer">
                                    <span class="status-badge {{ $statusClass }}">{{ strtoupper($displayStatus) }}</span>
                                    <a href="{{ route('equipment.show', $item->id) }}" class="button view-button">Lihat</a>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        
@endsection
