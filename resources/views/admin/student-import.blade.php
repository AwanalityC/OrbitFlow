@extends('layouts.admin')

@section('title', 'Impor Siswa')
@section('page-title', 'Impor Siswa')
@section('page-description', 'Daftarkan banyak siswa sekaligus melalui file CSV sekolah.')

@section('content')
    <section class="admin-page-intro">
        <div>
            <h2>Impor data siswa</h2>
            <p>Unggah daftar siswa dari sekolah, periksa hasil validasi, lalu buat akun yang menunggu aktivasi.</p>
        </div>
        <a href="{{ route('admin.students.index') }}" class="admin-btn">← Kembali ke siswa</a>
    </section>

    <nav class="admin-account-tabs" aria-label="Kategori manajemen pengguna">
        <a href="{{ route('admin.students.index') }}">Siswa</a>
        <a href="{{ route('admin.students.import.index') }}" class="active">Impor CSV</a>
        @if (auth()->user()->isSuperAdmin())
            <a href="{{ route('admin.staff.index') }}">Admin &amp; Petugas</a>
        @endif
    </nav>

    <section class="admin-panel">
        <div class="admin-panel-head">
            <div><h3>1. Siapkan file CSV</h3><p>Gunakan template OrbitFlow agar nama kolom dan urutannya konsisten.</p></div>
            <a href="{{ route('admin.students.import.template') }}" class="admin-btn">↓ Unduh template CSV</a>
        </div>
        <div class="admin-panel-body">
            <div class="admin-import-columns">
                <div class="admin-info-box"><div class="admin-info-label">KOLOM WAJIB</div><div class="admin-info-value">nis, name, email, class_name</div></div>
                <div class="admin-info-box"><div class="admin-info-label">BATAS IMPOR</div><div class="admin-info-value">1.000 siswa / file · Maks. 2 MB</div></div>
            </div>
            <div class="admin-form-note admin-import-note">
                <strong>Perhatikan privasi data siswa.</strong> Unggah hanya file resmi yang diizinkan sekolah. NIS dan email harus unik. Baris yang tidak valid atau duplikat akan ditandai dan tidak ikut dibuat.
            </div>
        </div>
    </section>

    <section class="admin-panel admin-section-space">
        <div class="admin-panel-head"><div><h3>2. Unggah daftar siswa</h3><p>Format file CSV UTF-8 dengan pemisah koma atau titik koma.</p></div></div>
        <div class="admin-panel-body">
            <form method="POST" action="{{ route('admin.students.import.preview') }}" enctype="multipart/form-data" class="admin-account-form">
                @csrf
                <div class="admin-field">
                    <label for="csv_file">File CSV</label>
                    <input class="admin-input" id="csv_file" name="csv_file" type="file" accept=".csv,text/csv" required>
                    <small>File diproses secara privat. Maksimal 1.000 baris data dalam satu kali impor.</small>
                    @error('csv_file')<small class="admin-import-error">{{ $message }}</small>@enderror
                </div>
                <div class="admin-form-actions">
                    <a href="{{ route('admin.students.index') }}" class="admin-btn">Batal</a>
                    <button type="submit" class="admin-btn primary">Pratinjau data →</button>
                </div>
            </form>
        </div>
    </section>

    <section class="admin-panel admin-section-space">
        <div class="admin-panel-head"><div><h3>Bagaimana aktivasi bekerja?</h3><p>Akun dari impor tidak langsung dapat digunakan sebelum siswa mengaktifkannya.</p></div></div>
        <div class="admin-panel-body">
            <ol class="admin-import-steps">
                <li>OrbitFlow membuat akun siswa dengan status <strong>Menunggu aktivasi</strong>.</li>
                <li>Admin mengunduh file privat berisi tautan aktivasi unik untuk dibagikan kepada masing-masing siswa melalui saluran resmi sekolah.</li>
                <li>Siswa membuka tautan dan membuat kata sandi sendiri. Tautan hanya dapat dipakai sekali dan berlaku selama 7 hari.</li>
                <li>Jika tautan kedaluwarsa atau hilang, Petugas atau Super Admin dapat menerbitkan tautan baru dari direktori siswa.</li>
            </ol>
            <p class="admin-import-warning">OrbitFlow belum mengirim email otomatis. Jangan membagikan file tautan aktivasi di grup umum atau kanal yang tidak aman.</p>
        </div>
    </section>
@endsection
