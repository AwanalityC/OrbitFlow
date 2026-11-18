@extends('layouts.admin')

@section('title', 'Admin & Petugas')
@section('page-title', 'Manajemen Pengguna')
@section('page-description', 'Kelola akun Super Admin dan Petugas Inventaris.')

@section('content')
    <section class="admin-page-intro">
        <div>
            <h2>Manajemen akun admin &amp; petugas</h2>
            <p>Halaman ini hanya dapat diakses Super Admin. Setiap staf harus menggunakan akun pribadinya sendiri.</p>
        </div>
        <a href="{{ route('admin.staff.create') }}" class="admin-btn primary">＋ Tambah akun staf</a>
    </section>

    <nav class="admin-account-tabs" aria-label="Kategori manajemen pengguna">
        <a href="{{ route('admin.students.index') }}">Siswa</a>
        <a href="{{ route('admin.staff.index') }}" class="active">Admin &amp; Petugas</a>
    </nav>

    <section class="admin-stat-grid" aria-label="Ringkasan akun staf">
        <a class="admin-stat-link" href="{{ route('admin.staff.index') }}" aria-label="Lihat semua akun staf">
            <article class="admin-stat blue"><div class="admin-stat-top"><span class="admin-stat-label">Total staf</span><span class="admin-stat-icon">♙</span></div><div class="admin-stat-value">{{ number_format($staffCount) }}</div><div class="admin-stat-note">Semua akun administratif</div><span class="admin-stat-hint">Lihat semua →</span></article>
        </a>
        <a class="admin-stat-link" href="{{ route('admin.staff.index', ['status' => 'active']) }}" aria-label="Lihat akun staf aktif">
            <article class="admin-stat teal"><div class="admin-stat-top"><span class="admin-stat-label">Akun aktif</span><span class="admin-stat-icon">✓</span></div><div class="admin-stat-value">{{ number_format($activeStaffCount) }}</div><div class="admin-stat-note">Dapat masuk ke sistem</div><span class="admin-stat-hint">Lihat akun aktif →</span></article>
        </a>
        <a class="admin-stat-link" href="{{ route('admin.staff.index', ['status' => 'inactive']) }}" aria-label="Lihat akun staf nonaktif">
            <article class="admin-stat red"><div class="admin-stat-top"><span class="admin-stat-label">Akun nonaktif</span><span class="admin-stat-icon">!</span></div><div class="admin-stat-value">{{ number_format($inactiveStaffCount) }}</div><div class="admin-stat-note">Akses masuk dinonaktifkan</div><span class="admin-stat-hint">Lihat akun nonaktif →</span></article>
        </a>
        <a class="admin-stat-link" href="{{ route('admin.staff.index', ['role' => 'super_admin', 'status' => 'active']) }}" aria-label="Lihat Super Admin aktif">
            <article class="admin-stat violet"><div class="admin-stat-top"><span class="admin-stat-label">Super Admin aktif</span><span class="admin-stat-icon">◆</span></div><div class="admin-stat-value">{{ number_format($superAdminCount) }}</div><div class="admin-stat-note">Minimal satu akun harus tetap aktif</div><span class="admin-stat-hint">Lihat Super Admin →</span></article>
        </a>
    </section>

    <section class="admin-panel admin-section-space">
        <div class="admin-panel-head"><div><h3>Cari dan filter staf</h3><p>Cari berdasarkan nama atau email dan filter berdasarkan peran/status.</p></div></div>
        <div class="admin-panel-body">
            <form method="GET" action="{{ route('admin.staff.index') }}" class="admin-filter-form" data-live-filter data-live-target="#admin-staff-results" data-live-debounce="180">
                <input class="admin-input search" type="search" name="q" value="{{ $search }}" placeholder="Cari nama atau email staf..." aria-label="Cari staf berdasarkan nama atau email" autocomplete="off">
                <select class="admin-select" name="role" aria-label="Filter peran">
                    <option value="all" @selected($roleFilter === 'all')>Semua peran</option>
                    <option value="super_admin" @selected($roleFilter === 'super_admin')>Super Admin</option>
                    <option value="petugas" @selected($roleFilter === 'petugas')>Petugas</option>
                </select>
                <select class="admin-select" name="status" aria-label="Filter status akun">
                    <option value="all" @selected($statusFilter === 'all')>Semua status</option>
                    <option value="active" @selected($statusFilter === 'active')>Aktif</option>
                    <option value="inactive" @selected($statusFilter === 'inactive')>Nonaktif</option>
                </select>
                <button type="submit" class="admin-btn primary js-live-filter-fallback">Terapkan</button>
                <a href="{{ route('admin.staff.index') }}" class="admin-btn" data-live-reset>Reset</a>
            </form>
        </div>
    </section>

    <section class="admin-panel admin-section-space" id="admin-staff-results" data-live-results>
        <div class="admin-panel-head"><div><h3>Daftar akun staf</h3><p>Akun yang sedang digunakan tidak dapat dinonaktifkan oleh pemiliknya sendiri.</p></div><span class="admin-status active">{{ $staffMembers->total() }} hasil</span></div>
        @if ($staffMembers->isEmpty())
            <div class="admin-panel-body"><div class="admin-empty">Tidak ada akun staf yang cocok dengan pencarian atau filter.</div></div>
        @else
            <div class="admin-table-wrap admin-account-desktop">
                <table class="admin-table admin-account-table">
                    <thead><tr><th>Identitas</th><th>Peran</th><th>Status</th><th>Bergabung</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($staffMembers as $member)
                            <tr>
                                <td><span class="admin-primary-text">{{ $member->name }}</span><span class="admin-secondary-text">{{ $member->email }}@if($member->id === auth()->id()) · Akun Anda @endif</span></td>
                                <td><span class="admin-status {{ $member->isSuperAdmin() ? 'active' : 'available' }}">{{ $member->role_label }}</span></td>
                                <td><span class="admin-status {{ $member->is_active ? 'available' : 'overdue' }}">{{ $member->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td>{{ $member->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}</td>
                                <td>
                                    <div class="admin-actions-cell">
                                        <a href="{{ route('admin.staff.edit', $member) }}" class="admin-btn small">Edit</a>
                                        @if ($member->role === \App\Models\User::ROLE_PETUGAS && $member->is_active)
                                            <form method="POST" action="{{ route('admin.staff.password-reset-link', $member) }}" onsubmit="return confirm('Terbitkan tautan reset kata sandi untuk petugas ini? Tautan hanya tampil sekali dan berlaku 30 menit.')">
                                                @csrf
                                                <button type="submit" class="admin-btn small">Reset sandi</button>
                                            </form>
                                        @endif
                                        @if ($member->id !== auth()->id())
                                            <form method="POST" action="{{ route('admin.staff.toggle-status', $member) }}" onsubmit="return confirm('{{ $member->is_active ? 'Nonaktifkan akun staf ini?' : 'Aktifkan kembali akun staf ini?' }}')">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="admin-btn small {{ $member->is_active ? 'danger' : 'primary' }}">{{ $member->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                            </form>
                                        @else
                                            <span class="admin-secondary-text">Akun saat ini</span>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="admin-account-cards" aria-label="Daftar akun staf dalam tampilan kartu">
                @foreach ($staffMembers as $member)
                    <article class="admin-account-card">
                        <header class="admin-account-card-head">
                            <div class="admin-account-card-identity">
                                <h4>{{ $member->name }}</h4>
                                <span>{{ $member->email }}@if($member->id === auth()->id()) · Akun Anda @endif</span>
                            </div>
                            <div class="admin-account-card-badges">
                                <span class="admin-status {{ $member->isSuperAdmin() ? 'active' : 'available' }}">{{ $member->role_label }}</span>
                                <span class="admin-status {{ $member->is_active ? 'available' : 'overdue' }}">{{ $member->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                            </div>
                        </header>
                        <dl class="admin-account-card-details staff-details">
                            <div><dt>Peran</dt><dd>{{ $member->role_label }}</dd></div>
                            <div><dt>Bergabung</dt><dd>{{ $member->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}</dd></div>
                        </dl>
                        <div class="admin-account-card-actions" aria-label="Aksi untuk {{ $member->name }}">
                            <a href="{{ route('admin.staff.edit', $member) }}" class="admin-btn small">Edit</a>
                            @if ($member->role === \App\Models\User::ROLE_PETUGAS && $member->is_active)
                                <form method="POST" action="{{ route('admin.staff.password-reset-link', $member) }}" onsubmit="return confirm('Terbitkan tautan reset kata sandi untuk petugas ini? Tautan hanya tampil sekali dan berlaku 30 menit.')">
                                    @csrf
                                    <button type="submit" class="admin-btn small">Reset sandi</button>
                                </form>
                            @endif
                            @if ($member->id !== auth()->id())
                                <form method="POST" action="{{ route('admin.staff.toggle-status', $member) }}" onsubmit="return confirm('{{ $member->is_active ? 'Nonaktifkan akun staf ini?' : 'Aktifkan kembali akun staf ini?' }}')">
                                    @csrf @method('PATCH')
                                    <button type="submit" class="admin-btn small {{ $member->is_active ? 'danger' : 'primary' }}">{{ $member->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                            @else
                                <span class="admin-secondary-text">Akun saat ini</span>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="admin-pager"><span>Menampilkan {{ $staffMembers->firstItem() }}–{{ $staffMembers->lastItem() }} dari {{ $staffMembers->total() }} akun staf</span>{{ $staffMembers->onEachSide(1)->links() }}</div>
        @endif
    </section>

    <div class="admin-form-note admin-section-space"><strong>Pengaman Super Admin:</strong> akun yang sedang digunakan tidak dapat menonaktifkan dirinya sendiri. Sistem juga mencegah perubahan yang membuat jumlah Super Admin aktif menjadi nol.</div>
@endsection
