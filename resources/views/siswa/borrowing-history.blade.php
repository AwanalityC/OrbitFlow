@extends('layouts.siswa')

@section('title', 'Riwayat Peminjaman - OrbitFlow')
@section('page-heading', 'Riwayat Peminjaman')
@section('page-subtitle', 'Lihat catatan pengajuan alat yang pernah Anda buat.')

@section('content')
    <section class="student-history-intro" aria-labelledby="student-history-title">
        <div>
            <span class="student-flow-eyebrow">AKTIVITAS PEMINJAMAN</span>
            <h2 id="student-history-title">Semua pengajuanmu, dalam satu tempat.</h2>
            <p>Pantau status, periode, dan detail setiap pengajuan peminjaman.</p>
        </div>
        <a class="button primary student-history-new-button" href="{{ route('borrowing.create') }}">＋ Pengajuan baru</a>
    </section>

    <section class="student-history-panel" aria-label="Daftar riwayat peminjaman">
        <div class="history-toolbar student-history-toolbar">
            <label class="student-history-search-wrap">
                <span aria-hidden="true">⌕</span>
                <input id="history-search" class="field-control history-search" type="search" placeholder="Cari ID atau nama alat..." aria-label="Cari ID atau nama alat" autocomplete="off" aria-controls="history-rows">
            </label>
            <select id="history-status" class="field-control history-filter" aria-label="Filter status" aria-controls="history-rows">
                <option value="">Status: Semua</option>
                @foreach (['Pending', 'Approved', 'Rejected', 'Borrowed', 'Returned', 'Overdue', 'CompletedWithLoss'] as $status)
                    <option value="{{ strtolower($status) }}">{{ $status }}</option>
                @endforeach
            </select>
            <button type="button" class="button secondary student-history-reset" id="history-reset">Reset</button>
            <span class="history-count" aria-live="polite">{{ $borrowings->count() }} pengajuan ditemukan</span>
        </div>

        @if ($borrowings->isEmpty())
            <div class="empty-state large student-history-empty">
                <span class="student-history-empty-icon" aria-hidden="true">▤</span>
                <strong>Belum ada riwayat peminjaman.</strong>
                <span>Pengajuan yang kamu buat akan muncul di sini.</span>
                <a class="button primary" href="{{ route('borrowing.create') }}">Ajukan peminjaman</a>
            </div>
        @else
            <div class="student-history-no-results" id="history-no-results" role="status" hidden>
                <strong>Tidak ada pengajuan yang cocok.</strong>
                <span>Coba kata kunci atau status yang berbeda.</span>
            </div>
            <div class="table-wrap student-history-table-wrap">
                <table class="history-table student-history-table">
                    <thead>
                        <tr>
                            <th>ID Peminjaman</th><th>Diajukan pada</th><th>Periode</th><th>Alat yang Dipinjam</th><th>Jumlah</th><th>Status</th><th>Catatan Admin</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="history-rows">
                        @foreach ($borrowings as $borrowing)
                            @php
                                $equipmentNames = $borrowing->items->map(fn ($item) => $item->equipment->name)->implode(' + ');
                                $totalQuantity = $borrowing->items->sum('quantity');
                            @endphp
                            <tr data-search="{{ strtolower('OF-' . str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT) . ' ' . $equipmentNames) }}" data-status="{{ strtolower($borrowing->status) }}">
                                <td class="history-id" data-label="ID Peminjaman">OF-{{ str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT) }}</td>
                                <td class="history-submitted" data-label="Diajukan pada">
                                    @if ($borrowing->created_at)
                                        {{ $borrowing->created_at->copy()->timezone('Asia/Jakarta')->format('d-m-Y') }}
                                        <span class="history-submitted-time">{{ $borrowing->created_at->copy()->timezone('Asia/Jakarta')->format('H:i') }} WIB</span>
                                    @else
                                        <span class="student-history-muted">—</span>
                                    @endif
                                </td>
                                <td class="history-period" data-label="Periode">{{ $borrowing->start_date->format('d-m-Y') }}<br><span class="student-history-muted">s.d.</span> {{ $borrowing->end_date->format('d-m-Y') }}</td>
                                <td class="history-equipment" data-label="Alat yang dipinjam"><div class="history-equipment-name">{{ $equipmentNames ?: 'Tidak ada item' }}</div><div class="history-equipment-sub">{{ $borrowing->items->count() }} jenis alat</div></td>
                                <td data-label="Jumlah">{{ $totalQuantity }} unit</td>
                                <td data-label="Status"><span class="status-chip status-{{ strtolower($borrowing->status) }}">{{ $borrowing->status === 'CompletedWithLoss' ? 'Selesai dengan kehilangan' : $borrowing->status }}</span></td>
                                <td data-label="Catatan Admin">
                                    @if ($borrowing->status === 'Rejected' && $borrowing->rejection_reason)
                                        {{ $borrowing->rejection_reason }}
                                    @else
                                        <span class="student-history-muted">—</span>
                                    @endif
                                </td>
                                <td data-label="Aksi"><a class="button history-action" href="{{ route('borrowing.status', ['id' => $borrowing->id]) }}">Lihat detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection

@push('scripts')
<script>
    (() => {
        const search = document.getElementById('history-search');
        const status = document.getElementById('history-status');
        const reset = document.getElementById('history-reset');
        const rows = Array.from(document.querySelectorAll('#history-rows tr'));
        const count = document.querySelector('.history-count');
        const noResults = document.getElementById('history-no-results');

        function applyFilters() {
            const term = (search?.value || '').trim().toLowerCase();
            const state = status?.value || '';
            let visible = 0;

            rows.forEach(row => {
                const matches = row.dataset.search.includes(term) && (!state || row.dataset.status === state);
                row.hidden = !matches;
                if (matches) visible++;
            });

            if (count) count.textContent = `${visible} pengajuan ditemukan`;
            if (noResults) noResults.hidden = rows.length === 0 || visible > 0;
        }

        search?.addEventListener('input', applyFilters);
        status?.addEventListener('change', applyFilters);
        reset?.addEventListener('click', () => {
            if (search) search.value = '';
            if (status) status.value = '';
            applyFilters();
            search?.focus();
        });
    })();
</script>
@endpush
