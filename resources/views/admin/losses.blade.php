@extends('layouts.admin')

@section('title', 'Laporan Kehilangan')
@section('page-title', 'Laporan Kehilangan Alat')
@section('page-description', 'Periksa laporan kehilangan unit yang masih dipinjam sebelum memperbarui status inventaris.')

@section('content')
    <section class="admin-page-intro">
        <div><h2>Verifikasi kehilangan</h2><p>Konfirmasi laporan, tentukan tindak lanjut kompensasi, dan catat bila unit ditemukan kembali.</p></div>
        <div class="admin-actions"><a href="{{ route('admin.dashboard') }}" class="admin-btn">← Ringkasan operasional</a></div>
    </section>

    <section class="admin-stat-grid loss-stat-grid">
        <article class="admin-stat amber"><div class="admin-stat-top"><span class="admin-stat-label">Menunggu pemeriksaan</span><span class="admin-stat-icon">!</span></div><div class="admin-stat-value">{{ $pendingCount }}</div><div class="admin-stat-note">Belum mengubah status unit</div></article>
        <article class="admin-stat red"><div class="admin-stat-top"><span class="admin-stat-label">Menunggu tindak lanjut</span><span class="admin-stat-icon">×</span></div><div class="admin-stat-value">{{ $confirmedCount }}</div><div class="admin-stat-note">Laporan sudah dikonfirmasi</div></article>
        <article class="admin-stat red"><div class="admin-stat-top"><span class="admin-stat-label">Hilang permanen</span><span class="admin-stat-icon">!</span></div><div class="admin-stat-value">{{ $permanentLossCount }}</div><div class="admin-stat-note">Menunggu / sudah ada penyelesaian kompensasi</div></article>
        <article class="admin-stat blue"><div class="admin-stat-top"><span class="admin-stat-label">Ditolak</span><span class="admin-stat-icon">↩</span></div><div class="admin-stat-value">{{ $rejectedCount }}</div><div class="admin-stat-note">Unit tetap dalam alokasi pinjaman</div></article>
        <article class="admin-stat"><div class="admin-stat-top"><span class="admin-stat-label">Ditemukan kembali</span><span class="admin-stat-icon">✓</span></div><div class="admin-stat-value">{{ $recoveredCount }}</div><div class="admin-stat-note">Kondisi unit sudah diperiksa</div></article>
    </section>

    <section class="admin-panel admin-section-space">
        <div class="admin-panel-head"><div><h3>Daftar laporan</h3><p>Catatan kehilangan dan kompensasi disimpan untuk riwayat inventaris dan audit.</p></div></div>
        <div class="admin-panel-body">
            <form method="GET" action="{{ route('admin.losses.index') }}" class="admin-filter-form" data-live-filter data-live-target="#admin-loss-results" data-live-partial="true" data-live-debounce="120">
                <input class="admin-input search" type="search" name="q" value="{{ $search }}" placeholder="Cari siswa, kode unit, atau keterangan..." aria-label="Cari laporan kehilangan">
                <select class="admin-select" name="status" aria-label="Filter status laporan">
                    @foreach (['Dilaporkan' => 'Menunggu pemeriksaan', 'Semua' => 'Semua status', 'Dikonfirmasi' => 'Dikonfirmasi', 'Hilang Permanen' => 'Hilang permanen', 'Ditolak' => 'Ditolak', 'DitemukanKembali' => 'Ditemukan kembali'] as $value => $label)
                        <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="admin-btn primary js-live-filter-fallback" type="submit">Terapkan</button>
                <a class="admin-btn" href="{{ route('admin.losses.index') }}" data-live-reset>Reset</a>
            </form>
        </div>
        <div id="admin-loss-results" data-live-results aria-live="polite">
            @include('admin.partials.loss-results', ['losses' => $losses])
        </div>
    </section>
@endsection

@push('styles')
<style>
.loss-stat-grid{margin-bottom:14px}.loss-table{min-width:1120px}.loss-description{display:block;max-width:280px;white-space:normal;line-height:1.55}.loss-actions{display:grid;gap:7px;min-width:190px;margin-top:8px}.loss-actions form{display:grid;gap:5px}.loss-review-note{width:100%;min-width:0;min-height:31px;font-size:10px}.loss-table td[data-label="Status & tindak lanjut"]{min-width:270px}.loss-compensation{display:grid;gap:7px;margin-top:12px;padding-top:10px;border-top:1px solid #e8edf3}.loss-compensation-form{display:grid;gap:6px;padding:9px;border:1px solid #e2eaf2;border-radius:9px;background:#f8fafc}.loss-field-label{font-size:10px;font-weight:800;color:#607086}.loss-compensation-form .admin-input,.loss-compensation-form .admin-select{width:100%;min-width:0;font-size:11px}.loss-compensation-form textarea{min-height:60px}.loss-compensation-details{display:grid;gap:4px;font-size:11px;color:#526176;line-height:1.45}.loss-compensation-details strong{color:#26364b}
@media(max-width:760px){.loss-table-wrap{overflow:visible}.loss-table{display:block;min-width:0}.loss-table thead{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}.loss-table tbody{display:grid;gap:10px;padding:10px}.loss-table tr{display:block;overflow:hidden;border:1px solid #e5ebf2;border-radius:11px;background:#fff}.loss-table td{display:grid;grid-template-columns:minmax(105px,.38fr) minmax(0,1fr);gap:9px;padding:9px 11px;border-bottom:1px solid #eef2f6}.loss-table td:before{content:attr(data-label);color:#8795a7;font-size:8px;font-weight:850;text-transform:uppercase}.loss-table td:last-child{border-bottom:0}.loss-description{max-width:none}.loss-actions{min-width:0}.loss-compensation{min-width:0}.admin-pager{flex-wrap:wrap;gap:10px;padding:12px}}
@media(max-width:420px){.loss-table td{grid-template-columns:1fr;gap:4px}}
</style>
@endpush


@push('scripts')
<script>
(() => {
    'use strict';

    const syncCompensationForm = (form) => {
        const type = form.querySelector('[data-compensation-type]')?.value || '';
        const amountGroup = form.querySelector('[data-compensation-field="amount"]');
        const conditionGroup = form.querySelector('[data-compensation-field="condition"]');
        const amount = amountGroup?.querySelector('input');
        const condition = conditionGroup?.querySelector('select');

        if (amountGroup) amountGroup.hidden = type !== 'Bayar Kompensasi';
        if (conditionGroup) conditionGroup.hidden = type !== 'Barang Ditemukan';
        if (amount) amount.required = type === 'Bayar Kompensasi';
        if (condition) condition.required = type === 'Barang Ditemukan';
    };

    document.querySelectorAll('[data-compensation-plan]').forEach(syncCompensationForm);
    document.addEventListener('change', (event) => {
        if (!event.target.matches('[data-compensation-type]')) return;
        const form = event.target.closest('[data-compensation-plan]');
        if (form) syncCompensationForm(form);
    });

    document.addEventListener('submit', (event) => {
        const form = event.target.closest('[data-compensation-plan], [data-compensation-complete]');
        if (!form) return;
        if (!window.confirm('Simpan tindak lanjut kompensasi untuk laporan ini? Pastikan nominal dan catatan sudah sesuai keputusan sekolah.')) {
            event.preventDefault();
        }
    });
})();
</script>
@endpush
