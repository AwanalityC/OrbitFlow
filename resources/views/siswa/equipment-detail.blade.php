@extends('layouts.siswa')

@section('title', $equipment->name . ' - OrbitFlow')
@section('page-heading', 'Detail Alat')
@section('page-subtitle', 'Informasi lengkap dan ketersediaan alat sekolah.')

@section('content')
    <div class="breadcrumbs"><a href="{{ route('equipment.index') }}">‹ Kembali ke Daftar Alat</a></div>

    @php
        $displayStatus = $equipment->borrowableUnits->isNotEmpty()
            ? 'Tersedia'
            : (collect(['Dipinjam', 'Rusak', 'Hilang', 'Tidak Tersedia'])->first(fn ($status) => $equipment->units->contains('status', $status)) ?? 'Tidak Tersedia');
        $conditionLabels = $equipment->units->pluck('condition')->filter()->unique()->values();
        $conditionText = $conditionLabels->isEmpty() ? 'Belum ada data kondisi' : $conditionLabels->implode(' / ');
        $photoUrl = $equipment->catalogPhotoUrl();
        $photoCondition = $equipment->catalogPhotoCondition();
        $totalUnits = $equipment->units->count();
        $availabilityPercent = $totalUnits > 0 ? (int) round(($available / $totalUnits) * 100) : 0;
        $statusClass = $displayStatus === 'Tersedia' ? 'is-available' : 'is-unavailable';
    @endphp

    <section class="panel equipment-detail-card student-equipment-detail">
        <figure class="student-detail-media {{ $photoUrl ? 'has-photo' : 'is-placeholder' }}">
            @if ($photoUrl)
                <img src="{{ $photoUrl }}" alt="Foto {{ $equipment->name }}" loading="eager">
                <figcaption class="student-detail-photo-label">{{ $photoCondition ? 'Kondisi foto: ' . $photoCondition : 'Foto katalog' }}</figcaption>
            @else
                <div class="student-detail-placeholder" role="img" aria-label="Foto {{ $equipment->name }} belum tersedia">
                    <span aria-hidden="true">▧</span>
                    <strong>Foto alat belum tersedia</strong>
                    <small>Foto kondisi akan ditampilkan setelah dicatat petugas.</small>
                </div>
            @endif
        </figure>

        <div class="equipment-detail-copy student-detail-copy">
            <span class="student-detail-eyebrow">INFORMASI PERALATAN</span>
            <div class="equipment-detail-title-row student-detail-title-row">
                <div class="student-detail-title-copy">
                    <h2 class="equipment-detail-title student-detail-title">{{ $equipment->name }}</h2>
                    <p class="equipment-detail-category student-detail-category">{{ $equipment->category ?: 'Tanpa kategori' }}</p>
                </div>
                <span class="status-chip student-detail-status {{ $statusClass }}">{{ $displayStatus }}</span>
            </div>

            <p class="equipment-detail-description student-detail-description">{{ $equipment->description ?: 'Informasi alat sekolah belum ditambahkan.' }}</p>

            <div class="student-detail-availability">
                <div class="student-detail-availability-copy">
                    <span>Unit siap dipinjam</span>
                    <strong>{{ $available }} <small>dari {{ $totalUnits }} unit</small></strong>
                </div>
                <div class="student-stock-meter" role="progressbar" aria-label="Persentase unit tersedia" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $availabilityPercent }}">
                    <span style="width: {{ $availabilityPercent }}%"></span>
                </div>
            </div>

            <h3 class="info-heading student-detail-section-heading">Informasi alat</h3>
            <div class="info-rows student-detail-info-rows">
                <div class="info-row"><span class="info-row-label">Nama alat</span><span class="info-row-value">{{ $equipment->name }}</span></div>
                <div class="info-row"><span class="info-row-label">Kategori</span><span class="info-row-value">{{ $equipment->category ?: 'Tanpa kategori' }}</span></div>
                <div class="info-row"><span class="info-row-label">Status</span><span class="info-row-value">{{ $displayStatus }}</span></div>
                <div class="info-row"><span class="info-row-label">Kondisi unit</span><span class="info-row-value">{{ $conditionText }}</span></div>
                <div class="info-row"><span class="info-row-label">Ketersediaan</span><span class="info-row-value">{{ $available }} dari {{ $totalUnits }} unit siap dipinjam</span></div>
            </div>

            <div class="detail-note student-detail-note">Pengajuan peminjaman mengikuti jumlah unit yang tersedia dan pemeriksaan petugas.</div>

            <div class="detail-actions student-detail-actions">
                @if ($available > 0)
                    <a href="{{ route('borrowing.create', ['equipment_id' => $equipment->id]) }}" class="button primary">Ajukan Peminjaman</a>
                @else
                    <span class="button student-detail-unavailable" aria-disabled="true">Alat Tidak Tersedia</span>
                @endif
                <a href="{{ route('equipment.index') }}" class="button secondary">Kembali ke Daftar Alat</a>
            </div>
        </div>
    </section>
@endsection
