@extends('layouts.admin')

@section('title', 'Hasil Impor Siswa')
@section('page-title', 'Hasil Impor Siswa')
@section('page-description', 'Ringkasan pembuatan akun dan distribusi tautan aktivasi.')

@section('content')
    <section class="admin-page-intro">
        <div>
            <h2>Impor selesai</h2>
            <p>Akun telah dibuat. Siswa belum dapat masuk sampai mereka menyelesaikan aktivasi.</p>
        </div>
        <span class="admin-status available">Proses selesai</span>
    </section>

    <section class="admin-stat-grid admin-import-stat-grid">
        <article class="admin-stat teal"><div class="admin-stat-top"><span class="admin-stat-label">Akun dibuat</span><span class="admin-stat-icon">✓</span></div><div class="admin-stat-value">{{ number_format($result['created_count']) }}</div><div class="admin-stat-note">Menunggu aktivasi siswa</div></article>
        <article class="admin-stat red"><div class="admin-stat-top"><span class="admin-stat-label">Baris dilewati</span><span class="admin-stat-icon">!</span></div><div class="admin-stat-value">{{ number_format($result['skipped_count']) }}</div><div class="admin-stat-note">Tidak valid atau terdeteksi duplikat</div></article>
        <article class="admin-stat blue"><div class="admin-stat-top"><span class="admin-stat-label">Batas aktivasi</span><span class="admin-stat-icon">◷</span></div><div class="admin-stat-value admin-import-date">{{ $result['expires_at'] }}</div><div class="admin-stat-note">Batas tautan pada impor ini</div></article>
    </section>

    @if ($canDownload)
        <section class="admin-panel admin-section-space">
            <div class="admin-panel-head"><div><h3>Tautan aktivasi siswa</h3><p>Unduh file ini dan bagikan setiap tautan kepada siswa yang sesuai melalui saluran resmi dan privat sekolah.</p></div></div>
            <div class="admin-panel-body">
                <div class="admin-form-note admin-import-note"><strong>File ini hanya dapat diunduh sekali.</strong> Demi keamanan, OrbitFlow tidak menyimpan tautan asli setelah file diunduh. Jika file hilang atau tautan kedaluwarsa, terbitkan tautan baru dari direktori siswa.</div>
                <div class="admin-form-actions admin-import-result-actions">
                    <a href="{{ route('admin.students.index', ['status' => 'pending']) }}" class="admin-btn">Lihat akun menunggu aktivasi</a>
                    <form method="POST" action="{{ route('admin.students.import.result.download') }}">
                        @csrf
                        <button type="submit" class="admin-btn primary">↓ Unduh tautan aktivasi CSV</button>
                    </form>
                </div>
            </div>
        </section>
    @elseif ($result['created_count'] > 0)
        <div class="admin-import-warning admin-section-space">Sesi unduhan sudah berakhir atau file tautan telah diunduh. Untuk siswa yang masih menunggu aktivasi, terbitkan tautan baru dari direktori siswa.</div>
    @else
        <div class="admin-form-note admin-section-space">Tidak ada akun baru yang dibuat. Periksa kembali file CSV dan data yang dilewati.</div>
    @endif

    <div class="admin-form-actions admin-section-space">
        <a href="{{ route('admin.students.import.index') }}" class="admin-btn">Impor file lain</a>
        <a href="{{ route('admin.students.index') }}" class="admin-btn primary">Kembali ke direktori siswa</a>
    </div>
@endsection
