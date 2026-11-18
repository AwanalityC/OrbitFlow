@extends('layouts.admin')

@section('title', 'Semua Pengajuan')
@section('page-title', 'Pengajuan Peminjaman')
@section('page-description', 'Cari, filter, dan tinjau seluruh pengajuan peminjaman siswa.')

@section('content')
    <section class="admin-page-intro">
        <div><h2>Register peminjaman</h2><p>Termasuk pengajuan menunggu, peminjaman aktif, ditolak, terlambat, dan sudah dikembalikan.</p></div>
        <div class="admin-actions"><a href="{{ route('admin.dashboard') }}" class="admin-btn">← Kembali ke ringkasan</a></div>
    </section>

    <section class="admin-panel">
        <div class="admin-panel-head"><div><h3>Filter data pengajuan</h3><p>Gunakan kata kunci nama siswa, email, nama alat, tujuan, atau nomor pengajuan.</p></div></div>
        <div class="admin-panel-body">
            <form method="GET" action="{{ route('admin.borrowings.index') }}" class="admin-filter-form" data-live-filter data-live-target="#admin-borrowing-results" data-live-debounce="180">
                <input class="admin-input search" type="search" name="q" value="{{ $search }}" placeholder="Cari siswa, alat, tujuan, atau ID...">
                <select class="admin-select" name="status" aria-label="Filter status">
                    <option value="all" @selected($status === 'all')>Semua status</option>
                    <option value="pending" @selected($status === 'pending')>Menunggu persetujuan</option>
                    <option value="active" @selected($status === 'active')>Peminjaman aktif</option>
                    <option value="Overdue" @selected($status === 'Overdue')>Terlambat</option>
                    <option value="Returned" @selected($status === 'Returned')>Dikembalikan</option>
                    <option value="CompletedWithLoss" @selected($status === 'CompletedWithLoss')>Selesai dengan kehilangan</option>
                    <option value="Rejected" @selected($status === 'Rejected')>Ditolak</option>
                </select>
                <input class="admin-input" type="date" name="from" value="{{ request('from') }}" aria-label="Diajukan dari tanggal">
                <input class="admin-input" type="date" name="to" value="{{ request('to') }}" aria-label="Diajukan sampai tanggal">
                <button class="admin-btn primary js-live-filter-fallback" type="submit">Terapkan filter</button>
                <a class="admin-btn" href="{{ route('admin.borrowings.index') }}" data-live-reset>Reset</a>
            </form>
        </div>
    </section>

    <section class="admin-panel admin-section-space" id="admin-borrowing-results" data-live-results>
        @php
            $quickFilters = [
                'all' => 'Semua',
                'pending' => 'Menunggu',
                'active' => 'Aktif',
                'Overdue' => 'Terlambat',
                'Returned' => 'Dikembalikan',
                'CompletedWithLoss' => 'Selesai + kehilangan',
                'Rejected' => 'Ditolak',
            ];
            $preservedFilters = request()->only(['q', 'from', 'to']);
            $currentQuickFilter = $status ?: 'all';
        @endphp
        <nav class="borrowing-quick-filters" aria-label="Filter cepat status peminjaman">
            @foreach ($quickFilters as $filterValue => $filterLabel)
                <a
                    class="borrowing-quick-filter {{ $currentQuickFilter === $filterValue ? 'is-active' : '' }}"
                    href="{{ route('admin.borrowings.index', array_merge($preservedFilters, ['status' => $filterValue])) }}"
                    @if ($currentQuickFilter === $filterValue) aria-current="page" @endif
                >{{ $filterLabel }}</a>
            @endforeach
        </nav>
        <div class="admin-panel-head borrowing-results-heading"><div><h3>Daftar pengajuan</h3><p>Data terbaru ditampilkan lebih dahulu. Pilih satu pengajuan untuk melihat keputusan, unit, dan riwayat pengembalian.</p></div><span class="admin-status active">{{ $borrowings->total() }} data</span></div>
        @if ($borrowings->isEmpty())
            <div class="admin-panel-body"><div class="admin-empty">Tidak ada pengajuan yang cocok dengan filter tersebut.</div></div>
        @else
            <div class="admin-table-wrap borrowing-register-wrap">
                <table class="admin-table borrowing-register-table">
                    <thead><tr><th>Nomor / Diajukan</th><th>Peminjam</th><th>Alat</th><th>Periode pinjam</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($borrowings as $borrowing)
                            <tr>
                                <td data-label="Nomor / diajukan"><span class="admin-primary-text">OF-{{ str_pad($borrowing->id, 5, '0', STR_PAD_LEFT) }}</span><span class="admin-secondary-text">{{ $borrowing->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</span></td>
                                <td data-label="Peminjam"><span class="admin-primary-text">{{ $borrowing->user->name }}</span><span class="admin-secondary-text">{{ $borrowing->user->email }}</span></td>
                                <td data-label="Alat">@foreach ($borrowing->items as $item)<span>{{ $item->equipment->name }} × {{ $item->quantity }}</span>@if(!$loop->last)<br>@endif @endforeach</td>
                                <td data-label="Periode pinjam">{{ $borrowing->start_date->format('d/m/Y') }}<span class="admin-secondary-text">s.d. {{ $borrowing->end_date->format('d/m/Y') }}</span></td>
                                <td data-label="Status">
                                    @php
                                        $statusLabels = [
                                            'Pending' => 'Menunggu',
                                            'Approved' => 'Disetujui',
                                            'Borrowed' => 'Dipinjam',
                                            'Overdue' => 'Terlambat',
                                            'Returned' => 'Dikembalikan',
                                            'CompletedWithLoss' => 'Selesai dengan kehilangan',
                'CompletedWithLoss' => 'Selesai + kehilangan',
                                            'Rejected' => 'Ditolak',
                                        ];
                                    @endphp
                                    <span class="admin-status {{ strtolower($borrowing->status) }}" aria-label="Status {{ $statusLabels[$borrowing->status] ?? $borrowing->status }}">{{ $statusLabels[$borrowing->status] ?? $borrowing->status }}</span>
                                </td>
                                <td data-label="Tindakan">
                                    <div class="admin-actions-cell">
                                        @if (in_array($borrowing->status, ['Borrowed', 'Overdue'], true))
                                            <a class="admin-btn small {{ $borrowing->status === 'Overdue' ? 'danger' : '' }}" href="{{ route('admin.borrowing.return.form', $borrowing) }}">Pengembalian</a>
                                            <a class="admin-btn small" href="{{ route('admin.borrowing.review', $borrowing) }}">Lihat detail</a>
                                        @else
                                            <a class="admin-btn small {{ $borrowing->status === 'Pending' ? 'primary' : '' }}" href="{{ route('admin.borrowing.review', $borrowing) }}">{{ $borrowing->status === 'Pending' ? 'Periksa pengajuan' : 'Lihat detail' }}</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="admin-pager"><span>Menampilkan {{ $borrowings->firstItem() }}–{{ $borrowings->lastItem() }} dari {{ $borrowings->total() }} pengajuan</span>{{ $borrowings->onEachSide(1)->links() }}</div>
        @endif
    </section>
@endsection


@push('styles')
<style>
.borrowing-quick-filters{display:flex;gap:7px;overflow-x:auto;padding:12px 15px 2px;scrollbar-width:thin}
.borrowing-quick-filter{display:inline-flex;flex:0 0 auto;align-items:center;min-height:31px;padding:0 12px;border:1px solid #e1e8f0;border-radius:999px;background:#fff;color:#65768b;font-size:9px;font-weight:800;text-decoration:none;transition:background .15s ease,border-color .15s ease,color .15s ease}
.borrowing-quick-filter:hover{border-color:#b9d9d1;background:#f5fbf9;color:#167d6b}
.borrowing-quick-filter.is-active{border-color:#b7ded4;background:#eaf8f3;color:#147b68}
.borrowing-quick-filter:focus-visible{outline:3px solid rgba(37,99,235,.3);outline-offset:2px}
.borrowing-results-heading{margin-top:8px}
.borrowing-register-table td[data-label="Tindakan"]{min-width:145px}
@media(max-width:760px){
 .borrowing-register-wrap{overflow:visible}
 .borrowing-register-table{display:block;min-width:0}
 .borrowing-register-table thead{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
 .borrowing-register-table tbody{display:grid;gap:10px;padding:10px}
 .borrowing-register-table tr{display:block;overflow:hidden;border:1px solid #e5ebf2;border-radius:11px;background:#fff;box-shadow:0 3px 12px rgba(20,42,69,.035)}
 .borrowing-register-table td{display:grid;grid-template-columns:minmax(104px,.38fr) minmax(0,1fr);gap:10px;padding:9px 11px;border-bottom:1px solid #eef2f6}
 .borrowing-register-table td:before{content:attr(data-label);color:#8795a7;font-size:8px;font-weight:850;letter-spacing:.35px;text-transform:uppercase}
 .borrowing-register-table td:last-child{border-bottom:0}
 .borrowing-register-table td[data-label="Tindakan"] .admin-actions-cell{justify-content:flex-start}
 .borrowing-register-table td[data-label="Alat"]{align-items:start}
 .borrowing-register-table td[data-label="Tindakan"]{min-width:0}
 .admin-pager{flex-wrap:wrap;gap:10px;padding:12px}
}
@media(max-width:420px){
 .borrowing-register-table td{grid-template-columns:1fr;gap:4px}
 .borrowing-register-table td:before{font-size:7px}
 .borrowing-quick-filters{padding-left:11px;padding-right:11px}
}
@media(prefers-reduced-motion:reduce){.borrowing-quick-filter{transition:none}}
</style>
@endpush
