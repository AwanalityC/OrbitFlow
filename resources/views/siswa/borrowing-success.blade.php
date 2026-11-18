@extends('layouts.siswa')

@section('title', 'Pengajuan Berhasil - OrbitFlow')
@section('page-heading', 'Pengajuan Berhasil')
@section('page-subtitle', 'Pengajuan peminjaman alat sekolah telah tercatat.')

@section('content')
    @php $referenceId = 'OF-' . str_pad((string) $borrowing->id, 5, '0', STR_PAD_LEFT); @endphp
    <div class="success-area student-success-area">
        <section class="success-card student-success-card" aria-labelledby="success-heading">
            <div class="student-success-check" aria-hidden="true">✓</div>
            <span class="student-flow-eyebrow">PENGAJUAN TERSIMPAN</span>
            <h2 class="success-title" id="success-heading">Pengajuan berhasil dibuat.</h2>
            <p class="success-message">Pengajuan alatmu sudah dikirim dan menunggu pemeriksaan petugas inventaris.</p>

            <div class="student-success-reference">
                <span>Nomor referensi</span>
                <strong>{{ $referenceId }}</strong>
                <span class="status-chip status-pending">{{ $borrowing->status }}</span>
            </div>

            <div class="student-success-next-step">
                <span class="student-success-next-icon" aria-hidden="true">i</span>
                <div><strong>Langkah berikutnya</strong><p>Pantau perkembangan pengajuan melalui halaman status. Petugas akan memperbarui status setelah pemeriksaan.</p></div>
            </div>

            <div class="success-actions student-success-actions">
                <a href="{{ route('borrowing.status', ['id' => $borrowing->id]) }}" class="button primary">Lihat status pengajuan <span aria-hidden="true">→</span></a>
                <a href="{{ route('borrowing.history') }}" class="button secondary">Buka riwayat</a>
            </div>
        </section>
    </div>
@endsection
