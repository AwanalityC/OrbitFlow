@extends('layouts.admin')

@section('title', $isEditing ? 'Edit Siswa' : 'Tambah Siswa')
@section('page-title', 'Manajemen Pengguna')
@section('page-description', 'Kelola identitas akun siswa OrbitFlow.')

@section('content')
    <section class="admin-page-intro">
        <div>
            <h2>{{ $isEditing ? 'Edit informasi siswa' : 'Buat akun siswa' }}</h2>
            <p>{{ $isEditing ? 'Perubahan identitas tidak menghapus atau mengubah riwayat peminjaman.' : 'Buat akun untuk siswa yang identitas sekolahnya sudah diverifikasi.' }}</p>
        </div>
        <a href="{{ route('admin.students.index') }}" class="admin-btn">← Kembali ke daftar siswa</a>
    </section>

    <nav class="admin-account-tabs" aria-label="Kategori manajemen pengguna">
        <a href="{{ route('admin.students.index') }}" class="active">Siswa</a>
        @if (auth()->user()->isSuperAdmin())
            <a href="{{ route('admin.staff.index') }}">Admin &amp; Petugas</a>
        @endif
    </nav>

    <section class="admin-panel">
        <div class="admin-panel-head"><div><h3>{{ $isEditing ? 'Informasi akun' : 'Informasi siswa baru' }}</h3><p>Pastikan NIS dan kelas sesuai dengan data resmi sekolah.</p></div></div>
        <div class="admin-panel-body">
            <form method="POST" action="{{ $isEditing ? route('admin.students.update', $student) : route('admin.students.store') }}" class="admin-account-form">
                @csrf
                @if ($isEditing) @method('PUT') @endif
                <div class="admin-form-grid">
                    <div class="admin-field">
                        <label for="name">Nama lengkap</label>
                        <input class="admin-input" id="name" name="name" type="text" maxlength="255" value="{{ old('name', $student->name) }}" required autocomplete="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                        @error('name')<small class="admin-field-error" role="alert">{{ $message }}</small>@enderror
                    </div>
                    <div class="admin-field">
                        <label for="email">Email akun</label>
                        <input class="admin-input" id="email" name="email" type="email" maxlength="255" value="{{ old('email', $student->email) }}" required autocomplete="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                        @error('email')<small class="admin-field-error" role="alert">{{ $message }}</small>@enderror
                    </div>
                    <div class="admin-field">
                        <label for="nis">Nomor Induk Siswa (NIS)</label>
                        <input class="admin-input" id="nis" name="nis" type="text" maxlength="50" value="{{ old('nis', $student->nis) }}" required inputmode="numeric" aria-invalid="{{ $errors->has('nis') ? 'true' : 'false' }}">
                        <small>NIS harus unik dan berasal dari data resmi sekolah.</small>
                        @error('nis')<small class="admin-field-error" role="alert">{{ $message }}</small>@enderror
                    </div>
                    <div class="admin-field">
                        <label for="class_name">Kelas</label>
                        <input class="admin-input" id="class_name" name="class_name" type="text" maxlength="50" value="{{ old('class_name', $student->class_name) }}" placeholder="Contoh: XII RPL 2" required aria-invalid="{{ $errors->has('class_name') ? 'true' : 'false' }}">
                        @error('class_name')<small class="admin-field-error" role="alert">{{ $message }}</small>@enderror
                    </div>
                    @unless ($isEditing)
                        <div class="admin-field">
                            <label for="password">Kata sandi awal</label>
                            <input class="admin-input" id="password" name="password" type="password" minlength="10" required autocomplete="new-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                            <small>Minimal 10 karakter. Sampaikan kepada siswa melalui cara yang aman.</small>
                            @error('password')<small class="admin-field-error" role="alert">{{ $message }}</small>@enderror
                        </div>
                        <div class="admin-field">
                            <label for="password_confirmation">Konfirmasi kata sandi</label>
                            <input class="admin-input" id="password_confirmation" name="password_confirmation" type="password" minlength="10" required autocomplete="new-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                        </div>
                    @endunless
                </div>
                <div class="admin-form-note"><strong>Perlindungan data:</strong> akun siswa tidak dapat dihapus dari halaman ini. Gunakan fitur aktif/nonaktif agar riwayat peminjaman tetap tersimpan.</div>
                <div class="admin-form-actions">
                    <a href="{{ route('admin.students.index') }}" class="admin-btn">Batal</a>
                    <button type="submit" class="admin-btn primary">{{ $isEditing ? 'Simpan perubahan' : 'Buat akun siswa' }}</button>
                </div>
            </form>
        </div>
    </section>
@endsection
