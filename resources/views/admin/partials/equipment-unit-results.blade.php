@if ($units->isEmpty())
    <div class="admin-panel-body"><div class="admin-empty">Tidak ada unit yang cocok dengan pencarian atau filter.</div></div>
@else
    <div class="admin-table-wrap">
        <table class="admin-table inventory-photo-table">
            <thead>
                <tr>
                    <th>Foto kondisi</th>
                    <th>Nama alat</th>
                    <th>Kode unit</th>
                    <th>Kondisi</th>
                    <th>Status</th>
                    <th>Perbarui foto</th>
                    <th>Perbarui kondisi</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($units as $unit)
                    @php
                        $photoPath = $unit->current_photo_path ?: $unit->equipment->image_path;
                    @endphp
                    <tr>
                        <td>
                            @if ($photoPath)
                                <img class="inventory-unit-thumb" src="{{ asset('storage/' . $photoPath) }}" alt="Foto kondisi {{ $unit->unit_code }}" loading="lazy">
                            @else
                                <div class="inventory-unit-thumb inventory-unit-thumb-empty" aria-label="Foto belum tersedia">Belum ada foto</div>
                            @endif
                        </td>
                        <td><span class="admin-primary-text">{{ $unit->equipment->name }}</span><span class="admin-secondary-text">{{ $unit->equipment->category }}</span></td>
                        <td><span class="admin-primary-text">{{ $unit->unit_code }}</span></td>
                        <td>{{ $unit->condition ?: 'Belum dicatat' }}</td>
                        <td><span class="admin-status {{ $unit->status === 'Tersedia' ? 'available' : (in_array($unit->status, ['Hilang', 'Tidak Tersedia', 'Rusak']) ? 'overdue' : 'active') }}">{{ $unit->status }}</span></td>
                        <td>
                            @if ($unit->status === 'Dipinjam')
                                <span class="admin-secondary-text">Foto diperbarui saat pengembalian.</span>
                            @else
                                <form class="inventory-photo-form" method="POST" enctype="multipart/form-data" action="{{ route('admin.equipment-units.update-photo', $unit) }}">
                                    @csrf
                                    <input id="unit-photo-{{ $unit->id }}" class="admin-input js-image-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp" capture="environment" data-camera-capture data-camera-preview="unit-photo-preview-{{ $unit->id }}" required aria-label="Foto kondisi {{ $unit->unit_code }}">
                                    <small>Pilih kamera belakang atau galeri.</small>
                                    <img id="unit-photo-preview-{{ $unit->id }}" class="inventory-photo-preview" alt="Pratinjau foto {{ $unit->unit_code }}" hidden>
                                    <button type="button" class="admin-btn small" data-camera-clear="unit-photo-{{ $unit->id }}">Hapus foto</button>
                                    <button type="submit" class="admin-btn small">Simpan foto</button>
                                </form>
                            @endif
                        </td>
                        <td>
                            @if ($unit->status === 'Dipinjam')
                                <span class="admin-secondary-text">Catat pengembalian terlebih dahulu.</span>
                            @else
                                <form class="admin-condition-form" method="POST" action="{{ route('admin.equipment-units.update-condition', $unit) }}" onsubmit="return confirm('Simpan perubahan kondisi unit ini? Perubahan dapat memengaruhi ketersediaan alat.')">
                                    @csrf
                                    <select class="admin-select" name="condition" required aria-label="Kondisi baru {{ $unit->unit_code }}">
                                        @foreach (['Baik', 'Rusak Ringan', 'Rusak Berat', 'Hilang'] as $condition)
                                            <option value="{{ $condition }}" @selected($unit->condition === $condition)>{{ $condition }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="admin-btn small">Simpan</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="admin-pager">
        <span>Menampilkan {{ $units->firstItem() }}–{{ $units->lastItem() }} dari {{ $units->total() }} unit</span>
        {{ $units->onEachSide(1)->links() }}
    </div>
@endif
