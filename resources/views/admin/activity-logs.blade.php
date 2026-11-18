@extends('layouts.admin')

@section('title', 'Log Aktivitas')
@section('page-title', 'Log Aktivitas')
@section('page-description', 'Rekam jejak tindakan administratif OrbitFlow.')

@section('content')
    <section class="admin-page-intro">
        <div>
            <h2>Jejak aktivitas administratif</h2>
            <p>
                @if ($isSuperAdmin)
                    Lihat seluruh tindakan administratif yang tercatat di OrbitFlow.
                @else
                    Lihat tindakan yang dilakukan menggunakan akun Anda. Catatan staf lain tidak dapat diakses.
                @endif
            </p>
        </div>
        <span class="admin-status active">Catatan tidak dapat diedit atau dihapus</span>
    </section>

    <section class="admin-stat-grid activity-stat-grid" aria-label="Ringkasan log aktivitas">
        <article class="admin-stat blue"><div class="admin-stat-top"><span class="admin-stat-label">Aktivitas hari ini</span><span class="admin-stat-icon">◷</span></div><div class="admin-stat-value">{{ number_format($todayCount) }}</div><div class="admin-stat-note">Berdasarkan waktu WIB</div></article>
        <article class="admin-stat teal"><div class="admin-stat-top"><span class="admin-stat-label">Total catatan terlihat</span><span class="admin-stat-icon">≡</span></div><div class="admin-stat-value">{{ number_format($totalVisible) }}</div><div class="admin-stat-note">Sesuai cakupan akses akun</div></article>
        <article class="admin-stat violet"><div class="admin-stat-top"><span class="admin-stat-label">Kategori aktivitas</span><span class="admin-stat-icon">▦</span></div><div class="admin-stat-value">{{ number_format($categoryCount) }}</div><div class="admin-stat-note">Kategori dengan catatan</div></article>
    </section>

    <section class="admin-panel admin-section-space">
        <div class="admin-panel-head"><div><h3>Cari dan filter aktivitas</h3><p>Cari berdasarkan pelaku, ringkasan tindakan, atau objek yang terdampak.</p></div></div>
        <div class="admin-panel-body">
            <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="admin-filter-form activity-filter-form" data-live-filter data-live-target="#admin-activity-results" data-live-debounce="180">
                <input class="admin-input search" type="search" name="q" value="{{ $search }}" placeholder="Cari aktivitas, nama staf, atau objek...">
                <select class="admin-select" name="category" aria-label="Kategori aktivitas">
                    <option value="all" @selected($category === 'all')>Semua kategori</option>
                    <option value="borrowing" @selected($category === 'borrowing')>Peminjaman</option>
                    <option value="returns" @selected($category === 'returns')>Pengembalian</option>
                    <option value="inventory" @selected($category === 'inventory')>Inventaris</option>
                    <option value="accounts" @selected($category === 'accounts')>Akun</option>
                    <option value="imports" @selected($category === 'imports')>Impor data</option>
                    <option value="security" @selected($category === 'security')>Keamanan</option>
                    <option value="backup" @selected($category === 'backup')>Cadangan &amp; Pemulihan</option>
                </select>
                @if ($isSuperAdmin)
                    <select class="admin-select" name="actor_id" aria-label="Filter pelaku">
                        <option value="">Semua pelaku</option>
                        @foreach ($actors as $actor)
                            <option value="{{ $actor->actor_user_id }}" @selected((string) request('actor_id') === (string) $actor->actor_user_id)>{{ $actor->actor_name }}{{ $actor->actor_email ? ' · ' . $actor->actor_email : '' }}</option>
                        @endforeach
                    </select>
                @endif
                <input class="admin-input activity-date-input" type="date" name="from" value="{{ request('from') }}" aria-label="Dari tanggal">
                <input class="admin-input activity-date-input" type="date" name="to" value="{{ request('to') }}" aria-label="Sampai tanggal">
                <button type="submit" class="admin-btn primary js-live-filter-fallback">Terapkan</button>
                <a href="{{ route('admin.activity-logs.index') }}" class="admin-btn" data-live-reset>Reset</a>
            </form>
        </div>
    </section>

    <section class="admin-panel admin-section-space" id="admin-activity-results" data-live-results>
        <div class="admin-panel-head"><div><h3>Riwayat aktivitas</h3><p>Waktu ditampilkan dalam WIB. Rincian perubahan tersedia pada setiap catatan.</p></div><span class="admin-status active">{{ $activityLogs->total() }} catatan</span></div>
        @if ($activityLogs->isEmpty())
            <div class="admin-panel-body"><div class="admin-empty">Belum ada aktivitas yang cocok dengan filter ini.</div></div>
        @else
            <div class="admin-table-wrap activity-log-desktop">
                <table class="admin-table activity-log-table">
                    <thead><tr><th>Waktu (WIB)</th><th>Pelaku</th><th>Aktivitas</th><th>Kategori</th><th>Objek</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($activityLogs as $activity)
                            <tr>
                                <td><span class="admin-primary-text">{{ $activity->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}</span><span class="admin-secondary-text">{{ $activity->created_at->timezone('Asia/Jakarta')->format('H:i:s') }} WIB</span></td>
                                <td><span class="admin-primary-text">{{ $activity->actor_name }}</span><span class="admin-secondary-text">{{ $activity->actor_role_label }}</span></td>
                                <td><span class="admin-primary-text">{{ $activity->event_label }}</span><span class="admin-secondary-text">{{ $activity->summary }}</span></td>
                                <td><span class="admin-status available">{{ $activity->category_label }}</span></td>
                                <td>{{ $activity->subject_label ?: '—' }}</td>
                                <td><a href="{{ route('admin.activity-logs.show', $activity) }}" class="admin-btn small">Detail</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="activity-log-cards" aria-label="Daftar aktivitas versi layar kecil">
                @foreach ($activityLogs as $activity)
                    <article class="activity-log-card">
                        <div class="activity-log-card-top">
                            <div class="activity-log-card-time">
                                <strong>{{ $activity->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}</strong>
                                <span>{{ $activity->created_at->timezone('Asia/Jakarta')->format('H:i:s') }} WIB</span>
                            </div>
                            <span class="admin-status available">{{ $activity->category_label }}</span>
                        </div>
                        <h4>{{ $activity->event_label }}</h4>
                        <p class="activity-log-card-summary">{{ $activity->summary }}</p>
                        <dl class="activity-log-card-meta">
                            <div><dt>Pelaku</dt><dd>{{ $activity->actor_name }} · {{ $activity->actor_role_label }}</dd></div>
                            <div><dt>Objek</dt><dd>{{ $activity->subject_label ?: '—' }}</dd></div>
                        </dl>
                        <a href="{{ route('admin.activity-logs.show', $activity) }}" class="admin-btn small activity-log-card-action">Lihat detail aktivitas</a>
                    </article>
                @endforeach
            </div>
            <div class="admin-pager"><span>Menampilkan {{ $activityLogs->firstItem() }}–{{ $activityLogs->lastItem() }} dari {{ $activityLogs->total() }} catatan</span>{{ $activityLogs->onEachSide(1)->links() }}</div>
        @endif
    </section>

    <div class="admin-form-note admin-section-space"><strong>Perlindungan catatan:</strong> log ini bersifat hanya-tambah melalui aplikasi. Kata sandi, token aktivasi, token reset, dan tautan rahasia tidak disimpan di dalam catatan.</div>
@endsection
