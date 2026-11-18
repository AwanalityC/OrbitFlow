<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tautan Aktivasi Tidak Berlaku - OrbitFlow</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/orbitflow-brand.css') }}?v=3">
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f3f7fa;font-family:Arial,sans-serif;color:#263b52}.card{width:min(100%,440px);padding:34px;background:#fff;border:1px solid #e3eaf0;border-radius:18px;box-shadow:0 18px 55px rgba(32,58,82,.09);text-align:center}.mark{display:grid;width:52px;height:52px;margin:0 auto 18px;place-items:center;border-radius:16px;background:#fff2e8;color:#b77530;font-size:23px;font-weight:900}h1{margin:0 0 10px;font-size:23px}p{color:#718196;font-size:13px;line-height:1.7}.button{display:inline-flex;align-items:center;justify-content:center;min-height:43px;margin-top:12px;padding:0 18px;border-radius:9px;background:#168b78;color:white;font-size:13px;font-weight:800;text-decoration:none}.brand{margin-top:24px;color:#9aa6b3;font-size:10px}
    </style>
</head>
<body>
    <main class="card">
        <div class="mark">!</div>
        <h1>Tautan aktivasi tidak berlaku</h1>
        <p>Tautan mungkin sudah digunakan, sudah kedaluwarsa, atau tidak valid. Hubungi Petugas atau Super Admin sekolah untuk meminta tautan aktivasi baru.</p>
        <a class="button" href="{{ route('login') }}">Kembali ke login</a>
        <div class="of-brand-lockup of-auth-brand">
            <img src="{{ asset('images/orbitflow-mark.svg') }}" alt="Logo OrbitFlow">
            <span class="of-brand-lockup-copy"><strong>OrbitFlow</strong><small>SCHOOL EQUIPMENT MANAGEMENT</small></span>
        </div>
    </main>
</body>
</html>
