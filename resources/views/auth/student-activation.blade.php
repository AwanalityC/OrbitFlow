<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Aktivasi Akun Siswa - OrbitFlow</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/orbitflow-brand.css') }}?v=3">
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f3f7fa;font-family:Arial,sans-serif;color:#263b52}.activation-card{width:min(100%,460px);padding:34px;background:#fff;border:1px solid #e3eaf0;border-radius:18px;box-shadow:0 18px 55px rgba(32,58,82,.09)}.brand{display:flex;align-items:center;gap:11px;margin-bottom:28px}.brand-mark{display:grid;width:42px;height:42px;place-items:center;border-radius:13px;background:#e8f6f2;color:#168b78;font-size:20px;font-weight:900}.brand strong{display:block;font-size:18px}.brand small{display:block;margin-top:3px;color:#8a99a9;font-size:10px;letter-spacing:1.4px}.eyebrow{color:#168b78;font-size:10px;font-weight:800;letter-spacing:1.3px}h1{margin:8px 0;color:#293f57;font-size:25px}p{color:#718196;font-size:13px;line-height:1.65}.student-name{margin:17px 0;padding:12px 14px;border:1px solid #e4eee9;border-radius:10px;background:#f7fbf9;color:#42665d;font-size:13px;font-weight:700}.field{display:flex;flex-direction:column;gap:7px;margin-top:18px}.field label{font-size:12px;font-weight:700;color:#52677e}.field input{width:100%;min-height:45px;padding:11px 13px;border:1px solid #dce5ec;border-radius:9px;font:inherit;font-size:14px}.field input:focus{outline:0;border-color:#65b9a7;box-shadow:0 0 0 3px rgba(22,139,120,.1)}.hint{font-size:11px;color:#8b98a7}.button{width:100%;min-height:46px;margin-top:24px;border:0;border-radius:9px;background:#168b78;color:white;font-size:14px;font-weight:800;cursor:pointer}.errors{margin-top:14px;padding:11px 13px;border:1px solid #f2d6d8;border-radius:9px;background:#fff6f6;color:#aa4851;font-size:12px}.footer{margin-top:22px;text-align:center;color:#9aa6b3;font-size:10px}
    </style>
</head>
<body>
    <main class="activation-card">
        <div class="of-brand-lockup of-auth-brand">
            <img src="{{ asset('images/orbitflow-mark.svg') }}" alt="Logo OrbitFlow">
            <span class="of-brand-lockup-copy"><strong>OrbitFlow</strong><small>SCHOOL EQUIPMENT MANAGEMENT</small></span>
        </div>
        <div class="eyebrow">AKTIVASI AKUN SISWA</div>
        <h1>Buat kata sandi akun</h1>
        <p>Halo, {{ $student->name }}. Buat kata sandi pribadi untuk menyelesaikan aktivasi akun OrbitFlow. Tautan ini hanya dapat digunakan sekali.</p>
        <div class="student-name">{{ $student->name }}</div>
        @if ($errors->any())<div class="errors">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('student.activation.activate', ['token' => $token]) }}" autocomplete="off">
            @csrf
            <div class="field">
                <label for="password">Kata sandi baru</label>
                <input id="password" name="password" type="password" minlength="10" autocomplete="new-password" required>
                <span class="hint">Minimal 10 karakter. Gunakan kata sandi yang tidak dipakai di akun lain.</span>
            </div>
            <div class="field">
                <label for="password_confirmation">Konfirmasi kata sandi</label>
                <input id="password_confirmation" name="password_confirmation" type="password" minlength="10" autocomplete="new-password" required>
            </div>
            <button class="button" type="submit">Aktifkan akun</button>
        </form>
        <div class="footer">OrbitFlow · Tautan aktivasi privat dan sekali pakai</div>
    </main>
</body>
</html>
