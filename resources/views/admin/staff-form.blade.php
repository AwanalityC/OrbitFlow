@extends('layouts.admin')

@section('title', $isEditing ? 'Edit Akun Staf' : 'Tambah Akun Staf')
@section('page-title', 'Manajemen Pengguna')
@section('page-description', 'Kelola akun administratif OrbitFlow.')

@section('content')
    <section class="admin-page-intro">
        <div>
            <h2>{{ $isEditing ? 'Edit akun staf' : 'Buat akun staf' }}</h2>
            <p>Berikan akses sesuai tanggung jawab staf di sekolah.</p>
        </div>
        <a href="{{ route('admin.staff.index') }}" class="admin-btn">← Kembali ke daftar staf</a>
    </section>

    <nav class="admin-account-tabs" aria-label="Kategori manajemen pengguna">
        <a href="{{ route('admin.students.index') }}">Siswa</a>
        <a href="{{ route('admin.staff.index') }}" class="active">Admin &amp; Petugas</a>
    </nav>

    <section class="admin-panel">
        <div class="admin-panel-head"><div><h3>{{ $isEditing ? 'Informasi akun staf' : 'Informasi staf baru' }}</h3><p>Gunakan email unik dan pilih peran sesuai kebutuhan.</p></div></div>
        <div class="admin-panel-body">
            <form method="POST" action="{{ $isEditing ? route('admin.staff.update', $staff) : route('admin.staff.store') }}" class="admin-account-form">
                @csrf
                @if ($isEditing) @method('PUT') @endif
                <div class="admin-form-grid">
                    <div class="admin-field">
                        <label for="name">Nama lengkap</label>
                        <input class="admin-input" id="name" name="name" type="text" maxlength="255" value="{{ old('name', $staff->name) }}" required autocomplete="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                        @error('name')<small class="admin-field-error" role="alert">{{ $message }}</small>@enderror
                    </div>
                    <div class="admin-field">
                        <label for="email">Email akun</label>
                        <input class="admin-input" id="email" name="email" type="email" maxlength="255" value="{{ old('email', $staff->email) }}" required autocomplete="email" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                        @error('email')<small class="admin-field-error" role="alert">{{ $message }}</small>@enderror
                    </div>
                    <div class="admin-field">
                        <label for="role">Peran administratif</label>
                        <select class="admin-select" id="role" name="role" required @disabled($isEditing && $staff->id === auth()->id())>
                            <option value="petugas" @selected(old('role', $staff->isSuperAdmin() ? 'super_admin' : $staff->role) === 'petugas')>Petugas Inventaris</option>
                            <option value="super_admin" @selected(old('role', $staff->isSuperAdmin() ? 'super_admin' : $staff->role) === 'super_admin')>Super Admin</option>
                        </select>
                        @error('role')<small class="admin-field-error" role="alert">{{ $message }}</small>@enderror
                        @if ($isEditing && $staff->id === auth()->id())
                            <input type="hidden" name="role" value="super_admin">
                            <small>Peran akun yang sedang digunakan tidak dapat diubah dari sesi ini.</small>
                        @else
                            <small>Super Admin memiliki akses penuh dan dapat mengelola akun staf. Petugas fokus pada operasional peminjaman dan inventaris.</small>
                        @endif
                    </div>
                    @unless ($isEditing)
                        <div class="admin-field">
                            <label for="password">Kata sandi awal</label>
                            <input class="admin-input" id="password" name="password" type="password" minlength="10" required autocomplete="new-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                            <small>Minimal 10 karakter. Sampaikan kepada staf melalui cara yang aman.</small>
                            @error('password')<small class="admin-field-error" role="alert">{{ $message }}</small>@enderror
                        </div>
                        <div class="admin-field">
                            <label for="password_confirmation">Konfirmasi kata sandi</label>
                            <input class="admin-input" id="password_confirmation" name="password_confirmation" type="password" minlength="10" required autocomplete="new-password" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}">
                        </div>
                    @endunless
                </div>
                <div class="admin-form-note"><strong>Pengamanan:</strong> setiap staf harus menggunakan akun pribadinya. Minimal satu Super Admin aktif harus selalu tersedia.</div>
                <div class="admin-form-actions">
                    <a href="{{ route('admin.staff.index') }}" class="admin-btn">Batal</a>
                    <button type="submit" class="admin-btn primary">{{ $isEditing ? 'Simpan perubahan' : 'Buat akun staf' }}</button>
                </div>
            </form>
        </div>
    </section>
@endsection
