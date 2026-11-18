@extends('layouts.admin')

@section('title', 'Ringkasan Operasional')
@section('page-title', 'Ringkasan Operasional')
@section('page-description', 'Pantau pengajuan, peminjaman aktif, dan kondisi inventaris sekolah.')

@section('content')
    <section class="admin-page-intro">
        <div>
            <h2>Selamat datang, {{ auth()->user()->name }}</h2>
            <p>Berikut hal-hal yang perlu diperhatikan dalam operasional hari ini. <strong>{{ now('Asia/Jakarta')->format('d/m/Y') }} WIB</strong></p>
        </div>
        <div class="admin-actions">
            <a href="{{ route('admin.borrowings.index', ['status' => 'pending']) }}" class="admin-btn primary">Lihat antrean pengajuan</a>
            <a href="{{ route('admin.equipment-units.index') }}" class="admin-btn">Kelola inventaris</a>
            <a href="{{ route('admin.losses.index') }}" class="admin-btn {{ $pendingLossCount > 0 ? 'primary' : '' }}">Laporan kehilangan ({{ $pendingLossCount }})</a>
        </div>
    </section>

    <section class="admin-stat-grid" aria-label="Ringkasan angka operasional">
        <a class="admin-stat-link" href="{{ route('admin.borrowings.index', ['status' => 'pending']) }}" aria-label="Buka antrean: {{ $pendingCount }} pengajuan menunggu persetujuan">
            <article class="admin-stat amber">
                <div class="admin-stat-top"><span class="admin-stat-label">Menunggu persetujuan</span><span class="admin-stat-icon" aria-hidden="true">≡</span></div>
                <div class="admin-stat-value">{{ $pendingCount }}</div>
                <div class="admin-stat-note">Pengajuan yang perlu diperiksa</div>
                <span class="admin-stat-hint">Buka antrean <span aria-hidden="true">↗</span></span>
            </article>
        </a>
        <a class="admin-stat-link" href="{{ route('admin.borrowings.index', ['status' => 'active']) }}" aria-label="Buka peminjaman aktif: {{ $activeCount }} peminjaman">
            <article class="admin-stat blue">
                <div class="admin-stat-top"><span class="admin-stat-label">Sedang dipinjam</span><span class="admin-stat-icon" aria-hidden="true">↔</span></div>
                <div class="admin-stat-value">{{ $activeCount }}</div>
                <div class="admin-stat-note">{{ $dueSoonCount }} jatuh tempo dalam 3 hari</div>
                <span class="admin-stat-hint">Lihat peminjaman <span aria-hidden="true">↗</span></span>
            </article>
        </a>
        <a class="admin-stat-link" href="{{ route('admin.borrowings.index', ['status' => 'Overdue']) }}" aria-label="Buka peminjaman terlambat: {{ $overdueCount }} peminjaman">
            <article class="admin-stat red">
                <div class="admin-stat-top"><span class="admin-stat-label">Terlambat</span><span class="admin-stat-icon" aria-hidden="true">!</span></div>
                <div class="admin-stat-value">{{ $overdueCount }}</div>
                <div class="admin-stat-note">Perlu tindak lanjut pengembalian</div>
                <span class="admin-stat-hint">Lihat yang terlambat <span aria-hidden="true">↗</span></span>
            </article>
        </a>
        <a class="admin-stat-link" href="{{ route('admin.equipment-units.index') }}" aria-label="Buka inventaris: {{ $availableUnits }} dari {{ $totalUnits }} unit tersedia">
            <article class="admin-stat teal">
                <div class="admin-stat-top"><span class="admin-stat-label">Unit tersedia</span><span class="admin-stat-icon" aria-hidden="true">▣</span></div>
                <div class="admin-stat-value">{{ $availableUnits }}<small class="admin-stat-denominator"> / {{ $totalUnits }}</small></div>
                <div class="admin-stat-note">Dari seluruh unit yang terdata</div>
                <span class="admin-stat-hint">Buka inventaris <span aria-hidden="true">↗</span></span>
            </article>
        </a>
    </section>

    <section class="admin-overview-strip" aria-label="Data keseluruhan">
        <div class="admin-overview-item">
            <span class="admin-overview-icon" aria-hidden="true">▤</span>
            <div><strong>{{ number_format($totalBorrowings) }}</strong><span>Total pengajuan tercatat</span></div>
        </div>
        <div class="admin-overview-divider" aria-hidden="true"></div>
        <div class="admin-overview-item">
            <span class="admin-overview-icon students" aria-hidden="true">♙</span>
            <div><strong>{{ number_format($studentCount) }}</strong><span>Siswa terdaftar</span></div>
        </div>
    </section>

    <section class="admin-grid-main">
        <div class="admin-stack">
            <section class="admin-panel" id="antrean-pengajuan">
                <div class="admin-panel-head">
                    <div><h3>Antrean pengajuan</h3><p>Pengajuan terbaru yang belum diputuskan admin.</p></div>
                    <a class="admin-panel-link" href="{{ route('admin.borrowings.index', ['status' => 'pending']) }}">Lihat semua →</a>
                </div>
                @if ($pendingBorrowings->isEmpty())
                    <div class="admin-panel-body"><div class="admin-empty">Tidak ada pengajuan yang menunggu persetujuan. Antrean sudah bersih.</div></div>
                @else
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Pengajuan / Waktu</th><th>Peminjam</th><th>Alat diminta</th><th>Periode</th><th>Aksi</th></tr></thead>
                            <tbody>
                                @foreach ($pendingBorrowings as $borrowing)
                                    <tr>
                                        <td><span class="admin-primary-text">OF-{{ str_pad($borrowing->id, 5, '0', STR_PAD_LEFT) }}</span><span class="admin-secondary-text">{{ $borrowing->created_at->timezone('Asia/Jakarta')->format('d/m/Y, H:i') }} WIB</span></td>
                                        <td><span class="admin-primary-text">{{ $borrowing->user->name }}</span><span class="admin-secondary-text">{{ $borrowing->user->email }}</span></td>
                                        <td>@foreach ($borrowing->items as $item)<span>{{ $item->equipment->name }} × {{ $item->quantity }}</span>@if(!$loop->last)<br>@endif @endforeach</td>
                                        <td>{{ $borrowing->start_date->format('d/m/Y') }}<span class="admin-secondary-text">s.d. {{ $borrowing->end_date->format('d/m/Y') }}</span></td>
                                        <td><a class="admin-btn primary small" href="{{ route('admin.borrowing.review', $borrowing) }}">Periksa</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="admin-panel" id="pengembalian">
                <div class="admin-panel-head">
                    <div><h3>Peminjaman aktif &amp; jatuh tempo</h3><p>Prioritas ditampilkan berdasarkan keterlambatan dan tanggal kembali.</p></div>
                    <a class="admin-panel-link" href="{{ route('admin.borrowings.index', ['status' => 'active']) }}">Semua peminjaman →</a>
                </div>
                @if ($activeBorrowings->isEmpty())
                    <div class="admin-panel-body"><div class="admin-empty">Belum ada alat yang sedang dipinjam.</div></div>
                @else
                    <div class="admin-table-wrap">
                        <table class="admin-table">
                            <thead><tr><th>Peminjam</th><th>Alat / Unit aktif</th><th>Batas pengembalian</th><th>Status</th><th>Aksi</th></tr></thead>
                            <tbody>
                                @foreach ($activeBorrowings as $borrowing)
                                    @php $daysUntilDue = today()->diffInDays($borrowing->end_date, false); @endphp
                                    <tr>
                                        <td><span class="admin-primary-text">{{ $borrowing->user->name }}</span><span class="admin-secondary-text">OF-{{ str_pad($borrowing->id, 5, '0', STR_PAD_LEFT) }}</span></td>
                                        <td>@foreach ($borrowing->items as $item)<span>{{ $item->equipment->name }} × {{ $item->activeUnits->count() }}</span>@if(!$loop->last)<br>@endif @endforeach</td>
                                        <td><span class="admin-primary-text">{{ $borrowing->end_date->format('d/m/Y') }}</span><span class="admin-secondary-text">@if($borrowing->status === 'Overdue')Terlambat {{ abs($daysUntilDue) }} hari @elseif($daysUntilDue === 0)Jatuh tempo hari ini @else{{ $daysUntilDue }} hari lagi @endif</span></td>
                                        <td><span class="admin-status {{ strtolower($borrowing->status) }}">{{ $borrowing->status }}</span></td>
                                        <td><a class="admin-btn small {{ $borrowing->status === 'Overdue' ? 'danger' : '' }}" href="{{ route('admin.borrowing.return.form', $borrowing) }}">Proses kembali</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <aside class="admin-stack">
            <section class="admin-panel">
                <div class="admin-panel-head"><div><h3>Akses cepat</h3><p>Pintasan sesuai hak akses akun ini.</p></div></div>
                <div class="admin-panel-body">
                    <div class="admin-shortcut-grid">
                        <a class="admin-shortcut" href="{{ route('admin.borrowings.index', ['status' => 'pending']) }}"><span class="admin-shortcut-icon">≡</span><span><strong>Periksa pengajuan</strong><small>Setujui atau tolak</small></span></a>
                        <a class="admin-shortcut" href="{{ route('admin.borrowings.index', ['status' => 'active']) }}"><span class="admin-shortcut-icon">↩</span><span><strong>Pengembalian</strong><small>Pantau alat dipinjam</small></span></a>
                        <a class="admin-shortcut" href="{{ route('admin.losses.index') }}"><span class="admin-shortcut-icon">!</span><span><strong>Laporan kehilangan</strong><small>{{ $pendingLossCount }} laporan menunggu</small></span></a>
                        <a class="admin-shortcut" href="{{ route('admin.equipment-units.index') }}"><span class="admin-shortcut-icon">▣</span><span><strong>Inventaris</strong><small>Unit dan kondisi alat</small></span></a>
                        <a class="admin-shortcut" href="{{ route('admin.students.index') }}"><span class="admin-shortcut-icon">♙</span><span><strong>Data peminjam</strong><small>Daftar siswa</small></span></a>
                        @if (auth()->user()->isSuperAdmin())
                            <a class="admin-shortcut" href="{{ route('admin.staff.index') }}"><span class="admin-shortcut-icon">♧</span><span><strong>Manajemen staf</strong><small>Petugas dan admin</small></span></a>
                            <a class="admin-shortcut" href="{{ route('admin.backups.index') }}"><span class="admin-shortcut-icon">▣</span><span><strong>Cadangan sistem</strong><small>Backup dan verifikasi</small></span></a>
                        @endif
                    </div>
                </div>
            </section>

            <section class="admin-panel">
                <div class="admin-panel-head"><div><h3>Kesehatan inventaris</h3><p>Ringkasan unit yang memerlukan perhatian.</p></div><a class="admin-panel-link" href="{{ route('admin.equipment-units.index') }}">Kelola →</a></div>
                <div class="admin-panel-body">
                    <div class="admin-alert-list">
                        <div class="admin-alert-item"><span class="admin-alert-mark">!</span><div class="admin-alert-copy"><strong>{{ $repairUnits }} unit memiliki catatan kerusakan</strong><span>Rusak ringan masih dapat dipinjam dengan prioritas kedua. Rusak berat tidak tersedia.</span></div></div>
                        <div class="admin-alert-item"><span class="admin-alert-mark red">×</span><div class="admin-alert-copy"><strong>{{ $lostUnits }} unit berstatus hilang</strong><span>Pastikan status dan tindak lanjut unit sudah diperiksa.</span></div></div>
                        <div class="admin-alert-item"><span class="admin-alert-mark">↗</span><div class="admin-alert-copy"><strong>{{ $pendingCompensationCount }} catatan kompensasi menunggu tindak lanjut</strong><span>Termasuk penggantian dan pembayaran yang belum diselesaikan. Nominal mengikuti keputusan sekolah.</span></div></div>
                    </div>
                </div>
            </section>

            @if ($pendingCompensations->isNotEmpty())
                <section class="admin-panel">
                    <div class="admin-panel-head"><div><h3>Catatan tindak lanjut</h3><p>Kompensasi yang masih menunggu keputusan, pembayaran, atau penggantian.</p></div></div>
                    <div class="admin-panel-body">
                        <div class="admin-alert-list">
                            @foreach ($pendingCompensations as $compensation)
                                <div class="admin-alert-item">
                                    <span class="admin-alert-mark">!</span>
                                    <div class="admin-alert-copy"><strong>{{ $compensation->type }} · {{ $compensation->user->name }}</strong><span>{{ $compensation->loss?->equipmentUnit?->unit_code ? 'Unit ' . $compensation->loss->equipmentUnit->unit_code . ' · ' : '' }}{{ $compensation->status }}@if($compensation->resolution_type) · {{ $compensation->resolution_type }}@endif @if($compensation->amount !== null) · Rp{{ number_format((float) $compensation->amount, 2, ',', '.') }}@endif</span></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </section>
            @endif
        </aside>
    </section>
@endsection
