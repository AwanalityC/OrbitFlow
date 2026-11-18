<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#142D4E">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}?v=1">
    <title>@yield('title', 'Panel Admin') - OrbitFlow</title>
    <link rel="stylesheet" href="{{ asset('css/orbitflow-ui.css') }}?v=6">
    <link rel="stylesheet" href="{{ asset('css/orbitflow-admin.css') }}?v=8">
    <link rel="stylesheet" href="{{ asset('css/orbitflow-brand.css') }}?v=3">
    @stack('styles')
</head>
<body class="admin-body">
    <div class="admin-shell">
        <aside class="admin-sidebar" id="admin-sidebar" aria-label="Navigasi administrasi">
            <div class="admin-sidebar-header">
                <a class="admin-brand" href="{{ route('admin.dashboard') }}">
                    <img class="admin-brand-mark" src="{{ asset('images/orbitflow-mark.svg') }}" alt="Logo OrbitFlow">
                    <span class="admin-brand-copy"><strong>OrbitFlow</strong><small>SCHOOL EQUIPMENT</small></span>
                </a>
                <button type="button" class="admin-drawer-close" data-admin-nav-close aria-label="Tutup menu navigasi">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18"/></svg>
                </button>
            </div>

            <nav class="admin-nav" aria-label="Navigasi admin">
                <div class="admin-side-caption">WORKSPACE</div>
                <a href="{{ route('admin.dashboard') }}" class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <span class="admin-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/></svg></span><span>Ringkasan</span>
                </a>

                <div class="admin-side-caption nav-group-caption">OPERASIONAL</div>
                <a href="{{ route('admin.borrowings.index', ['status' => 'pending']) }}" class="admin-nav-link {{ request()->routeIs('admin.borrowings.index') && request('status') === 'pending' ? 'active' : '' }}">
                    <span class="admin-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4h8M8 8h8M6 3h12a2 2 0 0 1 2 2v16H4V5a2 2 0 0 1 2-2Z"/><path d="m8 14 2 2 5-5"/></svg></span><span>Antrean Pengajuan</span>
                </a>
                <a href="{{ route('admin.borrowings.index', ['status' => 'active']) }}" class="admin-nav-link {{ request()->routeIs('admin.borrowings.index') && request('status') === 'active' ? 'active' : '' }}">
                    <span class="admin-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h15l-3-3M20 17H5l3 3"/><path d="M19 7v5M5 12v5"/></svg></span><span>Peminjaman Aktif</span>
                </a>
                <a href="{{ route('admin.borrowings.index', ['status' => 'all']) }}" class="admin-nav-link {{ request()->routeIs('admin.borrowings.index') && (!request('status') || request('status') === 'all') ? 'active' : '' }}">
                    <span class="admin-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 3.5h8l4 4V21H5V5.5a2 2 0 0 1 2-2Z"/><path d="M14 3.5V8h5M8 12h8M8 16h8"/></svg></span><span>Semua Pengajuan</span>
                </a>
                <a href="{{ route('admin.losses.index') }}" class="admin-nav-link {{ request()->routeIs('admin.losses.*') ? 'active' : '' }}">
                    <span class="admin-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3 9 16H3L12 3Z"/><path d="M12 9v4M12 16.5h.01"/></svg></span><span>Laporan Kehilangan</span>
                </a>

                <div class="admin-side-caption nav-group-caption">DATA &amp; AKUN</div>
                <a href="{{ route('admin.equipment-units.index') }}" class="admin-nav-link {{ request()->routeIs('admin.equipment-units.*', 'admin.equipment.*') ? 'active' : '' }}">
                    <span class="admin-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m4 7 8-4 8 4v10l-8 4-8-4V7Z"/><path d="m4 7 8 4 8-4M12 11v10"/></svg></span><span>Inventaris &amp; Unit</span>
                </a>
                <a href="{{ route('admin.students.index') }}" class="admin-nav-link {{ request()->routeIs('admin.students.*', 'admin.staff.*') ? 'active' : '' }}">
                    <span class="admin-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.2"/><path d="M3 20c.3-3.2 2.5-5.2 6-5.2s5.7 2 6 5.2M16 5.2a3.2 3.2 0 0 1 0 6.1M17 15c2.5.5 3.8 2.1 4 5"/></svg></span><span>Manajemen Pengguna</span>
                </a>
                <a href="{{ route('admin.activity-logs.index') }}" class="admin-nav-link {{ request()->routeIs('admin.activity-logs.*') ? 'active' : '' }}">
                    <span class="admin-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="8.5"/><path d="M12 7v5l3 2"/></svg></span><span>Log Aktivitas</span>
                </a>
                @if (auth()->user()->isSuperAdmin())
                    <div class="admin-side-caption nav-group-caption">SISTEM</div>
                    <a href="{{ route('admin.backups.index') }}" class="admin-nav-link {{ request()->routeIs('admin.backups.*') ? 'active' : '' }}">
                        <span class="admin-nav-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h16v12H4zM3 4h18v4H3z"/><path d="M9 13h6M12 11v5"/></svg></span><span>Cadangan &amp; Pemulihan</span>
                    </a>
                @endif
            </nav>

            <div class="admin-sidebar-bottom">
                <span class="admin-online-dot"></span>
                <span><strong>Workspace aktif</strong><small>Panel pengelolaan sekolah</small></span>
            </div>
        </aside>
        <button type="button" class="admin-nav-scrim" data-admin-nav-scrim aria-label="Tutup menu navigasi" hidden></button>

        <div class="admin-main">
            <header class="admin-topbar">
                <button type="button" class="admin-mobile-nav-toggle" data-admin-nav-toggle aria-controls="admin-sidebar" aria-expanded="false" aria-label="Buka menu navigasi">
                    <svg viewBox="0 0 24 24" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <span>Menu</span>
                </button>
                <div class="admin-topbar-heading">
                    <div class="admin-eyebrow">ORBITFLOW / ADMINISTRATION</div>
                    <h1>@yield('page-title', 'Panel Admin')</h1>
                    <p>@yield('page-description', 'Kelola operasional peminjaman dan inventaris sekolah.') </p>
                </div>
                <div class="admin-user-tools">
                    <div class="admin-user-avatar">{{ mb_strtoupper(mb_substr(auth()->user()->name, 0, 1)) }}</div>
                    <div class="admin-user-copy"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->role_label }}</small></div>
                    <form method="POST" action="{{ url('/logout') }}">
                        @csrf
                        <button type="submit" class="admin-logout">Keluar</button>
                    </form>
                </div>
            </header>

            <main class="admin-content">
                @if (session('success'))
                    <div class="admin-flash success"><span class="flash-symbol">✓</span><span>{{ session('success') }}</span></div>
                @endif
                @if ($errors->any())
                    <div class="admin-flash error"><span class="flash-symbol">!</span><div>@foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div></div>
                @endif

                @yield('content')

                <footer class="admin-footer">
                    <span>OrbitFlow <span aria-hidden="true">·</span> Panel Administrasi</span>
                    <span>Sistem Inventaris Sekolah</span>
                </footer>
            </main>
        </div>
    </div>
    @stack('scripts')
    <script src="{{ asset('js/orbitflow-live-filters.js') }}?v=2" defer></script>
    <script src="{{ asset('js/orbitflow-admin-sidebar-scroll.js') }}?v=1" defer></script>
    <script src="{{ asset('js/orbitflow-admin-nav-drawer.js') }}?v=1" defer></script>
    <script src="{{ asset('js/orbitflow-camera-capture.js') }}?v=1" defer></script>
</body>
</html>
