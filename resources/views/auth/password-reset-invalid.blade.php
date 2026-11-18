<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Tautan Tidak Berlaku - OrbitFlow</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/orbitflow-brand.css') }}?v=3">
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f5f7fb;font-family:Arial,sans-serif;color:#263b52}.card{width:min(100%,440px);padding:32px;background:#fff;border:1px solid #e1e7ef;border-radius:16px;box-shadow:0 16px 45px rgba(31,55,82,.08)}h1{margin:0 0 10px;font-size:23px}.muted{color:#718096;font-size:13px;line-height:1.65}.button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;margin-top:14px;padding:0 16px;border-radius:9px;background:#2563eb;color:#fff;text-decoration:none;font-size:13px;font-weight:700}
    </style>
</head>
<body>
    <main class="card">
        <div class="of-brand-lockup of-auth-brand">
            <img src="{{ asset('images/orbitflow-mark.svg') }}" alt="Logo OrbitFlow">
            <span class="of-brand-lockup-copy"><strong>OrbitFlow</strong><small>SCHOOL EQUIPMENT MANAGEMENT</small></span>
        </div>
        <h1>Tautan pemulihan tidak berlaku</h1>
        <p class="muted">Tautan mungkin sudah digunakan, telah kedaluwarsa, atau akun tidak lagi aktif. Minta tautan baru melalui halaman pemulihan atau hubungi administrator sekolah.</p>
        <a class="button" href="{{ route('password.request') }}">Minta tautan baru</a>
    </main>
</body>
</html>
