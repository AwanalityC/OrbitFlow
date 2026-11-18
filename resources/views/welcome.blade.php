<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102b4e">
    <meta name="description" content="OrbitFlow membantu sekolah mengelola inventaris, peminjaman, penyerahan, dan pengembalian alat.">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
    <link rel="stylesheet" href="{{ asset('css/orbitflow-ui.css') }}?v=6">
    <link rel="stylesheet" href="{{ asset('css/orbitflow-brand.css') }}?v=3">
    <title>OrbitFlow · Inventaris & Peminjaman Alat Sekolah</title>
    <style>
        :root{--ink:#102b4e;--muted:#61748b;--line:#e0e9f2;--blue:#2864d8;--cyan:#20b9cf;--surface:#fff;--page:#f4f8fc}
        *{box-sizing:border-box}body{margin:0;background:var(--page);color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif}
        a{color:inherit}.public-wrap{min-height:100vh;overflow:hidden}.public-nav{width:min(1160px,calc(100% - 40px));margin:auto;height:78px;display:flex;align-items:center;justify-content:space-between;gap:20px}
        .public-brand{display:inline-flex;align-items:center;gap:11px;text-decoration:none;font-weight:900;letter-spacing:-.04em;font-size:19px}.public-brand img{width:39px;height:39px;object-fit:contain}
        .public-nav-actions{display:flex;align-items:center;gap:10px}.public-login{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 18px;border-radius:12px;background:var(--ink);color:#fff;text-decoration:none;font-size:13px;font-weight:800;box-shadow:0 8px 20px #102b4e20}
        .public-hero{position:relative;width:min(1160px,calc(100% - 40px));margin:28px auto 0;display:grid;grid-template-columns:minmax(0,1.05fr) minmax(320px,.95fr);align-items:center;gap:34px;padding:60px 54px;border:1px solid #dce8f5;border-radius:30px;background:radial-gradient(circle at 85% 16%,#d7f7fb 0,transparent 34%),linear-gradient(130deg,#fff 0%,#f2f8ff 100%);box-shadow:0 22px 70px #18385b0d}
        .public-eyebrow{display:inline-flex;align-items:center;gap:8px;padding:7px 11px;border:1px solid #d4e8f2;border-radius:999px;background:#f5fcff;color:#28708a;font-size:10px;font-weight:900;letter-spacing:.13em;text-transform:uppercase}.public-eyebrow:before{content:"";width:7px;height:7px;border-radius:50%;background:#25b8c8;box-shadow:0 0 0 4px #25b8c81c}
        .public-hero h1{max-width:610px;margin:19px 0 14px;font-size:clamp(35px,5vw,58px);line-height:1.04;letter-spacing:-.055em}.public-hero h1 span{background:linear-gradient(95deg,#2362d7,#19aebe);-webkit-background-clip:text;background-clip:text;color:transparent}.public-lead{max-width:520px;margin:0;color:var(--muted);font-size:15px;line-height:1.8}.public-hero-actions{display:flex;flex-wrap:wrap;align-items:center;gap:12px;margin-top:27px}.public-primary{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:48px;padding:0 20px;border-radius:13px;background:linear-gradient(105deg,#245ed1,#197eae);color:#fff;text-decoration:none;font-size:13px;font-weight:900;box-shadow:0 12px 24px #2665d52a}.public-secondary{color:#536b86;font-size:12px;font-weight:800;text-decoration:none}.public-visual{position:relative;min-height:325px;display:grid;place-items:center}.public-glow{position:absolute;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,#c7f4f4 0%,#e4f0ff 60%,transparent 72%);filter:blur(5px)}.public-device{position:relative;width:min(100%,390px);padding:20px;border:1px solid #d6e3f0;border-radius:24px;background:#fff;box-shadow:0 28px 65px #17395820;transform:rotate(2deg)}.device-head{display:flex;align-items:center;justify-content:space-between;padding:1px 2px 15px;border-bottom:1px solid #edf2f7}.device-brand{display:flex;align-items:center;gap:7px;font-size:12px;font-weight:900}.device-brand img{width:24px;height:24px}.device-pill{padding:5px 8px;border-radius:99px;background:#eaf8f4;color:#17836c;font-size:8px;font-weight:900}.device-title{margin:17px 0 4px;font-size:18px;font-weight:900}.device-caption{color:#8292a6;font-size:9px}.device-metrics{display:grid;grid-template-columns:repeat(2,1fr);gap:9px;margin-top:15px}.device-metric{padding:12px;border:1px solid #e7eef5;border-radius:13px;background:#fbfdff}.device-metric strong{display:block;color:#1e5dc8;font-size:22px}.device-metric span{color:#78899c;font-size:8px}.device-list{display:grid;gap:8px;margin-top:14px}.device-row{display:flex;align-items:center;gap:9px;padding:10px;border:1px solid #edf2f7;border-radius:11px}.device-icon{display:grid;width:30px;height:30px;place-items:center;border-radius:9px;background:#eaf4ff;color:#2464cc;font-size:14px}.device-row strong{display:block;font-size:9px}.device-row small{color:#8a99ab;font-size:8px}.device-tag{margin-left:auto;padding:5px 7px;border-radius:8px;background:#eaf8f4;color:#19846e;font-size:7px;font-weight:900}
        .public-section{width:min(1160px,calc(100% - 40px));margin:76px auto}.public-section-head{max-width:650px;margin:0 auto 27px;text-align:center}.public-section-head span{color:#2388a6;font-size:10px;font-weight:900;letter-spacing:.16em}.public-section-head h2{margin:9px 0;font-size:clamp(25px,3.5vw,36px);letter-spacing:-.04em}.public-section-head p{margin:0;color:var(--muted);font-size:13px;line-height:1.7}
        .public-features{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}.public-feature{padding:20px;border:1px solid var(--line);border-radius:18px;background:#fff;box-shadow:0 8px 24px #1b3b5c08}.public-feature-icon{display:grid;width:40px;height:40px;place-items:center;margin-bottom:16px;border-radius:13px;background:#edf6ff;color:#2769d0;font-size:19px}.public-feature h3{margin:0 0 7px;font-size:14px}.public-feature p{margin:0;color:var(--muted);font-size:11px;line-height:1.7}
        .public-steps{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px}.public-step{padding:22px;border:1px solid var(--line);border-radius:18px;background:#fff}.public-step-num{display:grid;width:31px;height:31px;place-items:center;border-radius:10px;background:#e8f7fb;color:#147e9d;font-size:12px;font-weight:900}.public-step h3{margin:14px 0 6px;font-size:14px}.public-step p{margin:0;color:var(--muted);font-size:11px;line-height:1.7}
        .public-cta{display:flex;align-items:center;justify-content:space-between;gap:24px;padding:28px 32px;border-radius:22px;background:linear-gradient(110deg,#102b4e,#1d5485);color:#fff}.public-cta h2{margin:0 0 6px;font-size:22px;letter-spacing:-.03em}.public-cta p{margin:0;color:#c8d9eb;font-size:12px}.public-footer{padding:28px 20px;border-top:1px solid #e0e9f2;color:#7c8ea3;text-align:center;font-size:10px}.public-footer strong{color:#365575}
        @media(max-width:900px){.public-hero{padding:42px 32px;gap:18px}.public-features{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:680px){.public-nav{height:68px;width:calc(100% - 28px)}.public-brand{font-size:17px}.public-brand img{width:34px;height:34px}.public-hero{width:calc(100% - 28px);margin-top:12px;padding:30px 22px;grid-template-columns:1fr;gap:4px;border-radius:22px}.public-hero h1{font-size:38px}.public-lead{font-size:13px}.public-visual{min-height:295px;margin-top:10px}.public-device{max-width:350px}.public-section{width:calc(100% - 28px);margin:56px auto}.public-features{grid-template-columns:1fr 1fr}.public-feature{padding:15px}.public-steps{grid-template-columns:1fr}.public-cta{align-items:flex-start;flex-direction:column;padding:24px}.public-login{min-height:39px;padding:0 14px}}
        @media(max-width:380px){.public-hero h1{font-size:33px}.public-features{grid-template-columns:1fr}.public-device{padding:15px}}
        @media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;transition:none!important}}
    </style>
</head>
<body>
<div class="public-wrap">
    <header class="public-nav">
        <a class="public-brand" href="{{ route('home') }}"><img src="{{ asset('images/orbitflow-mark.svg') }}" alt=""> <span>OrbitFlow</span></a>
        <div class="public-nav-actions"><a class="public-login" href="{{ route('login') }}">Masuk ke aplikasi <span aria-hidden="true">→</span></a></div>
    </header>

    <main>
        <section class="public-hero">
            <div>
                <span class="public-eyebrow">School Equipment Management</span>
                <h1>Kelola alat sekolah dengan cara yang <span>lebih teratur.</span></h1>
                <p class="public-lead">OrbitFlow membantu siswa dan petugas mengelola katalog inventaris, pengajuan peminjaman, penyerahan, hingga pengembalian alat dalam satu aplikasi.</p>
                <div class="public-hero-actions"><a class="public-primary" href="{{ route('login') }}">Masuk ke OrbitFlow <span aria-hidden="true">↗</span></a><a class="public-secondary" href="#cara-kerja">Pelajari alurnya</a></div>
            </div>
            <div class="public-visual" aria-label="Ilustrasi ringkasan inventaris OrbitFlow">
                <div class="public-glow"></div>
                <div class="public-device">
                    <div class="device-head"><div class="device-brand"><img src="{{ asset('images/orbitflow-mark.svg') }}" alt=""> OrbitFlow</div><span class="device-pill">SISTEM AKTIF</span></div>
                    <div class="device-title">Ringkasan inventaris</div><div class="device-caption">Contoh tampilan pengelolaan alat sekolah</div>
                    <div class="device-metrics"><div class="device-metric"><strong>24</strong><span>Unit tersedia</span></div><div class="device-metric"><strong>08</strong><span>Sedang dipinjam</span></div></div>
                    <div class="device-list"><div class="device-row"><span class="device-icon">▣</span><span><strong>Kamera dokumentasi</strong><small>Multimedia · 4 unit</small></span><span class="device-tag">TERSEDIA</span></div><div class="device-row"><span class="device-icon">◉</span><span><strong>Tripod kamera</strong><small>Multimedia · 3 unit</small></span><span class="device-tag">TERSEDIA</span></div><div class="device-row"><span class="device-icon">♫</span><span><strong>Mikrofon</strong><small>Audio · 2 unit</small></span><span class="device-tag">TERSEDIA</span></div></div>
                </div>
            </div>
        </section>

        <section class="public-section" id="fitur">
            <div class="public-section-head"><span>FITUR UTAMA</span><h2>Semua proses inventaris dalam satu alur</h2><p>Informasi dibuat lebih mudah ditemukan agar siswa dan petugas dapat menyelesaikan tugasnya dengan jelas.</p></div>
            <div class="public-features">
                <article class="public-feature"><div class="public-feature-icon">▧</div><h3>Katalog inventaris</h3><p>Lihat nama alat, foto, kondisi, dan jumlah unit yang tersedia.</p></article>
                <article class="public-feature"><div class="public-feature-icon">▣</div><h3>Pengajuan pinjaman</h3><p>Pilih alat, jumlah unit, periode, dan tujuan penggunaan.</p></article>
                <article class="public-feature"><div class="public-feature-icon">◷</div><h3>Pelacakan status</h3><p>Pantau status pengajuan dan riwayat peminjaman melalui akun masing-masing.</p></article>
                <article class="public-feature"><div class="public-feature-icon">✓</div><h3>Penyerahan & pengembalian</h3><p>Petugas mencatat unit fisik dan bukti foto kondisi alat.</p></article>
            </div>
        </section>

        <section class="public-section" id="cara-kerja">
            <div class="public-section-head"><span>CARA KERJA</span><h2>Alur penggunaan OrbitFlow</h2><p>Akses fitur sesuai akun dan peran yang diberikan oleh sekolah.</p></div>
            <div class="public-steps">
                <article class="public-step"><span class="public-step-num">01</span><h3>Masuk dengan akun sekolah</h3><p>Siswa menggunakan akun yang telah diaktifkan. Petugas masuk untuk mengelola operasional.</p></article>
                <article class="public-step"><span class="public-step-num">02</span><h3>Ajukan dan periksa</h3><p>Siswa mengirim permintaan. Petugas memeriksa informasi dan ketersediaan unit fisik.</p></article>
                <article class="public-step"><span class="public-step-num">03</span><h3>Catat penyerahan dan pengembalian</h3><p>Unit dan kondisi dicatat bersama bukti foto agar riwayat inventaris lebih jelas.</p></article>
            </div>
        </section>

        <section class="public-section"><div class="public-cta"><div><h2>Siap menggunakan OrbitFlow?</h2><p>Masuk menggunakan akun yang telah disediakan atau diaktifkan oleh sekolah.</p></div><a class="public-primary" href="{{ route('login') }}">Masuk sekarang <span aria-hidden="true">→</span></a></div></section>
    </main>
    <footer class="public-footer"><strong>OrbitFlow</strong> · Sistem Inventarisasi dan Peminjaman Alat Sekolah</footer>
</div>
</body>
</html>
