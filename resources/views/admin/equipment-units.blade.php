@extends('layouts.admin')

@section('title', 'Inventaris & Unit')
@section('page-title', 'Inventaris & Unit Fisik')
@section('page-description', 'Kelola katalog alat, foto kondisi terkini, kode unit fisik, ketersediaan, dan kondisi setiap unit.')

@section('content')
    <section class="admin-page-intro">
        <div><h2>Kontrol inventaris</h2><p>Foto katalog menampilkan kondisi unit terbaik yang tercatat saat ini. Foto penyerahan dan pengembalian disimpan sebagai riwayat.</p></div>
        <div class="admin-actions"><a href="{{ route('admin.dashboard') }}" class="admin-btn">← Ringkasan operasional</a></div>
    </section>

    <section class="admin-stat-grid">
        <article class="admin-stat blue"><div class="admin-stat-top"><span class="admin-stat-label">Total unit fisik</span><span class="admin-stat-icon">▣</span></div><div class="admin-stat-value">{{ $totalUnits }}</div><div class="admin-stat-note">Seluruh unit terdaftar</div></article>
        <article class="admin-stat teal"><div class="admin-stat-top"><span class="admin-stat-label">Tersedia</span><span class="admin-stat-icon">✓</span></div><div class="admin-stat-value">{{ $availableUnits }}</div><div class="admin-stat-note">Termasuk unit rusak ringan yang berfungsi</div></article>
        <article class="admin-stat blue"><div class="admin-stat-top"><span class="admin-stat-label">Sedang dipinjam</span><span class="admin-stat-icon">↔</span></div><div class="admin-stat-value">{{ $borrowedUnits }}</div><div class="admin-stat-note">Foto diperbarui saat penyerahan dan pengembalian</div></article>
        <article class="admin-stat amber"><div class="admin-stat-top"><span class="admin-stat-label">Memiliki catatan kerusakan</span><span class="admin-stat-icon">!</span></div><div class="admin-stat-value">{{ $repairUnits }}</div><div class="admin-stat-note">Gabungan rusak ringan dan berat</div></article>
    </section>

    <section class="admin-form-grid admin-section-space">
        <details class="admin-details">
            <summary>＋ Tambahkan alat baru beserta unit awal</summary>
            <div class="admin-details-body">
                <form method="POST" action="{{ route('admin.equipment.store') }}" enctype="multipart/form-data" class="admin-stack" style="gap:12px">
                    @csrf
                    <div class="admin-field"><label for="equipment-name">Nama alat</label><input id="equipment-name" class="admin-input" name="name" value="{{ old('name') }}" maxlength="255" required placeholder="Contoh: Kamera DSLR"></div>
                    <div class="admin-field"><label for="equipment-category">Kategori</label><input id="equipment-category" class="admin-input" name="category" value="{{ old('category') }}" maxlength="100" required placeholder="Contoh: Multimedia"></div>
                    <div class="admin-field"><label for="equipment-description">Deskripsi</label><textarea id="equipment-description" class="admin-textarea" name="description" maxlength="2000" placeholder="Keterangan alat (opsional)">{{ old('description') }}</textarea></div>
                    <div class="admin-field" data-camera-field><label for="equipment-photo">Foto katalog awal (opsional)</label><input id="equipment-photo" class="admin-input js-image-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment" data-camera-capture data-camera-preview="equipment-photo-preview"><small>Di ponsel, pilih kamera belakang atau galeri. JPG, PNG, atau WEBP, maksimal 5 MB.</small><img id="equipment-photo-preview" class="camera-photo-preview" alt="Pratinjau foto katalog awal" hidden><button type="button" class="admin-btn small" data-camera-clear="equipment-photo">Hapus foto</button></div>
                    <div class="admin-field"><label for="initial-unit-codes">Kode unit awal</label><textarea id="initial-unit-codes" class="admin-textarea" name="initial_unit_codes" required placeholder="DSLR-001&#10;DSLR-002&#10;DSLR-003">{{ old('initial_unit_codes') }}</textarea><small>Satu kode untuk setiap baris. Maksimal 50 unit per proses. Semua unit baru dimulai dengan kondisi Baik dan status Tersedia.</small></div>
                    <button type="submit" class="admin-btn primary">Simpan alat &amp; unit</button>
                </form>
            </div>
        </details>

        <details class="admin-details">
            <summary>＋ Tambahkan unit ke alat yang sudah ada</summary>
            <div class="admin-details-body">
                @if ($equipmentOptions->isEmpty())
                    <div class="admin-empty">Tambahkan alat baru terlebih dahulu.</div>
                @else
                    <form method="POST" action="{{ route('admin.equipment-units.store', ['equipment' => $equipmentOptions->first()->id]) }}" data-unit-route-template="{{ url('/admin/inventaris/alat') }}/__EQUIPMENT_ID__/unit" class="admin-stack" style="gap:12px" id="add-existing-units-form">
                        @csrf
                        <div class="admin-field"><label for="existing-equipment-id">Pilih alat</label><select id="existing-equipment-id" class="admin-select" style="width:100%" required>@foreach ($equipmentOptions as $equipment)<option value="{{ $equipment->id }}">{{ $equipment->name }}</option>@endforeach</select></div>
                        <div class="admin-field"><label for="additional-unit-codes">Kode unit baru</label><textarea id="additional-unit-codes" class="admin-textarea" name="additional_unit_codes" required placeholder="DSLR-004&#10;DSLR-005">{{ old('additional_unit_codes') }}</textarea><small>Masukkan kode unik, satu kode pada setiap baris. Maksimal 50 unit.</small></div>
                        <button type="submit" class="admin-btn primary">Tambahkan unit</button>
                    </form>
                @endif
            </div>
        </details>
    </section>

    <section class="admin-panel admin-section-space">
        <div class="admin-panel-head"><div><h3>Temukan unit</h3><p>Pencarian dan filter diperbarui otomatis saat kamu mengetik atau mengganti pilihan.</p></div><span class="admin-status active" id="inventory-count">{{ $units->total() }} unit</span></div>
        <div class="admin-panel-body">
            <form method="GET" action="{{ route('admin.equipment-units.index') }}" class="admin-filter-form" id="inventory-live-filter">
                <input class="admin-input search" type="search" name="q" value="{{ $search }}" placeholder="Cari nama alat atau kode unit..." aria-label="Cari nama alat atau kode unit" autocomplete="off">
                <select class="admin-select" name="category" aria-label="Filter kategori">
                    <option value="">Semua kategori</option>
                    @foreach ($categories as $category)<option value="{{ $category }}" @selected($categoryFilter === $category)>{{ $category }}</option>@endforeach
                </select>
                <select class="admin-select" name="condition" aria-label="Filter kondisi">
                    <option value="">Semua kondisi</option>
                    @foreach (['Baik', 'Rusak Ringan', 'Rusak Berat', 'Hilang'] as $condition)<option value="{{ $condition }}" @selected($conditionFilter === $condition)>{{ $condition }}</option>@endforeach
                </select>
                <select class="admin-select" name="status" aria-label="Filter status">
                    <option value="">Semua status</option>
                    @foreach (['Tersedia', 'Dipinjam', 'Rusak', 'Tidak Tersedia', 'Hilang'] as $status)<option value="{{ $status }}" @selected($statusFilter === $status)>{{ $status }}</option>@endforeach
                </select>
                <button type="submit" class="admin-btn primary js-filter-fallback">Terapkan filter</button>
                <a href="{{ route('admin.equipment-units.index') }}" class="admin-btn" id="inventory-filter-reset">Reset</a>
            </form>
            <p class="inventory-live-status" id="inventory-live-status" role="status" aria-live="polite">Filter manual tersedia. Pencarian langsung sedang disiapkan…</p>
        </div>
        <div id="inventory-results" aria-live="polite">
            @include('admin.partials.equipment-unit-results', ['units' => $units])
        </div>
    </section>
@endsection

@push('styles')
<style>
    .inventory-live-status{display:flex;align-items:center;gap:8px;margin:8px 0 0;color:#71839a;font-size:12px;min-height:16px}
    .inventory-live-status.is-loading::before{content:"";width:12px;height:12px;flex:0 0 12px;border:2px solid #d7e4fb;border-top-color:#2563eb;border-radius:50%;animation:inventory-filter-spin .65s linear infinite}
    @keyframes inventory-filter-spin{to{transform:rotate(360deg)}}
    #inventory-results{transition:opacity .12s ease}
    #inventory-results[aria-busy="true"]{opacity:.58;pointer-events:none}
    #inventory-live-filter .js-filter-fallback{display:inline-flex}
    #inventory-live-filter.is-live .js-filter-fallback{display:none!important}
    #inventory-live-filter.is-live.has-error .js-filter-fallback{display:inline-flex!important}
    #inventory-live-filter.is-live .admin-input:focus,
    #inventory-live-filter.is-live .admin-select:focus{border-color:#7aa7ef;box-shadow:0 0 0 3px rgba(37,99,235,.10);outline:none}
    .inventory-photo-table{min-width:1080px}
    .inventory-unit-thumb{display:block;width:76px;height:58px;object-fit:cover;border:1px solid #dbe4ef;border-radius:9px;background:#f3f6fa}
    .inventory-unit-thumb-empty{display:grid;place-items:center;padding:5px;color:#8593a5;font-size:10px;text-align:center}
    .inventory-photo-form{display:grid;gap:6px;min-width:170px}
    .inventory-photo-form input[type=file]{max-width:190px;font-size:11px}
    .inventory-photo-form .admin-btn{justify-self:start}
    @media(max-width:700px){.inventory-photo-table{min-width:1000px}.admin-filter-form{grid-template-columns:1fr 1fr}.admin-filter-form input[type=search]{grid-column:1/-1}}
.inventory-photo-preview{display:block;width:min(100%,170px);max-height:125px;margin-top:7px;object-fit:cover;border:1px solid #dbe4ef;border-radius:9px}.inventory-photo-preview[hidden]{display:none}.camera-photo-preview{display:block;width:min(100%,220px);max-height:160px;margin-top:8px;object-fit:cover;border:1px solid #dbe4ef;border-radius:10px}.camera-photo-preview[hidden]{display:none}
</style>
@endpush

@push('scripts')
<script src="{{ asset('js/orbitflow-inventory-live.js') }}?v=1" defer></script>
@endpush
