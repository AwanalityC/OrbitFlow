@extends('layouts.admin')

@section('title', 'Cadangan & Pemulihan')
@section('page-title', 'Cadangan & Pemulihan')
@section('page-description', 'Buat, periksa, dan unduh cadangan database serta file publik OrbitFlow.')

@section('content')
    <section class="admin-page-intro backup-page-intro">
        <div>
            <h2>Perlindungan data OrbitFlow</h2>
            <p>Cadangan disimpan di penyimpanan privat server. Hanya Super Admin yang dapat mengelola dan mengunduhnya.</p>
        </div>
        <span class="admin-status active">Akses Super Admin</span>
    </section>

    <section class="backup-summary-grid">
        <article class="backup-summary-card">
            <span class="backup-summary-label">Jumlah cadangan tersimpan</span>
            <strong>{{ count($backups) }}</strong>
            <small>File backup yang masih berada di penyimpanan privat</small>
        </article>
        <article class="backup-summary-card">
            <span class="backup-summary-label">Retensi otomatis</span>
            <strong>{{ $retentionDays }} hari</strong>
            <small>Backup yang lebih lama dibersihkan setelah backup baru berhasil</small>
        </article>
        <article class="backup-summary-card">
            <span class="backup-summary-label">Jadwal otomatis</span>
            <strong>{{ $scheduleTime }} WIB</strong>
            <small>Setiap hari, jika Laravel Scheduler dijalankan di server</small>
        </article>
    </section>

    <section class="admin-panel backup-create-panel">
        <div class="admin-panel-head">
            <div>
                <h3>Buat backup sekarang</h3>
                <p>Backup mencakup database dan file pada storage/app/public.</p>
            </div>
            <span class="admin-status active">AES-256</span>
        </div>
        <div class="admin-panel-body backup-create-body">
            <div class="backup-create-copy">
                <strong>Backup terenkripsi dan dapat diverifikasi</strong>
                <p>Isi arsip dienkripsi menggunakan kunci turunan dari APP_KEY. File .env tidak dimasukkan ke dalam arsip.</p>
            </div>
            <form method="POST" action="{{ route('admin.backups.store') }}" onsubmit="return confirm('Buat backup database dan file publik OrbitFlow sekarang?');">
                @csrf
                <button type="submit" class="admin-btn primary">Buat backup sekarang</button>
            </form>
        </div>
    </section>

    <section class="admin-panel backup-list-panel">
        <div class="admin-panel-head">
            <div>
                <h3>Daftar backup</h3>
                <p>Verifikasi checksum sebelum menyimpan salinan backup ke lokasi lain atau menggunakannya untuk pemulihan.</p>
            </div>
            <span class="admin-status">{{ count($backups) }} file</span>
        </div>
        @if (count($backups) > 0)
            <div class="admin-table-wrap backup-desktop-list">
                <table class="admin-table backup-table">
                    <thead>
                        <tr>
                            <th>Nama file</th>
                            <th>Dibuat / diperbarui</th>
                            <th>Ukuran</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($backups as $backup)
                            <tr>
                                <td>
                                    <span class="admin-primary-text backup-filename">{{ $backup['filename'] }}</span>
                                    <span class="admin-secondary-text">Arsip privat terenkripsi</span>
                                </td>
                                <td>{{ \Carbon\Carbon::createFromTimestamp($backup['modified_at'], 'Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB</td>
                                <td>{{ number_format($backup['size_bytes'] / 1048576, 2) }} MB</td>
                                <td>
                                    <div class="backup-row-actions">
                                        <form method="POST" action="{{ route('admin.backups.verify', ['filename' => $backup['filename']]) }}">
                                            @csrf
                                            <button type="submit" class="admin-btn small">Verifikasi</button>
                                        </form>
                                        <a class="admin-btn primary small" href="{{ route('admin.backups.download', ['filename' => $backup['filename']]) }}">Unduh</a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="backup-mobile-list" aria-label="Daftar backup versi layar kecil">
                @foreach ($backups as $backup)
                    <article class="backup-mobile-card">
                        <div class="backup-mobile-card-heading">
                            <span class="admin-status active">Arsip privat</span>
                            <strong>{{ number_format($backup['size_bytes'] / 1048576, 2) }} MB</strong>
                        </div>
                        <h4 class="backup-mobile-filename">{{ $backup['filename'] }}</h4>
                        <dl class="backup-mobile-meta">
                            <div><dt>Dibuat / diperbarui</dt><dd>{{ \Carbon\Carbon::createFromTimestamp($backup['modified_at'], 'Asia/Jakarta')->format('d/m/Y H:i:s') }} WIB</dd></div>
                            <div><dt>Isi</dt><dd>Database dan file storage publik</dd></div>
                        </dl>
                        <div class="backup-row-actions backup-mobile-actions">
                            <form method="POST" action="{{ route('admin.backups.verify', ['filename' => $backup['filename']]) }}">
                                @csrf
                                <button type="submit" class="admin-btn small">Verifikasi integritas</button>
                            </form>
                            <a class="admin-btn primary small" href="{{ route('admin.backups.download', ['filename' => $backup['filename']]) }}">Unduh backup</a>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="admin-panel-body">
                <div class="admin-empty">Belum ada backup. Buat backup pertama untuk mulai melindungi data OrbitFlow.</div>
            </div>
        @endif
    </section>

    <section class="admin-panel backup-recovery-panel">
        <div class="admin-panel-head">
            <div>
                <h3>Pemulihan melalui terminal</h3>
                <p>Pemulihan tidak disediakan sebagai tombol web untuk mencegah penggantian database secara tidak sengaja.</p>
            </div>
            <span class="admin-status pending">CLI saja</span>
        </div>
        <div class="admin-panel-body">
            <p class="backup-recovery-text">Untuk memulihkan backup, salin file backup ke komputer/server, letakkan di folder backup privat OrbitFlow, lalu jalankan perintah Artisan dari terminal. Sistem akan memverifikasi checksum dan membuat backup pengaman sebelum memulihkan data.</p>
            <code class="backup-command">php artisan orbitflow:backup:restore NAMA-FILE-BACKUP.zip</code>
            <p class="backup-recovery-text">Perintah akan meminta konfirmasi. Aplikasi masuk maintenance mode selama proses pemulihan. Jika pemulihan gagal, aplikasi tetap dalam maintenance mode sampai database diperiksa dan dipulihkan dengan aman.</p>
        </div>
    </section>

    <section class="backup-security-note">
        <strong>Catatan keamanan penting</strong>
        <ul>
            <li>Arsip tidak menyertakan .env. Simpan salinan .env dan APP_KEY secara terpisah di lokasi aman. Backup terenkripsi tidak dapat dibuka jika APP_KEY asli hilang atau berubah.</li>
            <li>Folder storage/app/private tidak dimasukkan karena berisi pratinjau CSV sementara dan ekspor tautan aktivasi yang bersifat rahasia.</li>
            <li>Arsip backup dilindungi di penyimpanan privat aplikasi dan hanya dapat diunduh oleh Super Admin. Simpan salinan tambahan di media atau lokasi lain yang aman.</li>
            <li>Jadwal otomatis membutuhkan Laravel Scheduler aktif. Pada Windows, gunakan Task Scheduler untuk menjalankan php artisan schedule:run setiap menit.</li>
            <li>Jangan mengandalkan backup yang hanya tersimpan pada disk server yang sama untuk menghadapi kerusakan fisik perangkat.</li>
        </ul>
    </section>
@endsection
