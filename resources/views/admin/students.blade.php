@extends('layouts.admin')

@section('title', 'Manajemen Siswa')
@section('page-title', 'Manajemen Pengguna')
@section('page-description', 'Kelola akun siswa, identitas sekolah, dan akses ke OrbitFlow.')

@section('content')
    <section class="admin-page-intro">
        <div>
            <h2>Manajemen akun siswa</h2>
            <p>Buat akun, perbarui identitas, dan atur akses siswa. Menonaktifkan akun tidak menghapus riwayat peminjaman.</p>
        </div>
        <div class="admin-actions-cell admin-student-toolbar-actions">
            <a href="{{ route('admin.students.import.index') }}" class="admin-btn">↑ Impor CSV</a>
            <a href="{{ route('admin.students.create') }}" class="admin-btn primary">＋ Tambah siswa</a>
        </div>
    </section>

    <nav class="admin-account-tabs" aria-label="Kategori manajemen pengguna">
        <a href="{{ route('admin.students.index') }}" class="active">Siswa</a>
        <a href="{{ route('admin.students.import.index') }}">Impor CSV</a>
        @if (auth()->user()->isSuperAdmin())
            <a href="{{ route('admin.staff.index') }}">Admin &amp; Petugas</a>
        @endif
    </nav>

    <section class="admin-stat-grid" aria-label="Ringkasan akun siswa">
        <a class="admin-stat-link" href="{{ route('admin.students.index') }}" aria-label="Lihat semua akun siswa">
            <article class="admin-stat blue">
                <div class="admin-stat-top"><span class="admin-stat-label">Total siswa</span><span class="admin-stat-icon">♙</span></div>
                <div class="admin-stat-value">{{ number_format($studentCount) }}</div>
                <div class="admin-stat-note">Semua akun siswa terdaftar</div>
                <span class="admin-stat-hint">Lihat semua →</span>
            </article>
        </a>
        <a class="admin-stat-link" href="{{ route('admin.students.index', ['status' => 'active']) }}" aria-label="Lihat akun siswa aktif">
            <article class="admin-stat teal">
                <div class="admin-stat-top"><span class="admin-stat-label">Akun aktif</span><span class="admin-stat-icon">✓</span></div>
                <div class="admin-stat-value">{{ number_format($activeStudentCount) }}</div>
                <div class="admin-stat-note">Dapat masuk ke sistem</div>
                <span class="admin-stat-hint">Lihat akun aktif →</span>
            </article>
        </a>
        <a class="admin-stat-link" href="{{ route('admin.students.index', ['status' => 'pending']) }}" aria-label="Lihat akun siswa yang menunggu aktivasi">
            <article class="admin-stat violet">
                <div class="admin-stat-top"><span class="admin-stat-label">Menunggu aktivasi</span><span class="admin-stat-icon">◷</span></div>
                <div class="admin-stat-value">{{ number_format($pendingActivationCount) }}</div>
                <div class="admin-stat-note">Siswa perlu membuat kata sandi</div>
                <span class="admin-stat-hint">Lihat antrean aktivasi →</span>
            </article>
        </a>
        <a class="admin-stat-link" href="{{ route('admin.students.index', ['status' => 'inactive']) }}" aria-label="Lihat akun siswa nonaktif">
            <article class="admin-stat red">
                <div class="admin-stat-top"><span class="admin-stat-label">Akun nonaktif</span><span class="admin-stat-icon">!</span></div>
                <div class="admin-stat-value">{{ number_format($inactiveStudentCount) }}</div>
                <div class="admin-stat-note">Akses masuk dinonaktifkan</div>
                <span class="admin-stat-hint">Lihat akun nonaktif →</span>
            </article>
        </a>
    </section>

    <section class="admin-panel admin-section-space">
        <div class="admin-panel-head">
            <div><h3>Cari dan filter siswa</h3><p>Pencarian berdasarkan nama, email, NIS, atau kelas.</p></div>
        </div>
        <div class="admin-panel-body">
            <form method="GET" action="{{ route('admin.students.index') }}" class="admin-filter-form" data-live-filter data-live-target="#admin-student-results" data-live-debounce="180">
                <input class="admin-input search" type="search" name="q" value="{{ $search }}" placeholder="Cari nama, email, NIS, atau kelas siswa..." aria-label="Cari siswa berdasarkan nama, email, NIS, atau kelas" autocomplete="off">
                <select class="admin-select" name="status" aria-label="Filter status akun">
                    <option value="all" @selected($statusFilter === 'all')>Semua akun</option>
                    <option value="active" @selected($statusFilter === 'active')>Aktif</option>
                    <option value="pending" @selected($statusFilter === 'pending')>Menunggu aktivasi</option>
                    <option value="inactive" @selected($statusFilter === 'inactive')>Nonaktif</option>
                </select>
                <button type="submit" class="admin-btn primary js-live-filter-fallback">Terapkan</button>
                <a href="{{ route('admin.students.index') }}" class="admin-btn" data-live-reset>Reset</a>
            </form>
        </div>
    </section>

    <section class="admin-panel admin-section-space" id="admin-student-results" data-live-results>
        <div class="admin-panel-head">
            <div><h3>Daftar akun siswa</h3><p>Gunakan Edit untuk memperbarui identitas dan tombol status untuk mengatur akses.</p></div>
            <span class="admin-status active">{{ $students->total() }} hasil</span>
        </div>
        @if ($students->isEmpty())
            <div class="admin-panel-body"><div class="admin-empty">Tidak ada akun siswa yang cocok dengan pencarian atau filter.</div></div>
        @else
            <div class="admin-table-wrap admin-account-desktop">
                <table class="admin-table admin-account-table">
                    <thead>
                        <tr><th>Identitas siswa</th><th>NIS</th><th>Kelas</th><th>Status akun</th><th>Total pengajuan</th><th>Aktif</th><th>Terlambat</th><th>Bergabung</th><th>Aksi</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($students as $student)
                            <tr>
                                <td><span class="admin-primary-text">{{ $student->name }}</span><span class="admin-secondary-text">{{ $student->email }}</span></td>
                                <td>{{ $student->nis ?: '—' }}</td>
                                <td>{{ $student->class_name ?: '—' }}</td>
                                <td>
                                    @if ($student->is_active)
                                        <span class="admin-status available">Aktif</span>
                                    @elseif ($student->has_pending_activation)
                                        <span class="admin-status pending">Menunggu aktivasi</span>
                                    @else
                                        <span class="admin-status overdue">Nonaktif</span>
                                    @endif
                                </td>
                                <td><span class="admin-primary-text">{{ $student->borrowings_count }}</span></td>
                                <td><span class="admin-status active">{{ $student->active_borrowings_count }}</span></td>
                                <td><span class="admin-status {{ $student->overdue_borrowings_count > 0 ? 'overdue' : 'returned' }}">{{ $student->overdue_borrowings_count }}</span></td>
                                <td>{{ $student->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}</td>
                                <td>
                                    <div class="admin-actions-cell">
                                        <a href="{{ route('admin.students.edit', $student) }}" class="admin-btn small">Edit</a>
                                        @if ($student->is_active)
                                            <form method="POST" action="{{ route('admin.students.password-reset-link', $student) }}" onsubmit="return confirm('Terbitkan tautan reset kata sandi untuk siswa ini? Tautan hanya tampil sekali dan berlaku 30 menit.')">
                                                @csrf
                                                <button type="submit" class="admin-btn small">Reset sandi</button>
                                            </form>
                                        @endif
                                        @if ($student->has_pending_activation && ! $student->is_active)
                                            <form method="POST" action="{{ route('admin.students.activation.reissue', $student) }}" onsubmit="return confirm('Terbitkan tautan aktivasi baru? Tautan lama akan langsung tidak berlaku.')">
                                                @csrf
                                                <button type="submit" class="admin-btn small primary">Terbitkan tautan</button>
                                            </form>
                                        @else
                                            <form method="POST" action="{{ route('admin.students.toggle-status', $student) }}" onsubmit="return confirm('{{ $student->is_active ? 'Nonaktifkan akun siswa ini? Riwayat peminjaman tetap tersimpan.' : 'Aktifkan kembali akun siswa ini?' }}')">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="admin-btn small {{ $student->is_active ? 'danger' : 'primary' }}">{{ $student->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="admin-account-cards" aria-label="Daftar akun siswa dalam tampilan kartu">
                @foreach ($students as $student)
                    <article class="admin-account-card">
                        <header class="admin-account-card-head">
                            <div class="admin-account-card-identity">
                                <h4>{{ $student->name }}</h4>
                                <span>{{ $student->email }}</span>
                            </div>
                            @if ($student->is_active)
                                <span class="admin-status available">Aktif</span>
                            @elseif ($student->has_pending_activation)
                                <span class="admin-status pending">Menunggu aktivasi</span>
                            @else
                                <span class="admin-status overdue">Nonaktif</span>
                            @endif
                        </header>
                        <dl class="admin-account-card-details">
                            <div><dt>NIS</dt><dd>{{ $student->nis ?: '—' }}</dd></div>
                            <div><dt>Kelas</dt><dd>{{ $student->class_name ?: '—' }}</dd></div>
                            <div><dt>Bergabung</dt><dd>{{ $student->created_at->timezone('Asia/Jakarta')->format('d/m/Y') }}</dd></div>
                        </dl>
                        <div class="admin-account-card-metrics" aria-label="Ringkasan peminjaman siswa">
                            <div><span>Total pengajuan</span><strong>{{ $student->borrowings_count }}</strong></div>
                            <div><span>Aktif</span><strong>{{ $student->active_borrowings_count }}</strong></div>
                            <div><span>Terlambat</span><strong>{{ $student->overdue_borrowings_count }}</strong></div>
                        </div>
                        <div class="admin-account-card-actions" aria-label="Aksi untuk {{ $student->name }}">
                            <a href="{{ route('admin.students.edit', $student) }}" class="admin-btn small">Edit</a>
                            @if ($student->is_active)
                                <form method="POST" action="{{ route('admin.students.password-reset-link', $student) }}" onsubmit="return confirm('Terbitkan tautan reset kata sandi untuk siswa ini? Tautan hanya tampil sekali dan berlaku 30 menit.')">
                                    @csrf
                                    <button type="submit" class="admin-btn small">Reset sandi</button>
                                </form>
                            @endif
                            @if ($student->has_pending_activation && ! $student->is_active)
                                <form method="POST" action="{{ route('admin.students.activation.reissue', $student) }}" onsubmit="return confirm('Terbitkan tautan aktivasi baru? Tautan lama akan langsung tidak berlaku.')">
                                    @csrf
                                    <button type="submit" class="admin-btn small primary">Terbitkan tautan</button>
                                </form>
                            @else
                                <form method="POST" action="{{ route('admin.students.toggle-status', $student) }}" onsubmit="return confirm('{{ $student->is_active ? 'Nonaktifkan akun siswa ini? Riwayat peminjaman tetap tersimpan.' : 'Aktifkan kembali akun siswa ini?' }}')">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="admin-btn small {{ $student->is_active ? 'danger' : 'primary' }}">{{ $student->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            <div class="admin-pager"><span>Menampilkan {{ $students->firstItem() }}–{{ $students->lastItem() }} dari {{ $students->total() }} siswa</span>{{ $students->onEachSide(1)->links() }}</div>
        @endif
    </section>
@endsection
