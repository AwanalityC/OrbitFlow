<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Lupa Kata Sandi - OrbitFlow</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/orbitflow-ui.css') }}?v=6">
    <link rel="stylesheet" href="{{ asset('css/orbitflow-brand.css') }}?v=3">
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f5f7fb;font-family:Arial,sans-serif;color:#263b52}.auth-card{width:min(100%,440px);padding:32px;background:#fff;border:1px solid #e1e7ef;border-radius:16px;box-shadow:0 16px 45px rgba(31,55,82,.08)}.brand{font-size:24px;font-weight:800;color:#233b55}.subtitle{margin:7px 0 24px;color:#718096;font-size:13px;line-height:1.6}label{display:block;margin:18px 0 7px;font-size:13px;font-weight:700}input{width:100%;min-height:46px;padding:11px 13px;border:1px solid #d8e0e9;border-radius:9px;font-size:14px}button{width:100%;min-height:46px;margin-top:20px;border:0;border-radius:9px;background:#2563eb;color:#fff;font-size:14px;font-weight:700;cursor:pointer}.message{margin:14px 0;padding:12px;border-radius:9px;background:#eff8f4;color:#26734f;font-size:13px;line-height:1.55}.error{margin:14px 0;color:#b42332;font-size:13px}.back{display:block;margin-top:18px;text-align:center;color:#2563eb;text-decoration:none;font-size:13px;font-weight:700}
    </style>
</head>
<body>
    <main class="auth-card">
        <div class="of-brand-lockup of-auth-brand">
            <img src="{{ asset('images/orbitflow-mark.svg') }}" alt="Logo OrbitFlow">
            <span class="of-brand-lockup-copy"><strong>OrbitFlow</strong><small>SCHOOL EQUIPMENT MANAGEMENT</small></span>
        </div>
        <h1 style="margin:18px 0 0;font-size:22px;line-height:1.3;">Lupa kata sandi?</h1>
        <p class="subtitle">Masukkan email yang terdaftar. Jika akun aktif dan email ditemukan, petunjuk pemulihan akan dikirim.</p>
        @if (session('status'))<div class="message">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="error">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <label for="email">Alamat email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus>
            <button type="submit">Kirim petunjuk pemulihan</button>
        </form>
        <a class="back" href="{{ route('login') }}">Kembali ke login</a>
    </main>
</body>
</html>
