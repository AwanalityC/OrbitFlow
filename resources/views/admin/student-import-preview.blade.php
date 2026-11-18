@extends('layouts.admin')

@section('title', 'Pratinjau Impor Siswa')
@section('page-title', 'Pratinjau Impor Siswa')
@section('page-description', 'Periksa data dan masalah sebelum akun siswa dibuat.')

@section('content')
    <section class="admin-page-intro">
        <div>
            <h2>Periksa hasil pembacaan CSV</h2>
            <p>Hanya baris valid yang akan dibuat. Baris bermasalah akan dilewati dan tidak mengubah akun yang sudah ada.</p>
        </div>
        <span class="admin-status active">Pratinjau sementara</span>
    </section>

    <section class="admin-stat-grid admin-import-stat-grid">
        <article class="admin-stat blue"><div class="admin-stat-top"><span class="admin-stat-label">Total baris data</span><span class="admin-stat-icon">▤</span></div><div class="admin-stat-value">{{ number_format($totalRows) }}</div><div class="admin-stat-note">Tidak termasuk judul kolom</div></article>
        <article class="admin-stat teal"><div class="admin-stat-top"><span class="admin-stat-label">Siap diimpor</span><span class="admin-stat-icon">✓</span></div><div class="admin-stat-value">{{ number_format($validCount) }}</div><div class="admin-stat-note">Akan menunggu aktivasi</div></article>
        <article class="admin-stat red"><div class="admin-stat-top"><span class="admin-stat-label">Perlu diperbaiki</span><span class="admin-stat-icon">!</span></div><div class="admin-stat-value">{{ number_format($invalidCount) }}</div><div class="admin-stat-note">Tidak akan ikut diimpor</div></article>
    </section>

    @if ($invalidCount > 0)
        <div class="admin-import-warning admin-section-space">Ada {{ $invalidCount }} baris tidak valid. Perbaiki file sumber dan unggah ulang jika ingin memasukkan baris tersebut. <a href="{{ route('admin.students.import.batch.errors', $batchId) }}">Unduh laporan kesalahan CSV</a>.</div>
    @endif

    @if ($totalRows > $rows->count())
        <div class="admin-form-note admin-section-space">Pratinjau menampilkan {{ $rows->count() }} dari {{ $totalRows }} baris. Validasi dilakukan untuk seluruh data.</div>
    @endif

    <section class="admin-panel admin-section-space">
        <div class="admin-panel-head"><div><h3>Rincian data</h3><p>Kolom yang ditampilkan: NIS, nama, email, kelas, dan hasil validasi.</p></div></div>
        <div class="admin-table-wrap admin-import-preview-desktop">
            <table class="admin-table admin-import-table">
                <thead><tr><th>Baris</th><th>NIS</th><th>Nama</th><th>Email</th><th>Kelas</th><th>Hasil</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['row_number'] }}</td>
                            <td>{{ $row['data']['nis'] ?: '—' }}</td>
                            <td>{{ $row['data']['name'] ?: '—' }}</td>
                            <td>{{ $row['data']['email'] ?: '—' }}</td>
                            <td>{{ $row['data']['class_name'] ?: '—' }}</td>
                            <td>
                                @if ($row['is_valid'])
                                    <span class="admin-status available">Valid</span>
                                @else
                                    <span class="admin-status overdue">Perlu diperbaiki</span>
                                    <ul class="admin-import-row-errors">@foreach ($row['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="admin-import-preview-cards" aria-label="Pratinjau CSV dalam tampilan kartu">
            @foreach ($rows as $row)
                <article class="admin-import-preview-card">
                    <div class="admin-import-preview-card-head">
                        <strong>Baris {{ $row['row_number'] }}</strong>
                        @if ($row['is_valid'])
                            <span class="admin-status available">Valid</span>
                        @else
                            <span class="admin-status overdue">Perlu diperbaiki</span>
                        @endif
                    </div>
                    <dl class="admin-import-preview-details">
                        <div><dt>NIS</dt><dd>{{ $row['data']['nis'] ?: '—' }}</dd></div>
                        <div><dt>Nama</dt><dd>{{ $row['data']['name'] ?: '—' }}</dd></div>
                        <div><dt>Email</dt><dd>{{ $row['data']['email'] ?: '—' }}</dd></div>
                        <div><dt>Kelas</dt><dd>{{ $row['data']['class_name'] ?: '—' }}</dd></div>
                    </dl>
                    @if (! $row['is_valid'])
                        <ul class="admin-import-row-errors">@foreach ($row['errors'] as $error)<li>{{ $error }}</li>@endforeach</ul>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    <section class="admin-panel admin-section-space">
        <div class="admin-panel-body admin-import-confirm">
            <div>
                <strong>{{ $validCount }} akun akan dibuat sebagai siswa nonaktif.</strong>
                <p>Setelah proses selesai, unduh file tautan aktivasi. File tersebut hanya tersedia sekali selama 30 menit.</p>
            </div>
            <div class="admin-form-actions">
                <form method="POST" action="{{ route('admin.students.import.batch.cancel', $batchId) }}" onsubmit="return confirm('Batalkan pratinjau impor? Data sementara dan hasil pemeriksaan CSV ini akan dibuang.')">
                    @csrf
                    <button type="submit" class="admin-btn">Batalkan</button>
                </form>
                @if ($validCount > 0)
                    <form method="POST" action="{{ route('admin.students.import.batch.process', $batchId) }}" onsubmit="return confirm('Buat {{ $validCount }} akun siswa yang valid dan menunggu aktivasi?')">
                        @csrf
                        <button type="submit" class="admin-btn primary">Impor {{ $validCount }} akun →</button>
                    </form>
                @else
                    <button type="button" class="admin-btn primary" disabled>Tidak ada data valid</button>
                @endif
            </div>
        </div>
    </section>
@endsection
