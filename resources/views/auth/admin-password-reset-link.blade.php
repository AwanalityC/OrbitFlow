<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex,nofollow">
    <title>Tautan Reset Kata Sandi - OrbitFlow</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/orbitflow-brand.css') }}?v=3">
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#f2f6f8;font-family:Arial,sans-serif;color:#263b52}.card{width:min(100%,600px);padding:30px;background:#fff;border:1px solid #e0e8ee;border-radius:16px;box-shadow:0 16px 45px rgba(31,55,82,.08)}.eyebrow{color:#168b78;font-size:11px;font-weight:800;letter-spacing:1.2px}.card h1{margin:9px 0;font-size:24px}.muted{color:#718096;font-size:13px;line-height:1.65}.target{margin:18px 0;padding:13px;border:1px solid #e2eaf0;border-radius:10px;background:#f8fafc;font-size:13px}.linkbox{display:block;width:100%;padding:13px;border:1px solid #cbd8e2;border-radius:9px;background:#f8fafc;color:#2455a6;font-size:12px;line-height:1.6;overflow-wrap:anywhere}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.button{display:inline-flex;justify-content:center;align-items:center;min-height:42px;padding:0 16px;border:0;border-radius:8px;background:#168b78;color:#fff;text-decoration:none;font-size:13px;font-weight:700;cursor:pointer}.button.secondary{background:#edf2f6;color:#40566b}.warning{margin-top:18px;padding:12px;border:1px solid #f0dfb4;border-radius:9px;background:#fffaf0;color:#825b16;font-size:12px;line-height:1.6}.footer{margin-top:18px;font-size:11px;color:#8290a0}
    </style>
</head>
<body>
    <main class="card">
        <div class="of-brand-lockup of-auth-brand">
            <img src="{{ asset('images/orbitflow-mark.svg') }}" alt="Logo OrbitFlow">
            <span class="of-brand-lockup-copy"><strong>OrbitFlow</strong><small>SCHOOL EQUIPMENT MANAGEMENT</small></span>
        </div>
        <div class="eyebrow">ORBITFLOW / PEMULIHAN AKUN</div>
        <h1>Tautan reset kata sandi</h1>
        <p class="muted">Tautan privat untuk {{ $purpose }} berhasil dibuat. Sampaikan tautan ini langsung kepada pemilik akun melalui saluran sekolah yang disetujui.</p>
        <div class="target"><strong>{{ $targetUser->name }}</strong><br>{{ $targetUser->email }}</div>
        <label for="reset-link" class="muted"><strong>Salin tautan ini sekarang</strong></label>
        <input id="reset-link" class="linkbox" type="text" readonly value="{{ $resetUrl }}" aria-label="Tautan reset kata sandi">
        <div class="actions">
            <button class="button" type="button" onclick="copyResetLink()">Salin tautan</button>
            <a class="button secondary" href="{{ $targetUser->isStudent() ? route('admin.students.index') : route('admin.staff.index') }}">Kembali ke daftar akun</a>
        </div>
        <div class="warning"><strong>Jaga kerahasiaan tautan ini.</strong> Tautan hanya dapat digunakan sekali, berlaku {{ $expiresMinutes }} menit, dan akan menggantikan tautan reset sebelumnya. Halaman ini tidak akan menyimpan tautan untuk ditampilkan ulang.</div>
        <div class="footer">Jangan kirim tautan melalui grup publik atau membagikannya kepada orang lain.</div>
    </main>
    <script>
        function copyResetLink() {
            const input = document.getElementById('reset-link');
            input.select();
            input.setSelectionRange(0, 99999);
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value).then(() => alert('Tautan berhasil disalin.'));
            } else {
                document.execCommand('copy');
                alert('Tautan disalin.');
            }
        }
    </script>
</body>
</html>
