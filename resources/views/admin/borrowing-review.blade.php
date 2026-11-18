@extends('layouts.admin')

@section('title', 'Detail Pengajuan')
@section('page-title', 'Detail Pengajuan')
@section('page-description', 'Periksa informasi peminjam, periode, kebutuhan alat, dan keputusan pengajuan.')

@section('content')
    <div class="admin-page-intro">
        <div><h2>Pengajuan OF-{{ str_pad($borrowing->id, 5, '0', STR_PAD_LEFT) }}</h2><p>Diajukan {{ $borrowing->created_at->timezone('Asia/Jakarta')->format('d/m/Y, H:i') }} WIB.</p></div>
        <div class="admin-actions"><a href="{{ route('admin.borrowings.index') }}" class="admin-btn">← Kembali ke semua pengajuan</a></div>
    </div>

    <section class="admin-panel">
        <div class="admin-panel-head"><div><h3>Informasi peminjaman</h3><p>Ringkasan permintaan dan identitas siswa.</p></div><span class="admin-status {{ strtolower($borrowing->status) }}">{{ $borrowing->status }}</span></div>
        <div class="admin-panel-body">
            <div class="admin-info-grid">
                <div class="admin-info-box"><div class="admin-info-label">Nama peminjam</div><div class="admin-info-value">{{ $borrowing->user->name }}</div></div>
                <div class="admin-info-box"><div class="admin-info-label">Email siswa</div><div class="admin-info-value">{{ $borrowing->user->email }}</div></div>
                <div class="admin-info-box"><div class="admin-info-label">Tanggal peminjaman</div><div class="admin-info-value">{{ $borrowing->start_date->format('d/m/Y') }}</div></div>
                <div class="admin-info-box"><div class="admin-info-label">Batas pengembalian</div><div class="admin-info-value">{{ $borrowing->end_date->format('d/m/Y') }}</div></div>
            </div>
        </div>
    </section>

    @php
        $workflowState = match ($borrowing->status) {
            'Pending' => ['title' => 'Menunggu pemeriksaan', 'description' => 'Periksa alasan, periode, dan ketersediaan unit sebelum menyetujui atau menolak pengajuan.', 'class' => 'pending'],
            'Borrowed' => ['title' => 'Sedang dipinjam', 'description' => 'Unit fisik telah dialokasikan. Catat pengembalian hanya setelah unit benar-benar diterima dan diperiksa.', 'class' => 'active'],
            'Overdue' => ['title' => 'Melewati batas pengembalian', 'description' => 'Prioritaskan tindak lanjut pengembalian dan periksa setiap unit yang masih aktif.', 'class' => 'overdue'],
            'Returned' => ['title' => 'Pengajuan selesai', 'description' => 'Seluruh unit pada pengajuan ini sudah tercatat kembali.', 'class' => 'returned'],
            'Rejected' => ['title' => 'Pengajuan ditolak', 'description' => 'Pengajuan ini tidak dapat diproses lebih lanjut. Alasan penolakan ditampilkan di bawah jika tersedia.', 'class' => 'rejected'],
            default => ['title' => $borrowing->status, 'description' => 'Periksa detail status sebelum mengambil tindakan.', 'class' => 'neutral'],
        };
    @endphp
    <section class="borrowing-workflow-state {{ $workflowState['class'] }}" role="status">
        <span class="borrowing-workflow-state-mark" aria-hidden="true">
            @if ($borrowing->status === 'Pending') ! @elseif ($borrowing->status === 'Overdue') ! @elseif ($borrowing->status === 'Returned') ✓ @else · @endif
        </span>
        <div><strong>{{ $workflowState['title'] }}</strong><p>{{ $workflowState['description'] }}</p></div>
    </section>

    <section class="admin-panel admin-section-space">
        <div class="admin-panel-head"><div><h3>Tujuan penggunaan</h3><p>Alasan yang ditulis siswa pada saat mengajukan peminjaman.</p></div></div>
        <div class="admin-panel-body"><p style="margin:0;color:#53677f;font-size:10px;line-height:1.7">{{ $borrowing->purpose }}</p></div>
    </section>

    <section class="admin-panel admin-section-space">
        <div class="admin-panel-head"><div><h3>Alat yang diajukan</h3><p>Jumlah unit yang diminta serta ketersediaan saat halaman dibuka.</p></div></div>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead><tr><th>Nama alat</th><th>Diminta</th><th>Unit Baik</th><th>Unit Rusak Ringan</th><th>Total layak dipinjam</th></tr></thead>
                <tbody>
                    @foreach ($borrowing->items as $item)
                        @php
                            $availableUnits = $item->equipment->borrowableUnits;
                            $availableNormal = $availableUnits->where('condition', 'Baik')->count();
                            $availableMinor = $availableUnits->where('condition', 'Rusak Ringan')->count();
                        @endphp
                        <tr>
                            <td><span class="admin-primary-text">{{ $item->equipment->name }}</span><span class="admin-secondary-text">{{ $item->equipment->category }}</span></td>
                            <td>{{ $item->quantity }} unit</td>
                            <td>{{ $availableNormal }}</td>
                            <td>{{ $availableMinor }}</td>
                            <td><span class="admin-primary-text">{{ $availableNormal + $availableMinor }} unit</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="admin-panel-body"><p class="admin-priority-note">Alokasi fisik dilakukan saat persetujuan. OrbitFlow memilih unit Baik terlebih dahulu, lalu unit Rusak Ringan sebagai cadangan jika diperlukan. Ketersediaan dapat berubah sebelum admin menyetujui.</p></div>
    </section>


    @if ($borrowing->returnRecords->isNotEmpty())
        <section class="admin-panel admin-section-space">
            <div class="admin-panel-head"><div><h3>Catatan pengembalian</h3><p>Unit yang sudah dikembalikan dan kondisi yang dicatat admin.</p></div><span class="admin-status returned">{{ $borrowing->returnRecords->count() }} catatan</span></div>
            <div class="admin-table-wrap">
                <table class="admin-table borrowing-return-records-table">
                    <thead><tr><th>Unit</th><th>Foto kondisi kembali</th><th>Kondisi saat kembali</th><th>Waktu pengembalian</th><th>Catatan</th></tr></thead>
                    <tbody>
                        @foreach ($borrowing->returnRecords as $record)
                            <tr>
                                <td data-label="Unit"><span class="admin-primary-text">{{ $record->equipmentUnit->unit_code ?? 'Unit dihapus' }}</span></td>
                                <td data-label="Foto kondisi kembali">@if ($record->photo_path)<a href="{{ asset('storage/' . $record->photo_path) }}" target="_blank" rel="noopener"><img class="admin-return-photo-thumb" src="{{ asset('storage/' . $record->photo_path) }}" alt="Foto pengembalian {{ $record->equipmentUnit->unit_code ?? '' }}" loading="lazy"></a>@else—@endif</td>
                                <td data-label="Kondisi saat kembali">{{ $record->condition }}</td>
                                <td data-label="Waktu pengembalian">{{ $record->returned_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '—' }} WIB</td>
                                <td data-label="Catatan">{{ $record->notes ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if ($borrowing->status === 'Pending')
        <section class="admin-form-grid admin-section-space">
            <div class="admin-panel">
                <div class="admin-panel-head"><div><h3>Setujui pengajuan</h3><p>Stok akan diperiksa ulang dan unit fisik dialokasikan saat disetujui.</p></div></div>
                <div class="admin-panel-body">
                    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.borrowing.approve', $borrowing) }}" class="admin-stack handover-photo-form" style="gap:12px" onsubmit="return confirm('Setujui pengajuan ini? Unggah foto kondisi semua jenis alat yang akan diserahkan.');">
                        @csrf
                        <p class="admin-priority-note">Foto wajib diambil saat alat diserahkan. Unggah satu foto untuk setiap jenis alat; jika jumlahnya lebih dari satu, pastikan semua unit terlihat dalam foto.</p>
                        @foreach ($borrowing->items as $item)
                            <div class="admin-field handover-photo-field">
                                <label for="handover-photo-{{ $item->equipment_id }}">Foto penyerahan · {{ $item->equipment->name }} ({{ $item->quantity }} unit)</label>
                                <input id="handover-photo-{{ $item->equipment_id }}" class="admin-input js-handover-photo" type="file" name="handover_photos[{{ $item->equipment_id }}]" accept="image/jpeg,image/png,image/webp" capture="environment" data-camera-capture data-camera-preview="handover-preview-{{ $item->equipment_id }}" aria-describedby="handover-help-{{ $item->equipment_id }}" required>
                                <small id="handover-help-{{ $item->equipment_id }}">Ambil foto dengan kamera belakang atau pilih dari galeri. JPG, PNG, atau WEBP. Maksimal 5 MB.</small>
                                <img id="handover-preview-{{ $item->equipment_id }}" class="handover-photo-preview" alt="Pratinjau foto penyerahan {{ $item->equipment->name }}" hidden>
                                <button type="button" class="admin-btn small" data-camera-clear="handover-photo-{{ $item->equipment_id }}">Hapus foto</button>
                            </div>
                        @endforeach
                        <button type="submit" class="admin-btn primary">✓ Setujui &amp; simpan foto penyerahan</button>
                    </form>
                </div>
            </div>
            <div class="admin-panel">
                <div class="admin-panel-head"><div><h3>Tolak pengajuan</h3><p>Alasan penolakan wajib diisi dan dapat dilihat oleh siswa.</p></div></div>
                <div class="admin-panel-body">
                    <form method="POST" action="{{ route('admin.borrowing.reject', $borrowing) }}" class="admin-stack" style="gap:10px" onsubmit="return confirm('Tolak pengajuan ini?');">
                        @csrf
                        <div class="admin-field"><label for="rejection-reason">Alasan penolakan</label><textarea class="admin-textarea" id="rejection-reason" name="rejection_reason" maxlength="1000" required placeholder="Jelaskan alasan penolakan dengan singkat dan jelas.">{{ old('rejection_reason') }}</textarea></div>
                        <button type="submit" class="admin-btn danger">Tolak pengajuan</button>
                    </form>
                </div>
            </div>
        </section>
    @elseif ($borrowing->status === 'Rejected')
        <section class="admin-panel admin-section-space">
            <div class="admin-panel-head"><div><h3>Alasan penolakan</h3><p>Alasan yang tersimpan pada pengajuan ini.</p></div></div>
            <div class="admin-panel-body"><div class="admin-rejection-note">{{ $borrowing->rejection_reason ?: 'Tidak ada alasan yang tercatat untuk pengajuan lama ini.' }}</div></div>
        </section>
    @elseif (in_array($borrowing->status, ['Borrowed', 'Overdue'], true))
        <section class="admin-panel admin-section-space">
            <div class="admin-panel-head"><div><h3>Unit fisik yang masih dipinjam</h3><p>Unit yang belum dicatat sebagai pengembalian.</p></div></div>
            <div class="admin-panel-body">
                @php $activeUnits = $borrowing->items->flatMap(fn ($item) => $item->activeUnits); @endphp
                @if ($activeUnits->isEmpty())
                    <div class="admin-empty">Tidak ada unit aktif yang terhubung dengan pengajuan ini.</div>
                @else
                    <div class="admin-alert-list">
                        @foreach ($activeUnits as $unit)
                            <div class="admin-alert-item"><span class="admin-alert-mark">▣</span><div class="admin-alert-copy"><strong>{{ $unit->equipment->name ?? 'Unit alat' }} · {{ $unit->unit_code }}</strong><span>Kondisi saat dipinjam: {{ $unit->pivot->condition_at_borrowing ?: 'Belum tercatat' }}</span>@if ($unit->pivot->handover_photo_path)<span><a href="{{ asset('storage/' . $unit->pivot->handover_photo_path) }}" target="_blank" rel="noopener">Lihat foto saat diserahkan</a></span>@endif</div></div>
                        @endforeach
                    </div>
                    <div style="margin-top:13px"><a href="{{ route('admin.borrowing.return.form', $borrowing) }}" class="admin-btn primary">Proses pengembalian</a></div>
                @endif
            </div>
        </section>
    @endif
@endsection

@push('styles')
<style>
.admin-return-photo-thumb{display:block;width:76px;height:58px;object-fit:cover;border-radius:8px;border:1px solid #dce5ef}
.handover-photo-form .admin-field{padding:12px;border:1px solid #e1e8f0;border-radius:11px;background:#f8fafc}
.handover-photo-form .admin-field label{display:block;margin-bottom:7px;color:#304761;font-size:10px;font-weight:850}
.handover-photo-form input[type=file]{max-width:100%;width:100%}
.handover-photo-form small{display:block;margin-top:6px;color:#7c8ca1;font-size:9px;line-height:1.5}
.handover-photo-preview{display:block;width:min(100%,220px);max-height:150px;margin-top:9px;object-fit:cover;border:1px solid #dce5ef;border-radius:9px}.handover-photo-preview[hidden]{display:none}
.borrowing-workflow-state{display:flex;align-items:flex-start;gap:11px;margin:14px 0 18px;padding:13px 15px;border:1px solid #dfe7f0;border-radius:12px;background:#f8fafc}
.borrowing-workflow-state-mark{display:grid;width:25px;height:25px;flex:0 0 25px;place-items:center;border-radius:8px;background:#eaf0f8;color:#45617f;font-weight:900}
.borrowing-workflow-state strong{display:block;color:#304761;font-size:10px}
.borrowing-workflow-state p{margin:4px 0 0;color:#74859a;font-size:9px;line-height:1.55}
.borrowing-workflow-state.pending{border-color:#f0e0bd;background:#fffaf0}
.borrowing-workflow-state.pending .borrowing-workflow-state-mark{background:#fff0d3;color:#9a6a18}
.borrowing-workflow-state.overdue{border-color:#f1cfd2;background:#fff5f5}
.borrowing-workflow-state.overdue .borrowing-workflow-state-mark{background:#ffe7e8;color:#b74652}
.borrowing-workflow-state.returned{border-color:#cce9dc;background:#f1fbf6}
.borrowing-workflow-state.returned .borrowing-workflow-state-mark{background:#def5e9;color:#24795d}
.borrowing-workflow-state.rejected{border-color:#e7d6d8;background:#fff8f8}
@media(max-width:760px){
 .borrowing-return-records-table{display:block;min-width:0}
 .borrowing-return-records-table thead{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
 .borrowing-return-records-table tbody{display:grid;gap:9px;padding:9px}
 .borrowing-return-records-table tr{display:block;overflow:hidden;border:1px solid #e5ebf2;border-radius:10px}
 .borrowing-return-records-table td{display:grid;grid-template-columns:minmax(112px,.45fr) minmax(0,1fr);gap:10px;padding:9px;border-bottom:1px solid #eef2f6}
 .borrowing-return-records-table td:before{content:attr(data-label);color:#8795a7;font-size:8px;font-weight:850}
 .borrowing-return-records-table td:last-child{border-bottom:0}
}
@media(max-width:420px){.borrowing-return-records-table td{grid-template-columns:1fr;gap:4px}}
</style>
@endpush


