@extends('layouts.siswa')

@section('title', 'Daftar Alat - OrbitFlow')
@section('page-heading', 'Daftar Alat')
@section('page-subtitle', 'Jelajahi seluruh inventaris alat sekolah dan ketersediaannya.')

@section('content')
    <div class="toolbar student-catalog-toolbar">
        <form method="GET" action="{{ route('equipment.index') }}" class="filter-form student-catalog-filter" aria-label="Cari dan filter inventaris alat" data-live-filter data-live-target="#student-equipment-results" data-live-debounce="180">
            <label class="search-wrap">
                <span class="search-icon" aria-hidden="true">⌕</span>
                <input
                    class="search-input"
                    type="search"
                    name="search"
                    placeholder="Cari nama alat atau deskripsi..."
                    value="{{ request('search') }}"
                    aria-label="Cari nama alat atau deskripsi"
                    autocomplete="off"
                >
            </label>

            <select class="filter-select" name="category" aria-label="Filter kategori">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                @endforeach
            </select>

            <select class="filter-select status-select" name="status" aria-label="Filter status">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>
                @endforeach
            </select>

            <button type="submit" class="button primary js-live-filter-fallback">Terapkan filter</button>

            @if (request()->filled('search') || request()->filled('category') || request()->filled('status'))
                <a href="{{ route('equipment.index') }}" class="button secondary student-filter-reset" data-live-reset>Reset</a>
            @endif
        </form>

        <a href="{{ route('borrowing.create') }}" class="button primary student-catalog-request">＋ Ajukan Peminjaman</a>
    </div>

    <section id="student-equipment-results" class="student-catalog-results" data-live-results aria-labelledby="equipment-list-heading">
        <div class="catalog-heading-row student-catalog-heading">
            <div class="student-catalog-heading-copy">
                <span class="student-catalog-eyebrow">INVENTARIS SEKOLAH</span>
                <h2 class="catalog-title" id="equipment-list-heading">Temukan Peralatan Sekolah</h2>
                <p class="catalog-subtitle">Periksa foto, kondisi, dan jumlah unit sebelum mengajukan peminjaman.</p>
            </div>
            <div class="inventory-count" aria-live="polite">
                <strong>{{ $equipment->count() }}</strong>
                <span>alat ditemukan</span>
            </div>
        </div>

        @if ($equipment->isEmpty())
            <div class="empty-state student-catalog-empty">
                <span class="student-empty-icon" aria-hidden="true">⌕</span>
                <strong>Tidak ada alat yang cocok</strong>
                <span>Coba kata kunci atau pilihan filter yang berbeda.</span>
                @if (request()->filled('search') || request()->filled('category') || request()->filled('status'))
                    <a href="{{ route('equipment.index') }}">Hapus semua filter</a>
                @endif
            </div>
        @else
            <div class="equipment-grid student-equipment-grid">
                @foreach ($equipment as $item)
                    @php
                        $photoUrl = $item->catalogPhotoUrl();
                        $photoCondition = $item->catalogPhotoCondition();
                        $available = (int) $item->available_unit_count;
                        $total = (int) $item->total_unit_count;
                        $availabilityPercent = $total > 0 ? (int) round(($available / $total) * 100) : 0;
                    @endphp

                    <article class="equipment-card student-equipment-card">
                        <div class="student-equipment-media {{ $photoUrl ? 'has-photo' : 'is-placeholder' }}">
                            @if ($photoUrl)
                                <img src="{{ $photoUrl }}" alt="Foto {{ $item->name }}" loading="lazy">
                            @else
                                <div class="student-equipment-placeholder" role="img" aria-label="Foto {{ $item->name }} belum tersedia">
                                    <span class="student-placeholder-icon" aria-hidden="true">▧</span>
                                    <span>Foto belum tersedia</span>
                                </div>
                            @endif

                            <div class="student-equipment-media-labels">
                                <span class="student-equipment-category">{{ $item->category ?: 'Tanpa kategori' }}</span>
                                @if ($photoUrl)
                                    <span class="student-photo-condition">{{ $photoCondition ? 'Kondisi ' . $photoCondition : 'Foto katalog' }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="student-equipment-card-body">
                            <div class="student-equipment-card-meta">
                                <span>{{ $total }} unit terdata</span>
                                @if ($available > 0)
                                    <span class="student-availability-dot" aria-hidden="true"></span>
                                    <span class="student-availability-label">Siap dipinjam</span>
                                @else
                                    <span class="student-unavailable-label">Belum tersedia</span>
                                @endif
                            </div>

                            <h3 class="equipment-name student-equipment-name">{{ $item->name }}</h3>

                            @if ($item->description)
                                <p class="catalog-description student-equipment-description">{{ $item->description }}</p>
                            @else
                                <p class="catalog-description student-equipment-description is-muted">Belum ada deskripsi alat.</p>
                            @endif

                            <div class="student-equipment-stock">
                                <div class="student-stock-heading">
                                    <span>Unit tersedia</span>
                                    <strong>{{ $available }}<small> / {{ $total }}</small></strong>
                                </div>
                                <div class="student-stock-meter" role="progressbar" aria-label="Persentase unit tersedia untuk {{ $item->name }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $availabilityPercent }}">
                                    <span style="width: {{ $availabilityPercent }}%"></span>
                                </div>
                                <div class="student-condition-breakdown">
                                    <span>Baik <strong>{{ $item->available_normal_count }}</strong></span>
                                    <span>Rusak ringan <strong>{{ $item->available_minor_damage_count }}</strong></span>
                                </div>
                            </div>

                            <div class="catalog-statuses student-catalog-statuses" aria-label="Status unit alat">
                                @foreach ($item->catalogStatuses as $status)
                                    @php
                                        $statusClass = match ($status) {
                                            'Tersedia' => 'available',
                                            'Dipinjam' => 'borrowed',
                                            'Rusak' => 'damaged',
                                            'Hilang' => 'lost',
                                            default => 'unavailable',
                                        };
                                    @endphp
                                    <span class="status-badge {{ $statusClass }}">{{ $status }}</span>
                                @endforeach
                            </div>

                            <div class="card-footer student-equipment-actions">
                                <a href="{{ route('equipment.show', $item->id) }}" class="button secondary view-button" aria-label="Lihat detail {{ $item->name }}">Detail alat</a>
                                @if ($available > 0)
                                    <a href="{{ route('borrowing.create', ['equipment_id' => $item->id]) }}" class="button primary view-button" aria-label="Ajukan peminjaman {{ $item->name }}">Ajukan</a>
                                @else
                                    <span class="button view-button student-unavailable-button" aria-disabled="true">Tidak tersedia</span>
                                @endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
@endsection
