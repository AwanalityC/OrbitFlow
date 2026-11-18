<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#142D4E">
    <title>Masuk - OrbitFlow</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/orbitflow-ui.css') }}?v=6">
    <link rel="stylesheet" href="{{ asset('css/orbitflow-brand.css') }}?v=3">
    <link rel="stylesheet" href="{{ asset('css/orbitflow-auth.css') }}?v=1">
</head>
<body class="of-login-page">
    <main class="of-login-layout">
        <section class="of-login-story" aria-label="Tentang OrbitFlow">
            <a class="of-brand-lockup of-login-brand" href="{{ route('login') }}" aria-label="OrbitFlow - halaman masuk">
                <img src="{{ asset('images/orbitflow-mark.svg') }}" alt="Logo OrbitFlow">
                <span class="of-brand-lockup-copy"><strong>OrbitFlow</strong><small>SCHOOL EQUIPMENT MANAGEMENT</small></span>
            </a>

            <div class="of-login-story-content">
                <div class="of-login-eyebrow">Sistem inventaris sekolah</div>
                <h1>Kelola. Pinjam.<br><span>Kembalikan.</span></h1>
                <p class="of-login-story-copy">Satu tempat untuk melihat ketersediaan alat, mengajukan peminjaman, dan mengelola inventaris sekolah dengan lebih teratur.</p>
                <div class="of-login-feature-list" aria-label="Keunggulan OrbitFlow">
                    <span class="of-login-feature">Aman</span>
                    <span class="of-login-feature">Cepat</span>
                    <span class="of-login-feature">Terstruktur</span>
                    <span class="of-login-feature">Terpercaya</span>
                </div>
            </div>

            <div class="of-login-story-footer">OrbitFlow · Sistem Peminjaman &amp; Inventaris Sekolah</div>
        </section>

        <section class="of-login-form-side" aria-label="Form masuk">
            <div class="of-login-card">
                <p class="of-login-card-kicker">Selamat datang kembali</p>
                <h2>Masuk ke OrbitFlow</h2>
                <p class="of-login-card-subtitle">Gunakan akun sekolah yang telah terdaftar untuk melanjutkan.</p>

                @if (session('activation_success'))
                    <div class="of-login-alert success" role="status">{{ session('activation_success') }}</div>
                @endif

                @if (session('status'))
                    <div class="of-login-alert success" role="status">{{ session('status') }}</div>
                @endif

                @if (isset($throttleMessage))
                    <div class="of-login-alert" role="alert">{{ $throttleMessage }}</div>
                @endif

                @if ($errors->any())
                    <div class="of-login-alert" role="alert">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="/login">
                    @csrf
                    <div class="of-login-field">
                        <label for="email">Alamat email</label>
                        <input type="email" id="email" autocomplete="username" maxlength="255" name="email" value="{{ old('email', $prefillEmail ?? '') }}" placeholder="nama@sekolah.sch.id" required autofocus>
                    </div>
                    <div class="of-login-field">
                        <label for="password">Kata sandi</label>
                        <input type="password" id="password" autocomplete="current-password" maxlength="255" name="password" placeholder="Masukkan kata sandi" required>
                    </div>
                    <button class="of-login-submit" type="submit">Masuk ke akun <span aria-hidden="true">→</span></button>
                </form>

                <a class="of-login-forgot" href="{{ route('password.request') }}">Lupa kata sandi?</a>
                <div class="of-login-security-note">Akses hanya diberikan kepada akun siswa dan petugas sekolah yang telah diaktifkan.</div>
            </div>
        </section>
    </main>
</body>
</html>
