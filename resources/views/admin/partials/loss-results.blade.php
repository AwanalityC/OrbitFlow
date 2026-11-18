@if ($losses->isEmpty())
    <div class="admin-panel-body"><div class="admin-empty">Tidak ada laporan kehilangan yang cocok dengan filter.</div></div>
@else
    <div class="admin-table-wrap loss-table-wrap">
        <table class="admin-table loss-table">
            <thead><tr><th>Waktu laporan</th><th>Peminjam</th><th>Unit</th><th>Keterangan</th><th>Bukti</th><th>Status &amp; tindak lanjut</th></tr></thead>
            <tbody>
            @foreach ($losses as $loss)
                <tr>
                    <td data-label="Waktu laporan"><span class="admin-primary-text">{{ $loss->reported_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '—' }} WIB</span><span class="admin-secondary-text">OF-{{ str_pad($loss->borrowing_id, 5, '0', STR_PAD_LEFT) }}</span></td>
                    <td data-label="Peminjam"><span class="admin-primary-text">{{ $loss->borrowing?->user?->name ?? 'Pengguna tidak tersedia' }}</span><span class="admin-secondary-text">{{ $loss->borrowing?->user?->email }}</span></td>
                    <td data-label="Unit"><span class="admin-primary-text">{{ $loss->equipmentUnit?->unit_code ?? 'Unit dihapus' }}</span><span class="admin-secondary-text">{{ $loss->equipmentUnit?->equipment?->name ?? 'Alat tidak tersedia' }}</span></td>
                    <td data-label="Keterangan"><span class="loss-description">{{ $loss->description }}</span>@if($loss->review_notes)<span class="admin-secondary-text">Catatan: {{ $loss->review_notes }}</span>@endif</td>
                    <td data-label="Bukti">@if($loss->evidence_photo_path)<a class="admin-btn small" href="{{ route('admin.losses.evidence', $loss) }}" target="_blank" rel="noopener">Lihat bukti</a>@else<span class="admin-secondary-text">Tidak ada</span>@endif</td>
                    <td data-label="Status & tindak lanjut">
                        <span class="admin-status {{ in_array($loss->status, ['Dikonfirmasi', 'Hilang Permanen'], true) ? 'overdue' : ($loss->status === 'Ditolak' ? 'rejected' : ($loss->status === 'DitemukanKembali' ? 'returned' : 'pending')) }}">{{ $loss->status_label }}</span>
                        @if ($loss->status === 'Dilaporkan')
                            <div class="loss-actions">
                                <form method="POST" action="{{ route('admin.losses.confirm', $loss) }}" onsubmit="return confirm('Konfirmasi laporan ini? Unit akan ditandai Hilang dan alokasi pinjamnya ditutup. Tidak ada nominal kompensasi yang dihitung otomatis.');">
                                    @csrf
                                    <input class="admin-input loss-review-note" name="review_notes" maxlength="1000" placeholder="Catatan konfirmasi (opsional)">
                                    <button type="submit" class="admin-btn primary small">Konfirmasi</button>
                                </form>
                                <form method="POST" action="{{ route('admin.losses.reject', $loss) }}" onsubmit="return confirm('Tolak laporan ini? Unit akan tetap tercatat sedang dipinjam.');">
                                    @csrf
                                    <input class="admin-input loss-review-note" name="review_notes" minlength="5" maxlength="1000" required placeholder="Alasan penolakan wajib diisi">
                                    <button type="submit" class="admin-btn danger small">Tolak</button>
                                </form>
                            </div>
                        @else
                            <span class="admin-secondary-text">Diperiksa {{ $loss->reviewed_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '—' }}@if($loss->reviewer) oleh {{ $loss->reviewer->name }}@endif</span>
                        @endif

                        @if ($loss->compensation)
                            <div class="loss-compensation">
                                <strong>Tindak lanjut kompensasi</strong>
                                <span class="admin-status {{ in_array($loss->compensation->status, ['Pending', 'Menunggu Pembayaran', 'Menunggu Penggantian'], true) ? 'pending' : 'returned' }}">{{ $loss->compensation->status }}</span>

                                @if ($loss->compensation->status === 'Pending' && $loss->status === 'Dikonfirmasi')
                                    <form method="POST" action="{{ route('admin.losses.compensation', $loss) }}" class="loss-compensation-form" data-compensation-plan>
                                        @csrf
                                        <label class="loss-field-label">Pilihan penyelesaian</label>
                                        <select class="admin-select" name="resolution_type" data-compensation-type required>
                                            <option value="">Pilih tindak lanjut</option>
                                            <option value="Ganti Barang">Ganti barang</option>
                                            <option value="Bayar Kompensasi">Bayar kompensasi</option>
                                            <option value="Dibebaskan">Dibebaskan</option>
                                            <option value="Barang Ditemukan">Barang ditemukan kembali</option>
                                        </select>
                                        <div data-compensation-field="amount" hidden>
                                            <label class="loss-field-label">Nominal yang ditetapkan sekolah (Rp)</label>
                                            <input class="admin-input" type="number" name="amount" min="0.01" max="9999999999.99" step="0.01" placeholder="Contoh: 150000">
                                        </div>
                                        <div data-compensation-field="condition" hidden>
                                            <label class="loss-field-label">Kondisi unit saat ditemukan</label>
                                            <select class="admin-select" name="recovered_condition">
                                                <option value="">Pilih kondisi</option>
                                                <option value="Baik">Baik</option>
                                                <option value="Rusak Ringan">Rusak ringan</option>
                                                <option value="Rusak Berat">Rusak berat</option>
                                            </select>
                                        </div>
                                        <label class="loss-field-label">Alasan / catatan keputusan</label>
                                        <textarea class="admin-input loss-review-note" name="resolution_notes" minlength="5" maxlength="1000" required placeholder="Tuliskan dasar keputusan sekolah..."></textarea>
                                        <button type="submit" class="admin-btn primary small">Simpan keputusan</button>
                                    </form>
                                @elseif (in_array($loss->compensation->status, ['Menunggu Pembayaran', 'Menunggu Penggantian'], true))
                                    <div class="loss-compensation-details">
                                        <span>{{ $loss->compensation->resolution_type }}</span>
                                        @if ($loss->compensation->amount !== null)
                                            <strong>Rp{{ number_format((float) $loss->compensation->amount, 2, ',', '.') }}</strong>
                                        @endif
                                        @if ($loss->compensation->resolution_notes)
                                            <span>{{ $loss->compensation->resolution_notes }}</span>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('admin.losses.compensation.complete', $loss) }}" class="loss-compensation-form" data-compensation-complete data-resolution-type="{{ $loss->compensation->resolution_type }}">
                                        @csrf
                                        @if ($loss->compensation->resolution_type === 'Ganti Barang')
                                            <label class="loss-field-label">Kode unit pengganti</label>
                                            <input class="admin-input" name="replacement_unit_code" maxlength="100" required placeholder="Masukkan kode unit baru">
                                            <label class="loss-field-label">Kondisi unit pengganti</label>
                                            <select class="admin-select" name="replacement_condition" required>
                                                <option value="">Pilih kondisi</option>
                                                <option value="Baik">Baik</option>
                                                <option value="Rusak Ringan">Rusak ringan</option>
                                            </select>
                                        @else
                                            <p class="admin-secondary-text">Gunakan tindakan ini setelah pembayaran benar-benar diterima dan diverifikasi.</p>
                                        @endif
                                        <label class="loss-field-label">Catatan penyelesaian</label>
                                        <textarea class="admin-input loss-review-note" name="completion_notes" minlength="5" maxlength="1000" required placeholder="Catat bukti penerimaan pembayaran atau barang pengganti..."></textarea>
                                        <button type="submit" class="admin-btn primary small">{{ $loss->compensation->resolution_type === 'Ganti Barang' ? 'Terima unit pengganti & selesai' : 'Konfirmasi pembayaran diterima' }}</button>
                                    </form>
                                @else
                                    <div class="loss-compensation-details">
                                        <strong>{{ $loss->compensation->resolution_type }}</strong>
                                        @if ($loss->compensation->amount !== null)
                                            <span>Nominal: Rp{{ number_format((float) $loss->compensation->amount, 2, ',', '.') }}</span>
                                        @endif
                                        @if ($loss->compensation->recovered_condition)
                                            <span>Kondisi ditemukan: {{ $loss->compensation->recovered_condition }}</span>
                                        @endif
                                        @if ($loss->compensation->replacementEquipmentUnit)
                                            <span>Unit pengganti: {{ $loss->compensation->replacementEquipmentUnit->unit_code }}</span>
                                        @endif
                                        @if ($loss->compensation->resolution_notes)
                                            <span>Keputusan: {{ $loss->compensation->resolution_notes }}</span>
                                        @endif
                                        @if ($loss->compensation->completion_notes)
                                            <span>Penyelesaian: {{ $loss->compensation->completion_notes }}</span>
                                        @endif
                                        <span class="admin-secondary-text">Selesai {{ $loss->compensation->resolved_at?->timezone('Asia/Jakarta')->format('d/m/Y H:i') ?? '—' }}@if($loss->compensation->resolver) oleh {{ $loss->compensation->resolver->name }}@endif</span>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="admin-pager"><span>Menampilkan {{ $losses->firstItem() }}–{{ $losses->lastItem() }} dari {{ $losses->total() }} laporan</span>{{ $losses->onEachSide(1)->links() }}</div>
@endif
