@extends('layouts.siswa')

@section('title', 'Form Pengajuan Peminjaman - OrbitFlow')
@section('page-heading', 'Form Pengajuan Peminjaman')
@section('page-subtitle', 'Lengkapi data berikut untuk mengajukan alat sekolah.')

@section('content')
    <div class="breadcrumbs"><a href="{{ route('siswa.dashboard') }}">‹ Kembali ke Dashboard</a></div>

    <section class="student-borrowing-intro" aria-labelledby="student-borrowing-intro-title">
        <div class="student-borrowing-intro-copy">
            <span class="student-flow-eyebrow">PENGAJUAN BARU</span>
            <h2 id="student-borrowing-intro-title">Siapkan kebutuhan alatmu.</h2>
            <p>Pilih satu atau beberapa jenis alat, tentukan periode penggunaan, lalu jelaskan tujuan peminjaman.</p>
        </div>
        <div class="student-borrowing-intro-note">
            <span class="student-borrowing-note-icon" aria-hidden="true">✓</span>
            <div><strong>Periksa ketersediaan</strong><small>Jumlah tersedia ditampilkan saat alat dipilih.</small></div>
        </div>
    </section>

    <div class="student-borrowing-steps" aria-label="Tahapan pengajuan">
        <div class="student-borrowing-step"><span>01</span><strong>Pilih alat</strong></div>
        <span class="student-borrowing-step-line" aria-hidden="true"></span>
        <div class="student-borrowing-step"><span>02</span><strong>Tentukan tanggal</strong></div>
        <span class="student-borrowing-step-line" aria-hidden="true"></span>
        <div class="student-borrowing-step"><span>03</span><strong>Tujuan penggunaan</strong></div>
    </div>

    @if ($errors->any())
        <div class="alert-errors student-borrowing-errors" role="alert">
            <strong>Pengajuan belum dapat dikirim.</strong>
            <p>Periksa kembali informasi berikut, lalu coba kirim ulang.</p>
            <ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    @php
        $oldItems = old('items', [['equipment_id' => request('equipment_id', ''), 'quantity' => 1]]);
    @endphp

    <form method="POST" action="{{ route('borrowing.store') }}" class="student-borrowing-form" id="student-borrowing-form">
        @csrf
        <div class="request-grid student-request-grid">
            <section class="panel student-request-panel" aria-labelledby="student-items-heading">
                <div class="panel-header student-request-panel-header">
                    <div class="student-request-heading-row">
                        <span class="student-request-step-number">01</span>
                        <div>
                            <h2 class="panel-title" id="student-items-heading">Daftar alat yang dipinjam</h2>
                            <p class="panel-caption">Pilih alat dan jumlah unit yang kamu perlukan.</p>
                        </div>
                    </div>
                </div>
                <div class="panel-body student-request-panel-body">
                    @if ($equipment->isEmpty())
                        <div class="student-borrowing-empty-inventory" role="status">
                            <strong>Belum ada alat yang dapat ditampilkan.</strong>
                            <span>Silakan kembali lagi setelah inventaris sekolah diperbarui.</span>
                        </div>
                    @endif

                    <div id="items-container" class="item-list student-item-list">
                        @foreach ($oldItems as $index => $oldItem)
                            <div class="item-row student-item-row" data-borrowing-item-row>
                                <div class="item-picker student-item-picker">
                                    <span class="mini-equipment-icon student-item-icon" aria-hidden="true">▣</span>
                                    <div class="item-select-wrap student-item-select-wrap">
                                        <label class="field-label" for="equipment-{{ $index }}">Pilih alat</label>
                                        <select class="equipment-select student-equipment-select" id="equipment-{{ $index }}" name="items[{{ $index }}][equipment_id]" required data-equipment-select>
                                            <option value="">Pilih alat...</option>
                                            @foreach ($equipment as $item)
                                                @php
                                                    $availableUnits = $item->borrowableUnits;
                                                    $availableCount = $availableUnits->count();
                                                    $normalCount = $availableUnits->where('condition', 'Baik')->count();
                                                    $minorCount = $availableUnits->where('condition', 'Rusak Ringan')->count();
                                                @endphp
                                                <option
                                                    value="{{ $item->id }}"
                                                    data-available="{{ $availableCount }}"
                                                    data-good="{{ $normalCount }}"
                                                    data-minor="{{ $minorCount }}"
                                                    @selected((string) ($oldItem['equipment_id'] ?? '') === (string) $item->id)
                                                >{{ $item->name }}</option>
                                            @endforeach
                                        </select>
                                        <p class="student-item-availability" data-item-availability aria-live="polite">Pilih alat untuk melihat jumlah unit yang tersedia.</p>
                                    </div>
                                </div>
                                <div class="quantity-control student-quantity-control">
                                    <label class="quantity-label" for="quantity-{{ $index }}">Jumlah unit</label>
                                    <div class="student-quantity-stepper">
                                        <button type="button" aria-label="Kurangi jumlah" onclick="changeQuantity(this, -1)">−</button>
                                        <input id="quantity-{{ $index }}" type="number" name="items[{{ $index }}][quantity]" value="{{ $oldItem['quantity'] ?? 1 }}" min="1" required aria-label="Jumlah unit">
                                        <button type="button" aria-label="Tambah jumlah" onclick="changeQuantity(this, 1)">+</button>
                                    </div>
                                </div>
                                <button type="button" class="remove-button student-remove-item" onclick="removeItem(this)" aria-label="Hapus pilihan alat {{ $index + 1 }}">Hapus</button>
                            </div>
                        @endforeach
                    </div>

                    <div class="add-item-wrap student-add-item-wrap">
                        <button type="button" class="button secondary student-add-item-button" onclick="addItem()">＋ Tambah jenis alat</button>
                        <span class="student-add-item-hint">Alat yang sama cukup ditambahkan satu kali, lalu atur jumlah unitnya.</span>
                    </div>
                </div>
            </section>

            <section class="panel student-request-panel" aria-labelledby="student-details-heading">
                <div class="panel-header student-request-panel-header">
                    <div class="student-request-heading-row">
                        <span class="student-request-step-number">02</span>
                        <div>
                            <h2 class="panel-title" id="student-details-heading">Detail pengajuan</h2>
                            <p class="panel-caption">Tentukan periode dan tujuan peminjaman.</p>
                        </div>
                    </div>
                </div>
                <div class="panel-body student-request-panel-body">
                    <div class="form-fields student-request-fields">
                        <div class="student-date-field">
                            <label class="field-label" for="start_date">Tanggal peminjaman</label>
                            <input class="field-control student-form-control" type="date" id="start_date" name="start_date" value="{{ old('start_date') }}" required>
                            <small class="student-field-help">Tanggal mulai penggunaan alat.</small>
                        </div>
                        <div class="student-date-field">
                            <label class="field-label" for="end_date">Tanggal pengembalian</label>
                            <input class="field-control student-form-control" type="date" id="end_date" name="end_date" value="{{ old('end_date') }}" required>
                            <small class="student-field-help">Tanggal ini tidak boleh sebelum tanggal peminjaman.</small>
                        </div>
                        <div class="student-purpose-field">
                            <div class="student-purpose-label-row">
                                <label class="field-label" for="purpose">Tujuan penggunaan</label>
                                <span class="student-character-count" id="purpose-count">0 / 1000</span>
                            </div>
                            <textarea class="field-control student-form-control student-purpose-control" id="purpose" name="purpose" maxlength="1000" placeholder="Contoh: digunakan untuk praktik produksi video pada mata pelajaran ..." required>{{ old('purpose') }}</textarea>
                            <small class="student-field-help">Jelaskan kegiatan atau keperluan penggunaan alat.</small>
                        </div>
                        <div class="student-submit-area">
                            <button type="submit" class="button primary full student-submit-button">
                                <span>Ajukan peminjaman</span><span aria-hidden="true">→</span>
                            </button>
                            <p class="student-submit-note">Pastikan jumlah unit dan tanggal sudah benar sebelum mengirim.</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </form>

    <select id="equipment-options-template" hidden aria-hidden="true" tabindex="-1">
        <option value="">Pilih alat...</option>
        @foreach ($equipment as $item)
            @php
                $availableUnits = $item->borrowableUnits;
                $availableCount = $availableUnits->count();
                $normalCount = $availableUnits->where('condition', 'Baik')->count();
                $minorCount = $availableUnits->where('condition', 'Rusak Ringan')->count();
            @endphp
            <option value="{{ $item->id }}" data-available="{{ $availableCount }}" data-good="{{ $normalCount }}" data-minor="{{ $minorCount }}">{{ $item->name }}</option>
        @endforeach
    </select>
@endsection

@push('scripts')
<script>
    let itemIndex = {{ count($oldItems) }};

    function changeQuantity(button, amount) {
        const input = button.parentElement.querySelector('input[type="number"]');
        const current = Number.parseInt(input.value || '1', 10);
        input.value = Math.max(1, current + amount);
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function updateItemAvailability(select) {
        const row = select.closest('[data-borrowing-item-row]');
        const hint = row?.querySelector('[data-item-availability]');
        const option = select.selectedOptions?.[0];
        if (!hint) return;

        if (!option || !option.value) {
            hint.textContent = 'Pilih alat untuk melihat jumlah unit yang tersedia.';
            hint.classList.remove('is-unavailable');
            return;
        }

        const available = Number.parseInt(option.dataset.available || '0', 10);
        const good = Number.parseInt(option.dataset.good || '0', 10);
        const minor = Number.parseInt(option.dataset.minor || '0', 10);
        hint.textContent = `${available} unit tersedia · ${good} baik · ${minor} rusak ringan`;
        hint.classList.toggle('is-unavailable', available < 1);
    }

    function addItem() {
        const container = document.getElementById('items-container');
        const row = document.createElement('div');
        row.className = 'item-row student-item-row';
        row.setAttribute('data-borrowing-item-row', '');
        row.innerHTML = `
            <div class="item-picker student-item-picker">
                <span class="mini-equipment-icon student-item-icon" aria-hidden="true">▣</span>
                <div class="item-select-wrap student-item-select-wrap">
                    <label class="field-label" for="equipment-${itemIndex}">Pilih alat</label>
                    <select class="equipment-select student-equipment-select" id="equipment-${itemIndex}" required data-equipment-select></select>
                    <p class="student-item-availability" data-item-availability aria-live="polite">Pilih alat untuk melihat jumlah unit yang tersedia.</p>
                </div>
            </div>
            <div class="quantity-control student-quantity-control">
                <label class="quantity-label" for="quantity-${itemIndex}">Jumlah unit</label>
                <div class="student-quantity-stepper">
                    <button type="button" aria-label="Kurangi jumlah" onclick="changeQuantity(this, -1)">−</button>
                    <input id="quantity-${itemIndex}" type="number" value="1" min="1" required aria-label="Jumlah unit">
                    <button type="button" aria-label="Tambah jumlah" onclick="changeQuantity(this, 1)">+</button>
                </div>
            </div>
            <button type="button" class="remove-button student-remove-item" onclick="removeItem(this)" aria-label="Hapus pilihan alat">Hapus</button>`;

        const select = row.querySelector('select');
        select.name = `items[${itemIndex}][equipment_id]`;
        select.innerHTML = document.getElementById('equipment-options-template').innerHTML;
        select.addEventListener('change', () => updateItemAvailability(select));

        const quantity = row.querySelector('input[type="number"]');
        quantity.name = `items[${itemIndex}][quantity]`;
        row.querySelector('button.student-remove-item').setAttribute('aria-label', `Hapus pilihan alat ${itemIndex + 1}`);

        container.appendChild(row);
        itemIndex++;
        updateItemAvailability(select);
        select.focus();
    }

    function removeItem(button) {
        const container = document.getElementById('items-container');
        if (container.querySelectorAll('.item-row').length > 1) {
            const row = button.closest('.item-row');
            const nextSelect = row.nextElementSibling?.querySelector('[data-equipment-select]')
                || row.previousElementSibling?.querySelector('[data-equipment-select]');
            row.remove();
            nextSelect?.focus();
        }
    }

    document.querySelectorAll('[data-equipment-select]').forEach(select => {
        select.addEventListener('change', () => updateItemAvailability(select));
        updateItemAvailability(select);
    });

    const startDate = document.getElementById('start_date');
    const endDate = document.getElementById('end_date');
    function updateEndDateMinimum() {
        if (startDate && endDate) endDate.min = startDate.value || '';
    }
    startDate?.addEventListener('change', updateEndDateMinimum);
    updateEndDateMinimum();

    const purpose = document.getElementById('purpose');
    const purposeCount = document.getElementById('purpose-count');
    function updatePurposeCount() {
        if (purpose && purposeCount) purposeCount.textContent = `${purpose.value.length} / 1000`;
    }
    purpose?.addEventListener('input', updatePurposeCount);
    updatePurposeCount();
</script>
@endpush
