@extends('layouts.siswa')

@section('title', 'Status Peminjaman - OrbitFlow')
@section('page-heading', 'Status Peminjaman')
@section('page-subtitle', 'Informasi terkini status peminjaman alat sekolah Anda.')

@section('content')
    <div class="breadcrumbs"><a href="{{ route('borrowing.history') }}">‹ Kembali ke Riwayat Peminjaman</a></div>

    @if (session('success'))
        <div class="student-loss-feedback success" role="status" aria-live="polite">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="student-loss-feedback error" role="alert" aria-live="assertive">
            <strong>Laporan belum berhasil dikirim.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (!$borrowing)
        <div class="empty-state large student-status-empty">
            <span class="student-history-empty-icon" aria-hidden="true">◷</span>
            <strong>Belum ada pengajuan peminjaman untuk ditampilkan.</strong>
            <span>Setelah mengirim pengajuan, perkembangannya akan terlihat di halaman ini.</span>
            <a class="button primary" href="{{ route('borrowing.create') }}">Ajukan peminjaman</a>
        </div>
    @else
        @php
            $referenceId = 'OF-' . str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT);
            $statusLabel = match ($borrowing->status) {
                'Pending' => 'Menunggu pemeriksaan petugas',
                'Approved' => 'Pengajuan disetujui',
                'Borrowed' => 'Alat sedang dipinjam',
                'Rejected' => 'Pengajuan ditolak',
                'Returned' => 'Alat sudah dikembalikan',
                'CompletedWithLoss' => 'Selesai dengan kehilangan alat',
                'Overdue' => 'Pengembalian terlambat',
                default => $borrowing->status,
            };
            $statusGuidance = match ($borrowing->status) {
                'Pending' => 'Pengajuanmu sudah tercatat. Petugas akan memeriksa data dan ketersediaan alat.',
                'Approved' => 'Pengajuan telah disetujui. Ikuti arahan petugas untuk proses penyerahan alat.',
                'Borrowed' => 'Alat telah diserahkan. Jaga alat dan kembalikan sesuai tanggal yang tercantum.',
                'Rejected' => 'Pengajuan belum dapat dipenuhi. Baca alasan penolakan di bawah sebelum membuat pengajuan baru.',
                'Returned' => 'Pengembalian sudah dicatat. Riwayat pengajuan ini tetap tersimpan sebagai referensi.',
                'CompletedWithLoss' => 'Pengajuan selesai setelah laporan kehilangan dikonfirmasi petugas. Catatan tindak lanjut mengikuti kebijakan sekolah.',
                'Overdue' => 'Tanggal pengembalian telah lewat. Hubungi petugas sekolah untuk menyelesaikan proses pengembalian.',
                default => 'Periksa kembali informasi pengajuan ini atau hubungi petugas sekolah jika membutuhkan bantuan.',
            };
            $reviewed = $borrowing->status !== 'Pending';
            $finished = in_array($borrowing->status, ['Approved', 'Borrowed', 'Returned', 'Overdue', 'Rejected', 'CompletedWithLoss'], true);
            $statusClass = match ($borrowing->status) {
                'Pending' => 'status-pending',
                'Approved', 'Borrowed' => 'status-active',
                'Rejected' => 'status-rejected',
                'Overdue' => 'status-overdue',
                'Returned', 'CompletedWithLoss' => 'status-returned',
                default => '',
            };
        @endphp

        <section class="status-overview student-status-overview" aria-labelledby="student-status-heading">
            <div class="student-status-overview-top">
                <div>
                    <p class="eyebrow">REFERENSI PENGAJUAN</p>
                    <h2 class="student-status-reference" id="student-status-heading">{{ $referenceId }}</h2>
                    <p class="student-status-updated">Diperbarui {{ $borrowing->updated_at?->copy()->timezone('Asia/Jakarta')->format('d-m-Y, H:i') ?? 'belum tersedia' }} WIB</p>
                </div>
                <span class="status-chip {{ $statusClass }}">{{ $borrowing->status }}</span>
            </div>
            <div class="student-status-guidance {{ $statusClass }}" role="status">
                <strong>{{ $statusLabel }}</strong>
                <span>{{ $statusGuidance }}</span>
            </div>
            <div class="timeline student-status-timeline" aria-label="Tahapan pengajuan">
                <div class="timeline-step done">
                    <div class="timeline-dot">✓</div><div class="timeline-label">Pengajuan</div><div class="timeline-sub">Telah dibuat</div>
                </div>
                <div class="timeline-step {{ $reviewed ? 'done' : 'current' }}">
                    <div class="timeline-dot">{{ $reviewed ? '✓' : '2' }}</div><div class="timeline-label">Pemeriksaan</div><div class="timeline-sub">Verifikasi petugas</div>
                </div>
                <div class="timeline-step {{ $finished ? 'done' : '' }}">
                    <div class="timeline-dot">{{ $finished ? ($borrowing->status === 'Rejected' ? '×' : '✓') : '3' }}</div><div class="timeline-label">Keputusan</div><div class="timeline-sub">Disetujui / ditolak</div>
                </div>
            </div>
        </section>

        @if ($borrowing->status === 'Rejected' && $borrowing->rejection_reason)
            <section class="panel student-rejection-panel" aria-labelledby="student-rejection-heading">
                <div class="panel-header"><h2 class="panel-title" id="student-rejection-heading">Alasan penolakan petugas</h2></div>
                <div class="panel-body">{{ $borrowing->rejection_reason }}</div>
            </section>
        @endif

        <div class="status-detail-grid student-status-detail-grid">
            <section class="panel student-status-panel">
                <div class="panel-header"><h2 class="panel-title">Detail peminjaman</h2></div>
                <div class="panel-body detail-fields">
                    <div class="detail-field"><div class="field-label">Tanggal peminjaman</div><div class="detail-value">{{ $borrowing->start_date->format('d-m-Y') }}</div></div>
                    <div class="detail-field"><div class="field-label">Tanggal pengembalian</div><div class="detail-value">{{ $borrowing->end_date->format('d-m-Y') }}</div></div>
                    <div class="detail-field"><div class="field-label">Tujuan penggunaan</div><div class="detail-value student-purpose-value">{{ $borrowing->purpose }}</div></div>
                </div>
            </section>
            <section class="panel student-status-panel">
                <div class="panel-header"><h2 class="panel-title">Daftar alat yang diajukan</h2></div>
                <div class="panel-body">
                    @if ($borrowing->items->isEmpty())
                        <div class="student-status-no-items">Tidak ada rincian alat pada pengajuan ini.</div>
                    @else
                        <div class="borrowed-equipment-list student-borrowed-equipment-list">
                            @foreach ($borrowing->items as $item)
                                <div class="borrowed-equipment-row student-borrowed-equipment-row">
                                    <span class="mini-equipment-icon" aria-hidden="true">▣</span>
                                    <div class="borrowed-equipment-copy"><div class="borrowed-equipment-name">{{ $item->equipment->name }}</div><div class="borrowed-equipment-category">{{ $item->equipment->category }}</div></div>
                                    <span class="quantity-pill">{{ $item->quantity }} unit</span>
                                </div>
                                @if ($item->units->isNotEmpty())
                                    <div class="student-borrowed-unit-list">
                                        @foreach ($item->units as $unit)
                                            @php
                                                $unitLoss = $borrowing->losses->where('equipment_unit_id', $unit->id)->sortByDesc('reported_at')->first();
                                                $unitIsActive = $unit->pivot->returned_at === null && (int) $unit->pivot->active_equipment_unit_id === (int) $unit->id;
                                            @endphp
                                            <div class="student-unit-status-row">
                                                <strong>{{ $unit->unit_code }}</strong>
                                                <span>Kondisi saat dipinjam: {{ $unit->pivot->condition_at_borrowing === 'Rusak Ringan' ? 'Rusak Ringan' : ($unit->pivot->condition_at_borrowing === 'Baik' ? 'Baik' : 'Belum tercatat') }}</span>
                                                @if ($unitLoss)
                                                    <span class="student-loss-state">Laporan kehilangan: {{ $unitLoss->status_label }}</span>
                                                    @if ($unitLoss->status === 'Ditolak' && $unitLoss->review_notes)
                                                        <span>Catatan petugas: {{ $unitLoss->review_notes }}</span>
                                                    @endif
                                                @endif
                                                @if ($unitIsActive && in_array($borrowing->status, ['Borrowed', 'Overdue'], true) && (! $unitLoss || $unitLoss->status === 'Ditolak'))
                                                    <details class="student-loss-form-details" @if ($errors->any()) open @endif>
                                                        <summary>Laporkan unit ini hilang</summary>
                                                        <form method="POST" enctype="multipart/form-data" action="{{ route('borrowing.losses.store', ['borrowing' => $borrowing->id, 'unit' => $unit->id]) }}" class="student-loss-form">
                                                            @csrf
                                                            <label for="loss-description-{{ $unit->id }}">Keterangan kejadian</label>
                                                            <textarea id="loss-description-{{ $unit->id }}" name="description" maxlength="2000" required placeholder="Jelaskan kapan dan bagaimana alat diketahui hilang.">{{ old('description') }}</textarea>
                                                            @error('description')<small class="student-loss-field-error">{{ $message }}</small>@enderror
                                                            <label for="loss-evidence-{{ $unit->id }}">Bukti pendukung (opsional)</label>
                                                            <input id="loss-evidence-{{ $unit->id }}" type="file" name="evidence_photo" accept="image/jpeg,image/png,image/webp" capture="environment" data-camera-capture data-camera-preview="loss-evidence-preview-{{ $unit->id }}">
                                                            @error('evidence_photo')<small class="student-loss-field-error">{{ $message }}</small>@enderror
                                                            <small>Gunakan kamera belakang atau pilih dari galeri. Maksimal 5 MB.</small>
                                                            <img id="loss-evidence-preview-{{ $unit->id }}" class="student-camera-preview" alt="Pratinjau bukti kehilangan" hidden>
                                                            <button type="button" class="button secondary" data-camera-clear="loss-evidence-{{ $unit->id }}">Hapus foto</button>
                                                            <button type="submit" class="button primary" onclick="return confirm('Kirim laporan kehilangan untuk unit ini? Unit tidak akan ditandai hilang sebelum diperiksa petugas.')">Kirim laporan</button>
                                                        </form>
                                                    </details>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                    <div class="status-bottom-actions student-status-bottom-actions">
                        <a class="button primary full" href="{{ route('borrowing.history') }}">Kembali ke riwayat</a>
                        <a class="button secondary full" href="{{ route('equipment.index') }}">Lihat daftar alat</a>
                    </div>
                </div>
            </section>
        </div>

        @if ($borrowing->losses->isNotEmpty())
            <section class="panel student-status-panel student-loss-history">
                <div class="panel-header"><h2 class="panel-title">Riwayat laporan kehilangan</h2></div>
                <div class="panel-body">
                    @foreach ($borrowing->losses->sortByDesc('reported_at') as $loss)
                        <article class="student-loss-history-item"><strong>{{ $loss->equipmentUnit?->unit_code ?? 'Unit' }} · {{ $loss->status_label }}</strong><p>{{ $loss->description }}</p>@if($loss->review_notes)<small>Catatan petugas: {{ $loss->review_notes }}</small>@endif</article>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
@endsection

@push('styles')
<style>
.student-unit-status-row{display:grid;gap:5px;padding:10px 0;border-bottom:1px solid #edf1f5}.student-loss-feedback{margin:12px 0;padding:12px 14px;border-radius:10px;font-size:12px;line-height:1.55}.student-loss-feedback.success{background:#ecfdf3;border:1px solid #b7ebc9;color:#176b3a}.student-loss-feedback.error{background:#fff1f0;border:1px solid #ffc9c5;color:#a12622}.student-loss-feedback ul{margin:6px 0 0;padding-left:19px}.student-loss-field-error{color:#b42318!important;font-size:9px}.student-loss-state{color:#9a5b17;font-size:9px;font-weight:800}.student-loss-form-details{margin:5px 0 2px;padding:9px;border:1px solid #e1eaf2;border-radius:10px;background:#f9fbfd}.student-loss-form-details summary{color:#9a5b17;font-size:9px;font-weight:850;cursor:pointer}.student-loss-form{display:grid;gap:7px;margin-top:10px}.student-loss-form label{font-size:9px;font-weight:800;color:#40536a}.student-loss-form textarea{width:100%;min-height:74px;padding:8px;border:1px solid #d9e2ec;border-radius:8px;font:inherit;font-size:10px}.student-loss-form input[type=file]{max-width:100%;font-size:9px}.student-loss-form small{color:#7c8da1;font-size:8px}.student-camera-preview{width:min(100%,190px);max-height:145px;object-fit:cover;border:1px solid #d9e2ec;border-radius:9px}.student-camera-preview[hidden]{display:none}.student-loss-history{margin-top:12px}.student-loss-history-item{padding:10px 0;border-bottom:1px solid #edf1f5}.student-loss-history-item:last-child{border-bottom:0}.student-loss-history-item strong{font-size:10px}.student-loss-history-item p{margin:5px 0;color:#61748b;font-size:9px;line-height:1.55}.student-loss-history-item small{color:#9a5b17;font-size:9px}
</style>
@endpush
