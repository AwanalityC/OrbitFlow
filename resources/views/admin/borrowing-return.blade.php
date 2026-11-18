@extends('layouts.admin')

@section('title', 'Proses Pengembalian')
@section('page-title', 'Proses Pengembalian')
@section('page-description', 'Catat unit yang benar-benar kembali dan unggah foto kondisi terbaru setiap unit.')

@section('content')
    <div class="admin-page-intro">
        <div><h2>Pengembalian OF-{{ str_pad($borrowing->id, 5, '0', STR_PAD_LEFT) }}</h2><p>Periksa unit berdasarkan kode fisik. Foto kondisi setelah pengembalian wajib diunggah untuk setiap unit yang dipilih.</p></div>
        <div class="admin-actions"><a href="{{ route('admin.borrowings.index', ['status' => 'active']) }}" class="admin-btn">← Kembali ke peminjaman aktif</a></div>
    </div>

    <section class="admin-panel">
        <div class="admin-panel-head"><div><h3>Informasi peminjaman</h3><p>Pastikan data siswa dan periode sesuai sebelum memproses.</p></div><span class="admin-status {{ strtolower($borrowing->status) }}">{{ $borrowing->status }}</span></div>
        <div class="admin-panel-body">
            <div class="admin-info-grid">
                <div class="admin-info-box"><div class="admin-info-label">Peminjam</div><div class="admin-info-value">{{ $borrowing->user->name }}</div></div>
                <div class="admin-info-box"><div class="admin-info-label">Tanggal pengajuan</div><div class="admin-info-value">{{ $borrowing->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</div></div>
                <div class="admin-info-box"><div class="admin-info-label">Tanggal peminjaman</div><div class="admin-info-value">{{ $borrowing->start_date->format('d/m/Y') }}</div></div>
                <div class="admin-info-box"><div class="admin-info-label">Batas pengembalian</div><div class="admin-info-value">{{ $borrowing->end_date->format('d/m/Y') }}</div></div>
            </div>
        </div>
    </section>

    @php $activeUnitCount = $borrowing->items->sum(fn ($item) => $item->activeUnits->count()); @endphp
    @if ($activeUnitCount === 0)
        <section class="admin-panel admin-section-space"><div class="admin-panel-body"><div class="admin-empty">Belum ada unit aktif yang tercatat untuk pengajuan ini. Jika peminjaman dibuat sebelum fitur alokasi unit tersedia, jangan catat pengembalian sampai unit fisiknya diperiksa.</div></div></section>
    @else
        <form method="POST" enctype="multipart/form-data" action="{{ route('admin.borrowing.return.store', $borrowing) }}" id="unit-return-form">
            @csrf
            <section class="admin-panel admin-section-space">
                <div class="admin-panel-head"><div><h3>Unit yang dikembalikan</h3><p>Centang hanya unit yang benar-benar sudah diterima. Setiap unit terpilih wajib memiliki foto kondisi terbaru.</p></div><span class="admin-status active">{{ $activeUnitCount }} unit aktif</span></div>
                <div class="return-selection-summary" role="status" aria-live="polite">
                    <span class="return-selection-icon" aria-hidden="true">✓</span>
                    <span><strong id="return-selected-count">{{ $activeUnitCount }}</strong> dari {{ $activeUnitCount }} unit dipilih untuk dikembalikan.</span>
                    <small>Unit yang tidak dicentang tetap tercatat sedang dipinjam.</small>
                </div>
                <div class="return-mobile-hint">Pada layar kecil, setiap unit ditampilkan sebagai kartu agar foto dan kondisi lebih mudah diperiksa.</div>
                <div class="admin-table-wrap return-table-wrap">
                    <table class="admin-table return-photo-table">
                        <thead><tr><th scope="col">Kembali?</th><th scope="col">Nama alat &amp; kode</th><th scope="col">Foto saat diserahkan</th><th scope="col">Kondisi saat dipinjam</th><th scope="col">Kondisi saat kembali</th><th scope="col">Foto wajib saat kembali</th><th scope="col">Catatan</th></tr></thead>
                        <tbody>
                            @foreach ($borrowing->items as $item)
                                @foreach ($item->activeUnits as $unit)
                                    @php
                                        $initialCondition = $unit->pivot->condition_at_borrowing;
                                        $defaultReturnCondition = $initialCondition === 'Rusak Ringan' ? 'Rusak Ringan' : 'Normal';
                                        $selectedReturnCondition = old('conditions.' . $unit->id, $defaultReturnCondition);
                                        $oldReturnedUnits = old('returned_units');
                                        $isChecked = $oldReturnedUnits === null || in_array($unit->id, $oldReturnedUnits);
                                        $handoverPhoto = $unit->pivot->handover_photo_path;
                                    @endphp
                                    <tr class="return-unit-row" data-unit-row="{{ $unit->id }}">
                                        <td data-label="Pilih unit"><input class="js-return-unit-check" type="checkbox" name="returned_units[]" value="{{ $unit->id }}" @checked($isChecked) aria-label="Kembalikan {{ $unit->unit_code }}"></td>
                                        <td data-label="Nama alat &amp; kode"><span class="admin-primary-text">{{ $item->equipment->name }}</span><span class="admin-secondary-text">{{ $unit->unit_code }}</span></td>
                                        <td data-label="Foto saat diserahkan">
                                            @if ($handoverPhoto)
                                                <a href="{{ asset('storage/' . $handoverPhoto) }}" target="_blank" rel="noopener"><img class="return-photo-thumb" src="{{ asset('storage/' . $handoverPhoto) }}" alt="Foto penyerahan {{ $unit->unit_code }}" loading="lazy"></a>
                                            @else
                                                <span class="admin-secondary-text">Belum ada foto</span>
                                            @endif
                                        </td>
                                        <td data-label="Kondisi saat dipinjam">{{ $initialCondition ?: 'Belum tercatat' }}</td>
                                        <td data-label="Kondisi saat kembali">
                                            <select class="admin-select js-return-condition" name="conditions[{{ $unit->id }}]" required>
                                                <option value="Normal" @selected($selectedReturnCondition === 'Normal')>Normal / Baik</option>
                                                <option value="Rusak Ringan" @selected($selectedReturnCondition === 'Rusak Ringan')>Rusak Ringan</option>
                                                <option value="Rusak Berat" @selected($selectedReturnCondition === 'Rusak Berat')>Rusak Berat</option>
                                            </select>
                                        </td>
                                        <td data-label="Foto wajib saat kembali">
                                            <div class="return-photo-upload" data-camera-field>
                                                <input id="return-photo-{{ $unit->id }}" class="admin-input js-return-photo" type="file" name="photos[{{ $unit->id }}]" accept="image/jpeg,image/png,image/webp" capture="environment" data-camera-capture data-camera-preview="return-photo-preview-{{ $unit->id }}" @if($isChecked) required @else disabled @endif>
                                                <small>Ambil foto dengan kamera belakang atau pilih dari galeri. JPG/PNG/WEBP · Maks. 5 MB</small>
                                                <img id="return-photo-preview-{{ $unit->id }}" class="return-photo-preview" alt="Pratinjau foto {{ $unit->unit_code }}" hidden>
                                                <button type="button" class="admin-btn small" data-camera-clear="return-photo-{{ $unit->id }}">Hapus foto</button>
                                            </div>
                                        </td>
                                        <td data-label="Catatan"><textarea class="admin-textarea js-return-notes" name="notes[{{ $unit->id }}]" maxlength="1000" rows="2" placeholder="Catatan opsional">{{ old('notes.' . $unit->id) }}</textarea></td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="admin-panel-body">
                    <p class="admin-priority-note">Foto saat kembali menjadi bukti kondisi terbaru unit dan akan digunakan sebagai salah satu sumber foto katalog siswa. Rusak Ringan tetap tersedia dengan prioritas kedua; Rusak Berat menjadi tidak tersedia.</p>
                    <div class="admin-actions"><button type="submit" class="admin-btn primary">Simpan pengembalian &amp; foto</button><a href="{{ route('admin.borrowings.index', ['status' => 'active']) }}" class="admin-btn">Batal</a></div>
                </div>
            </section>
        </form>
    @endif
@endsection

@push('styles')
<style>
.return-photo-table{min-width:1250px}
.return-photo-thumb{display:block;width:88px;height:64px;object-fit:cover;border:1px solid #dbe4ef;border-radius:9px}
.return-photo-upload{display:grid;gap:5px;min-width:175px}
.return-photo-upload input[type=file]{font-size:11px;max-width:190px;width:100%}
.return-photo-upload small{color:#7c8ca1;font-size:10px;line-height:1.45}
.return-photo-preview{width:90px;height:68px;object-fit:cover;border-radius:8px;border:1px solid #dbe4ef}
.return-unit-row.is-not-returned{opacity:.58}
.return-selection-summary{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:12px 15px 8px;padding:10px 12px;border:1px solid #dcebe5;border-radius:10px;background:#f4fbf7;color:#426b5b;font-size:10px}
.return-selection-icon{display:grid;width:22px;height:22px;place-items:center;border-radius:7px;background:#dff3e8;color:#217754;font-weight:900}
.return-selection-summary strong{color:#176c50;font-size:12px}
.return-selection-summary small{color:#7e9a8e;font-size:8px}
.return-mobile-hint{display:none;margin:8px 15px;color:#8795a7;font-size:9px;line-height:1.5}
.return-unit-row.is-not-returned .return-photo-upload{pointer-events:none}
.return-unit-row:focus-within{outline:2px solid rgba(37,99,235,.22);outline-offset:-2px}
@media(max-width:760px){
 .return-mobile-hint{display:block}
 .return-table-wrap{overflow:visible}
 .return-photo-table{display:block;min-width:0}
 .return-photo-table thead{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
 .return-photo-table tbody{display:grid;gap:10px;padding:10px}
 .return-photo-table .return-unit-row{display:block;overflow:hidden;border:1px solid #e2eaf1;border-radius:12px;background:#fff;box-shadow:0 3px 12px rgba(20,42,69,.035)}
 .return-photo-table .return-unit-row td{display:grid;grid-template-columns:minmax(120px,.42fr) minmax(0,1fr);align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid #edf1f5}
 .return-photo-table .return-unit-row td:before{content:attr(data-label);color:#8795a7;font-size:8px;font-weight:850;line-height:1.4}
 .return-photo-table .return-unit-row td:first-child{display:flex;gap:10px;min-height:42px;background:#f8fafc}
 .return-photo-table .return-unit-row td:first-child:before{content:"Pilih unit untuk dikembalikan";flex:1}
 .return-photo-table .return-unit-row td:last-child{border-bottom:0}
 .return-photo-upload{min-width:0}
 .return-photo-upload input[type=file]{min-width:0;font-size:10px}
 .return-photo-table .admin-select,.return-photo-table .admin-textarea{width:100%;min-width:0}
}
@media(max-width:420px){
 .return-photo-table .return-unit-row td{grid-template-columns:1fr;gap:5px}
 .return-photo-table .return-unit-row td:first-child{display:flex}
 .return-selection-summary{align-items:flex-start}
 .return-selection-summary small{flex-basis:100%;padding-left:30px}
}
</style>
@endpush

@push('scripts')
<script>
(() => {
    const returnForm = document.getElementById('unit-return-form');
    if (returnForm) {
        returnForm.addEventListener('submit', (event) => {
            const selectedCount = returnForm.querySelectorAll('.js-return-unit-check:checked').length;
            if (selectedCount === 0) {
                event.preventDefault();
                window.alert('Pilih minimal satu unit yang benar-benar sudah kembali.');
                return;
            }

            const confirmed = window.confirm(
                `Catat pengembalian untuk ${selectedCount} unit yang dipilih? Pastikan kondisi dan foto setiap unit sudah benar.`
            );
            if (!confirmed) event.preventDefault();
        });
    }

    document.querySelectorAll('.return-unit-row').forEach((row) => {
        const checkbox = row.querySelector('.js-return-unit-check');
        const photo = row.querySelector('.js-return-photo');
        const condition = row.querySelector('.js-return-condition');
        const notes = row.querySelector('.js-return-notes');
        const updateSelectedCount = () => {
            const selectedCount = document.querySelectorAll('.js-return-unit-check:checked').length;
            const counter = document.getElementById('return-selected-count');
            if (counter) counter.textContent = String(selectedCount);
        };
        const update = () => {
            const enabled = checkbox.checked;
            photo.disabled = !enabled;
            photo.required = enabled;
            condition.disabled = !enabled;
            notes.disabled = !enabled;
            row.classList.toggle('is-not-returned', !enabled);
            updateSelectedCount();
        };
        checkbox.addEventListener('change', update);
        update();

    });
})();
</script>
@endpush
